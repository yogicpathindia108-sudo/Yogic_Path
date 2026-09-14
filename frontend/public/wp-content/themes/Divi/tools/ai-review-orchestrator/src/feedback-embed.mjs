import fs from "fs";
import path from "path";
import dotenv from "dotenv";
import OpenAI from "openai";
import { getDefaultDbPath, openDb, replaceCommentTags, updateCommentNormalization, updatePrBodySummary } from "./db.mjs";
import {
  buildEmbedDocument,
  buildNormalizePrompt,
  buildPrSummaryPrompt,
  buildTagRecords,
  coerceNormalization,
  derivePathTags,
  normalizeSchema,
  parseNormalizationJson,
  prSummarySchema,
  truncateText,
} from "./feedback-context.mjs";

const args = process.argv.slice(2);

const getArgValue = (name) => {
  const index = args.indexOf(name);
  if (-1 === index) {
    return null;
  }
  return args[index + 1] ?? null;
};

const hasArg = (name) => args.includes(name);

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

const SKIP_COMMENT_REGEX = [
  /deephive test failure summary/i,
  /deephive automated analysis/i,
  /\blgtm\b/i,
  /\bclos(e|ing)\s+(this|the)\s+pr\b/i,
  /\bfixed in\b/i,
  /\bi'?ve addressed\b/i,
  /\bfix eslint errors?\b/i,
  /\bfix snapshot\b/i,
  /^\s*(?:see\s+)?(?:\[[0-9a-f]{5,40}\]\([^)]+\)|https?:\/\/github\.com\/\S+)\s*$/i,
  /^\s*@[\w-]+\s+i have small feedback\.?\s*$/i,
];

const shouldSkipByRegex = (body) =>
  SKIP_COMMENT_REGEX.some((pattern) => pattern.test(body));

const EMBED_MAX_CHARS = 6000;

const getResponseText = (response) =>
  response.output_text ||
  response.output?.map((item) => item.content?.[0]?.text ?? "").join("\n") ||
  "";

const parseJsonSafe = (text) => {
  try {
    return JSON.parse(String(text || "").trim());
  } catch (error) {
    const match = String(text || "").match(/\{[\s\S]*\}/);
    if (null === match) {
      return null;
    }
    try {
      return JSON.parse(match[0]);
    } catch (innerError) {
      return null;
    }
  }
};

const createJsonSchemaFormat = (name, schema) => ({
  type: "json_schema",
  name,
  strict: true,
  schema,
});

const summarizePr = async ({ client, model, title, body }) => {
  try {
    const response = await client.responses.create({
      model,
      input: buildPrSummaryPrompt({ title, body }),
      text: {
        format: createJsonSchemaFormat("pr_summary", prSummarySchema),
      },
    });
    const parsed = parseJsonSafe(getResponseText(response));
    return String(parsed?.summary || "").trim();
  } catch (error) {
    console.warn(
      `pr summary failed (${title || "untitled"}): ${error.message || error}`
    );
    return "";
  }
};

const normalizeComment = async ({
  client,
  model,
  body,
  path: filePath,
  hunk,
  prTitle,
  prSummary,
}) => {
  try {
    const response = await client.responses.create({
      model,
      input: buildNormalizePrompt({
        body,
        path: filePath,
        hunk,
        prTitle,
        prSummary,
      }),
      text: {
        format: createJsonSchemaFormat("feedback_normalize", normalizeSchema),
      },
    });
    const parsed = parseJsonSafe(getResponseText(response));
    return coerceNormalization(parsed);
  } catch (error) {
    return coerceNormalization({
      keep: true,
      reason: "nano_parse_fallback",
      claim: "",
      kind: "correctness",
      signal: "medium",
      tags: [],
      reviewer_hint: "",
    });
  }
};

const persistNormalization = (db, { commentId, normalized, pathTags }) => {
  updateCommentNormalization(db, {
    id: commentId,
    normalized_claim: normalized.claim || null,
    normalized_kind: normalized.kind || null,
    normalized_signal: normalized.signal || null,
    reviewer_hint: normalized.reviewer_hint || null,
    normalized_json: JSON.stringify(normalized),
  });
  replaceCommentTags(db, {
    commentId,
    tags: buildTagRecords({
      pathTags,
      nanoTags: normalized.tags,
      kind: normalized.kind,
    }),
  });
};

const fetchComments = ({
  db,
  repoSlug,
  since,
  until,
  model,
  dimensions,
  onlyNew,
  limit,
}) => {
  const joinParams = [];
  const whereParams = [];
  let where = "WHERE prs.repo = ?";
  whereParams.push(repoSlug);

  if (since) {
    where += " AND prs.closed_at >= ?";
    whereParams.push(since.toISOString());
  }
  if (until) {
    where += " AND prs.closed_at <= ?";
    whereParams.push(until.toISOString());
  }

  let join = `
    LEFT JOIN comment_filters cf ON cf.comment_id = comments.id
  `;
  where += " AND (cf.status IS NULL OR cf.status != 'skipped')";
  if (true === onlyNew) {
    join += `
      LEFT JOIN comment_embeddings ce
        ON ce.comment_id = comments.id
       AND ce.model = ?
       AND ce.dimensions = ?
    `;
    joinParams.push(model, dimensions);
    where += " AND ce.id IS NULL";
  }

  let limitSql = "";
  const limitParams = [];
  if (null != limit) {
    limitSql = "LIMIT ?";
    limitParams.push(limit);
  }

  const params = [...joinParams, ...whereParams, ...limitParams];

  return db.prepare(`
    SELECT
      comments.id,
      comments.body,
      comments.path,
      comments.diff_hunk,
      comments.normalized_json,
      comments.created_at as comment_created_at,
      cf.status as filter_status,
      cf.reason as filter_reason,
      prs.id as pr_id,
      prs.number as pr_number,
      prs.title as pr_title,
      prs.body as pr_body,
      prs.body_summary as pr_body_summary,
      prs.url as pr_url
    FROM comments
    INNER JOIN prs ON prs.id = comments.pr_id
    ${join}
    ${where}
    ORDER BY prs.closed_at DESC, comments.created_at ASC
    ${limitSql}
  `).all(...params);
};

const upsertCommentFilter = (db, { commentId, status, reason, model }) => {
  db.prepare(`
    INSERT INTO comment_filters (
      comment_id,
      status,
      reason,
      model,
      created_at
    ) VALUES (
      @comment_id,
      @status,
      @reason,
      @model,
      @created_at
    )
    ON CONFLICT(comment_id) DO UPDATE SET
      status = excluded.status,
      reason = excluded.reason,
      model = excluded.model,
      created_at = excluded.created_at
  `).run({
    comment_id: commentId,
    status,
    reason,
    model,
    created_at: new Date().toISOString(),
  });
};

const upsertEmbedding = (db, { commentId, model, dimensions, embedding }) => {
  db.prepare(`
    INSERT INTO comment_embeddings (
      comment_id,
      model,
      dimensions,
      embedding,
      created_at
    ) VALUES (
      @comment_id,
      @model,
      @dimensions,
      @embedding,
      @created_at
    )
    ON CONFLICT(comment_id, model, dimensions) DO UPDATE SET
      embedding = excluded.embedding,
      created_at = excluded.created_at
  `).run({
    comment_id: commentId,
    model,
    dimensions,
    embedding,
    created_at: new Date().toISOString(),
  });
};

const toEmbeddingBlob = (embedding) => {
  const array = Float32Array.from(embedding);
  return Buffer.from(array.buffer);
};

const main = async () => {
  const repoRoot = getRepoRoot();
  loadEnv(repoRoot);

  const repoSlug = getArgValue("--repo") || "elegantthemes/submodule-builder-5";
  const since = parseDate(getArgValue("--since"));
  const until = parseDate(getArgValue("--until"));
  const model =
    getArgValue("--model") ||
    process.env.OPENAI_EMBEDDING_MODEL ||
    "text-embedding-3-small";
  const dimensionsValue = getArgValue("--dimensions");
  const dimensions =
    null == dimensionsValue ? null : Number(dimensionsValue);
  const onlyNew = false === hasArg("--all");
  const forceRenormalize = true === hasArg("--force-normalize");
  const limitValue = getArgValue("--limit");
  const limit = null == limitValue ? null : Number(limitValue);
  const progressEveryValue = getArgValue("--progress-every");
  const progressEvery = null == progressEveryValue
    ? 10
    : Number(progressEveryValue);
  const useNanoFilter = false === hasArg("--no-nano-filter");
  const nanoModel =
    getArgValue("--nano-model") ||
    process.env.OPENAI_NANO_MODEL ||
    "gpt-5-nano";
  const minCommentLengthValue = getArgValue("--min-comment-length");
  const minCommentLength =
    null == minCommentLengthValue ? 20 : Number(minCommentLengthValue);

  if (true === Number.isNaN(dimensions)) {
    throw new Error("Invalid --dimensions value.");
  }
  if (true === Number.isNaN(limit)) {
    throw new Error("Invalid --limit value.");
  }
  if (true === Number.isNaN(progressEvery)) {
    throw new Error("Invalid --progress-every value.");
  }
  if (true === Number.isNaN(minCommentLength)) {
    throw new Error("Invalid --min-comment-length value.");
  }

  const dbArg = getArgValue("--db");
  const dbContext = openDb({ repoRoot, dbPath: dbArg });
  const db = dbContext.db;

  console.log(`db: ${dbContext.dbPath ?? getDefaultDbPath(repoRoot)}`);
  console.log(`model: ${model}`);
  console.log(`nano: ${useNanoFilter ? nanoModel : "disabled"}`);
  console.log(`mode: ${true === onlyNew ? "new-only" : "re-embed --all"}`);
  console.log(`normalize: ${true === forceRenormalize ? "force" : "reuse cached claims"}`);

  const rows = fetchComments({
    db,
    repoSlug,
    since,
    until,
    model,
    dimensions,
    onlyNew,
    limit,
  });
  console.log(`comments: ${rows.length}`);

  const client = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });
  const prSummaryCache = new Map();
  let skippedByRegex = 0;
  let skippedByNano = 0;
  let skippedByLength = 0;
  let skippedByFilter = 0;
  let keptByFilter = 0;
  let normalized = 0;
  let reusedNormalization = 0;
  let summarizedPrs = 0;
  let embedded = 0;
  let failed = 0;
  let processed = 0;

  const resolvePrSummary = async (row) => {
    if (true === prSummaryCache.has(row.pr_id)) {
      return prSummaryCache.get(row.pr_id);
    }
    if (row.pr_body_summary && false === forceRenormalize) {
      prSummaryCache.set(row.pr_id, row.pr_body_summary);
      return row.pr_body_summary;
    }
    const body = String(row.pr_body || "").trim();
    if (body.length < 40) {
      const fallback = String(row.pr_title || "").trim();
      prSummaryCache.set(row.pr_id, fallback);
      return fallback;
    }
    const summary = await summarizePr({
      client,
      model: nanoModel,
      title: row.pr_title,
      body,
    });
    const resolved = summary || String(row.pr_title || "").trim();
    if (summary) {
      updatePrBodySummary(db, { prId: row.pr_id, bodySummary: summary });
      summarizedPrs += 1;
    }
    prSummaryCache.set(row.pr_id, resolved);
    return resolved;
  };

  for (const row of rows) {
    processed += 1;
    const body = row.body?.trim() ?? "";
    if ("skipped" === row.filter_status) {
      skippedByFilter += 1;
      continue;
    }
    if ("kept" === row.filter_status) {
      keptByFilter += 1;
    }
    if (body.length < minCommentLength) {
      skippedByLength += 1;
      upsertCommentFilter(db, {
        commentId: row.id,
        status: "skipped",
        reason: "min_length",
        model: useNanoFilter ? nanoModel : null,
      });
      continue;
    }
    if (true === shouldSkipByRegex(body)) {
      skippedByRegex += 1;
      upsertCommentFilter(db, {
        commentId: row.id,
        status: "skipped",
        reason: "regex",
        model: useNanoFilter ? nanoModel : null,
      });
      continue;
    }

    const pathTags = derivePathTags(row.path);
    let normalization = null;
    try {
      if (true === useNanoFilter) {
        const cached = false === forceRenormalize
          ? parseNormalizationJson(row.normalized_json)
          : null;
        if (cached) {
          normalization = cached;
          reusedNormalization += 1;
        } else {
          const prSummary = await resolvePrSummary(row);
          normalization = await normalizeComment({
            client,
            model: nanoModel,
            body,
            path: row.path,
            hunk: row.diff_hunk,
            prTitle: row.pr_title,
            prSummary,
          });
          persistNormalization(db, {
            commentId: row.id,
            normalized: normalization,
            pathTags,
          });
          normalized += 1;
        }
        if (false === normalization.keep) {
          skippedByNano += 1;
          upsertCommentFilter(db, {
            commentId: row.id,
            status: "skipped",
            reason: normalization.reason || "nano",
            model: nanoModel,
          });
        } else {
          upsertCommentFilter(db, {
            commentId: row.id,
            status: "kept",
            reason: normalization.reason || null,
            model: nanoModel,
          });
          if (cached) {
            persistNormalization(db, {
              commentId: row.id,
              normalized: normalization,
              pathTags,
            });
          }
        }
      } else {
        normalization = {
          keep: true,
          reason: "",
          claim: "",
          kind: "",
          signal: "",
          tags: [],
          reviewer_hint: "",
        };
        upsertCommentFilter(db, {
          commentId: row.id,
          status: "kept",
          reason: null,
          model: null,
        });
        replaceCommentTags(db, {
          commentId: row.id,
          tags: buildTagRecords({
            pathTags,
            nanoTags: [],
            kind: null,
          }),
        });
      }

      if (normalization && true === normalization.keep) {
        const embedInput = truncateText(
          buildEmbedDocument({
            claim: normalization.claim,
            hunk: row.diff_hunk,
            body,
          }),
          EMBED_MAX_CHARS
        );

        const embeddingResponse = await client.embeddings.create({
          model,
          input: embedInput,
          encoding_format: "float",
          ...(dimensions ? { dimensions } : {}),
        });
        const embedding = embeddingResponse?.data?.[0]?.embedding;
        if (!Array.isArray(embedding)) {
          throw new Error("Embedding response missing vector.");
        }
        const resolvedDimensions = dimensions ?? embedding.length;
        if (embedding.length !== resolvedDimensions) {
          throw new Error(
            `Embedding length ${embedding.length} does not match ${resolvedDimensions}.`
          );
        }
        upsertEmbedding(db, {
          commentId: row.id,
          model,
          dimensions: resolvedDimensions,
          embedding: toEmbeddingBlob(embedding),
        });
        embedded += 1;
      }
    } catch (error) {
      failed += 1;
      console.warn(
        `comment ${row.id} (PR #${row.pr_number}) failed: ${error.message || error}`
      );
    }
    if (0 < progressEvery && 0 === processed % progressEvery) {
      console.log(`progress: ${processed}/${rows.length} processed`);
    }
  }

  console.log(
    JSON.stringify(
      {
        repo: repoSlug,
        model,
        dimensions: dimensions ?? "default",
        processed,
        embedded,
        normalized,
        reused_normalization: reusedNormalization,
        summarized_prs: summarizedPrs,
        skipped_by_regex: skippedByRegex,
        skipped_by_nano: skippedByNano,
        skipped_by_length: skippedByLength,
        skipped_by_filter: skippedByFilter,
        kept_by_filter: keptByFilter,
        failed,
      },
      null,
      2
    )
  );
};

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
