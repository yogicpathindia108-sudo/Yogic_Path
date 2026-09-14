import path from "node:path";
import { SUMMARY_MODEL } from "../core/constants.mjs";
import {
  buildConventionalHeaderFromFinding,
  resolveConventionalMeta,
} from "./formatting.mjs";
import { log } from "../core/logging.mjs";
import { getOpenAIClient } from "../core/openai.mjs";
import { parseJsonSafe } from "../core/utils.mjs";
import {
  isRelatedReviewFile,
  parseRelatedRepoPath,
} from "../facts/helpers.mjs";

const parseLineRange = (value) => {
  if (null == value) {
    return null;
  }
  const match = String(value).match(/L?(\d+)(?:\s*-\s*L?(\d+))?/i);
  if (null === match) {
    return null;
  }
  return {
    start: Number(match[1]),
    end: Number(match[2] || match[1]),
  };
};

const normalizePathForLocalRepo = (filePath, facts) => {
  if (null == filePath || null == facts?.repoRoot || null == facts?.localRepoPath) {
    return { path: filePath, normalized: false };
  }
  const repoRelative = path.relative(facts.repoRoot, facts.localRepoPath);
  if ("" === repoRelative || "." === repoRelative) {
    return { path: filePath, normalized: false };
  }
  const prefix = repoRelative.split(path.sep).join("/");
  if (filePath.startsWith(`${prefix}/`)) {
    return { path: filePath.slice(prefix.length + 1), normalized: true };
  }
  return { path: filePath, normalized: false };
};

const buildDiffPositionMap = (patch) => {
  if (null == patch || "" === patch) {
    return {
      positionByNewLine: new Map(),
      newLineByPosition: new Map(),
      lines: [],
    };
  }
  const lines = patch.split("\n");
  const positionByNewLine = new Map();
  const newLineByPosition = new Map();
  let newLine = null;
  let position = 0;
  lines.forEach((line) => {
    position += 1;
    const match = line.match(/^@@\s+-\d+(?:,\d+)?\s+\+(\d+)(?:,(\d+))?\s+@@/);
    if (match) {
      newLine = Number(match[1]);
      return;
    }
    if (null === newLine) {
      return;
    }
    if (line.startsWith("+") && false === line.startsWith("+++")) {
      positionByNewLine.set(newLine, position);
      newLineByPosition.set(position, newLine);
      newLine += 1;
      return;
    }
    if (line.startsWith("-") && false === line.startsWith("---")) {
      return;
    }
    positionByNewLine.set(newLine, position);
    newLineByPosition.set(position, newLine);
    newLine += 1;
  });
  return { positionByNewLine, newLineByPosition, lines };
};

const normalizeDiffLine = (line) => line.replace(/^[+\- ]/, "").trim();

const findPositionBySnippet = (lines, snippet) => {
  if (null == snippet || "" === snippet) {
    return null;
  }
  const target = snippet
    .split("\n")
    .map((line) => line.trim())
    .find((line) => "" !== line);
  if (!target) {
    return null;
  }
  const index = lines.findIndex(
    (line) => normalizeDiffLine(line) === target
  );
  if (-1 === index) {
    return null;
  }
  return index + 1;
};

const parsePatchHunks = (lines) => {
  const hunks = [];
  let current = null;
  lines.forEach((line, index) => {
    const match = line.match(/^@@\s+-\d+(?:,\d+)?\s+\+(\d+)(?:,(\d+))?\s+@@/);
    if (match) {
      if (current) {
        current.endIndex = index - 1;
        hunks.push(current);
      }
      current = {
        startIndex: index,
        endIndex: index,
        newStart: Number(match[1]),
        newCount: Number(match[2] || "1"),
      };
      return;
    }
    if (current) {
      current.endIndex = index;
    }
  });
  if (current) {
    hunks.push(current);
  }
  return hunks;
};

const buildPatchContextForRepair = ({
  patch,
  lineRange,
  maxHunks = 2,
  maxLinesPerHunk = 140,
}) => {
  if (null == patch || "" === patch) {
    return [];
  }
  const lines = patch.split("\n");
  const hunks = parsePatchHunks(lines);
  if (0 === hunks.length) {
    return lines.map((line, index) => ({
      position: index + 1,
      line,
    }));
  }
  const scored = hunks.map((hunk) => {
    if (null == lineRange) {
      return { hunk, distance: 0 };
    }
    const hunkStart = hunk.newStart;
    const hunkEnd = hunk.newStart + Math.max(hunk.newCount - 1, 0);
    if (lineRange.start >= hunkStart && lineRange.start <= hunkEnd) {
      return { hunk, distance: 0 };
    }
    const distance = Math.min(
      Math.abs(lineRange.start - hunkStart),
      Math.abs(lineRange.start - hunkEnd)
    );
    return { hunk, distance };
  });
  const selected = scored
    .sort((a, b) => a.distance - b.distance)
    .slice(0, maxHunks)
    .map((entry) => entry.hunk);
  const positions = [];
  selected.forEach((hunk) => {
    const hunkLines = lines.slice(hunk.startIndex, hunk.endIndex + 1);
    const trimmed =
      hunkLines.length > maxLinesPerHunk
        ? hunkLines.slice(0, maxLinesPerHunk)
        : hunkLines;
    trimmed.forEach((line, offset) => {
      const position = hunk.startIndex + offset + 1;
      positions.push({ position, line });
    });
  });
  return positions;
};

const buildInlineRepairPrompt = ({ finding, location, positions }) => [
  "You are repairing an inline comment mapping for a git diff.",
  "Given the finding details and a subset of diff lines with their line positions,",
  "return the best matching diff line position for the finding.",
  "If you cannot find a good match, return position null.",
  "",
  `Finding title: ${finding?.title || "Finding"}`,
  `Path: ${location?.path || ""}`,
  `Lines: ${location?.lines || ""}`,
  `Snippet: ${location?.snippet || ""}`,
  "",
  "Diff context (format: <position>|<line>):",
  positions
    .map((entry) => `${entry.position}|${entry.line}`)
    .join("\n") || "(no diff context provided)",
].join("\n");

const repairInlineMapping = async ({
  finding,
  location,
  patch,
  openai,
  model,
}) => {
  if (null == openai || null == model) {
    return null;
  }
  const lineRange = parseLineRange(location?.lines);
  const positions = buildPatchContextForRepair({
    patch,
    lineRange,
  });
  if (0 === positions.length) {
    return null;
  }
  const prompt = buildInlineRepairPrompt({ finding, location, positions });
  const response = await openai.responses.create({
    model,
    input: prompt,
    text: {
      format: {
        type: "json_schema",
        name: "inline_mapping_repair",
        strict: true,
        schema: {
          type: "object",
          properties: {
            position: { type: ["integer", "null"], minimum: 1 },
            confidence: { type: "number", minimum: 0, maximum: 1 },
            note: { type: "string" },
          },
          required: ["position", "confidence", "note"],
          additionalProperties: false,
        },
      },
    },
  });
  const parsed = parseJsonSafe(response.output_text || "");
  if (null == parsed || null == parsed.position) {
    return null;
  }
  return parsed;
};

const buildInlineCommentBody = (finding) => {
  const confidenceValue = Number(finding.confidence);
  const confidenceLine = Number.isFinite(confidenceValue)
    ? `Confidence: ${Math.round(confidenceValue * 100)}%`
    : null;
  const reviewerLabel = finding.reviewer
    ? finding.reviewer.replace(/^review-/, "")
    : null;
  return [
    buildConventionalHeaderFromFinding(finding),
    reviewerLabel ? `Reviewer: ${reviewerLabel}` : null,
    confidenceLine,
    finding.rationale ? `Rationale: ${finding.rationale}` : null,
    finding.suggested_fix ? `Suggestion: ${finding.suggested_fix}` : null,
  ]
    .filter(Boolean)
    .join("\n");
};

const resolveInlinePosition = async ({
  finding,
  location,
  patch,
  openai,
  repairModel,
  debugMappingResolved,
  debugMappingFailure,
}) => {
  const { positionByNewLine, newLineByPosition, lines } =
    buildDiffPositionMap(patch);
  const range = parseLineRange(location.lines);
  let position = null;
  let line = null;
  if (range?.start) {
    position = positionByNewLine.get(range.start) || null;
    line = null == position ? null : range.start;
    if (null != position) {
      debugMappingResolved({
        finding,
        location,
        position,
        line,
        strategy: "range",
      });
    }
  }
  if (null == position) {
    const snippetPosition = findPositionBySnippet(lines, location?.snippet);
    if (null != snippetPosition) {
      position = snippetPosition;
      line = newLineByPosition.get(position) || null;
      debugMappingResolved({
        finding,
        location,
        position,
        line,
        strategy: "snippet",
      });
    }
  }
  if (null == position) {
    const repaired = await repairInlineMapping({
      finding,
      location,
      patch,
      openai,
      model: repairModel,
    });
    if (repaired?.position) {
      position = repaired.position;
      line = newLineByPosition.get(position) || null;
      debugMappingResolved({
        finding,
        location,
        position,
        line,
        strategy: "repair",
      });
    }
  }
  if (null == position) {
    debugMappingFailure({
      finding,
      location,
      reason: "range_match_failed_or_missing_line",
      detail: { hasRange: Boolean(range?.start) },
    });
    return null;
  }
  if (!lines[position - 1]) {
    debugMappingFailure({
      finding,
      location,
      reason: "position_out_of_range",
      detail: { position, patchLineCount: lines.length },
    });
    return null;
  }
  return { position, line };
};

export const buildFindingKey = (finding, location) => {
  const meta = resolveConventionalMeta(finding);
  const decorationKey = meta.decorations.join(",");
  return [
    meta.label || "",
    decorationKey,
    finding?.title || "",
    location?.path || "",
    location?.lines || "",
  ]
    .map((value) => String(value).trim())
    .join("|");
};

export const buildInlineComments = async (summary, facts) => {
  if ("pr-compare" !== facts.mode) {
    return { comments: [], inlinedKeys: new Set(), relatedComments: [] };
  }
  const openai = getOpenAIClient();
  const repairModel = facts.summaryModel || SUMMARY_MODEL;
  const files = facts.fileMetadata || [];
  const thisPrFiles = files.filter((file) => false === isRelatedReviewFile(file));
  const relatedFiles = files.filter((file) => true === isRelatedReviewFile(file));
  const filePatchMap = new Map(
    thisPrFiles.map((file) => [file.path, file.patch || ""])
  );
  const relatedFileByPath = new Map(
    relatedFiles.map((file) => [file.path, file])
  );
  const prFileSet = new Set(thisPrFiles.map((file) => file.path).filter(Boolean));
  const pathAliases = new Map(
    thisPrFiles
      .filter((file) => file.old_path && file.path && file.old_path !== file.path)
      .map((file) => [file.old_path, file.path])
  );
  const relatedPrNumberFor = (repoSlug) => {
    const match = (facts.relatedPrs || []).find(
      (entry) => entry?.repoSlug === repoSlug
    );
    return match?.prNumber ?? null;
  };
  const debugMappingFailure = ({ finding, location, reason, detail }) => {
    log(
      `[inline-map] skip: ${reason}`,
      JSON.stringify(
        {
          title: finding?.title || "Finding",
          path: location?.path || null,
          lines: location?.lines || null,
          detail: detail || null,
        },
        null,
        2
      )
    );
  };
  const debugMappingAttempt = ({
    finding,
    location,
    commentPath,
    patchSource,
    patchLineCount,
    normalizedPath,
    isInPr,
  }) => {
    log(
      "[inline-map] attempt",
      JSON.stringify(
        {
          title: finding?.title || "Finding",
          path: location?.path || null,
          comment_path: commentPath || null,
          normalized_path: normalizedPath || null,
          lines: location?.lines || null,
          snippet: location?.snippet || null,
          patch_source: patchSource || null,
          patch_lines: patchLineCount || 0,
          in_pr: Boolean(isInPr),
        },
        null,
        2
      )
    );
  };
  const debugMappingResolved = ({
    finding,
    location,
    position,
    line,
    strategy,
  }) => {
    log(
      `[inline-map] resolved: ${strategy}`,
      JSON.stringify(
        {
          title: finding?.title || "Finding",
          path: location?.path || null,
          lines: location?.lines || null,
          position,
          line,
        },
        null,
        2
      )
    );
  };
  const debugMappingFallback = ({ finding, location, line, reason }) => {
    log(
      `[inline-map] fallback: ${reason}`,
      JSON.stringify(
        {
          title: finding?.title || "Finding",
          path: location?.path || null,
          lines: location?.lines || null,
          line,
        },
        null,
        2
      )
    );
  };
  const comments = [];
  const relatedComments = [];
  const inlinedKeys = new Set();
  const findings = summary?.pr_comment?.findings || [];
  for (const finding of findings) {
    const location = finding.locations?.[0];
    if (!location?.path) {
      debugMappingFailure({
        finding,
        location,
        reason: "missing_path",
      });
      continue;
    }
    const relatedParsed = parseRelatedRepoPath(location.path);
    const relatedFile = relatedFileByPath.get(location.path) || null;
    if (relatedFile || relatedParsed) {
      const sourceRepo = relatedFile?.source_repo || relatedParsed?.repoSlug;
      const sourcePr = relatedFile?.source_pr ?? relatedPrNumberFor(sourceRepo);
      const originalPath =
        relatedFile?.original_path || relatedParsed?.originalPath || null;
      const relatedPatch = relatedFile?.patch || "";
      debugMappingAttempt({
        finding,
        location,
        commentPath: originalPath,
        patchSource: relatedPatch ? "related-pr-file-patch" : "none",
        patchLineCount: relatedPatch ? relatedPatch.split("\n").length : 0,
        normalizedPath: originalPath,
        isInPr: false,
      });
      if (!originalPath || "" === relatedPatch) {
        debugMappingFailure({
          finding,
          location,
          reason: "related_repo_path",
          detail: {
            source_repo: sourceRepo || null,
            source_pr: sourcePr || null,
            has_original_path: Boolean(originalPath),
            has_patch: "" !== relatedPatch,
          },
        });
        continue;
      }
      const relatedMapped = await resolveInlinePosition({
        finding,
        location,
        patch: relatedPatch,
        openai,
        repairModel,
        debugMappingResolved,
        debugMappingFailure,
      });
      if (relatedMapped?.position) {
        relatedComments.push({
          source_repo: sourceRepo,
          source_pr: sourcePr,
          path: originalPath,
          position: relatedMapped.position,
          body: buildInlineCommentBody(finding),
        });
      }
      continue;
    }
    const normalized = normalizePathForLocalRepo(location.path, facts);
    const normalizedPath = normalized.normalized ? normalized.path : location.path;
    const commentPath = pathAliases.get(normalizedPath) || normalizedPath;
    const isInPr =
      prFileSet.has(commentPath) ||
      prFileSet.has(location.path) ||
      (normalized.path ? prFileSet.has(normalized.path) : false);
    let patchSource = "none";
    if (true !== isInPr) {
      debugMappingFailure({
        finding,
        location,
        reason: "path_not_in_pr",
      });
      continue;
    }
    let patch =
      filePatchMap.get(commentPath) ||
      filePatchMap.get(normalized.path) ||
      filePatchMap.get(location.path) ||
      "";
    if (patch) {
      patchSource =
        filePatchMap.get(commentPath) ||
        filePatchMap.get(normalized.path) ||
        filePatchMap.get(location.path)
          ? "pr-file-patch"
          : "unknown";
    }
    if ("" === patch) {
      debugMappingFailure({
        finding,
        location,
        reason: "missing_pr_patch",
      });
      continue;
    }
    debugMappingAttempt({
      finding,
      location,
      commentPath,
      patchSource,
      patchLineCount: patch ? patch.split("\n").length : 0,
      normalizedPath: normalized.path || null,
      isInPr,
    });
    if ("pr-file-patch" !== patchSource) {
      debugMappingFailure({
        finding,
        location,
        reason: "non_pr_patch_source",
        detail: { patchSource },
      });
      continue;
    }
    const mapped = await resolveInlinePosition({
      finding,
      location,
      patch,
      openai,
      repairModel,
      debugMappingResolved,
      debugMappingFailure,
    });
    if (!mapped?.position) {
      continue;
    }
    comments.push({
      path: commentPath,
      position: mapped.position,
      body: buildInlineCommentBody(finding),
    });
    inlinedKeys.add(buildFindingKey(finding, location));
  }
  return { comments, inlinedKeys, relatedComments };
};
