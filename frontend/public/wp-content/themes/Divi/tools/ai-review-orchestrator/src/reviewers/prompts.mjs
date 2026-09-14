const formatRelatedPrs = (relatedPrs) => {
  if (!Array.isArray(relatedPrs) || 0 === relatedPrs.length) {
    return "(none)";
  }
  return relatedPrs
    .map((pr) => {
      const repo = pr.repoSlug || "(unknown-repo)";
      const number = pr.prNumber || "(unknown-pr)";
      const source = pr.source ? ` source=${pr.source}` : "";
      const sources =
        Array.isArray(pr.sources) && 0 < pr.sources.length
          ? ` sources=${pr.sources.join(",")}`
          : "";
      const issue = pr.issue?.repoSlug && pr.issue?.issueNumber
        ? ` issue=${pr.issue.repoSlug}#${pr.issue.issueNumber}`
        : "";
      const companion =
        true === pr?.companion?.sameIssue && true === pr?.companion?.sameBranch
          ? ` companion=same-issue+same-branch branch=${pr?.companion?.branchName || "(unknown)"}`
          : "";
      return `- ${repo}#${number}${source}${sources}${issue}${companion}`;
    })
    .join("\n");
};

const formatCompanionContext = (companionContext) => {
  if (null == companionContext) {
    return "(none)";
  }
  const status = companionContext.status || "unknown";
  const reason = companionContext.reason || "unknown";
  const branchName = companionContext.branchName || "(none)";
  const issueRefs = Array.isArray(companionContext.issueRefs)
    ? companionContext.issueRefs
        .map((issueRef) => `${issueRef.repoSlug}#${issueRef.issueNumber}`)
        .join(", ")
    : "";
  const companions = Array.isArray(companionContext.confirmedCompanions)
    ? companionContext.confirmedCompanions
        .map((entry) => `${entry.repoSlug}#${entry.prNumber}`)
        .join(", ")
    : "";
  return [
    `status=${status}`,
    `reason=${reason}`,
    `can_evaluate=${true === companionContext.canEvaluate}`,
    `has_confirmed_companion=${true === companionContext.hasConfirmedCompanion}`,
    `branch=${branchName}`,
    `issues=${issueRefs || "(none)"}`,
    `confirmed_companions=${companions || "(none)"}`,
  ].join("\n");
};

export const decisionPrompt = ({
  reviewers,
  changedFiles,
  codeFiles,
  taskFiles,
  sizeKey,
  config,
  taskContext,
  mode,
  baseRef,
  headRef,
  relatedPrs,
  companionContext,
}) => {
  const reviewerList = reviewers
    .map((reviewer) => `- ${reviewer.name}: ${reviewer.description}`)
    .join("\n");
  return [
    "You are operating in a GitHub Actions runner performing automated code review.",
    "The gh CLI is available and authenticated via GH_TOKEN. You may comment on pull requests.",
    "",
    "Decide which reviewers should run for this change.",
    "Return JSON only, with: selected_reviewers (array), rationale (string).",
    "Only select from the provided reviewer list.",
    "",
    `Mode: ${mode}`,
    baseRef ? `Base ref: ${baseRef}` : "Base ref: (none)",
    headRef ? `Head ref: ${headRef}` : "Head ref: (none)",
    `Review size: ${sizeKey}`,
    "",
    "Related PRs:",
    formatRelatedPrs(relatedPrs),
    "",
    "Companion PR context:",
    formatCompanionContext(companionContext),
    "",
    "Changed files:",
    changedFiles.map((file) => `- ${file}`).join("\n") || "(none)",
    "",
    "Task files:",
    taskFiles.map((file) => `- ${file}`).join("\n") || "(none)",
    "",
    "Task context:",
    JSON.stringify(taskContext, null, 2),
    "",
    "Reviewer list:",
    reviewerList,
    "",
    "Constraints:",
    JSON.stringify(
      {
        comment_budget_by_size: config?.comment_budget_by_size || {},
        comment_label_caps: config?.comment_label_caps || {},
        confidence_thresholds: config?.confidence_thresholds || {},
      },
      null,
      2
    ),
  ].join("\n");
};

export const buildReviewerSummaryContext = (
  summaries,
  outputPaths,
  focusedFiles
) => {
  if (null == summaries) {
    return "Summaries: (not available)";
  }
  const groupLines = (summaries.groups || [])
    .slice(0, 15)
    .map(
      (group) =>
        `- ${group.key}: ${group.summary || "(no summary)"} (${group.confidence ?? 0})`
    );
  const dynamicGroupLines = (summaries.dynamic_groups || [])
    .slice(0, 10)
    .map((group) => {
      const label = group.label || "Group";
      const summary = group.summary || "(no summary)";
      const fileList = Array.isArray(group.file_paths)
        ? group.file_paths.slice(0, 8).join(", ")
        : "";
      const suffix = fileList ? ` Files: ${fileList}` : "";
      return `- ${label}: ${summary} (${group.confidence ?? 0}).${suffix}`;
    });
  const focusedLines = (focusedFiles || [])
    .map((file) => {
      const size = (file.additions ?? 0) + (file.deletions ?? 0);
      const summary = file.summary || "(no summary)";
      return `- ${file.path} (${size} lines): ${summary}`;
    });
  return [
    "Summaries (use these before re-diffing):",
    summaries.overall?.summary
      ? `Overall: ${summaries.overall.summary}`
      : "Overall: (not available)",
    "",
    "Group summaries:",
    groupLines.length ? groupLines.join("\n") : "(none)",
    "",
    "Thematic group summaries:",
    dynamicGroupLines.length ? dynamicGroupLines.join("\n") : "(none)",
    "",
    "Focused file summaries:",
    focusedLines.length ? focusedLines.join("\n") : "(none)",
    "",
    outputPaths?.summariesFiles
      ? `Full file summaries: ${outputPaths.summariesFiles}`
      : "Full file summaries: (not available)",
  ].join("\n");
};

export const buildReviewerMergePrompt = ({
  reviewer,
  outputs,
  outputContract,
  sizeKey,
}) => [
  "You are merging multiple AI reviewer outputs into a single response.",
  "Deduplicate by theme. Merge locations when findings overlap.",
  "Keep the most severe classification when conflicts exist, but avoid escalation.",
  "If findings are equivalent, keep the higher confidence or clearer rationale.",
  "Preserve suggested fixes only when they are consistent across outputs.",
  "Return JSON only and follow the reviewer output contract below.",
  "",
  `Reviewer: ${reviewer?.name || "reviewer"}`,
  `Review size: ${sizeKey || "unknown"}`,
  "",
  "Reviewer output contract:",
  outputContract || "(output contract unavailable)",
  "",
  "Reviewer outputs to merge (JSON):",
  outputs.map((output, index) => `--- Output ${index + 1} ---\n${output}`).join("\n\n"),
].join("\n");

const formatWaivedThemes = (retroReview) => {
  if (false === Array.isArray(retroReview?.waived_items) || 0 === retroReview.waived_items.length) {
    return null;
  }
  return `Waived themes (do not re-raise, including reworded titles): ${retroReview.waived_items
    .map((item) => `${item.theme}@${item.path}`)
    .join(", ")}`;
};

const buildRoundGuidance = (retroReview) => {
  const waivedLine = formatWaivedThemes(retroReview);
  const round = Number(retroReview?.review_round || 1);
  if (round < 2) {
    const lines = [
      "Review round: 1 (first pass).",
      "This is the only chance to raise issues that already exist in this diff.",
      "Later rounds review only the delta since this review. Do not save findings for later.",
      "Inspect the actual diffs of every focused file before returning. Summaries are a map, not a substitute.",
      "After your first pass, do a second pass over remaining focused files for independent issues you skipped.",
      "If several findings have similar severity (for example multiple coverage gaps), report all of them now.",
      "Empty findings is allowed when the change is genuinely solid.",
    ];
    if (null != waivedLine) {
      lines.push(waivedLine);
    }
    return lines.join("\n");
  }
  const sameSha = true === retroReview?.same_sha_as_last_review;
  const deltaHasTests = true === retroReview?.delta_has_tests;
  const lines = [
    `Review round: ${round} (follow-up).`,
    "Empty findings is the successful outcome when prior feedback was addressed and the delta introduces no new defects.",
    "You are not being helpful by finding more. You are being helpful by confirming the PR is ready, or by catching a real regression in the new delta.",
    "Only raise a new finding if all of these are true:",
    "- it is in diff_since_last_run (or an unresolved prior thread still missing a fix), AND",
    "- it could not have been raised in a prior round on the then-existing diff, AND",
    "- it is merge-blocking (correctness, security, data loss, or a test that would not fail if the new delta were reverted).",
    "Do not hunt for additional tests, stricter assertions, spec maps, or sibling-file parity on code that existed in prior rounds.",
    "Do not re-raise prior feedback unless diff_since_last_run shows the fix is missing or incomplete (the retro-feedback reviewer owns that).",
    "If waived_items is non-empty, never re-raise those themes on those paths. A human decline in any phrasing, or a link back to a prior decline, is final. Rewording the same request is still the same finding.",
  ];
  if (null != waivedLine) {
    lines.push(waivedLine);
  }
  if (true === sameSha) {
    lines.push(
      "No new commits since the last DeepHive review. Return zero new findings unless verifying an unresolved prior thread."
    );
  }
  if (true === deltaHasTests) {
    lines.push(
      "Tests were added or updated in this delta. Treat that as sufficient unless the new tests are broken or would still pass if the new behavior were reverted."
    );
  } else {
    lines.push(
      "If the author added tests or specs in response to prior feedback, treat that as sufficient unless the new tests are broken or vacuous."
    );
  }
  return lines.join("\n");
};

export const reviewerPrompt = ({
  reviewer,
  changedFiles,
  taskFiles,
  taskContext,
  retroReview,
  mode,
  baseRef,
  headRef,
  prNumber,
  repoSlug,
  repoRoot,
  summaries,
  outputPaths,
  focusedFiles,
  outputContract,
  relatedPrs,
  companionContext,
}) => [
  "You are a senior software engineer performing a pull request review.",
  "Your goal is to identify high-impact issues, not stylistic preferences.",
  "Only comment on changed files or immediate context. Silence is acceptable.",
  "You are operating in a GitHub Actions runner performing automated code review.",
  "The gh CLI is available and authenticated via GH_TOKEN. You may comment on pull requests.",
  "You are read-only: do not modify files or apply patches.",
  "Do not re-raise prior feedback unless diff_since_last_run shows new evidence or context.",
  "Prior feedback is provided in the 'Prior review feedback' section as facts.retroReview JSON,",
  "including review_round, threads, waived_items, recent_comments, prior_findings, delta_paths, and diff_since_last_run.",
  "",
  `Reviewer: ${reviewer.name}`,
  "",
  reviewer.body,
  "",
  "Round rules:",
  buildRoundGuidance(retroReview),
  "",
  "Context:",
  `Repo root: ${repoRoot}`,
  `Mode: ${mode}`,
  baseRef ? `Base ref: ${baseRef}` : "Base ref: (none)",
  headRef ? `Head ref: ${headRef}` : "Head ref: (none)",
  prNumber ? `PR number: ${prNumber}` : "PR number: (none)",
  repoSlug ? `Repo slug: ${repoSlug}` : "Repo slug: (none)",
  `Review round: ${Number(retroReview?.review_round || 1)}`,
  "",
  "Related PRs:",
  formatRelatedPrs(relatedPrs),
  "",
  "Companion PR context:",
  formatCompanionContext(companionContext),
  "",
  "Changed files (filtered):",
  (focusedFiles || []).map((file) => `- ${file.path}`).join("\n") || "(none)",
  `Filtered file count: ${(focusedFiles || []).length}`,
  `Total changed files: ${changedFiles.length}`,
  "",
  "Task files:",
  taskFiles.map((file) => `- ${file}`).join("\n") || "(none)",
  "",
  "Task context:",
  JSON.stringify(taskContext, null, 2),
  "",
  "Prior review feedback:",
  retroReview ? JSON.stringify(retroReview, null, 2) : "(none)",
  "",
  buildReviewerSummaryContext(summaries, outputPaths, focusedFiles),
  "",
  Number(retroReview?.review_round || 1) >= 2
    ? "On this follow-up round, inspect diffs for focused files and diff_since_last_run only. Do not go looking through the rest of the original PR for new nits."
    : "Use summaries as a map, then inspect the actual diffs of every focused file. Base comments on the diffs, not the summaries.",
  "",
  "Companion / related PR guidance:",
  "- Same-issue related PRs and companion PRs are always merged together. Do not remind authors to merge both, merge in a certain order, or wait for the other PR.",
  "- Never raise a finding whose only concern is dependency/merge-order coordination, 'the companion PR needs to be merged', or 'make sure both PRs are merged'. Omit it.",
  "- If you would have tagged companion-dependency-order, omit the finding instead.",
  "- Never omit correctness, security, performance, or contract defects because companion/related PR context exists.",
  "",
  "Conventional Comments guidance: follow the output contract for labels and decorations.",
  "",
  "Return JSON only and follow the output contract below.",
  "",
  outputContract || "(output contract unavailable)",
].join("\n");
