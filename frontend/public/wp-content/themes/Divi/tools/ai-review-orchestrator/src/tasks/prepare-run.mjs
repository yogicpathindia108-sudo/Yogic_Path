import { task } from "@langchain/langgraph";

import { ensureDir, writeJson } from "../core/io.mjs";
import {
  buildFactsPayload,
  buildFilesIndex,
  buildOutputPaths,
  buildRunId,
  resolveLatestRunId,
} from "../core/paths.mjs";
import { codebaseFromRepo, startUsageRun } from "../core/usage.mjs";

export const prepareRun = task({ name: "prepareRun" }, async (facts) => {
  let runId = facts.resumeRunId;
  if (null == runId && true === facts.resumeLatest) {
    runId = resolveLatestRunId(facts.repoRoot);
  }
  if (null == runId) {
    runId = buildRunId({
      mode: facts.mode,
      prNumber: facts.prMeta?.number || null,
      baseRef: facts.baseRef,
      headRef: facts.headRef,
    });
  }
  const outputPaths = buildOutputPaths(facts.repoRoot, runId);
  ensureDir(outputPaths.outputRoot);
  const runInfo = {
    run_id: runId,
    started_at: facts.runStartedAt || new Date().toISOString(),
    mode: facts.mode,
    pr_number: facts.prMeta?.number || null,
    repo_slug: facts.repoSlug,
    related_prs: facts.relatedPrs || [],
    base_ref: facts.baseRef,
    head_ref: facts.headRef,
    head_sha: facts.prMeta?.headRefOid || null,
    summary_model: facts.summaryModel,
    summary_cache_dir: facts.summaryCacheDir || null,
    reviewer_model: facts.model || null,
    reviewer_concurrency: facts.reviewerConcurrency || null,
    judge_model: facts.judgeModel || null,
    resume_run: facts.resumeRunId || null,
    resume_latest: true === facts.resumeLatest,
    refresh_summaries: true === facts.refreshSummaries,
    preflight: facts.preflight || null,
  };
  writeJson(outputPaths.run, runInfo);
  startUsageRun({
    codebase: codebaseFromRepo({
      repoSlug: facts.repoSlug,
      repoArg: facts.localRepoPath || facts.repoSlug,
    }),
    prNumber: facts.prMeta?.number || null,
    repoSlug: facts.repoSlug,
    headRef: facts.headRef,
    runId,
    startedAt: runInfo.started_at,
  });
  writeJson(outputPaths.facts, buildFactsPayload(facts));
  writeJson(outputPaths.filesIndex, buildFilesIndex(facts));
  if (facts.retroReview) {
    writeJson(outputPaths.retroReview, facts.retroReview);
    if (facts.retroReview?.summary) {
      writeJson(outputPaths.retroSummary, facts.retroReview.summary);
    }
  }
  return { ...facts, runId, outputPaths };
});
