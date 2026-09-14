import path from "node:path";

import { task } from "@langchain/langgraph";

import { readJson, writeJson, writeText } from "../core/io.mjs";
import { log } from "../core/logging.mjs";
import { loadReviewerDefinitions } from "../reviewers/loaders.mjs";
import {
  buildFindingKey,
  buildInlineComments,
} from "../comments/inline-comments.mjs";
import {
  buildConventionalHeaderFromFinding,
  resolveConventionalMeta,
} from "../comments/formatting.mjs";
import { applyRetroDupeFilter } from "../comments/retro-dupe-filter.mjs";
import { applyRetroActions } from "../comments/retro-actions.mjs";
import {
  relatedTargetKey,
  resolveFindingReviewTarget,
} from "../facts/helpers.mjs";

const labelOrder = {
  issue_blocking: 5,
  issue_non_blocking: 4,
  suggestion: 3,
  question: 2,
  note: 1,
  nitpick: 0,
  other: 1,
};

const getFindingBucket = (finding) => {
  const meta = resolveConventionalMeta(finding);
  if ("issue" === meta.label) {
    return meta.decorations.includes("blocking")
      ? "issue_blocking"
      : "issue_non_blocking";
  }
  if ("suggestion" === meta.label) {
    return "suggestion";
  }
  if ("question" === meta.label) {
    return "question";
  }
  if ("note" === meta.label) {
    return "note";
  }
  if ("nitpick" === meta.label) {
    return "nitpick";
  }
  return "other";
};

const normalizeLocationPath = (locationPath) => {
  if (null === locationPath) {
    return null;
  }
  const normalized = path.normalize(locationPath);
  return normalized.replace(/^[.][\\/]/, "");
};

const filterFindingLocations = (finding, validPaths) => {
  const locations = Array.isArray(finding.locations) ? finding.locations : null;
  if (null === locations) {
    return finding;
  }
  const droppedPaths = new Set();
  const filteredLocations = locations
    .map((location) => {
      const locationPath = normalizeLocationPath(location?.path ?? null);
      if (null === locationPath) {
        return null;
      }
      if (true === validPaths.has(locationPath)) {
        return { ...location, path: locationPath };
      }
      droppedPaths.add(locationPath);
      return null;
    })
    .filter(Boolean);
  if (0 === filteredLocations.length) {
    if (0 < droppedPaths.size) {
      log(
        `[aggregate] dropped finding outside diff: ${finding?.title || "Finding"} -> ${[
          ...droppedPaths,
        ].join(", ")}`
      );
    }
    return null;
  }
  return { ...finding, locations: filteredLocations };
};

const githubPrUrl = (repoSlug, prNumber) => {
  if (!repoSlug || null == prNumber) {
    return null;
  }
  return `https://github.com/${repoSlug}/pull/${prNumber}`;
};

const formatFindingDetails = (finding, index) => {
  const header = buildConventionalHeaderFromFinding(finding);
  const titleLine = `${index + 1}. ${header}`;
  const detailLines = [];
  const target = finding.review_target;
  if (target && "related" === target.kind && target.repoSlug) {
    const companionLabel = relatedTargetKey(target) || target.repoSlug;
    const companionUrl = githubPrUrl(target.repoSlug, target.prNumber);
    detailLines.push(
      companionUrl
        ? `   Companion: [${companionLabel}](${companionUrl})`
        : `   Companion: ${companionLabel}`
    );
  }
  if (finding.reviewer) {
    detailLines.push(`   Reviewer: ${finding.reviewer.replace(/^review-/, "")}`);
  }
  const confidenceValue = Number(finding.confidence);
  if (Number.isFinite(confidenceValue)) {
    detailLines.push(`   Confidence: ${Math.round(confidenceValue * 100)}%`);
  }
  if (finding.rationale) {
    detailLines.push(`   Rationale: ${finding.rationale}`);
  }
  if (finding.suggested_fix) {
    detailLines.push(`   Suggestion: ${finding.suggested_fix}`);
  }
  const locations = Array.isArray(finding.locations) ? finding.locations : [];
  if (0 < locations.length) {
    detailLines.push("   Locations:");
    locations.forEach((location) => {
      const displayPath = target?.originalPath || location?.path;
      const relatedHint =
        target && "related" === target.kind && location?.path && displayPath !== location.path
          ? ` (review path: \`${location.path}\`)`
          : "";
      const pathLine = displayPath
        ? `- \`${displayPath}\`${location.lines ? ` (${location.lines})` : ""}${relatedHint}`
        : null;
      if (pathLine) {
        detailLines.push(`     ${pathLine}`);
      }
      if (location?.snippet) {
        detailLines.push(`     ${location.snippet}`);
      }
    });
  }
  return [titleLine, ...detailLines].join("\n");
};

const formatFindingsList = (findings) =>
  findings.map((finding, index) => formatFindingDetails(finding, index)).join("\n");

const buildOriginSummarySuffix = (findings) => {
  const relatedCounts = new Map();
  let thisPrCount = 0;
  findings.forEach((finding) => {
    const target = finding.review_target;
    if (target && "related" === target.kind && target.repoSlug) {
      const key = relatedTargetKey(target) || target.repoSlug;
      relatedCounts.set(key, (relatedCounts.get(key) || 0) + 1);
      return;
    }
    thisPrCount += 1;
  });
  if (0 === relatedCounts.size) {
    return "";
  }
  const relatedParts = [...relatedCounts.entries()].map(
    ([key, count]) => `${count} on companion ${key}`
  );
  return ` ${thisPrCount} this PR, ${relatedParts.join(", ")}.`;
};

const applyConfidenceRules = (finding, thresholds) => {
  if (null == thresholds) {
    return finding;
  }
  const confidence = Number(finding.confidence ?? 0);
  if (confidence < thresholds.drop_below) {
    return null;
  }
  const blockingMin = thresholds.blocking_min ?? thresholds.blocker_min ?? 0.9;
  const nonBlockingMin =
    thresholds.non_blocking_min ?? thresholds.concern_min ?? 0.75;
  const suggestionMin =
    thresholds.suggestion_min ?? thresholds.concern_min ?? nonBlockingMin;
  const updated = { ...finding };
  const meta = resolveConventionalMeta(updated);
  if ("issue" === meta.label && meta.decorations.includes("blocking")) {
    if (confidence < blockingMin) {
      updated.comment_label = "issue";
      updated.comment_decorations = ["non-blocking"];
    }
    return updated;
  }
  if ("issue" === meta.label && confidence < nonBlockingMin) {
    return null;
  }
  if ("suggestion" === meta.label && confidence < suggestionMin) {
    return null;
  }
  if ("issue" !== meta.label && "suggestion" !== meta.label) {
    if (confidence < nonBlockingMin) {
      return null;
    }
  }
  return updated;
};

const COMPANION_DEPENDENCY_TAG = "companion-dependency-order";

const COMPANION_MERGE_REMINDER_PATTERNS = [
  /\bboth\s+(?:prs|pull requests)\b.{0,80}\bmerg/,
  /\bmerg.{0,80}\bboth\s+(?:prs|pull requests)\b/,
  /\bcompanion\s+pr\b.{0,80}\bmerg/,
  /\bmerg.{0,80}\bcompanion\s+pr\b/,
  /\brelated\s+pr\b.{0,80}\bmerg/,
  /\bmerg.{0,80}\brelated\s+pr\b/,
  /\bother\s+pr\b.{0,80}\bmerg/,
  /\bmerg.{0,80}\bother\s+pr\b/,
  /\bmake sure\b.{0,80}\b(?:both|companion|related|other)\s+prs?\b/,
  /\b(?:companion|related|other)\s+pr\b.{0,80}\bmake sure\b/,
  /\bmerge[-\s]order\b/,
  /\bdo(?:n'?t| not)\s+merge\s+this\s+without\b/,
];

const normalizeTag = (value) =>
  null == value ? "" : String(value).trim().toLowerCase();

const hasCompanionDependencyTag = (finding) =>
  Array.isArray(finding?.tags) &&
  finding.tags.some((tag) => COMPANION_DEPENDENCY_TAG === normalizeTag(tag));

const findingSearchText = (finding) =>
  [finding?.title, finding?.rationale, finding?.suggested_fix]
    .filter(Boolean)
    .join(" ")
    .toLowerCase();

const isCompanionMergeReminder = (finding) => {
  if (true === hasCompanionDependencyTag(finding)) {
    return true;
  }
  const text = findingSearchText(finding);
  if ("" === text) {
    return false;
  }
  return COMPANION_MERGE_REMINDER_PATTERNS.some((pattern) => pattern.test(text));
};

const applyCompanionMergeReminderRule = (finding) => {
  if (false === isCompanionMergeReminder(finding)) {
    return finding;
  }
  log(
    `[aggregate] dropped companion merge reminder: ${finding?.title || "Finding"}`
  );
  return null;
};

const enforceCaps = (findings, config, sizeKey) => {
  const caps = config?.comment_label_caps || {};
  const budget = config?.comment_budget_by_size?.[sizeKey] ?? Infinity;
  const grouped = {
    issue_blocking: [],
    issue_non_blocking: [],
    suggestion: [],
    question: [],
    note: [],
    nitpick: [],
    other: [],
  };
  findings.forEach((finding) => {
    const bucket = getFindingBucket(finding);
    const key = grouped[bucket] ? bucket : "other";
    grouped[key].push(finding);
  });
  const capped = [];
  const overflow = [];
  const capFor = (bucket) => caps[`${bucket}_max`] ?? Infinity;
  [
    "issue_blocking",
    "issue_non_blocking",
    "suggestion",
    "question",
    "note",
    "nitpick",
    "other",
  ].forEach((bucket) => {
    const list = grouped[bucket].sort(
      (a, b) => (b.confidence || 0) - (a.confidence || 0)
    );
    const keep = list.slice(0, capFor(bucket));
    const drop = list.slice(capFor(bucket));
    capped.push(...keep);
    overflow.push(...drop);
  });
  const sorted = capped.sort((a, b) => {
    const bucketDiff =
      (labelOrder[getFindingBucket(b)] || 0) -
      (labelOrder[getFindingBucket(a)] || 0);
    if (0 !== bucketDiff) {
      return bucketDiff;
    }
    return (b.confidence || 0) - (a.confidence || 0);
  });
  const budgeted = sorted.slice(0, budget);
  const budgetOverflow = sorted.slice(budget);
  return {
    budgeted,
    overflow: [...overflow, ...budgetOverflow],
  };
};

export const aggregateResults = task(
  { name: "aggregateResults" },
  async ({ facts, results }) => {
    log("aggregate: start");
    const thresholds  = facts.config?.confidence_thresholds;
    const validPaths  = new Set(facts.changedFiles || []);
    const allFindings = [];
    const reviewerStats = {};
    results.forEach((result) => {
      const parsed = result.parsed;
      if (null == parsed || false === Array.isArray(parsed.findings)) {
        return;
      }
      reviewerStats[result.reviewer] = parsed.findings.length;
      parsed.findings.forEach((finding) => {
        const filtered = filterFindingLocations(finding, validPaths);
        if (null === filtered) {
          return;
        }
        const confidenceAdjusted = applyConfidenceRules(filtered, thresholds);
        if (confidenceAdjusted) {
          const companionAdjusted =
            applyCompanionMergeReminderRule(confidenceAdjusted);
          if (companionAdjusted) {
            allFindings.push({ ...companionAdjusted, reviewer: result.reviewer });
          }
        }
      });
    });
    const retroFiltered = await applyRetroDupeFilter({
      facts,
      findings: allFindings,
    });
    const filteredFindings =
      Array.isArray(retroFiltered?.filtered) && 0 < retroFiltered.filtered.length
        ? retroFiltered.filtered
        : allFindings;
    const retroDroppedCount = Array.isArray(retroFiltered?.dropped)
      ? retroFiltered.dropped.length
      : 0;
    const retroReport = retroFiltered?.report ?? null;
    const { budgeted, overflow } = enforceCaps(
      filteredFindings,
      facts.config,
      facts.sizeKey
    );
    const prFindings = budgeted.filter((finding) => {
      const meta = resolveConventionalMeta(finding);
      if ("suggestion" === meta.label) {
        const confidenceMin =
          facts.config?.confidence_thresholds?.suggestion_min ??
          facts.config?.confidence_thresholds?.non_blocking_min ??
          0.75;
        return Number(finding?.confidence ?? 0) >= confidenceMin;
      }
      if ("issue" === meta.label && meta.decorations.includes("blocking")) {
        return true;
      }
      return false;
    });
    const summaryForInline = { pr_comment: { findings: prFindings } };
    const inlineResult = facts.outputPaths
      ? await buildInlineComments(summaryForInline, facts)
      : { comments: [], inlinedKeys: new Set(), relatedComments: [] };
    const inlinedKeys = inlineResult?.inlinedKeys || new Set();
    const prFindingsForComment = prFindings.map((finding) => {
      const reviewTarget = resolveFindingReviewTarget(finding, facts);
      return {
        ...finding,
        review_target: reviewTarget,
        posted_inline:
          "related" !== reviewTarget.kind &&
          inlinedKeys.has(buildFindingKey(finding, finding.locations?.[0])),
      };
    });
    const summaryCounts = (() => {
      const counts = new Map();
      prFindingsForComment.forEach((finding) => {
        const meta = resolveConventionalMeta(finding);
        const label = meta.label || "note";
        counts.set(label, (counts.get(label) || 0) + 1);
      });
      const orderedLabels = ["issue", "suggestion", "question", "note", "nitpick"];
      const summaryParts = orderedLabels
        .filter((label) => counts.has(label))
        .map((label) => `${counts.get(label)} ${label}${1 === counts.get(label) ? "" : "s"}`);
      const remaining = [...counts.entries()]
        .filter(([label]) => false === orderedLabels.includes(label))
        .map(([label, count]) => `${count} ${label}${1 === count ? "" : "s"}`);
      const allParts = [...summaryParts, ...remaining];
      const labelSummary = allParts.length ? allParts.join(", ") + "." : "No findings.";
      if ("No findings." === labelSummary) {
        return labelSummary;
      }
      const originSuffix = buildOriginSummarySuffix(prFindingsForComment);
      return originSuffix
        ? `${labelSummary.replace(/\.$/, "")}.${originSuffix}`
        : labelSummary;
    })();
    const summary = {
      pr_comment: {
        summary: summaryCounts,
        findings: prFindingsForComment,
      },
      private_summary: {
        summary: `Total findings: ${allFindings.length}.`,
        findings: [...budgeted, ...overflow],
        trends: [],
        reviewer_stats: reviewerStats,
      },
    };
    log(
      `aggregate: pr_findings=${prFindings.length} total_findings=${allFindings.length}`
    );
    if (facts.outputPaths) {
      writeJson(facts.outputPaths.aggregateFindings, summary);
      if (facts.outputPaths.retroDupeReport && retroReport) {
        writeJson(facts.outputPaths.retroDupeReport, retroReport);
      }
      const inlineComments = inlineResult?.comments || [];
      writeJson(
        path.join(facts.outputPaths.outputRoot, "aggregate/inline-comments.json"),
        inlineComments
      );
      const relatedInlineComments = inlineResult?.relatedComments || [];
      writeJson(
        path.join(facts.outputPaths.outputRoot, "aggregate/related-inline-comments.json"),
        relatedInlineComments
      );
      const reviewersDecision = facts.outputPaths?.reviewersDecision
        ? readJson(facts.outputPaths.reviewersDecision)
        : null;
      const selectedReviewers = reviewersDecision?.selectedReviewers || [];
      const reviewerDefinitions = loadReviewerDefinitions(facts.repoRoot);
      const overallSummary = facts.outputPaths?.summariesOverall
        ? readJson(facts.outputPaths.summariesOverall)
        : null;
      const dynamicGroups = facts.outputPaths?.summariesDynamicGroups
        ? readJson(facts.outputPaths.summariesDynamicGroups)
        : null;
      const overallConfidence = Number(overallSummary?.confidence);
      const overallLines =
        overallSummary && false === overallSummary.skipped && overallSummary.summary
          ? [
              "## Overall Summary",
              overallSummary.summary,
              ...(Number.isFinite(overallConfidence)
                ? ["", `Confidence: ${Math.round(overallConfidence * 100)}%`]
                : []),
            ]
          : [];
      const reviewerLines = selectedReviewers.length
        ? (() => {
            const normalizedNames = selectedReviewers.map((name) =>
              name.replace(/^review-/, "")
            );
            const summaryLine = `(${normalizedNames.length}/${reviewerDefinitions.length}) ${normalizedNames.join(", ")}`;
            return [
              ...(reviewersDecision?.rationale ? [reviewersDecision.rationale, ""] : []),
              summaryLine,
            ];
          })()
        : [];
      const reviewerDetailsLines = reviewerLines.length
        ? [
            "<details>",
            "<summary>Reviewers</summary>",
            "",
            ...reviewerLines,
            "</details>",
          ]
        : [];
      const sizeLines = (() => {
        if (null == facts.sizeKey) {
          return [];
        }
        const sizeLabel = `${facts.sizeKey[0].toUpperCase()}${facts.sizeKey.slice(1)}`;
        const budget = facts.config?.comment_budget_by_size?.[facts.sizeKey];
        const reviewerRuns = facts.config?.reviewer_runs_by_size?.[facts.sizeKey];
        const parts = [`Size: ${sizeLabel}`];
        if (null != budget) {
          parts.push(`Comment Budget: ${budget}`);
        }
        if (null != reviewerRuns) {
          parts.push(`Reviewer Runs: ${reviewerRuns}`);
        }
        return parts.length ? [parts.join(", ") + "."] : [];
      })();
      const groupedChangesLines = (() => {
        if (
          null == dynamicGroups ||
          true === dynamicGroups.skipped ||
          false === Array.isArray(dynamicGroups.groups) ||
          0 === dynamicGroups.groups.length
        ) {
          return [];
        }
        const lines = ["## Grouped Changes"];
        dynamicGroups.groups.forEach((group, index) => {
          if (0 < index) {
            lines.push("");
          }
          const label = group.label || group.key || "Group";
          lines.push(`**${label}**`);
          lines.push(group.summary || "(no summary)");
          const filePaths = Array.isArray(group.file_paths) ? group.file_paths : [];
          filePaths.forEach((filePath) => {
            lines.push(`- \`${filePath}\``);
          });
        });
        return lines;
      })();
      const thisPrFindings = prFindingsForComment.filter(
        (finding) => "related" !== finding.review_target?.kind
      );
      const relatedFindings = prFindingsForComment.filter(
        (finding) => "related" === finding.review_target?.kind
      );
      const thisPrBodyFindings = thisPrFindings.filter(
        (finding) => true !== finding.posted_inline
      );
      const exactDropped = retroReport?.prefilter?.dropped_count ?? 0;
      const modelDropped = retroReport?.model?.dropped_count ?? 0;
      const dropParts =
        retroDroppedCount > 0
          ? [
              exactDropped ? `${exactDropped} exact` : null,
              modelDropped ? `${modelDropped} model` : null,
            ].filter(Boolean)
          : [];
      const retroDropLine =
        retroDroppedCount > 0
          ? `Retro dupe filter: Dropped ${retroDroppedCount} duplicate finding${
              retroDroppedCount === 1 ? "" : "s"
            }${dropParts.length ? ` (${dropParts.join(", ")})` : ""}.`
          : null;
      const summaryCommentLines = [
        "<!-- dh:review-summary -->",
        "## DeepHive Summary",
        summary.pr_comment.summary,
        ...sizeLines,
        ...(retroDropLine ? [retroDropLine] : []),
        ...(0 < overallLines.length ? ["", ...overallLines] : []),
        ...(0 < groupedChangesLines.length ? ["", ...groupedChangesLines] : []),
        ...(0 < reviewerDetailsLines.length ? ["", ...reviewerDetailsLines] : []),
      ];
      const summaryCommentBody = `${summaryCommentLines.join("\n")}\n`;
      writeText(facts.outputPaths.aggregateSummaryComment, summaryCommentBody);
      const thisPrFindingLines = (() => {
        if (thisPrBodyFindings.length) {
          return [formatFindingsList(thisPrBodyFindings)];
        }
        if (thisPrFindings.length) {
          return ["No additional feedback beyond inline comments."];
        }
        if (relatedFindings.length) {
          return ["No findings on this PR's diff."];
        }
        return ["No findings."];
      })();
      const relatedFindingLines = (() => {
        if (0 === relatedFindings.length) {
          return [];
        }
        const grouped = new Map();
        relatedFindings.forEach((finding) => {
          const key =
            relatedTargetKey(finding.review_target) ||
            finding.review_target?.repoSlug ||
            "related";
          if (!grouped.has(key)) {
            grouped.set(key, {
              repoSlug: finding.review_target?.repoSlug || null,
              prNumber: finding.review_target?.prNumber ?? null,
              findings: [],
            });
          }
          grouped.get(key).findings.push(finding);
        });
        const lines = [
          "## Companion PRs",
          "These findings are on related diffs, not this PR. They are listed here so the originating review still surfaces them, and they are posted to the companion PR when possible.",
        ];
        grouped.forEach((group, key) => {
          const url = githubPrUrl(group.repoSlug, group.prNumber);
          lines.push("");
          lines.push(url ? `### [${key}](${url})` : `### ${key}`);
          lines.push(formatFindingsList(group.findings));
        });
        return lines;
      })();
      const reviewCommentLines = [
        "## Summary",
        summary.pr_comment.summary,
        "",
        "## This PR",
        ...thisPrFindingLines,
        ...(0 < relatedFindingLines.length ? ["", ...relatedFindingLines] : []),
      ];
      const reviewCommentBody = `${reviewCommentLines.join("\n")}\n`;
      writeText(facts.outputPaths.aggregateReviewComment, reviewCommentBody);
      if (facts.outputPaths.aggregateReviewPayload) {
        const hasBlockingThisPr = thisPrFindings.some((finding) => {
          const meta = resolveConventionalMeta(finding);
          return meta.decorations.includes("blocking");
        });
        const reviewEvent = hasBlockingThisPr
          ? "REQUEST_CHANGES"
          : prFindingsForComment.length
            ? "COMMENT"
            : "APPROVE";
        const inlineComments = inlineResult?.comments || [];
        writeJson(facts.outputPaths.aggregateReviewPayload, {
          event: reviewEvent,
          body: reviewCommentBody,
          comments: inlineComments,
        });
      }
      const relatedPayloadPath =
        facts.outputPaths.aggregateRelatedReviewPayloads ||
        path.join(
          facts.outputPaths.outputRoot,
          "aggregate/related-review-payloads.json"
        );
      const relatedCommentsByTarget = new Map();
      relatedInlineComments.forEach((comment) => {
        if (!comment?.source_repo || null == comment?.source_pr) {
          return;
        }
        const key = `${comment.source_repo}#${comment.source_pr}`;
        if (!relatedCommentsByTarget.has(key)) {
          relatedCommentsByTarget.set(key, []);
        }
        relatedCommentsByTarget.get(key).push({
          path: comment.path,
          position: comment.position,
          body: comment.body,
        });
      });
      const sourcePrUrl = githubPrUrl(facts.repoSlug, facts.prMeta?.number);
      const relatedPayloads = [];
      const relatedGroups = new Map();
      relatedFindings.forEach((finding) => {
        const repoSlug = finding.review_target?.repoSlug;
        const prNumber = finding.review_target?.prNumber;
        if (!repoSlug || null == prNumber) {
          return;
        }
        const key = `${repoSlug}#${prNumber}`;
        if (!relatedGroups.has(key)) {
          relatedGroups.set(key, { repoSlug, prNumber, findings: [] });
        }
        relatedGroups.get(key).findings.push(finding);
      });
      relatedGroups.forEach((group, key) => {
        const inlineForTarget = relatedCommentsByTarget.get(key) || [];
        const originLine = sourcePrUrl
          ? `Found while reviewing [${facts.repoSlug}#${facts.prMeta.number}](${sourcePrUrl}).`
          : `Found while reviewing ${facts.repoSlug}#${facts.prMeta?.number}.`;
        const relatedBodyLines = [
          "## Summary",
          `${group.findings.length} issue${1 === group.findings.length ? "" : "s"} on this companion PR.`,
          originLine,
          "",
          "## Findings",
          inlineForTarget.length === group.findings.length
            ? "No additional feedback beyond inline comments."
            : formatFindingsList(group.findings),
        ];
        relatedPayloads.push({
          repoSlug: group.repoSlug,
          prNumber: group.prNumber,
          payload: {
            event: "COMMENT",
            body: `${relatedBodyLines.join("\n")}\n`,
            comments: inlineForTarget,
          },
        });
      });
      writeJson(relatedPayloadPath, relatedPayloads);
      const privateLines = [
        "## Summary",
        summary.private_summary.summary,
        "",
        "## Findings",
        summary.private_summary.findings
          .map((finding, index) => {
            const meta = resolveConventionalMeta(finding);
            const decorationText = meta.decorations.length
              ? ` (${meta.decorations.join(", ")})`
              : "";
            const labelText = `${meta.label}${decorationText}`;
            return `${index + 1}. [${labelText}] ${finding.title || "Finding"}`;
          })
          .join("\n") || "No findings.",
        "",
        "## Reviewer Stats",
        JSON.stringify(summary.private_summary.reviewer_stats, null, 2),
      ];
      writeText(
        facts.outputPaths.aggregatePrivateSummary,
        `${privateLines.join("\n")}\n`
      );
    }
    const retroReviewer = results.find(
      (result) => "review-retro-feedback" === result?.reviewer
    );
    if (null != retroReviewer?.parsed) {
      applyRetroActions({ facts, retroResult: retroReviewer.parsed });
    }
    return summary;
  }
);
