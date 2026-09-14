import crypto from "crypto";
import fs from "fs";
import path from "path";
import { spawnSync } from "child_process";
import dotenv from "dotenv";
import OpenAI from "openai";
import { loadConfig } from "./core/config.mjs";
import {
  getDefaultDbPath,
  insertFinding,
  openDb,
  upsertComment,
  upsertPr,
} from "./db.mjs";

const args = process.argv.slice(2);

const getArgValue = (name) => {
  const index = args.indexOf(name);
  if (-1 === index) {
    return null;
  }
  return args[index + 1] ?? null;
};

const hasArg = (name) => args.includes(name);

const getArgValues = (name) => {
  const values = [];
  args.forEach((arg, index) => {
    if (arg === name) {
      const value = args[index + 1];
      if (value) {
        values.push(value);
      }
    }
  });
  return values;
};

const log = (...parts) => {
  console.log(...parts);
};

const truncateOutput = (value, limit = 1200) => {
  if (null == value) {
    return "";
  }
  if (value.length <= limit) {
    return value;
  }
  return `${value.slice(0, limit)}\n... (truncated)`;
};

const run = (command, commandArgs, options = {}) => {
  log(`run: ${command} ${commandArgs.join(" ")}`);
  const result = spawnSync(command, commandArgs, {
    encoding: "utf8",
    maxBuffer: 50 * 1024 * 1024,
    ...options,
  });
  if (0 !== result.status) {
    const statusLabel = null != result.status
      ? result.status
      : result.signal || "unknown";
    const details = [
      result.error?.message,
      result.stderr || result.stdout,
    ]
      .filter(Boolean)
      .join("\n");
    throw new Error(
      `${command} failed (${statusLabel}): ${truncateOutput(details)}`
    );
  }
  return result.stdout.trimEnd();
};

const runJson = (command, commandArgs, options = {}) =>
  JSON.parse(run(command, commandArgs, options));

const ensureDir = (dirPath) => {
  if (false === fs.existsSync(dirPath)) {
    fs.mkdirSync(dirPath, { recursive: true });
  }
};

const writeJson = (filePath, payload) => {
  ensureDir(path.dirname(filePath));
  fs.writeFileSync(filePath, `${JSON.stringify(payload, null, 2)}\n`, "utf8");
};

const writeText = (filePath, contents) => {
  ensureDir(path.dirname(filePath));
  fs.writeFileSync(filePath, contents, "utf8");
};

const getRepoRoot = () => run("git", ["rev-parse", "--show-toplevel"]);

const loadEnv = () => {
  const repoRoot = getRepoRoot();
  const envPath = path.join(repoRoot, "tools/ai-review-orchestrator/.env");
  if (true === fs.existsSync(envPath)) {
    dotenv.config({ path: envPath });
  }
};

const parseRepoSlug = (repoSlug) => {
  if (null == repoSlug) {
    return { owner: null, repo: null };
  }
  const parts = repoSlug.split("/");
  if (parts.length < 2) {
    return { owner: null, repo: null };
  }
  return { owner: parts[0], repo: parts.slice(1).join("/") };
};

const parseDate = (value) => {
  if (null == value || "" === value) {
    return null;
  }
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    throw new Error(`Invalid date: ${value}`);
  }
  return parsed;
};

const DEFAULT_EXCLUDED_AUTHORS = ["deephiveet", "etstaging"];

const isBotUser = (user) => {
  if (null == user) {
    return false;
  }
  if ("Bot" === user.type) {
    return true;
  }
  if ("string" === typeof user.login && user.login.endsWith("[bot]")) {
    return true;
  }
  return false;
};

const normalizeLoginSet = (values) =>
  new Set(
    (values || [])
      .map((value) => String(value || "").trim().toLowerCase())
      .filter(Boolean)
  );

const resolveExcludedAuthors = ({ config, extraArg, includeExcluded }) => {
  if (true === includeExcluded) {
    return new Set();
  }
  const fromConfig = config?.feedback_harvest?.exclude_authors;
  return normalizeLoginSet([
    ...DEFAULT_EXCLUDED_AUTHORS,
    ...(Array.isArray(fromConfig) ? fromConfig : []),
    config?.feedback_bot_login,
    ...(extraArg ? extraArg.split(",") : []),
  ]);
};

const normalizeTrustedUsers = ({ trustedUsersArg, trustedUserArgs }) => {
  const combined = [
    ...(trustedUsersArg ? trustedUsersArg.split(",") : []),
    ...trustedUserArgs,
  ];
  return new Set(
    combined
      .map((value) => value.trim())
      .filter(Boolean)
      .map((value) => value.toLowerCase())
  );
};

const shouldIncludeAuthor = ({
  user,
  trustedUsers,
  includeBots,
  includeAllWhenUntrusted,
  excludedAuthors,
}) => {
  if (null == user) {
    return false;
  }
  const login = user.login ? user.login.toLowerCase() : "";
  if (0 !== trustedUsers.size) {
    return trustedUsers.has(login);
  }
  if (excludedAuthors && true === excludedAuthors.has(login)) {
    return false;
  }
  if (false === includeBots && true === isBotUser(user)) {
    return false;
  }
  return true === includeAllWhenUntrusted;
};

const formatDate = (value) => value.toISOString().split("T")[0];

const fetchClosedPrs = ({ repoSlug, limit, since, until }) => {
  const { owner, repo } = parseRepoSlug(repoSlug);
  if (null == owner || null == repo) {
    return [];
  }
  const pageSize = 100;
  const maxPages = null == limit ? Infinity : Math.ceil(limit / pageSize);

  if (since || until) {
    const terms = [
      `repo:${owner}/${repo}`,
      "is:pr",
      "is:closed",
    ];
    if (since && until) {
      terms.push(`closed:${formatDate(since)}..${formatDate(until)}`);
    } else {
      if (since) {
        terms.push(`closed:>=${formatDate(since)}`);
      }
      if (until) {
        terms.push(`closed:<=${formatDate(until)}`);
      }
    }
    const query = terms.join(" ");
    const all = [];
    for (let page = 1; page <= maxPages; page += 1) {
      const response = runJson("gh", [
        "api",
        "search/issues",
        "-X",
        "GET",
        "-H",
        "Accept: application/vnd.github+json",
        "-f",
        `q=${query}`,
        "-f",
        `per_page=${pageSize}`,
        "-f",
        `page=${page}`,
      ]);
      const items = response?.items ?? [];
      if (0 === items.length) {
        break;
      }
      log(`fetch: page ${page} (${items.length})`);
      all.push(...items);
      if (null != limit && all.length >= limit) {
        break;
      }
    }
    const prNumbers = all.map((item) => item.number);
    const prDetails = prNumbers
      .map((number) => fetchPr({ repoSlug: `${owner}/${repo}`, prNumber: number }))
      .filter(Boolean);
    if (null != limit) {
      return prDetails.slice(0, limit);
    }
    return prDetails;
  }

  const all = [];
  for (let page = 1; page <= maxPages; page += 1) {
    const response = runJson("gh", [
      "api",
      `repos/${owner}/${repo}/pulls`,
      "-X",
      "GET",
      "-H",
      "Accept: application/vnd.github+json",
      "-f",
      "state=closed",
      "-f",
      `per_page=${pageSize}`,
      "-f",
      `page=${page}`,
      "-f",
      "sort=updated",
      "-f",
      "direction=desc",
    ]);
    if (false === Array.isArray(response) || 0 === response.length) {
      break;
    }
    log(`fetch: page ${page} (${response.length})`);
    all.push(...response);
    if (null != limit && all.length >= limit) {
      break;
    }
  }
  if (null != limit) {
    return all.slice(0, limit);
  }
  return all;
};

const fetchPr = ({ repoSlug, prNumber }) => {
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

const fetchIssueComments = ({ repoSlug, prNumber }) => {
  const { owner, repo } = parseRepoSlug(repoSlug);
  if (null == owner || null == repo) {
    return [];
  }
  const response = runJson("gh", [
    "api",
    `repos/${owner}/${repo}/issues/${prNumber}/comments`,
    "--paginate",
    "-H",
    "Accept: application/vnd.github+json",
  ]);
  return Array.isArray(response) ? response : [];
};

const fetchReviewComments = ({ repoSlug, prNumber }) => {
  const { owner, repo } = parseRepoSlug(repoSlug);
  if (null == owner || null == repo) {
    return [];
  }
  const response = runJson("gh", [
    "api",
    `repos/${owner}/${repo}/pulls/${prNumber}/comments`,
    "--paginate",
    "-H",
    "Accept: application/vnd.github+json",
  ]);
  return Array.isArray(response) ? response : [];
};

const fetchReviews = ({ repoSlug, prNumber }) => {
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

const hashComment = (comment) =>
  crypto
    .createHash("sha256")
    .update(JSON.stringify(comment))
    .digest("hex");

const normalizeComment = ({ prNumber, type, comment, minLength }) => {
  const body = comment?.body?.trim() ?? "";
  if (body.length < minLength) {
    return null;
  }
  const normalized = {
    id: comment.id,
    pr_number: prNumber,
    type,
    author: comment?.user?.login ?? null,
    author_type: comment?.user?.type ?? null,
    created_at: comment?.created_at ?? null,
    body,
    path: comment?.path ?? null,
    position: comment?.position ?? null,
    line: comment?.line ?? null,
    original_line: comment?.original_line ?? null,
    original_position: comment?.original_position ?? null,
    diff_hunk: comment?.diff_hunk ?? null,
    commit_id: comment?.commit_id ?? null,
  };
  return {
    ...normalized,
    hash: hashComment(normalized),
  };
};

const gatherHumanComments = ({
  repoSlug,
  prNumber,
  trustedUsers,
  includeBots,
  includeAllWhenUntrusted,
  excludedAuthors,
  minLength,
}) => {
  const issueComments = fetchIssueComments({ repoSlug, prNumber });
  const reviewComments = fetchReviewComments({ repoSlug, prNumber });
  const reviews = fetchReviews({ repoSlug, prNumber });

  const filteredIssue = issueComments.filter((comment) =>
    shouldIncludeAuthor({
      user: comment.user,
      trustedUsers,
      includeBots,
      includeAllWhenUntrusted,
      excludedAuthors,
    })
  );
  const filteredReview = reviewComments.filter((comment) =>
    shouldIncludeAuthor({
      user: comment.user,
      trustedUsers,
      includeBots,
      includeAllWhenUntrusted,
      excludedAuthors,
    })
  );
  const filteredReviews = reviews.filter((review) =>
    shouldIncludeAuthor({
      user: review.user,
      trustedUsers,
      includeBots,
      includeAllWhenUntrusted,
      excludedAuthors,
    })
  );

  const normalized = [
    ...filteredIssue.map((comment) =>
      normalizeComment({ prNumber, type: "issue_comment", comment, minLength })
    ),
    ...filteredReview.map((comment) =>
      normalizeComment({ prNumber, type: "review_comment", comment, minLength })
    ),
    ...filteredReviews.map((comment) =>
      normalizeComment({ prNumber, type: "review", comment, minLength })
    ),
  ].filter(Boolean);

  return normalized;
};

const loadReviewerNames = (repoRoot) => {
  const reviewersDir = path.join(
    repoRoot,
    "tools/ai-review-orchestrator/reviewers"
  );
  if (false === fs.existsSync(reviewersDir)) {
    return [];
  }
  return fs
    .readdirSync(reviewersDir)
    .filter((file) => file.endsWith(".md"))
    .map((file) => file.replace(/\.md$/, ""));
};

const buildAnalysisPrompt = ({ reviewers, pr, comments }) => [
  {
    role: "system",
    content:
      "You are analyzing human PR feedback to improve automated review prompts.",
  },
  {
    role: "user",
    content: [
      "Given the PR and trusted human comments, map each comment to the best reviewer,",
      "or label as new_gap. Then propose reviewer improvements or new reviewer ideas.",
      "",
      `Reviewer names: ${reviewers.join(", ") || "none"}`,
      "",
      `PR: #${pr.number} ${pr.title}`,
      `URL: ${pr.html_url}`,
      `Base: ${pr.base?.ref ?? "unknown"}`,
      `Head: ${pr.head?.ref ?? "unknown"}`,
      "",
      "Comments (JSON):",
      JSON.stringify(comments, null, 2),
      "",
      "Return JSON only with:",
      "{",
      '  "mapped_comments": [',
      "    { comment_id, reviewer, label, rationale }",
      "  ],",
      '  "gap_summary": [',
      "    {",
      "      reviewer,",
      "      patch_type,",
      "      issue,",
      "      section_title,",
      "      operation,",
      "      target_text,",
      "      source_comment_ids,",
      "      suggested_prompt_change",
      "    }",
      "  ],",
      '  "new_reviewer_suggestions": [',
      "    {",
      "      name,",
      "      patch_type,",
      "      scope,",
      "      section_title,",
      "      operation,",
      "      target_text,",
      "      suggested_prompt",
      "    }",
      "  ]",
      "}",
      "",
      "Rules:",
      "- section_title should match or propose a markdown section title (## Heading).",
      "- operation must be 'add' or 'replace'.",
      "- When operation is 'replace', include target_text to replace.",
      "- When operation is 'add', target_text can be null.",
      "- patch_type must be one of: reviewer, rule, docs, other.",
      "- source_comment_ids should list comment ids that support the suggestion.",
    ].join("\n"),
  },
];

const analyzeComments = async ({ client, model, reviewers, pr, comments }) => {
  const response = await client.responses.create({
    model,
    input: buildAnalysisPrompt({ reviewers, pr, comments }),
  });
  const outputText =
    response.output_text ||
    response.output?.map((item) => item.content?.[0]?.text ?? "").join("\n") ||
    "";
  return outputText.trim();
};

const SKIP_COMMENT_REGEX = [
  /deephive test failure summary/i,
  /deephive automated analysis/i,
  /\blgtm\b/i,
  /\bclos(e|ing)\s+(this|the)\s+pr\b/i,
  /\bfixed in\b/i,
  /\bi'?ve addressed\b/i,
  /\bfix eslint errors?\b/i,
  /\bfix snapshot\b/i,
];

const shouldSkipByRegex = (body) =>
  SKIP_COMMENT_REGEX.some((pattern) => pattern.test(body));

const buildNanoPrompt = (commentBody) => [
  {
    role: "system",
    content:
      "You decide whether a PR comment is substantive feedback worth learning from.",
  },
  {
    role: "user",
    content: [
      "Answer with JSON only: { keep: boolean, reason: string }.",
      "",
      "Guidance:",
      "- keep=true for actionable review feedback, architectural concerns, or requests to change code behavior.",
      "- keep=false for status updates, acknowledgements, test bot summaries, or replies that only describe what changed.",
      "",
      "Comment:",
      commentBody,
    ].join("\n"),
  },
];

const shouldKeepByNano = async ({ client, model, commentBody }) => {
  const response = await client.responses.create({
    model,
    input: buildNanoPrompt(commentBody),
  });
  const outputText =
    response.output_text ||
    response.output?.map((item) => item.content?.[0]?.text ?? "").join("\n") ||
    "";
  try {
    const parsed = JSON.parse(outputText.trim());
    return true === parsed.keep;
  } catch (error) {
    return true;
  }
};

const renderSummary = ({ repoSlug, prs, analysis, trustedUsers }) => {
  const reviewerCounts = new Map();
  if (analysis) {
    analysis.forEach((entry) => {
      entry.mapped_comments?.forEach((item) => {
        const key = item.reviewer || "new_gap";
        reviewerCounts.set(key, (reviewerCounts.get(key) ?? 0) + 1);
      });
    });
  }
  const reviewersSummary = [...reviewerCounts.entries()]
    .sort((a, b) => b[1] - a[1])
    .map(([reviewer, count]) => `- ${reviewer}: ${count}`)
    .join("\n");

  return [
    `# PR Feedback Summary`,
    ``,
    `Repo: ${repoSlug}`,
    `PRs analyzed: ${prs.length}`,
    `Trusted users: ${0 === trustedUsers.size ? "none" : [...trustedUsers].join(", ")
    }`,
    ``,
    "## Reviewer Mapping Counts",
    reviewersSummary || "- (no analysis run)",
    ``,
  ].join("\n");
};

const main = async () => {
  loadEnv();
  const repoRoot = getRepoRoot();
  const repoSlug = getArgValue("--repo") || "elegantthemes/submodule-builder-5";
  const since = parseDate(getArgValue("--since"));
  const until = parseDate(getArgValue("--until"));
  const limitValue = getArgValue("--limit");
  const limit = null == limitValue ? null : Number(limitValue);
  const prListArg = getArgValue("--prs");
  const prNumbers = prListArg
    ? prListArg
      .split(",")
      .map((value) => Number(value.trim()))
      .filter((value) => Number.isFinite(value))
    : [];
  const trustedUsers = normalizeTrustedUsers({
    trustedUsersArg: getArgValue("--trusted-users"),
    trustedUserArgs: getArgValues("--trusted-user"),
  });
  const includeBots = hasArg("--include-bots");
  const includeAllWhenUntrusted = hasArg("--include-all")
    ? true
    : 0 === trustedUsers.size;
  const config = loadConfig(repoRoot);
  const excludedAuthors = resolveExcludedAuthors({
    config,
    extraArg: getArgValue("--exclude-authors"),
    includeExcluded: hasArg("--include-excluded-authors"),
  });
  const useDb = false === hasArg("--no-db");
  const dbArg = getArgValue("--db");
  const dbContext = useDb ? openDb({ repoRoot, dbPath: dbArg }) : null;
  const db = dbContext?.db ?? null;
  const minCommentLengthValue = getArgValue("--min-comment-length");
  const minCommentLength =
    null == minCommentLengthValue ? 20 : Number(minCommentLengthValue);
  const shouldAnalyze = hasArg("--analyze");
  const analysisModel =
    getArgValue("--analysis-model") ||
    process.env.OPENAI_SUMMARY_MODEL ||
    "gpt-5.3-codex";
  const nanoModel =
    getArgValue("--nano-model") ||
    process.env.OPENAI_NANO_MODEL ||
    "gpt-5-nano";
  const useNanoFilter = false === hasArg("--no-nano-filter");
  const outputRoot =
    getArgValue("--output-dir") ||
    path.join(
      repoRoot,
      "tools/ai-review-orchestrator/output",
      `${new Date().toISOString().replace(/[:.]/g, "-")}_feedback`
    );

  log(`db: ${dbContext?.dbPath ?? getDefaultDbPath(repoRoot)}`);
  log(`excluded authors: ${[...excludedAuthors].join(", ") || "(none)"}`);
  if (true === Number.isNaN(limit)) {
    throw new Error("Invalid --limit value.");
  }
  if (true === Number.isNaN(minCommentLength)) {
    throw new Error("Invalid --min-comment-length value.");
  }

  const reviewers = loadReviewerNames(repoRoot);

  const prs = prNumbers.length
    ? prNumbers
      .map((prNumber) => fetchPr({ repoSlug, prNumber }))
      .filter(Boolean)
    : fetchClosedPrs({ repoSlug, limit, since, until });

  const results = [];
  const analysisResults = [];
  const commentIdByGhId = new Map();
  const progressEvery = Number(getArgValue("--progress-every") || 25);
  const client = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });
  let skippedByRegex = 0;
  let skippedByNano = 0;
  let keptForAnalysis = 0;

  for (let index = 0; index < prs.length; index += 1) {
    const pr = prs[index];
    if (null == pr) {
      continue;
    }
    const prId = db
      ? upsertPr(db, {
          repo: repoSlug,
          number: pr.number,
          title: pr.title,
          url: pr.html_url,
          author: pr.user?.login ?? null,
          base_ref: pr.base?.ref ?? null,
          head_ref: pr.head?.ref ?? null,
          merged_at: pr.merged_at ?? null,
          closed_at: pr.closed_at ?? null,
          created_at: pr.created_at ?? null,
          updated_at: pr.updated_at ?? null,
        })
      : null;
    const comments = gatherHumanComments({
      repoSlug,
      prNumber: pr.number,
      trustedUsers,
      includeBots,
      includeAllWhenUntrusted,
      excludedAuthors,
      minLength: minCommentLength,
    });
    if (db && prId) {
      comments.forEach((comment) => {
        const commentId = upsertComment(db, {
          pr_id: prId,
          gh_comment_id: comment.id ?? null,
          type: comment.type,
          author: comment.author,
          author_type: comment.author_type,
          body: comment.body,
          path: comment.path,
          line: comment.line,
          position: comment.position,
          original_line: comment.original_line,
          original_position: comment.original_position,
          diff_hunk: comment.diff_hunk,
          commit_id: comment.commit_id,
          created_at: comment.created_at,
          hash: comment.hash,
        });
        comment.db_id = commentId;
        if (null != comment.id) {
          commentIdByGhId.set(comment.id, commentId);
        }
      });
    }
    const entry = {
      pr: {
        number: pr.number,
        title: pr.title,
        html_url: pr.html_url,
        closed_at: pr.closed_at,
        merged_at: pr.merged_at,
        user: pr.user?.login ?? null,
        base: pr.base?.ref ?? null,
        head: pr.head?.ref ?? null,
      },
      comments,
    };
    results.push(entry);

    if (true === shouldAnalyze && 0 !== comments.length) {
      const filteredComments = [];
      for (const comment of comments) {
        if (shouldSkipByRegex(comment.body)) {
          skippedByRegex += 1;
          continue;
        }
        if (true === useNanoFilter) {
          const keep = await shouldKeepByNano({
            client,
            model: nanoModel,
            commentBody: comment.body,
          });
          if (false === keep) {
            skippedByNano += 1;
            continue;
          }
        }
        filteredComments.push(comment);
      }

      if (0 === filteredComments.length) {
        continue;
      }

      keptForAnalysis += filteredComments.length;
      log(`analyze: pr #${pr.number} (${filteredComments.length} comments)`);
      const analysisText = await analyzeComments({
        client,
        model: analysisModel,
        reviewers,
        pr,
        comments: filteredComments,
      });
      let analysisJson = null;
      try {
        analysisJson = JSON.parse(analysisText);
      } catch (error) {
        analysisJson = null;
      }

      if (db && analysisJson) {
        analysisJson.gap_summary?.forEach((gap) => {
          const patchType = gap.patch_type ?? "reviewer";
          const sourceIds = Array.isArray(gap.source_comment_ids)
            ? gap.source_comment_ids
            : [];
          const findingIds = [];
          if (0 === sourceIds.length) {
            const findingId = insertFinding(db, {
              comment_id: null,
              reviewer: gap.reviewer ?? "unknown",
              patch_type: patchType,
              operation: gap.operation ?? "add",
              section_title: gap.section_title ?? null,
              target_text: gap.target_text ?? null,
              suggestion_text: gap.suggested_prompt_change ?? "",
              raw_json: JSON.stringify(gap),
              created_at: new Date().toISOString(),
            });
            findingIds.push(Number(findingId));
          } else {
            sourceIds.forEach((sourceId) => {
              const commentId = commentIdByGhId.get(sourceId) ?? null;
              const findingId = insertFinding(db, {
                comment_id: commentId,
                reviewer: gap.reviewer ?? "unknown",
                patch_type: patchType,
                operation: gap.operation ?? "add",
                section_title: gap.section_title ?? null,
                target_text: gap.target_text ?? null,
                suggestion_text: gap.suggested_prompt_change ?? "",
                raw_json: JSON.stringify(gap),
                created_at: new Date().toISOString(),
              });
              findingIds.push(Number(findingId));
            });
          }
          gap.finding_ids = findingIds;
          gap.finding_id = 1 === findingIds.length ? findingIds[0] : null;
        });

        analysisJson.new_reviewer_suggestions?.forEach((gap) => {
          const patchType = gap.patch_type ?? "reviewer";
          const findingId = insertFinding(db, {
            comment_id: null,
            reviewer: gap.name ?? "new-reviewer",
            patch_type: patchType,
            operation: gap.operation ?? "add",
            section_title: gap.section_title ?? null,
            target_text: gap.target_text ?? null,
            suggestion_text: gap.suggested_prompt ?? "",
            raw_json: JSON.stringify(gap),
            created_at: new Date().toISOString(),
          });
          gap.finding_ids = [Number(findingId)];
          gap.finding_id = Number(findingId);
        });
      }

      analysisResults.push({
        pr_number: pr.number,
        analysis: analysisText,
        analysis_json: analysisJson,
      });
    }

    if (0 < progressEvery && 0 === (index + 1) % progressEvery) {
      const commentCount = results.reduce(
        (total, item) => total + item.comments.length,
        0
      );
      log(`progress: ${index + 1}/${prs.length} (comments: ${commentCount})`);
    }
  }

  writeJson(path.join(outputRoot, "run.json"), {
    repo: repoSlug,
    since: since ? since.toISOString() : null,
    until: until ? until.toISOString() : null,
    limit,
    prs: prs.length,
    trusted_users: [...trustedUsers],
    excluded_authors: [...excludedAuthors],
    include_bots: includeBots,
    min_comment_length: minCommentLength,
    db_enabled: useDb,
    db_path: useDb ? dbContext?.dbPath ?? getDefaultDbPath(repoRoot) : null,
    analyzed: shouldAnalyze,
    analysis_model: shouldAnalyze ? analysisModel : null,
    nano_model: shouldAnalyze && useNanoFilter ? nanoModel : null,
    skipped_by_regex: skippedByRegex,
    skipped_by_nano: skippedByNano,
    kept_for_analysis: keptForAnalysis,
  });
  writeJson(path.join(outputRoot, "prs.json"), results);
  if (true === shouldAnalyze) {
    writeJson(path.join(outputRoot, "analysis.json"), analysisResults);
  }
  writeText(
    path.join(outputRoot, "summary.md"),
    renderSummary({
      repoSlug,
      prs,
      analysis: analysisResults
        .map((entry) => {
          if (entry.analysis_json) {
            return entry.analysis_json;
          }
          try {
            return JSON.parse(entry.analysis);
          } catch (error) {
            return null;
          }
        })
        .filter(Boolean),
      trustedUsers,
    })
  );

  const commentCount = results.reduce(
    (total, entry) => total + entry.comments.length,
    0
  );
  console.log(
    `done: ${outputRoot} (prs: ${results.length}, comments: ${commentCount})`
  );
};

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
