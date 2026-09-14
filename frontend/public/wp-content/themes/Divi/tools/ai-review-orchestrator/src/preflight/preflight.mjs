import fs from "node:fs";
import path from "node:path";

import { TASK_PATH_PREFIXES } from "../core/constants.mjs";
import { run, runJson } from "../core/exec.mjs";
import { writeJson } from "../core/io.mjs";
import { log } from "../core/logging.mjs";
import { parseRepoSlug } from "../facts/helpers.mjs";

export const resolvePreflightEnabled = ({ mode, flag }) => {
  if (null !== flag) {
    return true === flag;
  }
  return "pr-compare" === mode;
};

export const resolvePreflightStrict = ({ mode, strictFlag, warnFlag }) => {
  if (true === strictFlag) {
    return true;
  }
  if (true === warnFlag) {
    return false;
  }
  return "pr-compare" === mode;
};

const scanConflictMarkers = (repoRoot, filePaths) => {
  if (null == repoRoot || false === Array.isArray(filePaths)) {
    return [];
  }
  const hits = [];
  filePaths.forEach((filePath) => {
    if (null == filePath || "" === filePath) {
      return;
    }
    const fullPath = path.join(repoRoot, filePath);
    try {
      if (false === fs.existsSync(fullPath)) {
        return;
      }
      const stat = fs.statSync(fullPath);
      if (true === stat.isDirectory()) {
        return;
      }
      const contents = fs.readFileSync(fullPath, "utf8");
      if (
        true === contents.includes("<<<<<<<") ||
        true === contents.includes(">>>>>>>") ||
        true === contents.includes("=======")
      ) {
        hits.push(filePath);
      }
    } catch (error) {
      // Ignore unreadable files.
    }
  });
  return hits;
};

const buildPreflightIssue = ({ type, message, detail, level = "blocker" }) => ({
  type,
  level,
  message,
  detail: detail || null,
});

const runPrePreflightChecks = (facts) => {
  if ("pr-compare" !== facts.mode) {
    return { ok: true, details: [] };
  }
  log("preflight: auth checks");
  const results = [];
  const record = (label, output, error) => {
    results.push({
      label,
      output: output || "",
      error: error ? error.message || String(error) : null,
    });
  };
  try {
    const status = run("gh", ["auth", "status", "-h", "github.com"]);
    record("gh auth status", status, null);
  } catch (error) {
    record("gh auth status", "", error);
  }
  try {
    const response = run("gh", ["api", "-i", "/"]);
    record("gh api -i /", response, null);
  } catch (error) {
    record("gh api -i /", "", error);
  }
  try {
    const response = run("gh", ["api", "-i", "/orgs/elegantthemes"]);
    record("gh api -i /orgs/elegantthemes", response, null);
  } catch (error) {
    record("gh api -i /orgs/elegantthemes", "", error);
  }
  const failures = results.filter((entry) => entry.error);
  if (0 < failures.length) {
    process.stderr.write("\n[preflight] auth diagnostics (failed)\n");
    results.forEach((entry) => {
      const header = `\n[preflight] ${entry.label}`;
      const output = entry.output ? `\n${entry.output}` : "";
      const error = entry.error ? `\nError: ${entry.error}` : "";
      process.stderr.write(`${header}${output}${error}\n`);
    });
    process.stderr.write("\n");
  }
  return { ok: 0 === failures.length, details: results };
};

const fetchUnresolvedReviewThreads = ({ repoSlug, prNumber }) => {
  if (null == repoSlug || null == prNumber) {
    return [];
  }
  const { owner, repo } = parseRepoSlug(repoSlug);
  if (null == owner || null == repo) {
    return [];
  }
  let cursor = null;
  const unresolved = [];
  let hasNext = true;
  while (true === hasNext) {
    const response = runJson("gh", [
      "api",
      "graphql",
      "-f",
      "query=query($owner:String!,$repo:String!,$prNumber:Int!,$cursor:String){repository(owner:$owner,name:$repo){pullRequest(number:$prNumber){reviewThreads(first:100,after:$cursor){nodes{isResolved,isOutdated,path,line,comments(first:1){nodes{author{login},bodyText}}}pageInfo{hasNextPage,endCursor}}}}}",
      "-f",
      `owner=${owner}`,
      "-f",
      `repo=${repo}`,
      "-F",
      `prNumber=${Number(prNumber)}`,
      ...(cursor ? ["-f", `cursor=${cursor}`] : []),
    ]);
    const threads =
      response?.data?.repository?.pullRequest?.reviewThreads || null;
    if (null == threads) {
      break;
    }
    const nodes = Array.isArray(threads.nodes) ? threads.nodes : [];
    nodes.forEach((thread) => {
      if (true === thread?.isResolved) {
        return;
      }
      const comment = thread?.comments?.nodes?.[0] || null;
      unresolved.push({
        path: thread?.path || null,
        line: thread?.line ?? null,
        is_outdated: thread?.isOutdated ?? null,
        author: comment?.author?.login || null,
        body: comment?.bodyText ? comment.bodyText.slice(0, 200) : null,
      });
    });
    hasNext = true === threads.pageInfo?.hasNextPage;
    cursor = threads.pageInfo?.endCursor || null;
    if (false === hasNext) {
      break;
    }
  }
  return unresolved;
};

const fetchCommitStatuses = ({ repoSlug, sha }) => {
  if (null == repoSlug || null == sha) {
    return [];
  }
  const { owner, repo } = parseRepoSlug(repoSlug);
  if (null == owner || null == repo) {
    return [];
  }
  const response = runJson("gh", [
    "api",
    `repos/${owner}/${repo}/commits/${sha}/status`,
    "-H",
    "Accept: application/vnd.github+json",
  ]);
  const statuses = Array.isArray(response?.statuses) ? response.statuses : [];
  return statuses.map((status) => ({
    name: status?.context || "unknown",
    status: status?.state || null,
    conclusion: status?.state || null,
  }));
};

const shouldRequireTaskArtifacts = (facts) => {
  const repoSlug = String(facts.repoSlug || "");
  if (repoSlug.includes("submodule-builder-5")) {
    return true;
  }
  const repoPath = String(facts.localRepoPath || "");
  if (repoPath.includes(`${path.sep}includes${path.sep}builder-5`)) {
    return true;
  }
  return false;
};

export const runPreflight = async (facts) => {
  const preflight = facts.preflight || { enabled: false };
  if (true !== preflight.enabled) {
    return { ok: true, skipped: true, strict: false, issues: [] };
  }
  log("preflight: start");
  runPrePreflightChecks(facts);
  const issues = [];
  const addIssue = (issue) => {
    issues.push(issue);
  };

  if ("pr-compare" === facts.mode) {
    if (true !== preflight.allowMissingPrBody) {
      const body = facts.prMeta?.body ? String(facts.prMeta.body).trim() : "";
      if ("" === body) {
        addIssue(
          buildPreflightIssue({
            type: "missing_pr_body",
            message: "PR description is empty.",
          })
        );
      }
    }

    if (true !== preflight.allowFailingChecks) {
      try {
        const repoArgs = facts.repoSlug ? ["--repo", facts.repoSlug] : [];
        const prStatus = runJson("gh", [
          "pr",
          "view",
          String(facts.prMeta?.number || ""),
          "--json",
          "mergeable,mergeStateStatus",
          ...repoArgs,
        ]);
        const mergeable = prStatus?.mergeable;
        const mergeState = prStatus?.mergeStateStatus;
        if (false === mergeable || "CONFLICTING" === mergeState) {
          addIssue(
            buildPreflightIssue({
              type: "merge_conflict",
              message: "PR is not mergeable (merge conflict detected).",
              detail: { mergeable, merge_state: mergeState },
            })
          );
        }
        const checks = fetchCommitStatuses({
          repoSlug: facts.repoSlug,
          sha: facts.prMeta?.headRefOid || null,
        });
        const failingChecks = checks.filter((check) => {
          const conclusion = String(check.conclusion || "").toUpperCase();
          const status = String(check.status || "").toUpperCase();
          if ("SUCCESS" === conclusion) {
            return false;
          }
          if ("" !== conclusion) {
            return true;
          }
          return "" !== status && "COMPLETED" !== status;
        });
        if (0 < failingChecks.length) {
          addIssue(
            buildPreflightIssue({
              type: "failing_checks",
              message: "PR checks are not green.",
              detail: failingChecks.map((check) => ({
                name: check.name || check.context || "unknown",
                status: check.status || null,
                conclusion: check.conclusion || null,
              })),
            })
          );
        }
      } catch (error) {
        addIssue(
          buildPreflightIssue({
            type: "checks_unavailable",
            message: "Unable to query PR status checks.",
            detail: error.message || String(error),
          })
        );
      }
    }

    if (true !== preflight.allowUnresolvedThreads) {
      try {
        const unresolved = fetchUnresolvedReviewThreads({
          repoSlug: facts.repoSlug,
          prNumber: facts.prMeta?.number,
        });
        if (0 < unresolved.length) {
          addIssue(
            buildPreflightIssue({
              type: "unresolved_review_threads",
              message: "PR review threads are unresolved.",
              detail: {
                count: unresolved.length,
                sample: unresolved.slice(0, 10),
              },
            })
          );
        }
      } catch (error) {
        addIssue(
          buildPreflightIssue({
            type: "review_threads_unavailable",
            message: "Unable to query PR review threads.",
            detail: error.message || String(error),
          })
        );
      }
    }
  }

  if ("working-tree" === facts.mode || "branch-compare" === facts.mode) {
    if (true !== preflight.allowMergeConflicts) {
      try {
        const unresolved = run("git", ["diff", "--name-only", "--diff-filter=U"])
          .split("\n")
          .filter(Boolean);
        if (0 < unresolved.length) {
          addIssue(
            buildPreflightIssue({
              type: "merge_conflict",
              message: "Unresolved merge conflicts detected.",
              detail: unresolved,
            })
          );
        }
      } catch (error) {
        addIssue(
          buildPreflightIssue({
            type: "merge_conflict_check_failed",
            message: "Unable to check merge conflicts.",
            detail: error.message || String(error),
          })
        );
      }
      const markerHits = scanConflictMarkers(facts.repoRoot, facts.changedFiles);
      if (0 < markerHits.length) {
        addIssue(
          buildPreflightIssue({
            type: "conflict_markers",
            message: "Conflict markers found in changed files.",
            detail: markerHits,
          })
        );
      }
    }
  }

  if (true !== preflight.allowMissingTasks && shouldRequireTaskArtifacts(facts)) {
    if (0 === facts.taskFiles.length && 0 < facts.changedFiles.length) {
      const prBody = facts.prMeta?.body ? String(facts.prMeta.body).trim() : "";
      if ("" === prBody) {
        addIssue(
          buildPreflightIssue({
            type: "missing_task_artifacts",
            message: "No task files detected for this change.",
            detail: { task_roots: TASK_PATH_PREFIXES },
          })
        );
      }
    }
  }

  const strict = true === preflight.strict;
  const blockers = issues.filter((issue) => "blocker" === issue.level);
  const ok = true === strict ? 0 === blockers.length : true;
  const payload = {
    ok,
    strict,
    skipped: false,
    issues,
  };
  if (facts.outputPaths?.preflight) {
    writeJson(facts.outputPaths.preflight, payload);
  }
  if (0 === issues.length) {
    log("preflight: ok");
  } else {
    log(
      `preflight: ${issues.length} issue(s) (${blockers.length} blockers, strict=${strict})`
    );
  }
  return payload;
};

export const formatPreflightReport = (preflight) => {
  if (null == preflight) {
    return "Preflight: unavailable.";
  }
  if (true === preflight.skipped) {
    return "Preflight: skipped.";
  }
  const issues = Array.isArray(preflight.issues) ? preflight.issues : [];
  const blockers = issues.filter((issue) => "blocker" === issue.level);
  const warnings = issues.filter((issue) => "blocker" !== issue.level);
  const flagByType = {
    missing_pr_body: "--allow-missing-pr-body",
    failing_checks: "--allow-failing-checks",
    unresolved_review_threads: "--allow-unresolved-threads",
    merge_conflict: "--allow-merge-conflicts",
    conflict_markers: "--allow-merge-conflicts",
    missing_task_artifacts: "--allow-missing-tasks",
  };
  const lines = [
    `Preflight: ${preflight.ok ? "OK" : "FAILED"} (strict=${preflight.strict})`,
    `Issues: ${issues.length} (${blockers.length} blockers, ${warnings.length} warnings)`,
  ];
  issues.forEach((issue, index) => {
    const level = String(issue.level || "warn").toUpperCase();
    const type = issue.type || "unknown";
    const flag = flagByType[type] || null;
    lines.push(
      `${index + 1}. [${level}] ${issue.message || "Issue"} (${type})`
    );
    if (issue.detail) {
      lines.push(`   Detail: ${JSON.stringify(issue.detail)}`);
    }
    if (flag) {
      lines.push(`   Allow: ${flag}`);
    }
  });
  if (!preflight.ok && true === preflight.strict) {
    lines.push("Use --preflight-warn to continue despite blockers.");
  }
  return lines.join("\n");
};
