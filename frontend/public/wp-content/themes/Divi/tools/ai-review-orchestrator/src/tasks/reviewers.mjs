import path from "node:path";

import { task } from "@langchain/langgraph";

import { ensureDir, writeJson, writeText } from "../core/io.mjs";
import { log } from "../core/logging.mjs";
import { parseJsonSafe, unique } from "../core/utils.mjs";
import { loadReviewerDefinitions } from "../reviewers/loaders.mjs";
import {
  buildReviewerMergePrompt,
  decisionPrompt,
  reviewerPrompt,
} from "../reviewers/prompts.mjs";
import {
  buildRequiredReviewers,
  resolveReviewerModels,
  resolveReviewerRuns,
  selectReviewerFiles,
} from "../reviewers/selection.mjs";
import {
  callAgent,
  mapWithConcurrency,
  normalizeReviewerOutput,
  resolveConcurrency,
} from "../reviewers/agent.mjs";

export const decideReviewers = task({ name: "decideReviewers" }, async (facts) => {
  log("decide: start");
  const reviewers = loadReviewerDefinitions(facts.repoRoot);
  if (0 === reviewers.length) {
    return { selectedReviewers: [], rationale: "No reviewers found." };
  }
  const prompt = decisionPrompt({
    reviewers,
    changedFiles: facts.changedFiles,
    codeFiles: facts.codeFiles,
    taskFiles: facts.taskFiles,
    sizeKey: facts.sizeKey,
    config: facts.config,
    taskContext: facts.taskContext,
    mode: facts.mode,
    baseRef: facts.baseRef,
    headRef: facts.headRef,
    relatedPrs: facts.relatedPrs || [],
    companionContext: facts.companionContext || null,
  });
  let decisionOutput = null;
  try {
    const response = await callAgent(prompt, {
      model: facts.model,
      workspace: facts.repoRoot,
      timeoutMs: facts.timeoutMs,
      scope: "decide",
    });
    decisionOutput = parseJsonSafe(response);
    log(
      "decide: agent output",
      JSON.stringify(
        {
          parsed: decisionOutput,
          raw: response,
        },
        null,
        2
      )
    );
  } catch (error) {
    decisionOutput = null;
  }
  if (null == decisionOutput?.selected_reviewers) {
    log("decide: fallback to all reviewers");
    return {
      selectedReviewers: reviewers.map((reviewer) => reviewer.name),
      rationale: "Fallback to all reviewers.",
    };
  }
  const selectedReviewers = reviewers
    .filter((reviewer) => decisionOutput.selected_reviewers.includes(reviewer.name))
    .map((reviewer) => reviewer.name);
  const forced = (facts.forcedReviewers || []).filter((name) =>
    reviewers.some((reviewer) => reviewer.name === name)
  );
  const required = Array.from(buildRequiredReviewers(facts));
  const merged = unique([...selectedReviewers, ...forced, ...required]).filter(
    (name) => reviewers.some((reviewer) => reviewer.name === name)
  );
  if (0 < forced.length) {
    log(`decide: forced reviewers ${forced.join(", ")}`);
  }
  if (0 < required.length) {
    log(`decide: required reviewers ${required.join(", ")}`);
  }
  log(`decide: selected ${merged.join(", ")}`);
  return {
    selectedReviewers: merged,
    rationale: decisionOutput.rationale || "",
  };
});

export const runReviewers = task(
  { name: "runReviewers" },
  async ({ facts, decision }) => {
    log("reviewers: start");
    const reviewers = loadReviewerDefinitions(facts.repoRoot).filter((reviewer) =>
      decision.selectedReviewers.includes(reviewer.name)
    );
    const reviewerConcurrency =
      true === facts.sequential
        ? 1
        : resolveConcurrency(
            null == facts.reviewerConcurrency
              ? facts.config?.reviewers?.concurrency
              : facts.reviewerConcurrency,
            reviewers.length || 1
          );
    const reviewersTotal = reviewers.length;
    let reviewersDone = 0;
    log(
      `reviewers: running ${reviewers.length} concurrency=${reviewerConcurrency}`
    );
    let nextLaunchAt = Date.now();
    const waitForLaunchSlot = async () => {
      if (facts.staggerMs <= 0) {
        return;
      }
      const now = Date.now();
      const waitMs = Math.max(0, nextLaunchAt - now);
      nextLaunchAt = Math.max(now, nextLaunchAt) + facts.staggerMs;
      if (waitMs > 0) {
        await new Promise((resolve) => setTimeout(resolve, waitMs));
      }
    };
    const runReviewer = async (reviewer, index) => {
      if (true === facts.sequential) {
        // No stagger needed for sequential runs.
      }
      log(`reviewer: ${reviewer.name} start`);
      const reviewRound = Number(facts.retroReview?.review_round || 1);
      const isLaterRound = reviewRound >= 2;
      const maxFiles = isLaterRound
        ? facts.config?.review_rounds?.later_pass_max_files ?? 12
        : facts.config?.review_rounds?.first_pass_max_files ?? 24;
      const deltaOnly =
        true === isLaterRound &&
        false !== facts.config?.review_rounds?.later_pass_delta_only &&
        "review-retro-feedback" !== reviewer.name;
      const noNewDelta = true === facts.retroReview?.same_sha_as_last_review;
      const keepPaths = (facts.retroReview?.threads || [])
        .filter(
          (thread) =>
            "rebutted" !== thread?.status &&
            "waived" !== thread?.status &&
            (false === thread?.is_resolved ||
              "open" === thread?.status ||
              "acknowledged" === thread?.status)
        )
        .flatMap((thread) =>
          (thread.comments || []).map((comment) => comment.path).filter(Boolean)
        );
      const focusedFiles = selectReviewerFiles({
        reviewer,
        summaries: facts.summaries || null,
        maxFiles,
        deltaPaths: facts.retroReview?.delta_paths || null,
        keepPaths,
        deltaOnly,
        noNewDelta,
        preferKeepPaths: "review-retro-feedback" === reviewer.name,
        lowSignalGlobs: Array.isArray(
          facts.config?.review_rounds?.low_signal_globs
        )
          ? facts.config.review_rounds.low_signal_globs
          : undefined,
      });
      if (focusedFiles.length) {
        const sample = focusedFiles
          .slice(0, 6)
          .map((file) => file.path)
          .join(", ");
        log(
          `reviewer: ${reviewer.name} focused_files=${focusedFiles.length} round=${reviewRound} delta_only=${deltaOnly} sample=${sample}`
        );
      } else {
        log(
          `reviewer: ${reviewer.name} focused_files=0 round=${reviewRound} delta_only=${deltaOnly}`
        );
      }
      const outputContract =
        "review-retro-feedback" === reviewer.name
          ? facts.outputContracts?.retroFeedback
          : facts.outputContracts?.reviewer;
      const prompt = reviewerPrompt({
        reviewer,
        changedFiles: facts.changedFiles,
        taskFiles: facts.taskFiles,
        taskContext: facts.taskContext,
        retroReview: facts.retroReview || null,
        mode: facts.mode,
        baseRef: facts.baseRef,
        headRef: facts.headRef,
        prNumber: facts.prMeta?.number || null,
        repoSlug: facts.repoSlug,
        repoRoot: facts.repoRoot,
        summaries: facts.summaries || null,
        outputPaths: facts.outputPaths || null,
        focusedFiles,
        outputContract: outputContract || "",
        relatedPrs: facts.relatedPrs || [],
        companionContext: facts.companionContext || null,
      });
      const reviewerModel = "inherit" !== reviewer.model ? reviewer.model : facts.model;
      const runCount = resolveReviewerRuns({
        reviewer,
        sizeKey: facts.sizeKey,
        config: facts.config,
      });
      const models = resolveReviewerModels({
        reviewer,
        reviewerModel,
        config: facts.config,
        runCount,
      });
      const outputsRoot = facts.outputPaths?.reviewersOutputsRoot || null;
      const promptsRoot = facts.outputPaths?.reviewersPromptsRoot || null;
      if (promptsRoot) {
        if (1 === runCount) {
          const promptPath = path.join(promptsRoot, `${reviewer.name}.txt`);
          writeText(promptPath, `${prompt}\n`);
        } else {
          const reviewerPromptRoot = path.join(promptsRoot, reviewer.name);
          ensureDir(reviewerPromptRoot);
          models.forEach((model, runIndex) => {
            const promptPath = path.join(
              reviewerPromptRoot,
              `run-${String(runIndex + 1).padStart(2, "0")}.txt`
            );
            const modelLine = model ? `Model override: ${model}` : "Model override: (default)";
            writeText(promptPath, `${modelLine}\n\n${prompt}\n`);
          });
        }
      }
      const markReviewerDone = () => {
        reviewersDone += 1;
        log(`reviewer: ${reviewer.name} done (${reviewersDone}/${reviewersTotal})`);
      };
      const runSingle = async (model, runIndex) => {
        const modelLabel = model ? String(model).trim() : `run-${runIndex + 1}`;
        await waitForLaunchSlot();
        try {
          log(
            `reviewer: ${reviewer.name} run ${runIndex + 1} model=${modelLabel} timeout_ms=${facts.timeoutMs || "none"}`
          );
          const output = await callAgent(prompt, {
            model,
            workspace: facts.repoRoot,
            timeoutMs: facts.timeoutMs,
            scope: `${reviewer.name}:${modelLabel}`,
          });
          const normalized = normalizeReviewerOutput(output);
          if (outputsRoot) {
            const outputPath =
              1 === runCount
                ? path.join(outputsRoot, `${reviewer.name}.json`)
                : path.join(
                  outputsRoot,
                  reviewer.name,
                  `run-${String(runIndex + 1).padStart(2, "0")}.json`
                );
            ensureDir(path.dirname(outputPath));
            writeText(outputPath, `${normalized.cleanText || output}\n`);
          }
          return {
            output: normalized.cleanText || output,
            parsed: normalized.parsed,
            error: null,
          };
        } catch (error) {
          log(`reviewer: ${reviewer.name} run ${runIndex + 1} error ${error.message}`);
          if (outputsRoot) {
            const outputPath =
              1 === runCount
                ? path.join(outputsRoot, `${reviewer.name}.error.json`)
                : path.join(
                  outputsRoot,
                  reviewer.name,
                  `run-${String(runIndex + 1).padStart(2, "0")}.error.json`
                );
            ensureDir(path.dirname(outputPath));
            writeJson(outputPath, {
              reviewer: reviewer.name,
              run: runIndex + 1,
              error: error.message,
            });
          }
          return { output: "", parsed: null, error: error.message };
        }
      };
      const runResults = await Promise.all(
        models.map((model, runIndex) => runSingle(model, runIndex))
      );
      const validOutputs = runResults
        .map((result) => result.output)
        .filter((output) => output && output.trim());
      if (runCount > 1 && validOutputs.length > 1) {
        const judgeModel = facts.judgeModel || reviewerModel || facts.model;
        const judgePrompt = buildReviewerMergePrompt({
          reviewer,
          outputs: validOutputs,
          outputContract: facts.outputContracts?.reviewer || "",
          sizeKey: facts.sizeKey,
        });
        if (promptsRoot) {
          const judgePromptPath = path.join(promptsRoot, `${reviewer.name}.judge.txt`);
          writeText(judgePromptPath, `${judgePrompt}\n`);
        }
        try {
          await waitForLaunchSlot();
          const judgedOutput = await callAgent(judgePrompt, {
            model: judgeModel,
            workspace: facts.repoRoot,
            timeoutMs: facts.timeoutMs,
            scope: `${reviewer.name}:judge`,
          });
          const normalized = normalizeReviewerOutput(judgedOutput);
          if (outputsRoot) {
            const outputPath = path.join(outputsRoot, `${reviewer.name}.json`);
            writeText(outputPath, `${normalized.cleanText || judgedOutput}\n`);
          }
          log(`reviewer: ${reviewer.name} judge done`);
          markReviewerDone();
          return {
            reviewer: reviewer.name,
            output: normalized.cleanText || judgedOutput,
            parsed: normalized.parsed,
            modelRuns: models,
            judgeModel,
          };
        } catch (error) {
          log(`reviewer: ${reviewer.name} judge error ${error.message}`);
        }
      }
      const fallback =
        runResults.find((result) => result.parsed)?.output ||
        validOutputs[0] ||
        "";
      log(`reviewer: ${reviewer.name} done`);
      markReviewerDone();
      return {
        reviewer: reviewer.name,
        output: fallback,
        parsed: parseJsonSafe(fallback),
        modelRuns: models,
        judgeModel: runCount > 1 ? facts.judgeModel || reviewerModel || facts.model : null,
      };
    };
    if (true === facts.sequential) {
      const results = [];
      for (let index = 0; index < reviewers.length; index += 1) {
        results.push(await runReviewer(reviewers[index], index));
      }
      return results;
    }
    return mapWithConcurrency(reviewers, reviewerConcurrency, runReviewer);
  }
);
