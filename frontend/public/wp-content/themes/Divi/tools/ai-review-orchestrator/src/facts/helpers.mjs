import fs from "node:fs";
import path from "node:path";

import { TASK_PATH_PREFIXES } from "../core/constants.mjs";
import { run, runJson } from "../core/exec.mjs";
import { log } from "../core/logging.mjs";
import { unique } from "../core/utils.mjs";

const RELATED_REPO_PREFIX = "related";
const RELATED_PATH_RE = /^related\/([^/]+\/[^/]+)\/(.+)$/;

export const buildRelatedPath = (repoSlug, filePath) => {
  if (null == repoSlug || null == filePath) {
    return filePath || "";
  }
  return `${RELATED_REPO_PREFIX}/${repoSlug}/${filePath}`.replace(/\/+/g, "/");
};

export const parseRelatedRepoPath = (filePath) => {
  if (null == filePath) {
    return null;
  }
  const match = String(filePath).match(RELATED_PATH_RE);
  if (!match) {
    return null;
  }
  return { repoSlug: match[1], originalPath: match[2] };
};

export const stripRelatedPrefix = (filePath) => {
  if (null == filePath) {
    return filePath;
  }
  const parsed = parseRelatedRepoPath(filePath);
  return parsed ? parsed.originalPath : filePath;
};

export const isRelatedReviewFile = (file) =>
  Boolean(file?.source_repo) || Boolean(parseRelatedRepoPath(file?.path));

export const relatedTargetKey = ({ repoSlug, prNumber }) => {
  if (!repoSlug) {
    return null;
  }
  return null == prNumber ? repoSlug : `${repoSlug}#${prNumber}`;
};

export const resolveFindingReviewTarget = (finding, facts) => {
  const locationPath = finding?.locations?.[0]?.path || null;
  const files = Array.isArray(facts?.fileMetadata) ? facts.fileMetadata : [];
  const file = files.find((entry) => entry?.path === locationPath) || null;
  if (file?.source_repo) {
    return {
      kind: "related",
      repoSlug: file.source_repo,
      prNumber: file.source_pr ?? null,
      originalPath: file.original_path || stripRelatedPrefix(locationPath),
      path: locationPath,
    };
  }
  const parsed = parseRelatedRepoPath(locationPath);
  if (parsed) {
    const related = (Array.isArray(facts?.relatedPrs) ? facts.relatedPrs : []).find(
      (entry) => entry?.repoSlug === parsed.repoSlug
    );
    return {
      kind: "related",
      repoSlug: parsed.repoSlug,
      prNumber: related?.prNumber ?? null,
      originalPath: parsed.originalPath,
      path: locationPath,
    };
  }
  return {
    kind: "this_pr",
    repoSlug: facts?.repoSlug || null,
    prNumber: facts?.prMeta?.number ?? null,
    originalPath: locationPath,
    path: locationPath,
  };
};

export const normalizeTaskPath = (filePath) => stripRelatedPrefix(filePath);

export const resolveMode = ({ mode, baseRef, prNumber }) => {
  if (null !== mode && "auto" !== mode) {
    return mode;
  }
  if (null !== prNumber) {
    return "pr-compare";
  }
  if (null !== baseRef) {
    return "branch-compare";
  }
  return "working-tree";
};

export const resolveRepoArg = (value, repoRoot) => {
  if (null === value) {
    return null;
  }
  const resolvedPath =
    true === path.isAbsolute(value) || null == repoRoot
      ? value
      : path.join(repoRoot, value);
  if (true === fs.existsSync(resolvedPath)) {
    const remoteUrl = run("git", [
      "-C",
      resolvedPath,
      "remote",
      "get-url",
      "origin",
    ]);
    const match = remoteUrl.match(/github\.com[:/](.+?)(?:\.git)?$/);
    if (null !== match) {
      return match[1];
    }
  }
  return value;
};

export const resolveLocalRepoPath = (value, repoRoot) => {
  if (null === value) {
    return null;
  }
  const resolvedPath =
    true === path.isAbsolute(value) || null == repoRoot
      ? value
      : path.join(repoRoot, value);
  if (true === fs.existsSync(resolvedPath)) {
    return resolvedPath;
  }
  return null;
};

export const parseRepoSlug = (repoSlug) => {
  if (null == repoSlug) {
    return { owner: null, repo: null };
  }
  const parts = repoSlug.split("/");
  if (parts.length < 2) {
    return { owner: null, repo: null };
  }
  return { owner: parts[0], repo: parts.slice(1).join("/") };
};

export const fetchPrFiles = ({ prNumber, repoSlug }) => {
  if (null == prNumber || null == repoSlug) {
    return [];
  }
  const { owner, repo } = parseRepoSlug(repoSlug);
  if (null == owner || null == repo) {
    return [];
  }
  const response = runJson("gh", [
    "api",
    `repos/${owner}/${repo}/pulls/${prNumber}/files`,
    "--paginate",
    "-H",
    "Accept: application/vnd.github+json",
  ]);
  if (false === Array.isArray(response)) {
    return [];
  }
  return response;
};

export const fetchCompareDiff = ({ repoSlug, baseSha, headSha }) => {
  if (null == repoSlug || null == baseSha || null == headSha) {
    return "";
  }
  const { owner, repo } = parseRepoSlug(repoSlug);
  if (null == owner || null == repo) {
    return "";
  }
  return run("gh", [
    "api",
    `repos/${owner}/${repo}/compare/${baseSha}...${headSha}`,
    "-H",
    "Accept: application/vnd.github.v3.diff",
  ]);
};

export const fetchPrCommits = ({ prNumber, repoSlug }) => {
  if (null == prNumber || null == repoSlug) {
    return [];
  }
  const { owner, repo } = parseRepoSlug(repoSlug);
  if (null == owner || null == repo) {
    return [];
  }
  const response = runJson("gh", [
    "api",
    `repos/${owner}/${repo}/pulls/${prNumber}/commits`,
    "--paginate",
    "-H",
    "Accept: application/vnd.github+json",
  ]);
  return Array.isArray(response) ? response : [];
};

export const fetchPrReviews = ({ prNumber, repoSlug }) => {
  if (null == prNumber || null == repoSlug) {
    return [];
  }
  const { owner, repo } = parseRepoSlug(repoSlug);
  if (null == owner || null == repo) {
    return [];
  }
  const response = runJson("gh", [
    "api",
    `repos/${owner}/${repo}/pulls/${prNumber}/reviews`,
    "--paginate",
    "-H",
    "Accept: application/vnd.github+json",
  ]);
  return Array.isArray(response) ? response : [];
};

export const extractDiffPaths = (patch) => {
  if (null == patch || "" === patch) {
    return [];
  }
  const paths = [];
  String(patch)
    .split("\n")
    .forEach((line) => {
      if (false === line.startsWith("diff --git ")) {
        return;
      }
      const match = line.match(/^diff --git a\/(.+?) b\/(.+)$/);
      if (match) {
        paths.push(match[1], match[2]);
      }
    });
  return unique(paths);
};

export const fetchReviewThreads = ({ prNumber, repoSlug }) => {
  if (null == prNumber || null == repoSlug) {
    return [];
  }
  const { owner, repo } = parseRepoSlug(repoSlug);
  if (null == owner || null == repo) {
    return [];
  }
  const query = [
    "query($owner:String!,$repo:String!,$number:Int!,$after:String) {",
    "  repository(owner:$owner, name:$repo) {",
    "    pullRequest(number:$number) {",
    "      reviewThreads(first:50, after:$after) {",
    "        pageInfo { hasNextPage endCursor }",
    "        nodes {",
    "          id",
    "          isResolved",
    "          resolvedAt",
    "          resolvedBy { login }",
    "          path",
    "          comments(first:100) {",
    "            nodes {",
    "              id",
    "              databaseId",
    "              body",
    "              createdAt",
    "              author { login }",
    "              path",
    "              position",
    "              originalLine",
    "              line",
    "              diffHunk",
    "              url",
    "            }",
    "          }",
    "        }",
    "      }",
    "    }",
    "  }",
    "}",
  ].join("\n");
  const threads = [];
  let cursor = null;
  let hasNextPage = true;
  while (true === hasNextPage) {
    const args = [
      "api",
      "graphql",
      "-f",
      `query=${query}`,
      "-f",
      `owner=${owner}`,
      "-f",
      `repo=${repo}`,
      "-F",
      `number=${Number(prNumber)}`,
    ];
    if (cursor) {
      args.push("-f", `after=${cursor}`);
    }
    const response = runJson("gh", args);
    const payload = response?.data?.repository?.pullRequest?.reviewThreads;
    const nodes = Array.isArray(payload?.nodes) ? payload.nodes : [];
    threads.push(...nodes);
    hasNextPage = true === payload?.pageInfo?.hasNextPage;
    cursor = payload?.pageInfo?.endCursor || null;
    if (!hasNextPage) {
      break;
    }
  }
  return threads;
};

export const isTaskFile = (filePath) => {
  const normalized = normalizeTaskPath(filePath);
  return (
    null !== normalized &&
    TASK_PATH_PREFIXES.some((prefix) => normalized.startsWith(prefix))
  );
};

export const splitChangedFiles = (files) => {
  const taskFiles = [];
  const codeFiles = [];
  files.forEach((filePath) => {
    if (isTaskFile(filePath)) {
      taskFiles.push(filePath);
    } else {
      codeFiles.push(filePath);
    }
  });
  return { taskFiles, codeFiles };
};

export const extractIssueFromTaskPath = (filePath) => {
  const normalized = normalizeTaskPath(filePath);
  if (null === normalized) {
    return null;
  }
  const match = normalized.match(
    /(?:includes\/builder-5\/)?\.?et\/tasks\/\d+\/(\d+)\//
  );
  if (match) {
    return match[1];
  }
  const cursorMatch = normalized.match(/\.cursor\/tasks\/(\d+)\//);
  return cursorMatch ? cursorMatch[1] : null;
};

export const getTaskRoot = (repoRoot) => {
  const builderTaskRoot = path.join(
    repoRoot,
    "includes/builder-5/et/tasks"
  );
  if (true === fs.existsSync(builderTaskRoot)) {
    return builderTaskRoot;
  }
  const builderHiddenTaskRoot = path.join(
    repoRoot,
    "includes/builder-5/.et/tasks"
  );
  if (true === fs.existsSync(builderHiddenTaskRoot)) {
    return builderHiddenTaskRoot;
  }
  const cursorTaskRoot = path.join(repoRoot, ".cursor/tasks");
  if (true === fs.existsSync(cursorTaskRoot)) {
    return cursorTaskRoot;
  }
  return path.join(repoRoot, "et/tasks");
};

export const resolveTaskRoot = (repoRoot, taskFiles = []) => {
  if (taskFiles.some((filePath) => filePath.startsWith(".cursor/tasks/"))) {
    return path.join(repoRoot, ".cursor/tasks");
  }
  if (
    taskFiles.some((filePath) =>
      filePath.startsWith("includes/builder-5/.et/tasks/")
    )
  ) {
    return path.join(repoRoot, "includes/builder-5/.et/tasks");
  }
  if (
    taskFiles.some((filePath) =>
      filePath.startsWith("includes/builder-5/et/tasks/")
    )
  ) {
    return path.join(repoRoot, "includes/builder-5/et/tasks");
  }
  if (taskFiles.some((filePath) => filePath.startsWith("et/tasks/"))) {
    return path.join(repoRoot, "et/tasks");
  }
  return getTaskRoot(repoRoot);
};

export const getImplementationPlanPath = (
  repoRoot,
  issueNumber,
  taskFiles = []
) => {
  if (null == repoRoot || null == issueNumber) {
    return null;
  }
  const taskRoot = resolveTaskRoot(repoRoot, taskFiles);
  if (taskRoot.endsWith(path.join(".cursor", "tasks"))) {
    return path.join(taskRoot, String(issueNumber), "implementation-plan.md");
  }
  return path.join(
    taskRoot,
    String(issueNumber),
    "implementation-plan.md"
  );
};

export const getPlanExcerpt = (planPath) => {
  if (null === planPath || false === fs.existsSync(planPath)) {
    return null;
  }
  const contents = fs.readFileSync(planPath, "utf8");
  const sections = [];
  const lines = contents.split("\n");
  const captureSection = (heading) => {
    const index = lines.findIndex((line) => line.trim() === heading);
    if (-1 === index) {
      return;
    }
    const collected = [];
    for (let i = index; i < lines.length; i += 1) {
      const line = lines[i];
      if (index !== i && true === line.startsWith("## ")) {
        break;
      }
      collected.push(line);
    }
    sections.push(collected.join("\n").trim());
  };
  captureSection("## Problem Analysis");
  captureSection("## Solution Approach");
  const excerpt = sections.filter(Boolean).join("\n\n").trim();
  if ("" === excerpt) {
    return null;
  }
  return excerpt.slice(0, 4000);
};

export const buildTaskContext = (repoRoot, taskFiles) => {
  const issueNumbers = unique(
    taskFiles.map(extractIssueFromTaskPath).filter(Boolean)
  );
  const primaryIssueNumber = issueNumbers[0] || null;
  const planPath = getImplementationPlanPath(
    repoRoot,
    primaryIssueNumber,
    taskFiles
  );
  const planExcerpt = getPlanExcerpt(planPath);
  return {
    issue_numbers: issueNumbers,
    primary_issue_number: primaryIssueNumber,
    implementation_plan_path:
      null !== planPath && true === fs.existsSync(planPath) ? planPath : null,
    implementation_plan_excerpt: planExcerpt,
  };
};

export const applyPrBodyTaskFallback = (taskContext, prBody) => {
  const body = null == prBody ? "" : String(prBody).trim();
  if ("" === body) {
    return taskContext;
  }
  const context = taskContext || {};
  if (0 < (context.issue_numbers || []).length) {
    return context;
  }
  const excerpt = body.slice(0, 4000);
  return {
    ...context,
    task_context_source: "pr_body",
    pr_description: excerpt,
  };
};

export const filterPatchByPredicate = (patch, shouldInclude) => {
  if (null === patch || "" === patch) {
    return patch;
  }
  const lines = patch.split("\n");
  const output = [];
  let current = [];
  let includeBlock = true;
  const flush = () => {
    if (includeBlock && current.length) {
      output.push(...current);
    }
    current = [];
  };
  lines.forEach((line) => {
    if (line.startsWith("diff --git ")) {
      flush();
      current.push(line);
      const match = line.match(/^diff --git a\/(.+?) b\/(.+)$/);
      const filePath = match ? match[2] : null;
      includeBlock = shouldInclude(filePath);
      return;
    }
    current.push(line);
  });
  flush();
  return output.join("\n").trimEnd();
};

export const countPatchLines = (patch) => {
  if (null === patch || "" === patch) {
    return 0;
  }
  return patch
    .split("\n")
    .filter((line) => {
      if ("" === line) {
        return false;
      }
      if (line.startsWith("+++")) {
        return false;
      }
      if (line.startsWith("---")) {
        return false;
      }
      if (line.startsWith("@@")) {
        return false;
      }
      if (line.startsWith("diff --git")) {
        return false;
      }
      return line.startsWith("+") || line.startsWith("-");
    }).length;
};

export const parseNumstat = (output) =>
  output
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      const [additionsRaw, deletionsRaw, ...pathParts] = line.split(/\s+/);
      const filePath = pathParts.join(" ");
      const additions = "-" === additionsRaw ? null : Number(additionsRaw);
      const deletions = "-" === deletionsRaw ? null : Number(deletionsRaw);
      return {
        path: filePath,
        additions,
        deletions,
      };
    })
    .filter((entry) => entry.path);

export const parseNameStatus = (output) =>
  output
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      const parts = line.split(/\s+/);
      const status = parts[0];
      if (status.startsWith("R") || status.startsWith("C")) {
        return {
          status,
          path: parts[2] || parts[1],
          oldPath: parts[1] || null,
        };
      }
      return {
        status,
        path: parts[1] || parts[0],
      };
    })
    .filter((entry) => entry.path);

export const mergeNumstats = (entries) => {
  const merged = new Map();
  entries.forEach((entry) => {
    const existing = merged.get(entry.path);
    if (!existing) {
      merged.set(entry.path, { ...entry });
      return;
    }
    merged.set(entry.path, {
      path: entry.path,
      additions:
        null == existing.additions || null == entry.additions
          ? null
          : existing.additions + entry.additions,
      deletions:
        null == existing.deletions || null == entry.deletions
          ? null
          : existing.deletions + entry.deletions,
    });
  });
  return Array.from(merged.values());
};

export const getNumstatForMode = ({ mode, baseRef, headRef }) => {
  if ("working-tree" === mode) {
    const unstaged = parseNumstat(run("git", ["diff", "--numstat"]));
    const staged = parseNumstat(run("git", ["diff", "--cached", "--numstat"]));
    return mergeNumstats([...unstaged, ...staged]);
  }
  if ("branch-compare" === mode) {
    return parseNumstat(
      run("git", ["diff", "--numstat", `${baseRef}...${headRef}`])
    );
  }
  return [];
};

export const getNameStatusForMode = ({ mode, baseRef, headRef }) => {
  if ("working-tree" === mode) {
    const unstaged = parseNameStatus(run("git", ["diff", "--name-status"]));
    const staged = parseNameStatus(
      run("git", ["diff", "--cached", "--name-status"])
    );
    return [...unstaged, ...staged];
  }
  if ("branch-compare" === mode) {
    return parseNameStatus(
      run("git", ["diff", "--name-status", `${baseRef}...${headRef}`])
    );
  }
  return [];
};

export const getFilePatch = ({
  mode,
  baseRef,
  headRef,
  prNumber,
  repoSlug,
  filePath,
  contextLines,
  filePatch,
}) => {
  if (null == filePath || "" === filePath) {
    return "";
  }
  if ("working-tree" === mode) {
    const unstaged = run("git", [
      "diff",
      "--patch",
      `--unified=${contextLines}`,
      "--",
      filePath,
    ]);
    const staged = run("git", [
      "diff",
      "--cached",
      "--patch",
      `--unified=${contextLines}`,
      "--",
      filePath,
    ]);
    return [unstaged, staged].filter((patch) => patch && patch.trim()).join("\n");
  }
  if ("branch-compare" === mode) {
    return run("git", [
      "diff",
      "--patch",
      `--unified=${contextLines}`,
      `${baseRef}...${headRef}`,
      "--",
      filePath,
    ]);
  }
  if ("pr-compare" === mode) {
    return filePatch || "";
  }
  return "";
};

export const splitPatchIntoChunks = (patch, maxLines = 220) => {
  if (null === patch || "" === patch) {
    return [];
  }
  const lines = patch.split("\n");
  const header = [];
  let index = 0;
  while (index < lines.length && false === lines[index].startsWith("@@")) {
    header.push(lines[index]);
    index += 1;
  }
  if (index >= lines.length) {
    return [patch.trimEnd()];
  }
  const chunks = [];
  let current = [...header];
  let currentLines = header.length;
  let currentHasHunk = false;
  for (; index < lines.length; index += 1) {
    const line = lines[index];
    if (
      true === line.startsWith("@@") &&
      true === currentHasHunk &&
      currentLines >= maxLines
    ) {
      chunks.push(current.join("\n").trimEnd());
      current = [...header];
      currentLines = header.length;
      currentHasHunk = false;
    }
    current.push(line);
    currentLines += 1;
    if (true === line.startsWith("@@")) {
      currentHasHunk = true;
    }
  }
  if (current.length) {
    chunks.push(current.join("\n").trimEnd());
  }
  return chunks;
};

export const getPrMeta = ({ prNumber, repoSlug }) => {
  if (null == prNumber || null == repoSlug) {
    return null;
  }
  const { owner, repo } = parseRepoSlug(repoSlug);
  if (null == owner || null == repo) {
    return null;
  }
  const response = runJson("gh", [
    "pr",
    "view",
    String(prNumber),
    "--json",
    "number,url,title,body,baseRefName,headRefName,headRefOid",
    "--repo",
    repoSlug,
  ]);
  return response || null;
};

export const fetchIssueByNumber = ({ issueNumber, repoSlug }) => {
  if (null == issueNumber || null == repoSlug) {
    return null;
  }
  const { owner, repo } = parseRepoSlug(repoSlug);
  if (null == owner || null == repo) {
    return null;
  }
  try {
    return runJson("gh", [
      "api",
      `repos/${owner}/${repo}/issues/${issueNumber}`,
      "-H",
      "Accept: application/vnd.github+json",
    ]);
  } catch (error) {
    const reason = error instanceof Error ? error.message : String(error);
    log(
      `[pr-compare] warning: gh issue fetch failed for ${repoSlug}#${issueNumber}. ${reason}`
    );
    return null;
  }
};

export const getPrDiff = ({ prNumber, repoSlug, contextLines }) => {
  if (null == prNumber || null == repoSlug) {
    return "";
  }
  try {
    const diff = run("gh", [
      "pr",
      "diff",
      String(prNumber),
      "--repo",
      repoSlug,
      "--patch",
    ]);
    return diff || "";
  } catch (error) {
    const reason = error instanceof Error ? error.message : String(error);
    log(`[pr-compare] warning: gh pr diff failed; falling back to local git. ${reason}`);
  }
  return "";
};

export const fetchPrByNumber = ({ prNumber, repoSlug }) => {
  if (null == prNumber || null == repoSlug) {
    return null;
  }
  const { owner, repo } = parseRepoSlug(repoSlug);
  if (null == owner || null == repo) {
    return null;
  }
  return runJson("gh", [
    "api",
    `repos/${owner}/${repo}/pulls/${prNumber}`,
    "-H",
    "Accept: application/vnd.github+json",
  ]);
};

const parseRepoSlugFromApiUrl = (value) => {
  if (null == value) {
    return null;
  }
  const match = String(value).match(/\/repos\/([^/\s]+\/[^/\s]+)$/);
  return match ? match[1] : null;
};

const issueRefKey = (entry) => {
  if (!entry?.repoSlug || !entry?.issueNumber) {
    return null;
  }
  return `${entry.repoSlug}#${entry.issueNumber}`;
};

const sourcePriority = {
  explicit: 4,
  companion: 3,
  issue: 2,
  arg: 1,
};

const getPrimarySource = (sources = []) =>
  sources
    .filter(Boolean)
    .sort((left, right) => {
      const leftWeight = sourcePriority[left] || 0;
      const rightWeight = sourcePriority[right] || 0;
      return rightWeight - leftWeight;
    })[0] || null;

const searchPullRequestsByBranch = ({ owner, branchName }) => {
  if (null == owner || null == branchName) {
    return [];
  }
  const queryScope = [`org:${owner}`, `user:${owner}`];
  for (const scope of queryScope) {
    try {
      const query = [
        "is:pr",
        "is:open",
        "archived:false",
        `head:${owner}:${branchName}`,
        scope,
      ].join(" ");
      const response = runJson("gh", [
        "api",
        "search/issues",
        "-f",
        `q=${query}`,
        "-f",
        "per_page=100",
        "-H",
        "Accept: application/vnd.github+json",
      ]);
      const items = Array.isArray(response?.items) ? response.items : [];
      if (0 < items.length || scope.startsWith("user:")) {
        return items;
      }
    } catch (error) {
      const reason = error instanceof Error ? error.message : String(error);
      log(
        `[pr-compare] warning: companion branch search failed for owner=${owner} scope=${scope}. ${reason}`
      );
    }
  }
  return [];
};

const discoverCompanionPrsByIssueAndBranch = ({
  prMeta,
  repoSlug,
  issueRefs = [],
}) => {
  const branchName = prMeta?.headRefName || null;
  if (null == branchName || 0 === issueRefs.length) {
    return [];
  }
  const targetIssueKeys = new Set(
    issueRefs
      .map((issueRef) => issueRefKey(issueRef))
      .filter((issueRefValue) => null !== issueRefValue)
  );
  if (0 === targetIssueKeys.size) {
    return [];
  }
  const owners = unique(
    [repoSlug, ...issueRefs.map((issueRef) => issueRef.repoSlug)]
      .map((slug) => parseRepoSlug(slug).owner)
      .filter(Boolean)
  );
  const primaryKey = prMeta?.number ? `${repoSlug}#${prMeta.number}` : null;
  const companions = [];
  const seen = new Set();
  owners.forEach((owner) => {
    const candidates = searchPullRequestsByBranch({ owner, branchName });
    candidates.forEach((candidate) => {
      const candidateRepoSlug = parseRepoSlugFromApiUrl(candidate?.repository_url);
      const candidatePrNumber = Number(candidate?.number);
      if (!candidateRepoSlug || !Number.isFinite(candidatePrNumber)) {
        return;
      }
      const candidateKey = `${candidateRepoSlug}#${candidatePrNumber}`;
      if (candidateKey === primaryKey || true === seen.has(candidateKey)) {
        return;
      }
      seen.add(candidateKey);
      let prDetails = null;
      try {
        prDetails = fetchPrByNumber({
          prNumber: candidatePrNumber,
          repoSlug: candidateRepoSlug,
        });
      } catch (error) {
        const reason = error instanceof Error ? error.message : String(error);
        log(
          `[pr-compare] warning: companion PR metadata lookup failed for ${candidateKey}. ${reason}`
        );
        return;
      }
      const candidateBranchName = prDetails?.head?.ref || null;
      if (branchName !== candidateBranchName) {
        return;
      }
      const effectiveRepoSlug =
        prDetails?.base?.repo?.full_name ||
        prDetails?.head?.repo?.full_name ||
        candidateRepoSlug;
      const candidateIssueRefs = extractIssueRefsFromText({
        text: prDetails?.body || "",
        defaultRepoSlug: effectiveRepoSlug,
      });
      const matchedIssue = candidateIssueRefs.find((candidateIssueRef) =>
        targetIssueKeys.has(issueRefKey(candidateIssueRef))
      );
      if (!matchedIssue) {
        return;
      }
      companions.push({
        repoSlug: effectiveRepoSlug,
        prNumber: Number(prDetails?.number || candidatePrNumber),
        source: "companion",
        issue: {
          repoSlug: matchedIssue.repoSlug,
          issueNumber: matchedIssue.issueNumber,
        },
        companion: {
          sameIssue: true,
          sameBranch: true,
          branchName,
        },
      });
    });
  });
  return companions;
};

const uniquePrRefs = (entries) => {
  const map = new Map();
  entries.forEach((entry) => {
    if (!entry?.repoSlug || !entry?.prNumber) {
      return;
    }
    const key = `${entry.repoSlug}#${entry.prNumber}`;
    const sourceList = unique(
      [...(Array.isArray(entry?.sources) ? entry.sources : []), entry.source].filter(
        Boolean
      )
    );
    const normalizedEntry = {
      ...entry,
      source: getPrimarySource(sourceList) || entry.source || null,
      sources: sourceList,
    };
    if (!map.has(key)) {
      map.set(key, normalizedEntry);
      return;
    }
    const existing = map.get(key);
    const mergedSources = unique(
      [...(existing?.sources || []), ...(normalizedEntry?.sources || [])].filter(
        Boolean
      )
    );
    map.set(key, {
      ...existing,
      ...normalizedEntry,
      issue: existing?.issue || normalizedEntry?.issue || null,
      companion: existing?.companion || normalizedEntry?.companion || null,
      source:
        getPrimarySource(mergedSources) ||
        existing?.source ||
        normalizedEntry?.source ||
        null,
      sources: mergedSources,
    });
  });
  return Array.from(map.values());
};

export const parsePrReference = (value) => {
  if (null == value) {
    return null;
  }
  const text = String(value).trim();
  if ("" === text) {
    return null;
  }
  const urlMatch = text.match(
    /github\.com\/([^/\s]+\/[^/\s]+)\/pull\/(\d+)/i
  );
  if (urlMatch) {
    return { repoSlug: urlMatch[1], prNumber: Number(urlMatch[2]) };
  }
  const hashMatch = text.match(/^([^#\s]+)#[^0-9]*?(\d+)$/);
  if (hashMatch) {
    return { repoSlug: hashMatch[1], prNumber: Number(hashMatch[2]) };
  }
  const slashMatch = text.match(/^([^/\s]+\/[^/\s]+)\/pull\/(\d+)$/);
  if (slashMatch) {
    return { repoSlug: slashMatch[1], prNumber: Number(slashMatch[2]) };
  }
  return null;
};

export const parseRelatedPrArgs = (values = []) => {
  const entries = [];
  const list = Array.isArray(values) ? values : [values];
  list
    .flatMap((value) => String(value).split(","))
    .map((value) => value.trim())
    .filter(Boolean)
    .forEach((value) => {
      const parsed = parsePrReference(value);
      if (parsed?.repoSlug && parsed?.prNumber) {
        entries.push({ ...parsed, source: "arg" });
      }
    });
  return uniquePrRefs(entries);
};

export const extractIssueRefsFromText = ({ text, defaultRepoSlug }) => {
  if (null == text) {
    return [];
  }
  const entries = [];
  const body = String(text);
  const issueUrlRegex =
    /https?:\/\/github\.com\/([^/\s]+\/[^/\s]+)\/issues\/(\d+)/gi;
  let match = issueUrlRegex.exec(body);
  while (match) {
    entries.push({ repoSlug: match[1], issueNumber: Number(match[2]) });
    match = issueUrlRegex.exec(body);
  }
  if (0 === entries.length && defaultRepoSlug) {
    const issueHashRegex = /(^|\s)#(\d{3,})\b/g;
    let hashMatch = issueHashRegex.exec(body);
    while (hashMatch) {
      entries.push({
        repoSlug: defaultRepoSlug,
        issueNumber: Number(hashMatch[2]),
      });
      hashMatch = issueHashRegex.exec(body);
    }
  }
  const deduped = new Map();
  entries.forEach((entry) => {
    if (!entry.repoSlug || !entry.issueNumber) {
      return;
    }
    const key = `${entry.repoSlug}#${entry.issueNumber}`;
    if (!deduped.has(key)) {
      deduped.set(key, entry);
    }
  });
  return Array.from(deduped.values());
};

export const extractPrRefsFromText = (text) => {
  if (null == text) {
    return [];
  }
  const entries = [];
  const body = String(text);
  const prUrlRegex =
    /https?:\/\/github\.com\/([^/\s]+\/[^/\s]+)\/pull\/(\d+)/gi;
  let match = prUrlRegex.exec(body);
  while (match) {
    entries.push({ repoSlug: match[1], prNumber: Number(match[2]) });
    match = prUrlRegex.exec(body);
  }
  return uniquePrRefs(entries);
};

export const resolveRelatedPrs = ({
  prMeta,
  repoSlug,
  explicit = [],
  discover = true,
}) => {
  const related = [];
  const issueRefs =
    true === discover && prMeta?.body
      ? extractIssueRefsFromText({
          text: prMeta.body,
          defaultRepoSlug: repoSlug,
        })
      : [];
  const primaryKey = prMeta?.number
    ? `${repoSlug}#${prMeta.number}`
    : null;
  explicit.forEach((entry) => {
    if (entry?.repoSlug && entry?.prNumber) {
      related.push({ ...entry, source: "explicit" });
    }
  });
  if (true === discover && 0 < issueRefs.length) {
    issueRefs.forEach((issueRef) => {
      const issue = fetchIssueByNumber({
        issueNumber: issueRef.issueNumber,
        repoSlug: issueRef.repoSlug,
      });
      if (!issue?.body) {
        return;
      }
      const prRefs = extractPrRefsFromText(issue.body);
      prRefs.forEach((prRef) => {
        related.push({
          ...prRef,
          source: "issue",
          issue: {
            repoSlug: issueRef.repoSlug,
            issueNumber: issueRef.issueNumber,
          },
        });
      });
    });
    const companions = discoverCompanionPrsByIssueAndBranch({
      prMeta,
      repoSlug,
      issueRefs,
    });
    companions.forEach((entry) => {
      related.push(entry);
    });
  }
  const deduped = uniquePrRefs(related).filter((entry) => {
    if (null == primaryKey) {
      return true;
    }
    return `${entry.repoSlug}#${entry.prNumber}` !== primaryKey;
  });
  return annotateSameBranchCompanions({
    entries: deduped,
    branchName: prMeta?.headRefName || null,
  });
};

const annotateSameBranchCompanions = ({ entries, branchName }) => {
  if (!branchName || false === Array.isArray(entries)) {
    return entries;
  }
  return entries.map((entry) => {
    if (true === entry?.companion?.sameBranch) {
      return entry;
    }
    try {
      const details = fetchPrByNumber({
        prNumber: entry.prNumber,
        repoSlug: entry.repoSlug,
      });
      if (branchName !== details?.head?.ref) {
        return entry;
      }
      return {
        ...entry,
        companion: {
          sameIssue: Boolean(entry.issue),
          sameBranch: true,
          branchName,
        },
      };
    } catch (error) {
      const reason = error instanceof Error ? error.message : String(error);
      log(
        `[pr-compare] warning: related PR branch lookup failed for ${entry.repoSlug}#${entry.prNumber}. ${reason}`
      );
      return entry;
    }
  });
};

export const resolveCompanionContext = ({ prMeta, repoSlug, relatedPrs = [] }) => {
  const issueRefs = extractIssueRefsFromText({
    text: prMeta?.body || "",
    defaultRepoSlug: repoSlug,
  });
  const branchName = prMeta?.headRefName || null;
  const confirmedCompanions = (Array.isArray(relatedPrs) ? relatedPrs : [])
    .filter(
      (entry) =>
        true === entry?.companion?.sameIssue &&
        true === entry?.companion?.sameBranch
    )
    .map((entry) => ({
      repoSlug: entry.repoSlug,
      prNumber: entry.prNumber,
      source: entry.source || null,
      issue: entry.issue || null,
      branchName: entry?.companion?.branchName || branchName,
    }));
  const canEvaluate = null != branchName && 0 < issueRefs.length;
  const hasConfirmedCompanion = 0 < confirmedCompanions.length;
  const status = hasConfirmedCompanion
    ? "confirmed"
    : canEvaluate
      ? "not_confirmed"
      : "unknown";
  const reason = hasConfirmedCompanion
    ? "same_issue_same_branch_companion_detected"
    : null == branchName
      ? "missing_branch_context"
      : 0 === issueRefs.length
        ? "missing_issue_context"
        : "companion_not_found";
  return {
    status,
    reason,
    canEvaluate,
    branchName,
    issueRefs,
    hasConfirmedCompanion,
    confirmedCompanions,
  };
};

export const buildTaskContextFromFiles = ({ repoRoot, taskFiles }) => {
  const taskContext = buildTaskContext(repoRoot, taskFiles);
  if (null == taskContext?.implementation_plan_path) {
    return taskContext;
  }
  const normalizedPlanPath = path.normalize(taskContext.implementation_plan_path);
  if (false === fs.existsSync(normalizedPlanPath)) {
    log(`tasks: plan missing ${normalizedPlanPath}`);
  }
  return taskContext;
};
