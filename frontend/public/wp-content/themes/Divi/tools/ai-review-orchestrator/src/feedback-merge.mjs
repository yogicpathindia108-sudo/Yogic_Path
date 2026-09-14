import fs from "fs";
import path from "path";
import readline from "readline";
import chalk from "chalk";
import dotenv from "dotenv";
import OpenAI from "openai";
import {
  findFindingId,
  getDefaultDbPath,
  insertFinding,
  insertDecision,
  insertPatch,
  openDb,
} from "./db.mjs";
import { trimDiffHunk } from "./feedback-context.mjs";

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

const readJson = (filePath) => {
  if (false === fs.existsSync(filePath)) {
    return null;
  }
  return JSON.parse(fs.readFileSync(filePath, "utf8"));
};

const writeText = (filePath, contents) => {
  ensureDir(path.dirname(filePath));
  fs.writeFileSync(filePath, contents, "utf8");
};

const loadEnv = (repoRoot) => {
  const envPath = path.join(repoRoot, "tools/ai-review-orchestrator/.env");
  if (true === fs.existsSync(envPath)) {
    dotenv.config({ path: envPath });
  }
};

const getRepoRoot = () => {
  const gitDir = path.resolve(process.cwd());
  let current = gitDir;
  while (current !== path.dirname(current)) {
    if (fs.existsSync(path.join(current, ".git"))) {
      return current;
    }
    current = path.dirname(current);
  }
  throw new Error("Could not locate git repo root.");
};

const listReviewerFiles = (repoRoot) => {
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
    .map((file) => ({
      name: file.replace(/\.md$/, ""),
      path: path.join(reviewersDir, file),
    }));
};

const selectReviewerFile = ({ reviewer, reviewerFiles }) =>
  reviewerFiles.find((entry) => entry.name === reviewer) ?? null;

const escapeRegExp = (value) =>
  value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

const findSectionBounds = ({ content, sectionTitle }) => {
  if (null == sectionTitle || "" === sectionTitle.trim()) {
    return null;
  }
  const lines = content.split("\n");
  const heading = sectionTitle.trim().toLowerCase();
  let startIndex = -1;
  let endIndex = lines.length;
  for (let index = 0; index < lines.length; index += 1) {
    const line = lines[index];
    if (line.startsWith("## ")) {
      const title = line.slice(3).trim().toLowerCase();
      if (title === heading) {
        startIndex = index;
        continue;
      }
      if (-1 !== startIndex) {
        endIndex = index;
        break;
      }
    }
  }
  if (-1 === startIndex) {
    return null;
  }
  return { startIndex, endIndex, lines };
};

const ensureSection = ({ content, sectionTitle }) => {
  if (null == sectionTitle || "" === sectionTitle.trim()) {
    return { content, bounds: null };
  }
  const bounds = findSectionBounds({ content, sectionTitle });
  if (bounds) {
    return { content, bounds };
  }
  const addition = [
    "",
    "",
    `## ${sectionTitle.trim()}`,
    "",
    "",
  ].join("\n");
  const updated = content.trimEnd() + addition;
  return {
    content: updated,
    bounds: findSectionBounds({ content: updated, sectionTitle }),
  };
};

const applySuggestion = ({
  filePath,
  suggestion,
  operation,
  sectionTitle,
  targetText,
  dryRun,
}) => {
  let content = fs.readFileSync(filePath, "utf8");
  const section = ensureSection({ content, sectionTitle });
  content = section.content;

  const target = targetText ? targetText.trim() : "";
  const addition = suggestion.trim();
  let updated = content;

  if ("replace" === operation && "" !== target) {
    if (section.bounds) {
      const { startIndex, endIndex, lines } = section.bounds;
      const block = lines.slice(startIndex, endIndex).join("\n");
      const replaced = block.replace(
        new RegExp(escapeRegExp(target)),
        addition
      );
      if (replaced !== block) {
        const updatedLines = [
          ...lines.slice(0, startIndex),
          ...replaced.split("\n"),
          ...lines.slice(endIndex),
        ];
        updated = updatedLines.join("\n");
      } else {
        const merged = `${block.trimEnd()}\n${addition}\n`;
        const updatedLines = [
          ...lines.slice(0, startIndex),
          ...merged.split("\n"),
          ...lines.slice(endIndex),
        ];
        updated = updatedLines.join("\n");
      }
    } else {
      const replaced = content.replace(
        new RegExp(escapeRegExp(target)),
        addition
      );
      updated = replaced === content
        ? `${content.trimEnd()}\n\n${addition}\n`
        : replaced;
    }
  } else if (section.bounds) {
    const { startIndex, endIndex, lines } = section.bounds;
    const block = lines.slice(startIndex, endIndex).join("\n");
    const merged = `${block.trimEnd()}\n${addition}\n`;
    const updatedLines = [
      ...lines.slice(0, startIndex),
      ...merged.split("\n"),
      ...lines.slice(endIndex),
    ];
    updated = updatedLines.join("\n");
  } else {
    updated = `${content.trimEnd()}\n\n${addition}\n`;
  }

  if (true === dryRun) {
    return { updated };
  }

  writeText(filePath, updated);
  return { updated: null };
};

const promptEdit = async ({ rl, question, initial }) =>
  new Promise((resolve) => {
    rl.question(`${question}\n> `, (answer) => {
      const trimmed = answer.trim();
      if ("" === trimmed) {
        resolve(initial);
      } else {
        resolve(trimmed);
      }
    });
  });

const promptAction = async ({ rl }) =>
  new Promise((resolve) => {
    rl.question(
      "Action: (a)ccept, (m)odify, (s)kip, (d)efer, (q)uit\n> ",
      (answer) => {
        resolve(answer.trim().toLowerCase());
      }
    );
  });

const promptText = async ({ rl, question }) =>
  new Promise((resolve) => {
    rl.question(`${question}\n> `, (answer) => {
      resolve(answer.trim());
    });
  });

const buildRewritePrompt = ({
  suggestionText,
  clusterSummary,
  issue,
  reviewer,
  userPrompt,
}) => [
    {
      role: "system",
      content: "You rewrite reviewer prompt suggestions for clarity and intent.",
    },
    {
      role: "user",
      content: [
        "Rewrite the suggestion using the user prompt and cluster context.",
        "Keep it concise and actionable. Return text only.",
        "",
        `Reviewer: ${reviewer || "unknown"}`,
        `Issue: ${issue || "(none)"}`,
        `Cluster summary: ${clusterSummary || "(none)"}`,
        "",
        "Original suggestion:",
        suggestionText || "(none)",
        "",
        "User prompt:",
        userPrompt || "(none)",
      ].join("\n"),
    },
  ];

const rewriteSuggestion = async ({
  client,
  model,
  suggestionText,
  clusterSummary,
  issue,
  reviewer,
  userPrompt,
}) => {
  if (null == suggestionText || "" === suggestionText.trim()) {
    return suggestionText;
  }
  const response = await client.responses.create({
    model,
    input: buildRewritePrompt({
      suggestionText,
      clusterSummary,
      issue,
      reviewer,
      userPrompt,
    }),
  });
  const outputText =
    response.output_text ||
    response.output?.map((item) => item.content?.[0]?.text ?? "").join("\n") ||
    "";
  return outputText.trim() || suggestionText;
};

const normalizeSuggestions = ({ analysis }) => {
  if (false === Array.isArray(analysis)) {
    return [];
  }
  const suggestions = [];
  analysis.forEach((entry) => {
    let parsed = entry.analysis_json ?? null;
    if (null == parsed) {
      try {
        parsed = JSON.parse(entry.analysis);
      } catch (error) {
        parsed = null;
      }
    }
    if (null == parsed) {
      return;
    }
    parsed.gap_summary?.forEach((gap) => {
      suggestions.push({
        type: "gap_summary",
        reviewer: gap.reviewer ?? "unknown",
        patch_type: gap.patch_type ?? "reviewer",
        issue: gap.issue ?? "",
        suggested_prompt_change: gap.suggested_prompt_change ?? "",
        section_title: gap.section_title ?? "",
        operation: gap.operation ?? "add",
        target_text: gap.target_text ?? "",
        finding_id: gap.finding_id ??
          (Array.isArray(gap.finding_ids) ? gap.finding_ids[0] : null),
      });
    });
    parsed.new_reviewer_suggestions?.forEach((gap) => {
      suggestions.push({
        type: "new_reviewer",
        reviewer: gap.name ?? "new-reviewer",
        patch_type: gap.patch_type ?? "reviewer",
        issue: gap.scope ?? "",
        suggested_prompt_change: gap.suggested_prompt ?? "",
        section_title: gap.section_title ?? "",
        operation: gap.operation ?? "add",
        target_text: gap.target_text ?? "",
        finding_id: gap.finding_id ??
          (Array.isArray(gap.finding_ids) ? gap.finding_ids[0] : null),
      });
    });
  });
  return suggestions;
};

const ensureFindingForDecision = (db, suggestion) => {
  if (null == db) {
    return null;
  }
  const existingId =
    suggestion.finding_id ||
    findFindingId(db, {
      reviewer: suggestion.reviewer ?? "",
      patch_type: suggestion.patch_type ?? "reviewer",
      operation: suggestion.operation ?? "add",
      section_title: suggestion.section_title ?? "",
      target_text: suggestion.target_text ?? "",
      suggestion_text: suggestion.suggested_prompt_change ?? "",
    });
  if (existingId) {
    return existingId;
  }

  const rawJson = JSON.stringify(
    {
      cluster_id: suggestion.cluster_id ?? null,
      reviewer: suggestion.reviewer ?? null,
      patch_type: suggestion.patch_type ?? null,
      issue: suggestion.issue ?? null,
      section_title: suggestion.section_title ?? null,
      operation: suggestion.operation ?? null,
      target_text: suggestion.target_text ?? null,
      suggestion_text: suggestion.suggested_prompt_change ?? null,
      rationale: suggestion.rationale ?? null,
      confidence: suggestion.confidence ?? null,
    },
    null,
    2
  );

  return insertFinding(db, {
    comment_id: null,
    cluster_id: suggestion.cluster_id ?? null,
    reviewer: suggestion.reviewer ?? "unknown",
    patch_type: suggestion.patch_type ?? "reviewer",
    operation: suggestion.operation ?? "add",
    section_title: suggestion.section_title ?? "",
    target_text: suggestion.target_text ?? "",
    suggestion_text: suggestion.suggested_prompt_change ?? "",
    raw_json: rawJson,
    created_at: new Date().toISOString(),
  });
};

// On-the-fly suggestion generation functions.

const fetchClustersForGeneration = (db, { runId, includeDecided }) =>
  db
    .prepare(
      `
      SELECT
        c.id as cluster_id,
        c.title as cluster_title,
        c.summary as cluster_summary,
        c.tags_json as cluster_tags
      FROM clusters c
      WHERE c.run_id = ?
      ${includeDecided ? "" : "AND NOT EXISTS (SELECT 1 FROM decisions d JOIN findings f ON f.id = d.finding_id WHERE f.cluster_id = c.id)"}
      ORDER BY c.id ASC
    `
    )
    .all(runId);

const fetchClusterMembersDetailed = (db, clusterId, limit) =>
  db
    .prepare(
      `
      SELECT
        cm.distance,
        c.id as comment_id,
        c.author,
        c.type,
        c.path,
        c.created_at,
        c.body,
        c.diff_hunk,
        c.normalized_claim,
        c.normalized_kind,
        c.normalized_signal,
        c.reviewer_hint,
        p.number as pr_number,
        p.title as pr_title,
        p.body_summary as pr_summary,
        (
          SELECT GROUP_CONCAT(tag, ', ')
          FROM comment_tags
          WHERE comment_id = c.id
        ) as tags
      FROM cluster_members cm
      INNER JOIN comments c ON c.id = cm.comment_id
      INNER JOIN prs p ON p.id = c.pr_id
      WHERE cm.cluster_id = ?
      ORDER BY cm.distance ASC
      LIMIT ?
    `
    )
    .all(clusterId, limit);

const formatClusterTags = (tagsJson) => {
  if (null == tagsJson || "" === String(tagsJson).trim()) {
    return [];
  }
  try {
    const parsed = JSON.parse(tagsJson);
    if (false === Array.isArray(parsed)) {
      return [];
    }
    return parsed
      .map((entry) => {
        if (null == entry?.tag) {
          return null;
        }
        return `${entry.tag}${entry.count ? ` (${entry.count})` : ""}`;
      })
      .filter(Boolean);
  } catch (error) {
    return [];
  }
};

const readReviewerContent = (filePath) => {
  if (false === fs.existsSync(filePath)) {
    return { frontmatter: null, body: "", full: "" };
  }
  const full = fs.readFileSync(filePath, "utf8");

  // Parse frontmatter between --- delimiters.
  const frontmatterMatch = full.match(/^---\n([\s\S]*?)\n---\n([\s\S]*)$/);
  if (frontmatterMatch) {
    return {
      frontmatter: frontmatterMatch[1],
      body: frontmatterMatch[2].trim(),
      full,
    };
  }
  return { frontmatter: null, body: full.trim(), full };
};

const loadAllReviewerContents = (repoRoot) => {
  const reviewerFiles = listReviewerFiles(repoRoot);
  const contents = {};
  for (const entry of reviewerFiles) {
    contents[entry.name] = readReviewerContent(entry.path);
  }
  return contents;
};

const buildOnTheFlyPrompt = ({
  clusterId,
  clusterTitle,
  clusterSummary,
  clusterTags,
  members,
  reviewerContents,
  targetReviewer,
}) => [
    {
      role: "system",
      content:
        "You analyze clustered PR feedback and generate specific, actionable reviewer guidance updates.",
    },
    {
      role: "user",
      content: [
        "Given the clustered PR feedback and the current reviewer file contents, generate a specific suggestion for updating a reviewer.",
        "Feel free to use the cluster summary to help you generate the suggestion.",
        "Feel free, based on the cluster summary, to ignore any comments that are not relevant to the cluster, with the goal of making the suggestion more focused and actionable,",
        "so it's not tainting the suggestion with irrelevant comments that will be distracting to the reviewer or diluting the suggestion's focus.",
        "You can also feel free to not make a suggestion if the cluster summary is not clear or if the comments are not relevant or arent worth locking into the reviewer file.",
        "You can also feel free to NOT make a suggestion if the reviewer file is already up to date and doesnt need any updates, perhaps its good enough as is, and there are no gaps or issues to address.",
        "",
        "## Cluster Information",
        `Cluster ID: ${clusterId}`,
        clusterTitle ? `Cluster theme: ${clusterTitle}` : "",
        clusterSummary ? `Cluster summary: ${clusterSummary}` : "",
        clusterTags && clusterTags.length
          ? `Cluster tags: ${clusterTags.join(", ")}`
          : "",
        "",
        "## Cluster Members (normalized feedback)",
        JSON.stringify(
          members.map((m) => ({
            claim: m.normalized_claim || null,
            kind: m.normalized_kind || null,
            signal: m.normalized_signal || null,
            tags: m.tags || null,
            reviewer_hint: m.reviewer_hint || null,
            path: m.path,
            hunk: trimDiffHunk(m.diff_hunk, 20) || null,
            pr: `#${m.pr_number} ${m.pr_title}`,
            pr_summary: m.pr_summary || null,
            body: m.body?.slice(0, 400),
            distance: m.distance,
          })),
          null,
          2
        ),
        "",
        "## Current Reviewer Files",
        Object.entries(reviewerContents)
          .map(([name, content]) => {
            const bodyPreview =
              content.body.slice(0, 800) +
              (content.body.length > 800 ? "..." : "");
            return `\n### ${name}\n${bodyPreview}`;
          })
          .join("\n"),
        "",
        "## Task",
        targetReviewer
          ? `Generate a specific suggestion for the "${targetReviewer}" reviewer based on the feedback cluster.`
          : "Analyze the feedback cluster and determine which reviewer(s) should be updated, then generate a specific suggestion.",
        "",
        "Return JSON only:",
        "{",
        '  "reviewer": string, // Which reviewer file to update',
        '  "patch_type": "reviewer" | "rule" | "docs" | "other",',
        '  "section_title": string, // Section to add to or create',
        '  "operation": "add" | "replace",',
        '  "target_text": string | null, // If replace, what to replace',
        '  "suggestion_text": string, // The actual content to add/replace',
        '  "rationale": string, // Brief explanation of why this addresses the cluster',
        '  "confidence": number // 0-1 confidence this is the right change',
        "}",
        "",
        "Rules:",
        "- Prefer clusters that are both common (repeated claim/tags) and high-signal (signal=high, kind=security/correctness).",
        "- Use CLAIM + TAGS as the pattern to teach. Do not lock unique PR titles, module names, or issue numbers into reviewer files.",
        "- Low-signal nits and process comments should usually produce no suggestion.",
        "- suggestion_text should be specific, actionable guidance a reviewer can follow.",
        "- Write suggestion_text in the same voice as the reviewer files:",
        "- Imperative reviewer guidance (e.g., \"Check that...\", \"Verify...\", \"Flag...\", \"Avoid...\").",
        "- Do NOT address the reviewer as \"you\" or \"require reviewers to\".",
        "- Use short bullet-style lines aligned with existing reviewer phrasing.",
        "- Use separate lines for each bullet point, dont combine multiple points into a single line.",
        "- Avoid copy-pasting comment text verbatim; generalize the pattern.",
        "- If the feedback indicates a gap in an existing section, suggest an add operation.",
        "- If the feedback indicates something wrong/outdated, suggest a replace operation.",
        '  - patch_type: "reviewer" for reviewer guidance, "rule" for coding rules, "docs" for documentation.',
        "- Be concise but complete. Focus on the pattern, not individual PR details.",
      ]
        .filter(Boolean)
        .join("\n"),
    },
  ];

const generateOnTheFlySuggestion = async ({
  client,
  model,
  clusterId,
  clusterTitle,
  clusterSummary,
  clusterTags,
  members,
  reviewerContents,
  targetReviewer,
}) => {
  const response = await client.responses.create({
    model,
    input: buildOnTheFlyPrompt({
      clusterId,
      clusterTitle,
      clusterSummary,
      clusterTags,
      members,
      reviewerContents,
      targetReviewer,
    }),
  });
  const outputText =
    response.output_text ||
    response.output?.map((item) => item.content?.[0]?.text ?? "").join("\n") ||
    "";

  try {
    const parsed = JSON.parse(outputText.trim());
    return {
      reviewer: parsed.reviewer ?? targetReviewer ?? "unknown",
      patch_type: parsed.patch_type ?? "reviewer",
      section_title: parsed.section_title ?? "",
      operation: parsed.operation ?? "add",
      target_text: parsed.target_text ?? null,
      suggestion_text: parsed.suggestion_text ?? "",
      rationale: parsed.rationale ?? "",
      confidence: parsed.confidence ?? 0.5,
    };
  } catch (error) {
    // If JSON parsing fails, return null to indicate failure.
    return null;
  }
};

const main = async () => {
  const repoRoot = getRepoRoot();
  const outputDir = getArgValue("--output-dir");
  const fromDb = hasArg("--from-db");
  const generateSuggestions = hasArg("--generate-suggestions");
  const clusterRunIdValue = getArgValue("--cluster-run-id");
  const includeDecided = hasArg("--include-decided");
  const dryRun = hasArg("--dry-run");
  const createNew = hasArg("--create-new");
  const rewriteAi = hasArg("--rewrite-ai");
  let rewritePromptArg = getArgValue("--rewrite-prompt");
  const logRewritePrompt = hasArg("--log-rewrite-prompt");
  const useDb = false === hasArg("--no-db");
  const dbArg = getArgValue("--db");
  const decidedBy =
    getArgValue("--decided-by") || process.env.USER || "unknown";
  const generationModel =
    getArgValue("--generation-model") ||
    process.env.OPENAI_SUMMARY_MODEL ||
    "gpt-5.4-mini";
  const maxCommentsValue = getArgValue("--max-comments");
  const maxComments = null == maxCommentsValue ? 15 : Number(maxCommentsValue);
  const dbContext = useDb ? openDb({ repoRoot, dbPath: dbArg }) : null;
  const db = dbContext?.db ?? null;
  const analysisPath = getArgValue("--analysis") ||
    (outputDir ? path.join(outputDir, "analysis.json") : null);
  const clusterRunId = null == clusterRunIdValue ? null : Number(clusterRunIdValue);

  if (true === Number.isNaN(maxComments)) {
    throw new Error("Invalid --max-comments value.");
  }

  // Validate mode combinations.
  if (true === fromDb && false === useDb) {
    throw new Error("--from-db requires DB access (remove --no-db).");
  }
  if (true === generateSuggestions && false === useDb) {
    throw new Error("--generate-suggestions requires DB access (remove --no-db).");
  }
  if (true === generateSuggestions && null == clusterRunId) {
    throw new Error("--generate-suggestions requires --cluster-run-id.");
  }
  if (true === fromDb && null == clusterRunId) {
    throw new Error("Missing --cluster-run-id value.");
  }
  if (true === fromDb && true === Number.isNaN(clusterRunId)) {
    throw new Error("Invalid --cluster-run-id value.");
  }

  const analysis = (true === fromDb || true === generateSuggestions)
    ? null
    : (analysisPath ? readJson(analysisPath) : null);
  if (false === fromDb && false === generateSuggestions && null == analysis) {
    throw new Error("Missing or invalid analysis.json.");
  }

  if (true === useDb) {
    console.log(`db: ${dbContext?.dbPath ?? getDefaultDbPath(repoRoot)}`);
  }

  const reviewerFiles = listReviewerFiles(repoRoot);

  // Load suggestions based on mode.
  let suggestions = [];

  if (true === generateSuggestions) {
    // On-the-fly generation mode: we'll generate suggestions interactively.
    // We just need the cluster list here; suggestions are generated in the loop.
    if (null == db) {
      throw new Error("DB required for --generate-suggestions.");
    }

    loadEnv(repoRoot);
    if (null == process.env.OPENAI_API_KEY || "" === process.env.OPENAI_API_KEY) {
      throw new Error("Missing OPENAI_API_KEY (required for --generate-suggestions).");
    }

    const clusters = fetchClustersForGeneration(db, {
      runId: clusterRunId,
      includeDecided,
    });

    // Convert clusters to placeholder suggestions (actual content generated on-the-fly).
    suggestions = clusters.map((cluster) => ({
      type: "generated",
      reviewer: null, // Will be determined by AI.
      patch_type: "reviewer",
      issue: cluster.cluster_title ?? "",
      suggested_prompt_change: null, // Generated on-the-fly.
      section_title: "",
      operation: "add",
      target_text: null,
      finding_id: null,
      cluster_id: cluster.cluster_id,
      cluster_summary: cluster.cluster_summary ?? "",
      cluster_title: cluster.cluster_title ?? "",
      cluster_tags: formatClusterTags(cluster.cluster_tags),
      _generationMode: true,
    }));
  } else if (true === fromDb) {
    suggestions = db
      .prepare(
        `
        SELECT
          f.id as finding_id,
          f.reviewer,
          f.patch_type,
          f.operation,
          f.section_title,
          f.target_text,
          f.suggestion_text,
          f.cluster_id,
          c.title as cluster_title,
          c.summary as cluster_summary
        FROM findings f
        INNER JOIN clusters c ON c.id = f.cluster_id
        ${includeDecided ? "" : "LEFT JOIN decisions d ON d.finding_id = f.id"}
        WHERE c.run_id = ?
          AND f.cluster_id IS NOT NULL
          ${includeDecided ? "" : "AND d.id IS NULL"}
        ORDER BY c.id ASC, f.id ASC
      `
      )
      .all(clusterRunId)
      .map((row) => ({
        type: "cluster",
        reviewer: row.reviewer ?? "unknown",
        patch_type: row.patch_type ?? "reviewer",
        issue: row.cluster_title ?? "",
        suggested_prompt_change: row.suggestion_text ?? "",
        section_title: row.section_title ?? "",
        operation: row.operation ?? "add",
        target_text: row.target_text ?? "",
        finding_id: row.finding_id,
        cluster_id: row.cluster_id,
        cluster_summary: row.cluster_summary ?? "",
      }));
  } else {
    suggestions = normalizeSuggestions({ analysis });
  }

  if (0 === suggestions.length) {
    console.log("No merge suggestions found.");
    return;
  }

  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout,
  });

  let generationClient = null;
  let rewriteClient = null;
  let rewriteModel = getArgValue("--rewrite-model");

  if (true === generateSuggestions) {
    generationClient = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });
  }

  if (true === rewriteAi) {
    loadEnv(repoRoot);
    if (null == process.env.OPENAI_API_KEY || "" === process.env.OPENAI_API_KEY) {
      throw new Error("Missing OPENAI_API_KEY (required for --rewrite-ai).");
    }
    if (null == rewriteModel || "" === rewriteModel) {
      rewriteModel = process.env.OPENAI_SUMMARY_MODEL || "gpt-5.4-mini";
    }
    rewriteClient = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });
  }

  // Pre-load all reviewer contents for generation mode.
  let reviewerContents = {};
  if (true === generateSuggestions) {
    reviewerContents = loadAllReviewerContents(repoRoot);
  }

  const separator = chalk.dim(
    "-".repeat(Math.max(40, Math.min(process.stdout.columns ?? 80, 120)))
  );

  for (let index = 0; index < suggestions.length; index += 1) {
    const baseSuggestion = suggestions[index];

    // Generate suggestion on-the-fly if in generation mode.
    let suggestion = baseSuggestion;
    if (true === baseSuggestion._generationMode && db) {
      console.log(chalk.cyan(`\nGenerating suggestion for cluster ${baseSuggestion.cluster_id}...`));

      const members = fetchClusterMembersDetailed(db, baseSuggestion.cluster_id, maxComments);
      if (0 === members.length) {
        console.log(chalk.yellow("No members found for this cluster, skipping."));
        continue;
      }

      console.log(chalk.dim(`Cluster has ${members.length} member comments`));
      console.log(chalk.dim(`Cluster theme: ${baseSuggestion.cluster_title || "(none)"}`));
      if (baseSuggestion.cluster_tags && 0 < baseSuggestion.cluster_tags.length) {
        console.log(chalk.dim(`Cluster tags: ${baseSuggestion.cluster_tags.join(", ")}`));
      }

      const generated = await generateOnTheFlySuggestion({
        client: generationClient,
        model: generationModel,
        clusterId: baseSuggestion.cluster_id,
        clusterTitle: baseSuggestion.cluster_title,
        clusterSummary: baseSuggestion.cluster_summary,
        clusterTags: baseSuggestion.cluster_tags,
        members,
        reviewerContents,
        targetReviewer: null, // Let AI decide.
      });

      if (null === generated) {
        console.log(chalk.yellow("Failed to generate suggestion for this cluster."));
        const skipAction = await promptText({
          rl,
          question: "Skip this cluster? (y/n, default: y)",
        });
        if ("n" === skipAction.toLowerCase()) {
          // Allow manual entry as fallback.
          suggestion = {
            ...baseSuggestion,
            reviewer: await promptText({ rl, question: "Reviewer name:" }),
            suggested_prompt_change: await promptText({ rl, question: "Suggestion text:" }),
            section_title: await promptText({ rl, question: "Section title:" }),
            operation: (await promptText({ rl, question: "Operation (add/replace, default: add):" })) || "add",
          };
        } else {
          continue;
        }
      } else {
        suggestion = {
          ...baseSuggestion,
          reviewer: generated.reviewer,
          patch_type: generated.patch_type,
          section_title: generated.section_title,
          operation: generated.operation,
          target_text: generated.target_text,
          suggested_prompt_change: generated.suggestion_text,
          rationale: generated.rationale,
          confidence: generated.confidence,
        };
        console.log(chalk.green(`Generated suggestion (confidence: ${generated.confidence})`));
      }
    }

    if (0 !== index) {
      console.log(`\n${separator}\n`);
    }

    const summary = [
      `${chalk.bold("Type:")} ${suggestion.type}`,
      `${chalk.bold("Reviewer:")} ${suggestion.reviewer || "(to be determined)"}`,
      `${chalk.bold("Patch type:")} ${suggestion.patch_type || "reviewer"}`,
      `${chalk.bold("Issue/Theme:")} ${suggestion.issue || "(none)"}`,
      `${chalk.bold("Cluster:")} ${suggestion.cluster_id ?? "(none)"}`,
      suggestion.cluster_tags && 0 < suggestion.cluster_tags.length
        ? `${chalk.bold("Tags:")} ${suggestion.cluster_tags.join(", ")}`
        : null,
      suggestion.rationale
        ? `${chalk.bold("Rationale:")} ${suggestion.rationale}`
        : null,
      suggestion.confidence
        ? `${chalk.bold("Confidence:")} ${suggestion.confidence}`
        : null,
      `${chalk.bold("Section:")} ${suggestion.section_title || "(none)"}`,
      `${chalk.bold("Operation:")} ${suggestion.operation || "add"}`,
      `${chalk.bold("Target:")} ${suggestion.target_text || "(none)"}`,
      `${chalk.bold("Suggestion:")} ${suggestion.suggested_prompt_change || "(none)"}`,
      suggestion.cluster_summary
        ? `${chalk.bold("Cluster summary:")} ${suggestion.cluster_summary}`
        : null,
    ].filter(Boolean).join("\n");
    console.log(summary);
    console.log("");

    const action = await promptAction({ rl });
    if ("q" === action) {
      break;
    }
    if ("s" === action || "d" === action) {
      const reason = await promptText({
        rl,
        question: "Reason (optional):",
      });
      if (db && false === dryRun) {
        const findingId = ensureFindingForDecision(db, suggestion);
        if (findingId) {
          insertDecision(db, {
            finding_id: findingId,
            status: "s" === action ? "skip" : "defer",
            rationale: reason || null,
            final_text: null,
            decided_by: decidedBy,
            decided_at: new Date().toISOString(),
          });
        }
      }
      continue;
    }

    let rewriteText = suggestion.suggested_prompt_change;
    if (true === rewriteAi) {
      const previewLines = (text) => {
        const lines = (text || "").trim().split("\n").filter(Boolean);
        return lines.length ? `\n${lines.join("\n")}\n` : "\n(none)\n";
      };
      let shouldRewrite = true;
      while (true === shouldRewrite) {
        const rewritePrompt = await promptEdit({
          rl,
          question: "Rewrite prompt (leave blank to accept current text):",
          initial: rewritePromptArg || "",
        });
        if (!rewritePrompt) {
          break;
        }
        try {
          if (true === logRewritePrompt) {
            const rewriteInput = buildRewritePrompt({
              suggestionText: rewriteText,
              clusterSummary: suggestion.cluster_summary,
              issue: suggestion.issue,
              reviewer: suggestion.reviewer,
              userPrompt: rewritePrompt,
            });
            console.log(
              chalk.dim("Rewrite input prompt:") +
              `\n${JSON.stringify(rewriteInput, null, 2)}\n`
            );
          }
          rewriteText = await rewriteSuggestion({
            client: rewriteClient,
            model: rewriteModel,
            suggestionText: rewriteText,
            clusterSummary: suggestion.cluster_summary,
            issue: suggestion.issue,
            reviewer: suggestion.reviewer,
            userPrompt: rewritePrompt,
          });
          console.log(chalk.dim("Rewrite preview:") + previewLines(rewriteText));
        } catch (error) {
          console.log(chalk.yellow("rewrite: failed, keeping last suggestion"));
        }
        rewritePromptArg = null;
      }
    }

    const edited = await promptEdit({
      rl,
      question: "Edit text (leave blank to keep as-is):",
      initial: rewriteText,
    });

    let reviewerFile = selectReviewerFile({
      reviewer: suggestion.reviewer,
      reviewerFiles,
    });

    if (null == reviewerFile && true === createNew) {
      const reviewersDir = path.join(
        repoRoot,
        "tools/ai-review-orchestrator/reviewers"
      );
      const newFilePath = path.join(
        reviewersDir,
        `${suggestion.reviewer}.md`
      );
      writeText(
        newFilePath,
        [
          `# ${suggestion.reviewer}`,
          "",
          "## Scope",
          "",
          suggestion.issue || "TODO",
          "",
          "## Review Guidance",
          "",
        ].join("\n")
      );
      reviewerFile = { name: suggestion.reviewer, path: newFilePath };
      reviewerFiles.push(reviewerFile);
      console.log(`Created reviewer: ${newFilePath}`);
    }

    if (null == reviewerFile) {
      console.log(`Reviewer not found: ${suggestion.reviewer}`);
      continue;
    }

    const result = applySuggestion({
      filePath: reviewerFile.path,
      suggestion: edited,
      operation: suggestion.operation,
      sectionTitle: suggestion.section_title,
      targetText: suggestion.target_text,
      dryRun,
    });

    if (true === dryRun) {
      console.log(`(dry-run) Would update ${reviewerFile.path}`);
      console.log(result.updated.slice(-600));
    } else {
      console.log(`Updated ${reviewerFile.path}`);
    }

    if (db && false === dryRun) {
      const findingId = ensureFindingForDecision(db, suggestion);
      if (findingId) {
        const decisionId = insertDecision(db, {
          finding_id: findingId,
          status: "m" === action ? "modify" : "accept",
          rationale: null,
          final_text: edited,
          decided_by: decidedBy,
          decided_at: new Date().toISOString(),
        });
        insertPatch(db, {
          decision_id: decisionId,
          patch_type: suggestion.patch_type ?? "reviewer",
          file_path: reviewerFile.path,
          section_title: suggestion.section_title ?? null,
          diff_text: JSON.stringify(
            {
              operation: suggestion.operation ?? "add",
              target_text: suggestion.target_text ?? null,
              final_text: edited,
            },
            null,
            2
          ),
          applied_at: new Date().toISOString(),
        });
      }
    }
  }

  rl.close();
};

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
