import crypto from "node:crypto";
import path from "node:path";

import {
  DEFAULT_SUMMARY_CACHE_DIR,
  SUMMARY_CACHE_VERSION,
} from "../core/constants.mjs";
import { readJson } from "../core/io.mjs";
import { parseJsonSafe } from "../core/utils.mjs";

export const buildSummaryPrompt = ({
  filePath,
  fileMeta,
  chunks,
  taskContext,
}) => {
  const chunkList = chunks
    .map((chunk, index) => {
      const lines = chunk.split("\n");
      const excerpt = lines.slice(0, 40).join("\n");
      return [
        `Chunk ${index + 1}/${chunks.length}:`,
        excerpt,
        lines.length > 40 ? "...(truncated)" : "",
      ]
        .filter(Boolean)
        .join("\n");
    })
    .join("\n\n");
  const metaLines = [
    `Path: ${filePath}`,
    fileMeta?.status ? `Status: ${fileMeta.status}` : null,
    null != fileMeta?.additions ? `Additions: ${fileMeta.additions}` : null,
    null != fileMeta?.deletions ? `Deletions: ${fileMeta.deletions}` : null,
  ]
    .filter(Boolean)
    .join("\n");
  return [
    "You are summarizing a single file change for a large code review.",
    "Write 1-2 sentences that capture the actual change and its intent.",
    "Be concrete and specific, not vague. Avoid speculation.",
    "If the change is purely formatting or renaming, say so plainly.",
    "",
    "Return JSON only with:",
    "- summary (string, 1-2 sentences).",
    "- confidence (number 0-1).",
    "- evidence (array of short excerpt strings pulled from the diff).",
    "",
    "File metadata:",
    metaLines,
    "",
    "Task context (if any):",
    JSON.stringify(taskContext || {}, null, 2),
    "",
    "Diff chunks:",
    chunkList || "(no diff chunks provided)",
  ].join("\n");
};

export const resolveSummaryCacheDir = ({
  repoRoot,
  summaryCacheDir,
  disableSummaryCache,
}) => {
  if (true === disableSummaryCache) {
    return null;
  }
  const raw = null == summaryCacheDir ? "" : String(summaryCacheDir).trim();
  const candidate = "" !== raw ? raw : DEFAULT_SUMMARY_CACHE_DIR;
  if ("" === candidate) {
    return null;
  }
  if (true === path.isAbsolute(candidate)) {
    return candidate;
  }
  return path.join(repoRoot || process.cwd(), candidate);
};

export const buildSummaryCacheKey = ({ prompt, summaryModel }) => {
  const payload = [
    `version:${SUMMARY_CACHE_VERSION}`,
    `model:${summaryModel || ""}`,
    prompt,
  ].join("\n");
  return crypto.createHash("sha256").update(payload, "utf8").digest("hex");
};

export const readSummaryCache = (cachePath) => {
  const cached = readJson(cachePath);
  if (null == cached) {
    return null;
  }
  if (SUMMARY_CACHE_VERSION !== cached.version) {
    return null;
  }
  if ("string" !== typeof cached.summary || "" === cached.summary) {
    return null;
  }
  return cached;
};

export const readDynamicGroupsCache = (cachePath) => {
  const cached = readJson(cachePath);
  if (null == cached) {
    return null;
  }
  if (SUMMARY_CACHE_VERSION !== cached.version) {
    return null;
  }
  if (false === Array.isArray(cached.groups)) {
    return null;
  }
  return cached;
};

export const summarizeFile = async ({
  filePath,
  fileMeta,
  chunks,
  taskContext,
  prompt,
  openai,
  summaryModel,
}) => {
  const summaryPrompt =
    prompt ||
    buildSummaryPrompt({ filePath, fileMeta, chunks, taskContext });
  const response = await openai.responses.create({
    model: summaryModel,
    input: summaryPrompt,
    text: {
      format: {
        type: "json_schema",
        name: "file_summary",
        strict: true,
        schema: {
          type: "object",
          properties: {
            summary: { type: "string" },
            confidence: { type: "number", minimum: 0, maximum: 1 },
            evidence: {
              type: "array",
              items: { type: "string" },
            },
          },
          required: ["summary", "confidence", "evidence"],
          additionalProperties: false,
        },
      },
    },
  });
  const parsed = parseJsonSafe(response.output_text || "");
  if (null == parsed) {
    return {
      summary: "",
      confidence: 0,
      evidence: [],
      error: "Failed to parse summary output.",
    };
  }
  return parsed;
};

const findSequenceIndex = (parts, sequence) => {
  if (!Array.isArray(parts) || !Array.isArray(sequence) || 0 === sequence.length) {
    return -1;
  }
  for (let index = 0; index <= parts.length - sequence.length; index += 1) {
    let matches = true;
    for (let offset = 0; offset < sequence.length; offset += 1) {
      if (parts[index + offset] !== sequence[offset]) {
        matches = false;
        break;
      }
    }
    if (true === matches) {
      return index;
    }
  }
  return -1;
};

const SNAPSHOT_SEGMENT = "__snapshots__";

const resolveGroupKey = (filePath, depth) => {
  const parts = String(filePath || "")
    .split("/")
    .filter(Boolean);
  if (0 === parts.length) {
    return "(root)";
  }
  const snapshotIndex = parts.indexOf(SNAPSHOT_SEGMENT);
  if (-1 !== snapshotIndex) {
    return parts.slice(0, snapshotIndex + 1).join("/");
  }
  const vbPackagesIndex = findSequenceIndex(parts, ["visual-builder", "packages"]);
  if (-1 !== vbPackagesIndex) {
    const packageDepth = vbPackagesIndex + 3;
    const targetDepth = Math.max(packageDepth, depth);
    if (packageDepth <= parts.length) {
      return parts.slice(0, Math.min(targetDepth, parts.length)).join("/");
    }
  }
  const serverPackagesIndex = findSequenceIndex(parts, ["server", "Packages"]);
  if (-1 !== serverPackagesIndex) {
    const packageDepth = serverPackagesIndex + 3;
    const targetDepth = Math.max(packageDepth, depth);
    if (packageDepth <= parts.length) {
      return parts.slice(0, Math.min(targetDepth, parts.length)).join("/");
    }
  }
  return parts.slice(0, depth).join("/") || "(root)";
};

const isLockedGroupKey = (key) =>
  "string" === typeof key &&
  (key.includes(`/${SNAPSHOT_SEGMENT}`) || key.endsWith(SNAPSHOT_SEGMENT));

const buildGroups = (files, depth) => {
  const groups = new Map();
  files.forEach((file) => {
    const filePath = String(file.path || "");
    const key = resolveGroupKey(filePath, depth);
    const group = groups.get(key) || [];
    group.push(file);
    groups.set(key, group);
  });
  return groups;
};

export const groupFilesByPrefix = (
  files,
  depth = 2,
  { maxFiles = 12, maxDepth = 8 } = {}
) => {
  let currentDepth = Math.max(1, depth);
  let groups = buildGroups(files, currentDepth);
  while (currentDepth < maxDepth) {
    const oversizedSplittable = Array.from(groups.entries()).some(
      ([key, entries]) => entries.length > maxFiles && false === isLockedGroupKey(key)
    );
    if (!oversizedSplittable) {
      break;
    }
    currentDepth += 1;
    const nextGroups = new Map();
    groups.forEach((entries, key) => {
      if (entries.length <= maxFiles || true === isLockedGroupKey(key)) {
        nextGroups.set(key, entries);
        return;
      }
      const split = buildGroups(entries, currentDepth);
      split.forEach((splitEntries, splitKey) => {
        const existing = nextGroups.get(splitKey) || [];
        nextGroups.set(splitKey, [...existing, ...splitEntries]);
      });
    });
    groups = nextGroups;
  }
  return Array.from(groups.entries()).map(([key, entries]) => ({
    key,
    files: entries,
  }));
};

export const buildGroupSummaryPrompt = ({ groupKey, files, taskContext }) => [
  "You are summarizing a group of file changes for a large code review.",
  "A group is a folder-prefix bucket of files (path-based), not a theme cluster.",
  "These inputs are file-level summaries derived from a PR diff.",
  "Write 2-3 sentences that explain the change theme in this group.",
  "Be concrete and specific, not vague. Avoid speculation.",
  "Do not invent new artifacts (docs, features, migrations) unless stated.",
  "",
  `Group: ${groupKey}`,
  "",
  "Task context (if any):",
  JSON.stringify(taskContext || {}, null, 2),
  "",
  "File summaries:",
  files
    .map(
      (file) =>
        `- ${file.path}: ${file.summary || "(no summary available)"}`
    )
    .join("\n"),
].join("\n");

export const summarizeGroup = async ({ groupKey, files, openai, summaryModel }) => {
  const prompt = buildGroupSummaryPrompt({ groupKey, files });
  const response = await openai.responses.create({
    model: summaryModel,
    input: prompt,
    text: {
      format: {
        type: "json_schema",
        name: "group_summary",
        strict: true,
        schema: {
          type: "object",
          properties: {
            summary: { type: "string" },
            confidence: { type: "number", minimum: 0, maximum: 1 },
          },
          required: ["summary", "confidence"],
          additionalProperties: false,
        },
      },
    },
  });
  const parsed = parseJsonSafe(response.output_text || "");
  if (null == parsed) {
    return {
      summary: "",
      confidence: 0,
      error: "Failed to parse group summary output.",
    };
  }
  return parsed;
};

export const buildOverallSummaryPrompt = ({ groupSummaries, taskContext }) => [
  "You are summarizing a large code review at a high level.",
  "This is a summary of grouped file-change summaries from a PR diff.",
  "Write 3-5 sentences that explain the overall change.",
  "Be concrete and specific, not vague. Avoid speculation.",
  "Do not invent new artifacts (docs, features, migrations) unless stated.",
  "Use task context to frame intent, but do not restate it if unsupported.",
  "",
  "Task context (if any):",
  JSON.stringify(taskContext || {}, null, 2),
  "",
  "Group summaries:",
  groupSummaries
    .map(
      (group) =>
        `- ${group.key}: ${group.summary || "(no summary available)"}`
    )
    .join("\n"),
].join("\n");

export const summarizeOverall = async ({ groupSummaries, taskContext, openai, summaryModel }) => {
  const prompt = buildOverallSummaryPrompt({ groupSummaries, taskContext });
  const response = await openai.responses.create({
    model: summaryModel,
    input: prompt,
    text: {
      format: {
        type: "json_schema",
        name: "overall_summary",
        strict: true,
        schema: {
          type: "object",
          properties: {
            summary: { type: "string" },
            confidence: { type: "number", minimum: 0, maximum: 1 },
          },
          required: ["summary", "confidence"],
          additionalProperties: false,
        },
      },
    },
  });
  const parsed = parseJsonSafe(response.output_text || "");
  if (null == parsed) {
    return {
      summary: "",
      confidence: 0,
      error: "Failed to parse overall summary output.",
    };
  }
  return parsed;
};

export const buildDynamicGroupsPrompt = ({
  files,
  maxGroups = 20,
  maxFilesPerGroup = 10,
  maxFileCount = 200,
}) => {
  const fileList = Array.isArray(files) ? files : [];
  const limited = fileList.slice(0, maxFileCount);
  const truncated = limited.length < fileList.length;
  return [
    "You are clustering file changes by theme for a large code review.",
    "These groupings must aid successful, semantic code review.",
    "Each group should be reviewable on its own, one at a time.",
    "Use the group summary to tell the reviewer how to approach the review,",
    "including any hot paths, risk areas, or security implications.",
    "Group by change intent and subsystem, not by directory structure.",
    "Prefer pairing shared utilities/helpers with their consumers in the same group.",
    "Example: if a new util like isActive.ts is added, group it with components",
    "or modules that import or are updated to use it.",
    "Group tests, types, specs/docs, and package wiring with the feature they support.",
    "When snapshot tests change, pair a representative subset of those snapshots",
    "with the code change that caused them, and mention that other snapshots follow",
    "the same pattern when applicable.",
    "Prefer a focused group that combines a change with its tests when it improves",
    "review context and cause/effect understanding.",
    "Avoid generic groups (e.g., 'misc', 'other', 'assorted').",
    "Avoid singleton groups unless the change is truly isolated or cross-cutting.",
    "If a file fits multiple groups, allow overlap when it improves reviewer flow,",
    "but keep overlap intentional and limited to avoid noise.",
    "Prefer as many groups as needed to keep groupings natural and reviewable,",
    "rather than forcing unrelated changes into a small number of buckets.",
    "Use the file summaries to infer themes like data flow, UI, tests, migrations, etc.",
    "",
    "Return JSON only with:",
    "- groups: array of { label, summary, file_paths, confidence }",
    "",
    `Constraints: max_groups=${maxGroups}, max_files_per_group=${maxFilesPerGroup}`,
    truncated
      ? `Note: input list truncated to ${limited.length}/${fileList.length} files.`
      : null,
    "",
    "File summaries:",
    limited
      .map(
        (file) => `- ${file.path}: ${file.summary || "(no summary available)"}`
      )
      .join("\n"),
  ]
    .filter(Boolean)
    .join("\n");
};

export const summarizeDynamicGroups = async ({
  files,
  openai,
  summaryModel,
  maxGroups,
  maxFilesPerGroup,
  maxFileCount,
}) => {
  const prompt = buildDynamicGroupsPrompt({
    files,
    maxGroups,
    maxFilesPerGroup,
    maxFileCount,
  });
  const response = await openai.responses.create({
    model: summaryModel,
    input: prompt,
    text: {
      format: {
        type: "json_schema",
        name: "dynamic_group_summary",
        strict: true,
        schema: {
          type: "object",
          properties: {
            groups: {
              type: "array",
              items: {
                type: "object",
                properties: {
                  label: { type: "string" },
                  summary: { type: "string" },
                  file_paths: {
                    type: "array",
                    items: { type: "string" },
                  },
                  confidence: { type: "number", minimum: 0, maximum: 1 },
                },
                required: ["label", "summary", "file_paths", "confidence"],
                additionalProperties: false,
              },
            },
          },
          required: ["groups"],
          additionalProperties: false,
        },
      },
    },
  });
  const parsed = parseJsonSafe(response.output_text || "");
  if (null == parsed) {
    return {
      groups: [],
      error: "Failed to parse dynamic group output.",
    };
  }
  return parsed;
};
