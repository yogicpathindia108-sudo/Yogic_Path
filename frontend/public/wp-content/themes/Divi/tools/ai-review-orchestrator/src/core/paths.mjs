import fs from "node:fs";
import path from "node:path";

import { OUTPUT_DIR } from "./constants.mjs";
import { readJson } from "./io.mjs";
import { slugifySegment } from "./utils.mjs";

export const buildRunId = ({ mode, prNumber, baseRef, headRef }) => {
  const now = new Date();
  const timestamp = now.toISOString().replace(/[:.]/g, "-");
  const parts = [timestamp, mode];
  if (prNumber) {
    parts.push(`pr-${slugifySegment(prNumber)}`);
  }
  if (baseRef) {
    parts.push(`base-${slugifySegment(baseRef)}`);
  }
  if (headRef) {
    parts.push(`head-${slugifySegment(headRef)}`);
  }
  return parts.filter(Boolean).join("_");
};

export const normalizeRelativePath = (filePath) => {
  if (null == filePath || "" === filePath) {
    return "";
  }
  const normalized = path.normalize(filePath);
  return normalized.replace(/^(\.\.[/\\])+/, "").replace(/^[/\\]+/, "");
};

export const resolveLatestRunId = (repoRoot) => {
  const outputRoot = path.join(repoRoot, OUTPUT_DIR);
  if (false === fs.existsSync(outputRoot)) {
    return null;
  }
  const entries = fs
    .readdirSync(outputRoot, { withFileTypes: true })
    .filter((entry) => entry.isDirectory())
    .map((entry) => entry.name)
    .sort();
  if (0 === entries.length) {
    return null;
  }
  return entries[entries.length - 1];
};

export const resolvePrRuns = (repoRoot, prNumber) => {
  if (null == repoRoot || null == prNumber) {
    return [];
  }
  const outputRoot = path.join(repoRoot, OUTPUT_DIR);
  if (false === fs.existsSync(outputRoot)) {
    return [];
  }
  const entries = fs
    .readdirSync(outputRoot, { withFileTypes: true })
    .filter((entry) => entry.isDirectory())
    .map((entry) => entry.name)
    .sort();
  if (0 === entries.length) {
    return [];
  }
  const runs = entries
    .map((entry) => {
      const runPath = path.join(outputRoot, entry, "run.json");
      const runInfo = readJson(runPath);
      if (Number(runInfo?.pr_number) !== Number(prNumber)) {
        return null;
      }
      return {
        run_id: entry,
        started_at: runInfo?.started_at || null,
        run: runInfo || null,
        output_root: path.join(outputRoot, entry),
      };
    })
    .filter(Boolean);
  if (0 === runs.length) {
    return [];
  }
  runs.sort((a, b) => {
    const timeA = a.started_at ? new Date(a.started_at).getTime() : 0;
    const timeB = b.started_at ? new Date(b.started_at).getTime() : 0;
    if (timeA !== timeB) {
      return timeA - timeB;
    }
    return String(a.run_id).localeCompare(String(b.run_id));
  });
  return runs;
};

export const resolveLatestPrRun = (repoRoot, prNumber) => {
  const runs = resolvePrRuns(repoRoot, prNumber);
  if (0 === runs.length) {
    return null;
  }
  return runs[runs.length - 1];
};

export const loadOutputContracts = (repoRoot) => {
  const reviewerPath = path.join(
    repoRoot,
    "tools/ai-review-orchestrator/docs/output-contract-reviewer.md"
  );
  const retroFeedbackPath = path.join(
    repoRoot,
    "tools/ai-review-orchestrator/docs/output-contract-retro-feedback.md"
  );
  const orchestratorPath = path.join(
    repoRoot,
    "tools/ai-review-orchestrator/docs/output-contract-orchestrator.md"
  );
  const reviewer = fs.existsSync(reviewerPath)
    ? fs.readFileSync(reviewerPath, "utf8").trim()
    : "";
  const retroFeedback = fs.existsSync(retroFeedbackPath)
    ? fs.readFileSync(retroFeedbackPath, "utf8").trim()
    : "";
  const orchestrator = fs.existsSync(orchestratorPath)
    ? fs.readFileSync(orchestratorPath, "utf8").trim()
    : "";
  return { reviewer, retroFeedback, orchestrator };
};

export const buildOutputPaths = (repoRoot, runId) => {
  const outputRoot = path.join(repoRoot, OUTPUT_DIR, runId);
  return {
    outputRoot,
    run: path.join(outputRoot, "run.json"),
    facts: path.join(outputRoot, "facts.json"),
    preflight: path.join(outputRoot, "preflight.json"),
    filesIndex: path.join(outputRoot, "files/index.json"),
    filesByPathRoot: path.join(outputRoot, "files/by-path"),
    summariesFiles: path.join(outputRoot, "summaries/files.json"),
    summariesGroups: path.join(outputRoot, "summaries/groups.json"),
    summariesDynamicGroups: path.join(outputRoot, "summaries/dynamic-groups.json"),
    summariesOverall: path.join(outputRoot, "summaries/overall.json"),
    diffsRoot: path.join(outputRoot, "diffs/file-chunks"),
    reviewersDecision: path.join(outputRoot, "reviewers/decision.json"),
    reviewersPromptsRoot: path.join(outputRoot, "reviewers/prompts"),
    reviewersOutputsRoot: path.join(outputRoot, "reviewers/outputs"),
    retroReview: path.join(outputRoot, "retro/review.json"),
    retroSummary: path.join(outputRoot, "retro/summary.json"),
    retroDupeReport: path.join(outputRoot, "retro/dupe-report.json"),
    aggregateFindings: path.join(outputRoot, "aggregate/findings.json"),
    aggregateSummaryComment: path.join(outputRoot, "aggregate/summary-comment.md"),
    aggregateReviewComment: path.join(outputRoot, "aggregate/review-comment.md"),
    aggregatePrivateSummary: path.join(outputRoot, "aggregate/private-summary.md"),
    aggregateReviewPayload: path.join(outputRoot, "aggregate/review-payload.json"),
    aggregateRelatedReviewPayloads: path.join(
      outputRoot,
      "aggregate/related-review-payloads.json"
    ),
  };
};

export const buildFilesIndex = (facts) => ({
  total_files: facts.changedFiles.length,
  total_code_files: facts.codeFiles.length,
  total_task_files: facts.taskFiles.length,
  changed_files: facts.changedFiles,
  code_files: facts.codeFiles,
  task_files: facts.taskFiles,
  related_prs: facts.relatedPrs || [],
  files: (facts.fileMetadata || []).map((file) => ({
    path: file.path,
    additions: file.additions ?? null,
    deletions: file.deletions ?? null,
    status: file.status ?? null,
    changes: file.changes ?? null,
    old_path: file.old_path ?? null,
    source_repo: file.source_repo ?? null,
    source_pr: file.source_pr ?? null,
    original_path: file.original_path ?? null,
  })),
});

export const buildFactsPayload = (facts, options = {}) => {
  const prBodyLimit = options.prBodyLimit ?? 2000;
  const prMeta = facts.prMeta
    ? {
      number: facts.prMeta.number,
      url: facts.prMeta.url,
      title: facts.prMeta.title,
      baseRefName: facts.prMeta.baseRefName,
      headRefName: facts.prMeta.headRefName,
      body:
        "string" === typeof facts.prMeta.body
          ? facts.prMeta.body.slice(0, prBodyLimit)
          : null,
    }
    : null;
  return {
    repoRoot: facts.repoRoot,
    mode: facts.mode,
    baseRef: facts.baseRef,
    headRef: facts.headRef,
    prMeta,
    repoSlug: facts.repoSlug,
    relatedPrs: facts.relatedPrs || [],
    companionContext: facts.companionContext || null,
    changedFiles: facts.changedFiles,
    codeFiles: facts.codeFiles,
    taskFiles: facts.taskFiles,
    taskContext: facts.taskContext,
    config: facts.config,
    lineCount: facts.lineCount,
    sizeKey: facts.sizeKey,
    model: facts.model,
    summaryModel: facts.summaryModel,
    summaryCacheDir: facts.summaryCacheDir,
    judgeModel: facts.judgeModel,
    preflight: facts.preflight || null,
    forcedReviewers: facts.forcedReviewers,
    resumeRunId: facts.resumeRunId,
    resumeLatest: facts.resumeLatest,
    refreshSummaries: facts.refreshSummaries,
    retroReview: facts.retroReview || null,
    sequential: facts.sequential,
    staggerMs: facts.staggerMs,
    contextLines: facts.contextLines,
    timeoutMs: facts.timeoutMs,
    fileMetadata: (facts.fileMetadata || []).map((file) => ({
      path: file.path,
      additions: file.additions ?? null,
      deletions: file.deletions ?? null,
      status: file.status ?? null,
      changes: file.changes ?? null,
      old_path: file.old_path ?? null,
      source_repo: file.source_repo ?? null,
      source_pr: file.source_pr ?? null,
      original_path: file.original_path ?? null,
    })),
  };
};
