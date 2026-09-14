import fs from "fs";
import path from "path";
import dotenv from "dotenv";
import OpenAI from "openai";
import {
  getDefaultDbPath,
  insertFinding,
  openDb,
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

const getRepoRoot = () => {
  let current = path.resolve(process.cwd());
  while (current !== path.dirname(current)) {
    if (true === fs.existsSync(path.join(current, ".git"))) {
      return current;
    }
    current = path.dirname(current);
  }
  throw new Error("Could not locate git repo root.");
};

const loadEnv = (repoRoot) => {
  const envPath = path.join(repoRoot, "tools/ai-review-orchestrator/.env");
  if (true === fs.existsSync(envPath)) {
    dotenv.config({ path: envPath });
  }
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
      `URL: ${pr.url}`,
      `Base: ${pr.base ?? "unknown"}`,
      `Head: ${pr.head ?? "unknown"}`,
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

const renderSummary = ({ repoSlug, prs, analysis }) => {
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
    ``,
    "## Reviewer Mapping Counts",
    reviewersSummary || "- (no analysis run)",
    ``,
  ].join("\n");
};

const fetchCommentsForAnalysis = ({
  db,
  repoSlug,
  since,
  until,
  limit,
  onlyNew,
}) => {
  const params = [];
  let where = "WHERE prs.repo = ?";
  params.push(repoSlug);

  if (since) {
    where += " AND prs.closed_at >= ?";
    params.push(since.toISOString());
  }
  if (until) {
    where += " AND prs.closed_at <= ?";
    params.push(until.toISOString());
  }

  let join = "";
  if (true === onlyNew) {
    join = "LEFT JOIN findings ON findings.comment_id = comments.id";
    where += " AND findings.id IS NULL";
  }

  let limitSql = "";
  if (null != limit) {
    limitSql = "LIMIT ?";
    params.push(limit);
  }

  const rows = db.prepare(`
    SELECT
      comments.id as comment_db_id,
      comments.gh_comment_id as comment_gh_id,
      comments.type,
      comments.author,
      comments.author_type,
      comments.body,
      comments.path,
      comments.line,
      comments.position,
      comments.original_line as original_line,
      comments.original_position as original_position,
      comments.diff_hunk as diff_hunk,
      comments.commit_id,
      comments.created_at as comment_created_at,
      prs.id as pr_id,
      prs.number as pr_number,
      prs.title as pr_title,
      prs.url as pr_url,
      prs.base_ref as pr_base,
      prs.head_ref as pr_head,
      prs.closed_at as pr_closed_at,
      prs.merged_at as pr_merged_at,
      prs.author as pr_author
    FROM comments
    INNER JOIN prs ON prs.id = comments.pr_id
    ${join}
    ${where}
    ORDER BY prs.closed_at DESC, comments.created_at ASC
    ${limitSql}
  `).all(...params);

  return rows;
};

const main = async () => {
  const repoRoot = getRepoRoot();
  loadEnv(repoRoot);

  const repoSlug = getArgValue("--repo") || "elegantthemes/submodule-builder-5";
  const since = parseDate(getArgValue("--since"));
  const until = parseDate(getArgValue("--until"));
  const limitValue = getArgValue("--limit");
  const limit = null == limitValue ? null : Number(limitValue);
  const onlyNew = false === hasArg("--all");
  const dbArg = getArgValue("--db");
  const dbContext = openDb({ repoRoot, dbPath: dbArg });
  const db = dbContext.db;
  const reviewers = loadReviewerNames(repoRoot);
  const analysisModel =
    getArgValue("--analysis-model") ||
    process.env.OPENAI_SUMMARY_MODEL ||
    "gpt-5.4-mini";
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
      `${new Date().toISOString().replace(/[:.]/g, "-")}_feedback_analyze`
    );

  if (true === Number.isNaN(limit)) {
    throw new Error("Invalid --limit value.");
  }

  console.log(`db: ${dbContext?.dbPath ?? getDefaultDbPath(repoRoot)}`);
  if (true === useNanoFilter) {
    console.log(`nano: ${nanoModel}`);
  }

  const rows = fetchCommentsForAnalysis({
    db,
    repoSlug,
    since,
    until,
    limit,
    onlyNew,
  });

  const grouped = new Map();
  const commentIdByGhId = new Map();

  rows.forEach((row) => {
    if (null != row.comment_gh_id) {
      commentIdByGhId.set(row.comment_gh_id, row.comment_db_id);
    }
    const existing = grouped.get(row.pr_id) || {
      pr: {
        id: row.pr_id,
        number: row.pr_number,
        title: row.pr_title,
        url: row.pr_url,
        base: row.pr_base,
        head: row.pr_head,
        closed_at: row.pr_closed_at,
        merged_at: row.pr_merged_at,
        author: row.pr_author,
      },
      comments: [],
    };
    existing.comments.push({
      id: row.comment_gh_id ?? row.comment_db_id,
      db_id: row.comment_db_id,
      pr_number: row.pr_number,
      type: row.type,
      author: row.author,
      author_type: row.author_type,
      created_at: row.comment_created_at,
      body: row.body,
      path: row.path,
      position: row.position,
      line: row.line,
      original_line: row.original_line,
      original_position: row.original_position,
      diff_hunk: row.diff_hunk,
      commit_id: row.commit_id,
    });
    grouped.set(row.pr_id, existing);
  });

  const analysisResults = [];
  const prs = [];

  const progressEvery = Number(getArgValue("--progress-every") || 10);
  let processed = 0;

  const client = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });
  let skippedByRegex = 0;
  let skippedByNano = 0;
  let keptForAnalysis = 0;

  for (const entry of grouped.values()) {
    if (0 === entry.comments.length) {
      continue;
    }
    const filteredComments = [];
    for (const comment of entry.comments) {
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
    prs.push(entry.pr);
    console.log(
      `analyze: pr #${entry.pr.number} (${filteredComments.length} comments)`
    );
    const analysisText = await analyzeComments({
      client,
      model: analysisModel,
      reviewers,
      pr: entry.pr,
      comments: filteredComments,
    });
    let analysisJson = null;
    try {
      analysisJson = JSON.parse(analysisText);
    } catch (error) {
      analysisJson = null;
    }

    if (analysisJson) {
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
      pr_number: entry.pr.number,
      analysis: analysisText,
      analysis_json: analysisJson,
    });

    processed += 1;
    if (0 < progressEvery && 0 === processed % progressEvery) {
      console.log(`progress: ${processed}/${grouped.size} analyzed`);
    }
  }

  writeJson(path.join(outputRoot, "run.json"), {
    repo: repoSlug,
    since: since ? since.toISOString() : null,
    until: until ? until.toISOString() : null,
    limit,
    prs: prs.length,
    db_path: dbContext?.dbPath ?? getDefaultDbPath(repoRoot),
    analysis_model: analysisModel,
    nano_model: useNanoFilter ? nanoModel : null,
    skipped_by_regex: skippedByRegex,
    skipped_by_nano: skippedByNano,
    kept_for_analysis: keptForAnalysis,
  });
  writeJson(path.join(outputRoot, "analysis.json"), analysisResults);
  writeText(
    path.join(outputRoot, "summary.md"),
    renderSummary({
      repoSlug,
      prs,
      analysis: analysisResults
        .map((entry) => entry.analysis_json)
        .filter(Boolean),
    })
  );

  const analysisCount = analysisResults.length;
  console.log(
    `done: ${outputRoot} (prs: ${prs.length}, analyses: ${analysisCount})`
  );
};

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
