import { entrypoint } from "@langchain/langgraph";
import dotenv from "dotenv";

import { parseArgs } from "./core/args.mjs";
import { SUMMARY_MODEL } from "./core/constants.mjs";
import { readJson, writeJson } from "./core/io.mjs";
import { log } from "./core/logging.mjs";
import { setOpenAIUsageSubtask } from "./core/openai.mjs";
import { flushUsage, startUsageRun } from "./core/usage.mjs";
import { loadOutputContracts } from "./core/paths.mjs";
import { parseRelatedPrArgs } from "./facts/helpers.mjs";
import { parseForcedReviewers } from "./core/reviewers.mjs";
import { assertAgentAuth } from "./reviewers/agent.mjs";
import {
  formatPreflightReport,
  runPreflight,
} from "./preflight/preflight.mjs";
import { collectFacts } from "./tasks/facts.mjs";
import { prepareRun } from "./tasks/prepare-run.mjs";
import { summarizeFilesTask } from "./tasks/summarize.mjs";
import { decideReviewers, runReviewers } from "./tasks/reviewers.mjs";
import { aggregateResults } from "./tasks/aggregate.mjs";

dotenv.config();

const orchestrator = entrypoint({ name: "aiReviewOrchestrator" }, async (input) => {
  startUsageRun({
    prNumber: input.prNumber,
    startedAt: new Date().toISOString(),
  });
  setOpenAIUsageSubtask("facts");
  const facts = await collectFacts(input);
  const preparedFacts = await prepareRun(facts);
  const preflight = await runPreflight(preparedFacts);
  if (false === preflight.ok && true === preflight.strict) {
    process.stderr.write(`${formatPreflightReport(preflight)}\n`);
    throw new Error("Preflight failed.");
  }
  setOpenAIUsageSubtask("summary");
  const summaries = await summarizeFilesTask(preparedFacts);
  const outputContracts = loadOutputContracts(preparedFacts.repoRoot);
  const reviewFacts = { ...preparedFacts, summaries, outputContracts };
  assertAgentAuth(preparedFacts.model);
  const decision = await decideReviewers(reviewFacts);
  if (preparedFacts.outputPaths) {
    writeJson(preparedFacts.outputPaths.reviewersDecision, decision);
  }
  const results = await runReviewers({ facts: reviewFacts, decision });
  if (preparedFacts.outputPaths?.run) {
    const runInfo = readJson(preparedFacts.outputPaths.run) || {};
    const reviewerModels = {};
    results.forEach((result) => {
      reviewerModels[result.reviewer] = {
        runs: result.modelRuns || [],
        judge: result.judgeModel || null,
      };
    });
    writeJson(preparedFacts.outputPaths.run, {
      ...runInfo,
      reviewer_models_used: reviewerModels,
    });
  }
  setOpenAIUsageSubtask("aggregate");
  const summary = await aggregateResults({ facts: reviewFacts, results });
  return { facts: reviewFacts, decision, summary, summaries };
});

const main = async () => {
  const { getArgValue, getArgValues, hasArg } = parseArgs();
  if (true === hasArg("--help")) {
    process.stdout.write(
      [
        "Usage: node src/index.mjs [--mode auto|working-tree|branch-compare|pr-compare]",
        "  [--base <ref>] [--head <ref>] [--pr <number>] [--repo <slug|path>]",
        "  [--reviewer-model <cursor-model>] [--summary-model <openai-model>]",
        "  [--summary-cache-dir <path>] [--no-summary-cache]",
        "  [--judge-model <cursor-model>]",
        "  [--force-reviewer <name>] [--force-reviewers <a,b,c>]",
        "  [--resume-run <run-id>] [--resume-latest] [--refresh-summaries]",
        "  [--preflight|--no-preflight] [--preflight-strict|--preflight-warn]",
        "  [--allow-missing-tasks] [--allow-merge-conflicts]",
        "  [--allow-missing-pr-body] [--allow-failing-checks]",
        "  [--allow-unresolved-threads]",
        "  [--related-pr <owner/repo#123>] [--related-prs <a,b,c>]",
        "  [--no-related-prs]",
        "  [--reviewer-concurrency <n>]",
        "  [--context-lines <n>]",
        "",
      ].join("\n")
    );
    process.exit(0);
  }
  const forcedReviewers = parseForcedReviewers([
    ...getArgValues("--force-reviewer"),
    getArgValue("--force-reviewers"),
  ]);
  const reviewerModel =
    getArgValue("--reviewer-model") ||
    getArgValue("--model") ||
    process.env.CURSOR_MODEL ||
    null;
  const judgeModel =
    getArgValue("--judge-model") ||
    process.env.CURSOR_JUDGE_MODEL ||
    null;
  const summaryModel =
    getArgValue("--summary-model") ||
    process.env.OPENAI_SUMMARY_MODEL ||
    SUMMARY_MODEL;
  const reviewerConcurrency = Number.parseInt(
    null === getArgValue("--reviewer-concurrency")
      ? ""
      : getArgValue("--reviewer-concurrency"),
    10
  );
  const relatedPrs = parseRelatedPrArgs([
    ...getArgValues("--related-pr"),
    getArgValue("--related-prs"),
  ]);
  const discoverRelatedPrs = false === hasArg("--no-related-prs");
  const input = {
    mode: getArgValue("--mode") || "auto",
    baseRef: getArgValue("--base"),
    headRef: getArgValue("--head"),
    prNumber: getArgValue("--pr"),
    repoArg: getArgValue("--repo"),
    model: reviewerModel,
    summaryModel,
    summaryCacheDir: getArgValue("--summary-cache-dir"),
    disableSummaryCache: hasArg("--no-summary-cache"),
    judgeModel,
    forcedReviewers,
    resumeRunId: getArgValue("--resume-run"),
    resumeLatest: hasArg("--resume-latest"),
    refreshSummaries: hasArg("--refresh-summaries"),
    preflightEnabled: hasArg("--preflight")
      ? true
      : hasArg("--no-preflight")
        ? false
        : null,
    preflightStrict: hasArg("--preflight-strict"),
    preflightWarn: hasArg("--preflight-warn"),
    allowMissingTasks: hasArg("--allow-missing-tasks"),
    allowMergeConflicts: hasArg("--allow-merge-conflicts"),
    allowMissingPrBody: hasArg("--allow-missing-pr-body"),
    allowFailingChecks: hasArg("--allow-failing-checks"),
    allowUnresolvedThreads: hasArg("--allow-unresolved-threads"),
    relatedPrs,
    discoverRelatedPrs,
    sequential: hasArg("--sequential"),
    staggerMs: Number.parseInt(getArgValue("--stagger-ms") || "1000", 10),
    reviewerConcurrency,
    contextLines: Number.parseInt(
      null === getArgValue("--context-lines")
        ? "8"
        : getArgValue("--context-lines"),
      10
    ),
    timeoutMs: Number.parseInt(
      null === getArgValue("--timeout-ms")
        ? "600000"
        : getArgValue("--timeout-ms"),
      10
    ),
  };
  log(
    `start: mode=${input.mode} pr=${input.prNumber || "none"} base=${input.baseRef || "none"} head=${input.headRef || "none"}`
  );
  try {
    const result = await orchestrator.invoke(input);
    await flushUsage({ status: "success" });
    process.stdout.write(`${JSON.stringify(result.summary, null, 2)}\n`);
  } catch (error) {
    await flushUsage({
      status: "error",
      errorMessage: error.message,
    });
    throw error;
  }
};

main().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exit(1);
});
