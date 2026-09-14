import fs from "fs";
import path from "path";
import Database from "better-sqlite3";

const ensureDir = (dirPath) => {
  if (false === fs.existsSync(dirPath)) {
    fs.mkdirSync(dirPath, { recursive: true });
  }
};

const getDefaultDbPath = (repoRoot) =>
  path.join(repoRoot, "tools/ai-review-orchestrator/data/feedback.sqlite");

const ensureColumn = (db, { table, column, definition }) => {
  const info = db.prepare(`PRAGMA table_info(${table})`).all();
  const exists = info.some((entry) => entry.name === column);
  if (false === exists) {
    db.exec(`ALTER TABLE ${table} ADD COLUMN ${column} ${definition};`);
  }
};

const migrate = (db) => {
  db.exec(`
    CREATE TABLE IF NOT EXISTS prs (
      id INTEGER PRIMARY KEY,
      repo VARCHAR(255) NOT NULL,
      number INTEGER NOT NULL,
      title TEXT NOT NULL,
      url TEXT NOT NULL,
      author VARCHAR(255),
      base_ref VARCHAR(255),
      head_ref VARCHAR(255),
      merged_at DATETIME,
      closed_at DATETIME,
      created_at DATETIME,
      updated_at DATETIME,
      UNIQUE (repo, number)
    );

    CREATE TABLE IF NOT EXISTS comments (
      id INTEGER PRIMARY KEY,
      pr_id INTEGER NOT NULL,
      gh_comment_id BIGINT,
      type VARCHAR(64) NOT NULL,
      author VARCHAR(255),
      author_type VARCHAR(64),
      body TEXT NOT NULL,
      path TEXT,
      line INTEGER,
      position INTEGER,
      original_line INTEGER,
      original_position INTEGER,
      diff_hunk TEXT,
      commit_id VARCHAR(64),
      created_at DATETIME,
      hash VARCHAR(128) NOT NULL,
      FOREIGN KEY (pr_id) REFERENCES prs(id)
    );

    CREATE UNIQUE INDEX IF NOT EXISTS idx_comments_gh_id
      ON comments(gh_comment_id);
    CREATE UNIQUE INDEX IF NOT EXISTS idx_comments_hash
      ON comments(hash);
    CREATE INDEX IF NOT EXISTS idx_comments_pr
      ON comments(pr_id);

    CREATE TABLE IF NOT EXISTS findings (
      id INTEGER PRIMARY KEY,
      comment_id INTEGER,
      reviewer VARCHAR(255),
      patch_type VARCHAR(64),
      operation VARCHAR(16) NOT NULL,
      section_title VARCHAR(255),
      target_text TEXT,
      suggestion_text TEXT NOT NULL,
      raw_json TEXT,
      created_at DATETIME,
      FOREIGN KEY (comment_id) REFERENCES comments(id)
    );

    CREATE INDEX IF NOT EXISTS idx_findings_comment
      ON findings(comment_id);
    CREATE INDEX IF NOT EXISTS idx_findings_reviewer
      ON findings(reviewer);

    CREATE TABLE IF NOT EXISTS decisions (
      id INTEGER PRIMARY KEY,
      finding_id INTEGER NOT NULL,
      status VARCHAR(32) NOT NULL,
      rationale TEXT,
      final_text TEXT,
      decided_by VARCHAR(255),
      decided_at DATETIME,
      FOREIGN KEY (finding_id) REFERENCES findings(id)
    );

    CREATE INDEX IF NOT EXISTS idx_decisions_finding
      ON decisions(finding_id);
    CREATE INDEX IF NOT EXISTS idx_decisions_status
      ON decisions(status);

    CREATE TABLE IF NOT EXISTS patches (
      id INTEGER PRIMARY KEY,
      decision_id INTEGER NOT NULL,
      patch_type VARCHAR(64) NOT NULL,
      file_path TEXT NOT NULL,
      section_title VARCHAR(255),
      diff_text TEXT NOT NULL,
      applied_at DATETIME,
      FOREIGN KEY (decision_id) REFERENCES decisions(id)
    );

    CREATE INDEX IF NOT EXISTS idx_patches_decision
      ON patches(decision_id);
    CREATE INDEX IF NOT EXISTS idx_patches_file
      ON patches(file_path);

    CREATE TABLE IF NOT EXISTS comment_filters (
      id INTEGER PRIMARY KEY,
      comment_id INTEGER NOT NULL,
      status VARCHAR(32) NOT NULL,
      reason TEXT,
      model VARCHAR(255),
      created_at DATETIME,
      UNIQUE (comment_id),
      FOREIGN KEY (comment_id) REFERENCES comments(id)
    );

    CREATE INDEX IF NOT EXISTS idx_comment_filters_status
      ON comment_filters(status);

    CREATE TABLE IF NOT EXISTS comment_embeddings (
      id INTEGER PRIMARY KEY,
      comment_id INTEGER NOT NULL,
      model VARCHAR(255) NOT NULL,
      dimensions INTEGER NOT NULL,
      embedding BLOB NOT NULL,
      created_at DATETIME,
      UNIQUE (comment_id, model, dimensions),
      FOREIGN KEY (comment_id) REFERENCES comments(id)
    );

    CREATE INDEX IF NOT EXISTS idx_comment_embeddings_comment
      ON comment_embeddings(comment_id);
    CREATE INDEX IF NOT EXISTS idx_comment_embeddings_model
      ON comment_embeddings(model);

    CREATE TABLE IF NOT EXISTS cluster_runs (
      id INTEGER PRIMARY KEY,
      repo VARCHAR(255) NOT NULL,
      model VARCHAR(255) NOT NULL,
      dimensions INTEGER NOT NULL,
      k INTEGER NOT NULL,
      threshold REAL NOT NULL,
      min_members INTEGER NOT NULL,
      created_at DATETIME
    );

    CREATE TABLE IF NOT EXISTS clusters (
      id INTEGER PRIMARY KEY,
      run_id INTEGER NOT NULL,
      title TEXT,
      summary TEXT,
      reviewer VARCHAR(255),
      suggestion_json TEXT,
      created_at DATETIME,
      FOREIGN KEY (run_id) REFERENCES cluster_runs(id)
    );

    CREATE INDEX IF NOT EXISTS idx_clusters_run
      ON clusters(run_id);

    CREATE TABLE IF NOT EXISTS cluster_members (
      id INTEGER PRIMARY KEY,
      cluster_id INTEGER NOT NULL,
      comment_id INTEGER NOT NULL,
      distance REAL NOT NULL,
      created_at DATETIME,
      FOREIGN KEY (cluster_id) REFERENCES clusters(id),
      FOREIGN KEY (comment_id) REFERENCES comments(id)
    );

    CREATE INDEX IF NOT EXISTS idx_cluster_members_cluster
      ON cluster_members(cluster_id);
    CREATE INDEX IF NOT EXISTS idx_cluster_members_comment
      ON cluster_members(comment_id);

    CREATE TABLE IF NOT EXISTS comment_tags (
      id INTEGER PRIMARY KEY,
      comment_id INTEGER NOT NULL,
      tag VARCHAR(128) NOT NULL,
      source VARCHAR(32) NOT NULL,
      UNIQUE (comment_id, tag),
      FOREIGN KEY (comment_id) REFERENCES comments(id)
    );

    CREATE INDEX IF NOT EXISTS idx_comment_tags_comment
      ON comment_tags(comment_id);
    CREATE INDEX IF NOT EXISTS idx_comment_tags_tag
      ON comment_tags(tag);
  `);

  ensureColumn(db, {
    table: "findings",
    column: "patch_type",
    definition: "VARCHAR(64)",
  });
  ensureColumn(db, {
    table: "prs",
    column: "base_ref",
    definition: "VARCHAR(255)",
  });
  ensureColumn(db, {
    table: "prs",
    column: "head_ref",
    definition: "VARCHAR(255)",
  });
  ensureColumn(db, {
    table: "comments",
    column: "original_line",
    definition: "INTEGER",
  });
  ensureColumn(db, {
    table: "comments",
    column: "original_position",
    definition: "INTEGER",
  });
  ensureColumn(db, {
    table: "comments",
    column: "diff_hunk",
    definition: "TEXT",
  });
  ensureColumn(db, {
    table: "findings",
    column: "cluster_id",
    definition: "INTEGER",
  });
  ensureColumn(db, {
    table: "prs",
    column: "body",
    definition: "TEXT",
  });
  ensureColumn(db, {
    table: "prs",
    column: "body_summary",
    definition: "TEXT",
  });
  ensureColumn(db, {
    table: "comments",
    column: "normalized_claim",
    definition: "TEXT",
  });
  ensureColumn(db, {
    table: "comments",
    column: "normalized_kind",
    definition: "VARCHAR(64)",
  });
  ensureColumn(db, {
    table: "comments",
    column: "normalized_signal",
    definition: "VARCHAR(32)",
  });
  ensureColumn(db, {
    table: "comments",
    column: "reviewer_hint",
    definition: "VARCHAR(255)",
  });
  ensureColumn(db, {
    table: "comments",
    column: "normalized_json",
    definition: "TEXT",
  });
  ensureColumn(db, {
    table: "clusters",
    column: "tags_json",
    definition: "TEXT",
  });
};

const openDb = ({ repoRoot, dbPath }) => {
  const resolvedPath = dbPath || getDefaultDbPath(repoRoot);
  ensureDir(path.dirname(resolvedPath));
  const db = new Database(resolvedPath);
  db.pragma("foreign_keys = ON");
  migrate(db);
  return { db, dbPath: resolvedPath };
};

const loadVectorExtension = (db, extensionPath, entrypoint) => {
  if (null == extensionPath || "" === extensionPath) {
    return;
  }
  if (entrypoint) {
    db.loadExtension(extensionPath, entrypoint);
    return;
  }
  db.loadExtension(extensionPath);
};

const upsertPr = (db, pr) => {
  const payload = {
    ...pr,
    body: pr.body ?? null,
  };
  const stmt = db.prepare(`
    INSERT INTO prs (
      repo,
      number,
      title,
      url,
      author,
      base_ref,
      head_ref,
      merged_at,
      closed_at,
      created_at,
      updated_at,
      body
    ) VALUES (
      @repo,
      @number,
      @title,
      @url,
      @author,
      @base_ref,
      @head_ref,
      @merged_at,
      @closed_at,
      @created_at,
      @updated_at,
      @body
    )
    ON CONFLICT(repo, number) DO UPDATE SET
      title = excluded.title,
      url = excluded.url,
      author = excluded.author,
      base_ref = excluded.base_ref,
      head_ref = excluded.head_ref,
      merged_at = excluded.merged_at,
      closed_at = excluded.closed_at,
      created_at = excluded.created_at,
      updated_at = excluded.updated_at,
      body = COALESCE(excluded.body, prs.body)
  `);
  stmt.run(payload);
  return db
    .prepare("SELECT id FROM prs WHERE repo = ? AND number = ?")
    .get(payload.repo, payload.number)?.id;
};

const upsertComment = (db, comment) => {
  const payload = { ...comment };
  if (null != comment.gh_comment_id) {
    const stmt = db.prepare(`
      INSERT INTO comments (
        pr_id,
        gh_comment_id,
        type,
        author,
        author_type,
        body,
        path,
        line,
        position,
      original_line,
      original_position,
      diff_hunk,
        commit_id,
        created_at,
        hash
      ) VALUES (
        @pr_id,
        @gh_comment_id,
        @type,
        @author,
        @author_type,
        @body,
        @path,
        @line,
        @position,
      @original_line,
      @original_position,
      @diff_hunk,
        @commit_id,
        @created_at,
        @hash
      )
      ON CONFLICT(gh_comment_id) DO UPDATE SET
        body = excluded.body,
        path = excluded.path,
        line = excluded.line,
        position = excluded.position,
      original_line = excluded.original_line,
      original_position = excluded.original_position,
      diff_hunk = excluded.diff_hunk,
        commit_id = excluded.commit_id,
        created_at = excluded.created_at,
        hash = excluded.hash,
        author = excluded.author,
        author_type = excluded.author_type
    `);
    stmt.run(payload);
    return db
      .prepare("SELECT id FROM comments WHERE gh_comment_id = ?")
      .get(comment.gh_comment_id)?.id;
  }

  const stmt = db.prepare(`
    INSERT INTO comments (
      pr_id,
      gh_comment_id,
      type,
      author,
      author_type,
      body,
      path,
      line,
      position,
      original_line,
      original_position,
      diff_hunk,
      commit_id,
      created_at,
      hash
    ) VALUES (
      @pr_id,
      NULL,
      @type,
      @author,
      @author_type,
      @body,
      @path,
      @line,
      @position,
      @original_line,
      @original_position,
      @diff_hunk,
      @commit_id,
      @created_at,
      @hash
    )
    ON CONFLICT(hash) DO UPDATE SET
      body = excluded.body,
      path = excluded.path,
      line = excluded.line,
      position = excluded.position,
      original_line = excluded.original_line,
      original_position = excluded.original_position,
      diff_hunk = excluded.diff_hunk,
      commit_id = excluded.commit_id,
      created_at = excluded.created_at,
      author = excluded.author,
      author_type = excluded.author_type
  `);
  stmt.run(payload);
  return db
    .prepare("SELECT id FROM comments WHERE hash = ?")
    .get(comment.hash)?.id;
};

const updatePrBodySummary = (db, { prId, bodySummary }) => {
  db.prepare(
    `
      UPDATE prs
      SET body_summary = @body_summary
      WHERE id = @pr_id
    `
  ).run({
    pr_id: prId,
    body_summary: bodySummary,
  });
};

const updateCommentNormalization = (db, payload) => {
  db.prepare(
    `
      UPDATE comments
      SET
        normalized_claim = @normalized_claim,
        normalized_kind = @normalized_kind,
        normalized_signal = @normalized_signal,
        reviewer_hint = @reviewer_hint,
        normalized_json = @normalized_json
      WHERE id = @id
    `
  ).run(payload);
};

const replaceCommentTags = (db, { commentId, tags }) => {
  db.prepare("DELETE FROM comment_tags WHERE comment_id = ?").run(commentId);
  const insert = db.prepare(`
    INSERT INTO comment_tags (
      comment_id,
      tag,
      source
    ) VALUES (
      @comment_id,
      @tag,
      @source
    )
    ON CONFLICT(comment_id, tag) DO UPDATE SET
      source = excluded.source
  `);
  (tags || []).forEach((entry) => {
    if (null == entry?.tag || "" === String(entry.tag).trim()) {
      return;
    }
    insert.run({
      comment_id: commentId,
      tag: String(entry.tag).trim(),
      source: entry.source || "nano",
    });
  });
};

const updateClusterTags = (db, { clusterId, tagsJson }) => {
  db.prepare(
    `
      UPDATE clusters
      SET tags_json = @tags_json
      WHERE id = @cluster_id
    `
  ).run({
    cluster_id: clusterId,
    tags_json: tagsJson,
  });
};

const insertFinding = (db, finding) => {
  const stmt = db.prepare(`
    INSERT INTO findings (
      comment_id,
      cluster_id,
      reviewer,
      patch_type,
      operation,
      section_title,
      target_text,
      suggestion_text,
      raw_json,
      created_at
    ) VALUES (
      @comment_id,
      @cluster_id,
      @reviewer,
      @patch_type,
      @operation,
      @section_title,
      @target_text,
      @suggestion_text,
      @raw_json,
      @created_at
    )
  `);
  const result = stmt.run(finding);
  return result.lastInsertRowid;
};

const insertDecision = (db, decision) => {
  const stmt = db.prepare(`
    INSERT INTO decisions (
      finding_id,
      status,
      rationale,
      final_text,
      decided_by,
      decided_at
    ) VALUES (
      @finding_id,
      @status,
      @rationale,
      @final_text,
      @decided_by,
      @decided_at
    )
  `);
  const result = stmt.run(decision);
  return result.lastInsertRowid;
};

const insertPatch = (db, patch) => {
  const stmt = db.prepare(`
    INSERT INTO patches (
      decision_id,
      patch_type,
      file_path,
      section_title,
      diff_text,
      applied_at
    ) VALUES (
      @decision_id,
      @patch_type,
      @file_path,
      @section_title,
      @diff_text,
      @applied_at
    )
  `);
  const result = stmt.run(patch);
  return result.lastInsertRowid;
};

const findFindingId = (db, finder) => {
  const stmt = db.prepare(`
    SELECT id
    FROM findings
    WHERE reviewer = @reviewer
      AND COALESCE(patch_type, '') = @patch_type
      AND operation = @operation
      AND COALESCE(section_title, '') = @section_title
      AND COALESCE(target_text, '') = @target_text
      AND suggestion_text = @suggestion_text
    ORDER BY id DESC
    LIMIT 1
  `);
  return stmt.get(finder)?.id ?? null;
};

const getStats = (db) => {
  const tables = [
    "prs",
    "comments",
    "findings",
    "decisions",
    "patches",
    "comment_filters",
    "comment_embeddings",
    "cluster_runs",
    "clusters",
    "cluster_members",
    "comment_tags",
  ];
  const stats = {};
  tables.forEach((table) => {
    stats[table] = db.prepare(`SELECT COUNT(*) as count FROM ${table}`).get()
      ?.count;
  });
  return stats;
};

export {
  getDefaultDbPath,
  openDb,
  loadVectorExtension,
  upsertPr,
  upsertComment,
  updatePrBodySummary,
  updateCommentNormalization,
  replaceCommentTags,
  updateClusterTags,
  insertFinding,
  insertDecision,
  insertPatch,
  findFindingId,
  getStats,
};
