import path from "node:path";

import { task } from "@langchain/langgraph";

import { SUMMARY_CACHE_VERSION } from "../core/constants.mjs";
import { readJson, writeJson, writeText, ensureDir } from "../core/io.mjs";
import { log } from "../core/logging.mjs";
import { normalizeRelativePath } from "../core/paths.mjs";
import { getOpenAIClient } from "../core/openai.mjs";
import { getFilePatch, splitPatchIntoChunks } from "../facts/helpers.mjs";
import { resolveConcurrency, mapWithConcurrency } from "../reviewers/agent.mjs";
import {
  buildSummaryCacheKey,
  buildDynamicGroupsPrompt,
  buildGroupSummaryPrompt,
  buildOverallSummaryPrompt,
  buildSummaryPrompt,
  groupFilesByPrefix,
  readDynamicGroupsCache,
  readSummaryCache,
  summarizeDynamicGroups,
  summarizeFile,
  summarizeGroup,
  summarizeOverall,
} from "../summary/summary.mjs";

export const summarizeFilesTask = task(
  { name: "summarizeFiles" },
  async (facts) => {
    log("summaries: start");
    const openai = getOpenAIClient();
    if (null === openai) {
      log("summaries: missing OPENAI_API_KEY");
      writeJson(facts.outputPaths.summariesFiles, {
        skipped: true,
        reason: "Missing OPENAI_API_KEY.",
        files: [],
      });
      writeJson(facts.outputPaths.summariesGroups, {
        skipped: true,
        reason: "Missing OPENAI_API_KEY.",
        groups: [],
      });
      writeJson(facts.outputPaths.summariesDynamicGroups, {
        skipped: true,
        reason: "Missing OPENAI_API_KEY.",
        groups: [],
      });
      writeJson(facts.outputPaths.summariesOverall, {
        skipped: true,
        reason: "Missing OPENAI_API_KEY.",
        summary: "",
        confidence: 0,
      });
      return {
        skipped: true,
        files: [],
        groups: [],
        dynamic_groups: [],
        overall: null,
      };
    }
    const codeFileSet = new Set(facts.codeFiles);
    const filesToSummarize = (facts.fileMetadata || []).filter((file) =>
      codeFileSet.has(file.path)
    );
    const summaryCacheDir =
      "string" === typeof facts.summaryCacheDir &&
        "" !== facts.summaryCacheDir.trim()
        ? facts.summaryCacheDir.trim()
        : null;
    if (null != summaryCacheDir) {
      ensureDir(summaryCacheDir);
    }
    const fileConcurrency = resolveConcurrency(
      facts.config?.summaries?.file_concurrency,
      4
    );
    const fileStaggerMs = resolveConcurrency(
      facts.config?.summaries?.file_stagger_ms,
      100
    );
    const summaries = await mapWithConcurrency(
      filesToSummarize,
      fileConcurrency,
      async (fileMeta, index) => {
        const filePath = fileMeta.path;
        const logPrefix = `summaries: file ${index + 1}/${filesToSummarize.length} ${filePath}`;
        const normalizedPath = normalizeRelativePath(filePath);
        const perFilePath = path.join(
          facts.outputPaths.filesByPathRoot,
          `${normalizedPath}.json`
        );
        if (false === facts.refreshSummaries) {
          const existing = readJson(perFilePath);
          if (existing && existing.summary && 0 < String(existing.summary).length) {
            log(`${logPrefix} hit`);
            return existing;
          }
        }
        const patch = getFilePatch({
          mode: facts.mode,
          baseRef: facts.baseRef,
          headRef: facts.headRef,
          prNumber: facts.prMeta?.number || null,
          repoSlug: facts.repoSlug,
          filePath,
          contextLines: facts.contextLines,
          filePatch: fileMeta.patch ?? null,
        });
        const chunks = splitPatchIntoChunks(patch);
        const chunkIds = [];
        chunks.forEach((chunk, chunkIndex) => {
          const chunkId = `chunk-${String(chunkIndex + 1).padStart(4, "0")}`;
          chunkIds.push(chunkId);
          const chunkPath = path.join(
            facts.outputPaths.diffsRoot,
            normalizedPath,
            `${chunkId}.patch`
          );
          writeText(chunkPath, `${chunk}\n`);
        });
        const summaryPrompt = buildSummaryPrompt({
          filePath,
          fileMeta,
          chunks,
          taskContext: facts.taskContext,
        });
        const cacheKey =
          null != summaryCacheDir
            ? buildSummaryCacheKey({
              prompt: summaryPrompt,
              summaryModel: facts.summaryModel,
            })
            : null;
        const cachePath =
          null != summaryCacheDir && null != cacheKey
            ? path.join(summaryCacheDir, `${cacheKey}.json`)
            : null;
        if (false === facts.refreshSummaries && null != cachePath) {
          const cached = readSummaryCache(cachePath);
          if (cached) {
            log(`${logPrefix} hit`);
            const summaryEntry = {
              path: filePath,
              status: fileMeta.status || null,
              additions: fileMeta.additions ?? null,
              deletions: fileMeta.deletions ?? null,
              changes: fileMeta.changes ?? null,
              old_path: fileMeta.old_path ?? null,
              summary: cached.summary || "",
              confidence: cached.confidence ?? 0,
              evidence: cached.evidence || [],
              chunk_ids: chunkIds,
              error: cached.error || null,
            };
            writeJson(perFilePath, summaryEntry);
            return summaryEntry;
          }
        }
        log(`${logPrefix} miss`);
        let summaryResult = null;
        try {
          summaryResult = await summarizeFile({
            filePath,
            fileMeta,
            chunks,
            taskContext: facts.taskContext,
            prompt: summaryPrompt,
            openai,
            summaryModel: facts.summaryModel,
          });
        } catch (error) {
          summaryResult = {
            summary: "",
            confidence: 0,
            evidence: [],
            error: error.message,
          };
        }
        const summaryEntry = {
          path: filePath,
          status: fileMeta.status || null,
          additions: fileMeta.additions ?? null,
          deletions: fileMeta.deletions ?? null,
          changes: fileMeta.changes ?? null,
          old_path: fileMeta.old_path ?? null,
          summary: summaryResult.summary || "",
          confidence: summaryResult.confidence ?? 0,
          evidence: summaryResult.evidence || [],
          chunk_ids: chunkIds,
          error: summaryResult.error || null,
        };
        if (null != cachePath && summaryEntry.summary) {
          writeJson(cachePath, {
            version: SUMMARY_CACHE_VERSION,
            model: facts.summaryModel,
            summary: summaryEntry.summary,
            confidence: summaryEntry.confidence,
            evidence: summaryEntry.evidence,
            error: summaryEntry.error || null,
            cached_at: new Date().toISOString(),
          });
        }
        writeJson(perFilePath, summaryEntry);
        return summaryEntry;
      },
      fileStaggerMs
    );
    writeJson(facts.outputPaths.summariesFiles, {
      skipped: false,
      files: summaries,
    });
    const groups = groupFilesByPrefix(summaries);
    const groupCounts = groups.map((group) => ({
      key: group.key,
      count: group.files.length,
    }));
    const maxGroupSize = groupCounts.reduce(
      (max, group) => Math.max(max, group.count),
      0
    );
    const topGroups = [...groupCounts]
      .sort((a, b) => b.count - a.count || a.key.localeCompare(b.key))
      .slice(0, 12);
    log(
      `summaries: groups=${groupCounts.length} max_group_size=${maxGroupSize}`
    );
    log(
      `summaries: top_groups=${topGroups
        .map((group) => `${group.key} (${group.count})`)
        .join(", ")}`
    );
    const groupSummaries = [];
    for (let index = 0; index < groups.length; index += 1) {
      const group = groups[index];
      log(`summaries: group ${index + 1}/${groups.length} start ${group.key}`);
      let summaryResult = null;
      try {
        const groupPrompt = buildGroupSummaryPrompt({
          groupKey: group.key,
          files: group.files,
          taskContext: facts.taskContext,
        });
        const groupCacheKey =
          null != summaryCacheDir
            ? buildSummaryCacheKey({
              prompt: groupPrompt,
              summaryModel: facts.summaryModel,
            })
            : null;
        const groupCachePath =
          null != summaryCacheDir && null != groupCacheKey
            ? path.join(summaryCacheDir, `${groupCacheKey}.json`)
            : null;
        if (false === facts.refreshSummaries && null != groupCachePath) {
          const cached = readSummaryCache(groupCachePath);
          if (cached) {
            summaryResult = {
              summary: cached.summary || "",
              confidence: cached.confidence ?? 0,
              error: cached.error || null,
            };
          }
        }
        if (null == summaryResult) {
          summaryResult = await summarizeGroup({
            groupKey: group.key,
            files: group.files,
            openai,
            summaryModel: facts.summaryModel,
          });
          if (
            null != groupCachePath &&
            summaryResult.summary &&
            false === facts.refreshSummaries
          ) {
            writeJson(groupCachePath, {
              version: SUMMARY_CACHE_VERSION,
              model: facts.summaryModel,
              summary: summaryResult.summary,
              confidence: summaryResult.confidence ?? 0,
              error: summaryResult.error || null,
              cached_at: new Date().toISOString(),
            });
          }
        }
      } catch (error) {
        summaryResult = {
          summary: "",
          confidence: 0,
          error: error.message,
        };
      }
      log(`summaries: group ${index + 1}/${groups.length} done ${group.key}`);
      groupSummaries.push({
        key: group.key,
        summary: summaryResult.summary || "",
        confidence: summaryResult.confidence ?? 0,
        error: summaryResult.error || null,
        file_count: group.files.length,
      });
    }
    writeJson(facts.outputPaths.summariesGroups, {
      skipped: false,
      groups: groupSummaries,
    });
    let dynamicGroupsResult = null;
    try {
      log("summaries: dynamic_groups start");
      const dynamicConfig = facts.config?.summaries?.dynamic_groups || {};
      const dynamicGroupsPrompt = buildDynamicGroupsPrompt({
        files: summaries,
        maxGroups: dynamicConfig.max_groups ?? 8,
        maxFilesPerGroup: dynamicConfig.max_files_per_group ?? 8,
        maxFileCount: dynamicConfig.max_file_count ?? 200,
      });
      const dynamicGroupsCacheKey =
        null != summaryCacheDir
          ? buildSummaryCacheKey({
            prompt: dynamicGroupsPrompt,
            summaryModel: facts.summaryModel,
          })
          : null;
      const dynamicGroupsCachePath =
        null != summaryCacheDir && null != dynamicGroupsCacheKey
          ? path.join(summaryCacheDir, `${dynamicGroupsCacheKey}.json`)
          : null;
      if (false === facts.refreshSummaries && null != dynamicGroupsCachePath) {
        const cached = readDynamicGroupsCache(dynamicGroupsCachePath);
        if (cached) {
          dynamicGroupsResult = {
            groups: cached.groups || [],
            error: cached.error || null,
          };
        }
      }
      if (null == dynamicGroupsResult) {
        dynamicGroupsResult = await summarizeDynamicGroups({
          files: summaries,
          openai,
          summaryModel: facts.summaryModel,
          maxGroups: dynamicConfig.max_groups ?? 8,
          maxFilesPerGroup: dynamicConfig.max_files_per_group ?? 8,
          maxFileCount: dynamicConfig.max_file_count ?? 200,
        });
        if (
          null != dynamicGroupsCachePath &&
          false === facts.refreshSummaries &&
          Array.isArray(dynamicGroupsResult?.groups)
        ) {
          writeJson(dynamicGroupsCachePath, {
            version: SUMMARY_CACHE_VERSION,
            model: facts.summaryModel,
            groups: dynamicGroupsResult.groups,
            error: dynamicGroupsResult.error || null,
            cached_at: new Date().toISOString(),
          });
        }
      }
      const dynamicGroups = Array.isArray(dynamicGroupsResult?.groups)
        ? dynamicGroupsResult.groups
        : [];
      const dynamicGroupCounts = dynamicGroups.map((group) => ({
        label: String(group?.label || "(unlabeled)"),
        count: Array.isArray(group?.file_paths) ? group.file_paths.length : 0,
      }));
      const dynamicMaxGroupSize = dynamicGroupCounts.reduce(
        (max, group) => Math.max(max, group.count),
        0
      );
      const dynamicTopGroups = [...dynamicGroupCounts]
        .sort((a, b) => b.count - a.count || a.label.localeCompare(b.label))
        .slice(0, 12);
      log(
        `summaries: dynamic_groups groups=${dynamicGroupCounts.length} max_group_size=${dynamicMaxGroupSize}`
      );
      log(
        `summaries: dynamic_groups top_groups=${dynamicTopGroups
          .map((group) => `${group.label} (${group.count})`)
          .join(", ")}`
      );
      log("summaries: dynamic_groups done");
    } catch (error) {
      dynamicGroupsResult = {
        groups: [],
        error: error.message,
      };
    }
    writeJson(facts.outputPaths.summariesDynamicGroups, {
      skipped: false,
      groups: dynamicGroupsResult.groups || [],
      error: dynamicGroupsResult.error || null,
    });
    let overallSummary = null;
    try {
      log("summaries: overall start");
      const overallPrompt = buildOverallSummaryPrompt({
        groupSummaries,
        taskContext: facts.taskContext,
      });
      const overallCacheKey =
        null != summaryCacheDir
          ? buildSummaryCacheKey({
            prompt: overallPrompt,
            summaryModel: facts.summaryModel,
          })
          : null;
      const overallCachePath =
        null != summaryCacheDir && null != overallCacheKey
          ? path.join(summaryCacheDir, `${overallCacheKey}.json`)
          : null;
      if (false === facts.refreshSummaries && null != overallCachePath) {
        const cached = readSummaryCache(overallCachePath);
        if (cached) {
          overallSummary = {
            summary: cached.summary || "",
            confidence: cached.confidence ?? 0,
            error: cached.error || null,
          };
        }
      }
      if (null == overallSummary) {
        overallSummary = await summarizeOverall({
          groupSummaries,
          taskContext: facts.taskContext,
          openai,
          summaryModel: facts.summaryModel,
        });
        if (
          null != overallCachePath &&
          overallSummary.summary &&
          false === facts.refreshSummaries
        ) {
          writeJson(overallCachePath, {
            version: SUMMARY_CACHE_VERSION,
            model: facts.summaryModel,
            summary: overallSummary.summary,
            confidence: overallSummary.confidence ?? 0,
            error: overallSummary.error || null,
            cached_at: new Date().toISOString(),
          });
        }
      }
      log("summaries: overall done");
    } catch (error) {
      overallSummary = {
        summary: "",
        confidence: 0,
        error: error.message,
      };
    }
    writeJson(facts.outputPaths.summariesOverall, {
      skipped: false,
      summary: overallSummary.summary || "",
      confidence: overallSummary.confidence ?? 0,
      error: overallSummary.error || null,
    });
    return {
      skipped: false,
      files: summaries,
      groups: groupSummaries,
      dynamic_groups: dynamicGroupsResult?.groups || [],
      overall: overallSummary,
    };
  }
);
