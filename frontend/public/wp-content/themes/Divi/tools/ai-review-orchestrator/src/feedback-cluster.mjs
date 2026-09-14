import fs from "fs";
import path from "path";
import { getDefaultDbPath, loadVectorExtension, openDb, updateClusterTags } from "./db.mjs";

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

const fetchEmbeddings = ({ db, repoSlug, model, dimensions }) =>
  db.prepare(`
    SELECT
      ce.id as embedding_id,
      ce.comment_id,
      ce.embedding
    FROM comment_embeddings ce
    INNER JOIN comments c ON c.id = ce.comment_id
    INNER JOIN prs p ON p.id = c.pr_id
    LEFT JOIN comment_filters cf ON cf.comment_id = c.id
    WHERE p.repo = ?
      AND ce.model = ?
      AND ce.dimensions = ?
      AND (cf.status IS NULL OR cf.status = 'kept')
    ORDER BY ce.id ASC
  `).all(repoSlug, model, dimensions);

const resolveDimensions = ({ db, model, explicitDimensions }) => {
  if (null != explicitDimensions) {
    return explicitDimensions;
  }
  return db
    .prepare(
      `
      SELECT dimensions
      FROM comment_embeddings
      WHERE model = ?
      ORDER BY dimensions DESC
      LIMIT 1
    `
    )
    .get(model)?.dimensions ?? null;
};

const createClusterRun = (db, { repoSlug, model, dimensions, k, threshold, minMembers }) =>
  db.prepare(`
    INSERT INTO cluster_runs (
      repo,
      model,
      dimensions,
      k,
      threshold,
      min_members,
      created_at
    ) VALUES (
      @repo,
      @model,
      @dimensions,
      @k,
      @threshold,
      @min_members,
      @created_at
    )
  `).run({
    repo: repoSlug,
    model,
    dimensions,
    k,
    threshold,
    min_members: minMembers,
    created_at: new Date().toISOString(),
  }).lastInsertRowid;

const createCluster = (db, runId) =>
  db.prepare(`
    INSERT INTO clusters (
      run_id,
      created_at
    ) VALUES (
      @run_id,
      @created_at
    )
  `).run({
    run_id: runId,
    created_at: new Date().toISOString(),
  }).lastInsertRowid;

const addClusterMember = (db, { clusterId, commentId, distance }) => {
  db.prepare(`
    INSERT INTO cluster_members (
      cluster_id,
      comment_id,
      distance,
      created_at
    ) VALUES (
      @cluster_id,
      @comment_id,
      @distance,
      @created_at
    )
  `).run({
    cluster_id: clusterId,
    comment_id: commentId,
    distance,
    created_at: new Date().toISOString(),
  });
};

const rollupClusterTags = (db, clusterId) => {
  const rows = db
    .prepare(
      `
      SELECT
        ct.tag as tag,
        COUNT(*) as count
      FROM comment_tags ct
      INNER JOIN cluster_members cm ON cm.comment_id = ct.comment_id
      WHERE cm.cluster_id = ?
      GROUP BY ct.tag
      ORDER BY count DESC, ct.tag ASC
    `
    )
    .all(clusterId);
  updateClusterTags(db, {
    clusterId,
    tagsJson: JSON.stringify(rows),
  });
  return rows;
};

const fetchRunTagHistogram = (db, runId) =>
  db
    .prepare(
      `
      SELECT
        ct.tag as tag,
        COUNT(*) as count
      FROM comment_tags ct
      INNER JOIN cluster_members cm ON cm.comment_id = ct.comment_id
      INNER JOIN clusters cl ON cl.id = cm.cluster_id
      WHERE cl.run_id = ?
      GROUP BY ct.tag
      ORDER BY count DESC, ct.tag ASC
      LIMIT 20
    `
    )
    .all(runId);

const searchNeighbors = ({
  db,
  embedding,
  k,
  repoSlug,
  model,
  dimensions,
}) =>
  db.prepare(
    `
    SELECT
      e.comment_id as comment_id,
      v.distance as distance
    FROM vector_quantize_scan(
      'comment_embeddings',
      'embedding',
      ?,
      ?
    ) v
    INNER JOIN comment_embeddings e ON e.id = v.rowid
    INNER JOIN comments c ON c.id = e.comment_id
    INNER JOIN prs p ON p.id = c.pr_id
    LEFT JOIN comment_filters cf ON cf.comment_id = c.id
    WHERE p.repo = ?
      AND e.model = ?
      AND e.dimensions = ?
      AND (cf.status IS NULL OR cf.status = 'kept')
    ORDER BY v.distance ASC
  `
  ).all(embedding, BigInt(k), repoSlug, model, dimensions);

const main = () => {
  const repoRoot = getRepoRoot();
  const repoSlug = getArgValue("--repo") || "elegantthemes/submodule-builder-5";
  const model =
    getArgValue("--model") ||
    process.env.OPENAI_EMBEDDING_MODEL ||
    "text-embedding-3-small";
  const dimensionsValue = getArgValue("--dimensions");
  const explicitDimensions =
    null == dimensionsValue ? null : Number(dimensionsValue);
  const kValue = getArgValue("--k");
  const k = null == kValue ? 30 : Number(kValue);
  const thresholdValue = getArgValue("--threshold");
  const threshold = null == thresholdValue ? 0.2 : Number(thresholdValue);
  const minMembersValue = getArgValue("--min-members");
  const minMembers = null == minMembersValue ? 5 : Number(minMembersValue);
  const probeValue = getArgValue("--probe");
  const probeCount = null == probeValue ? 0 : Number(probeValue);
  const replaceRuns = true === hasArg("--replace");
  const dryRun = true === hasArg("--dry-run");
  const dbArg = getArgValue("--db");
  const extensionPath =
    getArgValue("--vector-extension") ||
    process.env.SQLITE_VECTOR_PATH ||
    null;
  const extensionEntrypoint =
    getArgValue("--vector-entrypoint") ||
    process.env.SQLITE_VECTOR_ENTRYPOINT ||
    null;
  const useQuantize = false === hasArg("--no-quantize");

  if (true === Number.isNaN(explicitDimensions)) {
    throw new Error("Invalid --dimensions value.");
  }
  if (true === Number.isNaN(k)) {
    throw new Error("Invalid --k value.");
  }
  if (false === Number.isInteger(k)) {
    throw new Error("--k must be an integer.");
  }
  if (true === Number.isNaN(threshold)) {
    throw new Error("Invalid --threshold value.");
  }
  if (true === Number.isNaN(minMembers)) {
    throw new Error("Invalid --min-members value.");
  }
  if (true === Number.isNaN(probeCount)) {
    throw new Error("Invalid --probe value.");
  }

  const dbContext = openDb({ repoRoot, dbPath: dbArg });
  const db = dbContext.db;
  console.log(`db: ${dbContext.dbPath ?? getDefaultDbPath(repoRoot)}`);

  const dimensions = resolveDimensions({
    db,
    model,
    explicitDimensions,
  });

  if (null == dimensions) {
    throw new Error("Could not resolve embedding dimensions.");
  }

  if (null == extensionPath) {
    throw new Error("Missing sqlite-vector extension path.");
  }

  loadVectorExtension(db, extensionPath, extensionEntrypoint);

  try {
    db.exec(
      `SELECT vector_init('comment_embeddings', 'embedding', 'type=FLOAT32,dimension=${dimensions}')`
    );
  } catch (error) {
    console.log("vector_init: skipped (already initialized or unsupported)");
  }

  if (true === useQuantize) {
    try {
      db.exec("SELECT vector_quantize('comment_embeddings', 'embedding')");
    } catch (error) {
      console.log("vector_quantize: skipped (already quantized or unsupported)");
    }
  }

  const embeddings = fetchEmbeddings({ db, repoSlug, model, dimensions });

  if (probeCount > 0) {
    const samples = embeddings.slice(0, probeCount);
    samples.forEach((entry) => {
      const neighbors = searchNeighbors({
        db,
        embedding: entry.embedding,
        k,
        repoSlug,
        model,
        dimensions,
      });
      const distances = neighbors.map((neighbor) => neighbor.distance);
      console.log(
        JSON.stringify(
          {
            comment_id: entry.comment_id,
            distances,
          },
          null,
          2
        )
      );
    });
    return;
  }

  if (true === replaceRuns && false === dryRun) {
    const runIds = db
      .prepare(
        `
        SELECT id
        FROM cluster_runs
        WHERE repo = ?
          AND model = ?
          AND dimensions = ?
      `
      )
      .all(repoSlug, model, dimensions)
      .map((row) => row.id);
    if (0 < runIds.length) {
      const runPlaceholders = runIds.map(() => "?").join(", ");
      const clusterIds = db
        .prepare(
          `
          SELECT id
          FROM clusters
          WHERE run_id IN (${runPlaceholders})
        `
        )
        .all(...runIds)
        .map((row) => row.id);
      if (0 < clusterIds.length) {
        const clusterPlaceholders = clusterIds.map(() => "?").join(", ");
        db.prepare(
          `DELETE FROM cluster_members WHERE cluster_id IN (${clusterPlaceholders})`
        ).run(...clusterIds);
        db.prepare(
          `DELETE FROM findings WHERE cluster_id IN (${clusterPlaceholders})`
        ).run(...clusterIds);
        db.prepare(
          `DELETE FROM clusters WHERE id IN (${clusterPlaceholders})`
        ).run(...clusterIds);
      }
      db.prepare(`DELETE FROM cluster_runs WHERE id IN (${runPlaceholders})`).run(
        ...runIds
      );
    }
  }

  const remaining = new Map();
  embeddings.forEach((entry) => {
    remaining.set(entry.comment_id, entry);
  });

  const runId = true === dryRun
    ? null
    : createClusterRun(db, {
        repoSlug,
        model,
        dimensions,
        k,
        threshold,
        minMembers,
      });

  let clustersCreated = 0;
  let membersAdded = 0;
  const clusterSizes = [];

  for (const entry of embeddings) {
    if (false === remaining.has(entry.comment_id)) {
      continue;
    }
    const neighbors = searchNeighbors({
      db,
      embedding: entry.embedding,
      k,
      repoSlug,
      model,
      dimensions,
    }).filter((neighbor) => neighbor.distance <= threshold);

    const eligible = neighbors.filter((neighbor) =>
      remaining.has(neighbor.comment_id)
    );

    if (eligible.length < minMembers) {
      remaining.delete(entry.comment_id);
      continue;
    }

    const clusterId = true === dryRun ? null : createCluster(db, runId);
    clustersCreated += 1;
    clusterSizes.push(eligible.length);

    eligible.forEach((neighbor) => {
      if (false === dryRun) {
        addClusterMember(db, {
          clusterId,
          commentId: neighbor.comment_id,
          distance: neighbor.distance,
        });
      }
      remaining.delete(neighbor.comment_id);
      membersAdded += 1;
    });
    if (false === dryRun) {
      rollupClusterTags(db, clusterId);
    }
  }

  const summarizeSizes = (sizes) => {
    if (0 === sizes.length) {
      return {
        min: 0,
        max: 0,
        avg: 0,
        p50: 0,
        p90: 0,
      };
    }
    const sorted = [...sizes].sort((a, b) => a - b);
    const sum = sorted.reduce((acc, value) => acc + value, 0);
    const percentile = (p) =>
      sorted[Math.min(sorted.length - 1, Math.floor(p * (sorted.length - 1)))];
    return {
      min: sorted[0],
      max: sorted[sorted.length - 1],
      avg: Number((sum / sorted.length).toFixed(2)),
      p50: percentile(0.5),
      p90: percentile(0.9),
    };
  };

  console.log(
    JSON.stringify(
      {
        repo: repoSlug,
        run_id: null == runId ? null : Number(runId),
        model,
        dimensions,
        k,
        threshold,
        min_members: minMembers,
        clusters: clustersCreated,
        members: membersAdded,
        dry_run: dryRun,
        cluster_sizes: summarizeSizes(clusterSizes),
        top_tags:
          false === dryRun && null != runId ? fetchRunTagHistogram(db, runId) : [],
      },
      null,
      2
    )
  );
};

main();
