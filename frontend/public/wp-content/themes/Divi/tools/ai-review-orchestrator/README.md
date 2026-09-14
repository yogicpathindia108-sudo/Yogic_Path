# AI Review Orchestrator (Local)

Node-based orchestrator that uses LangGraph for control flow and spawns
Cursor CLI reviewers in parallel. It uses native git commands and writes
structured output artifacts for each run.

## Setup

```bash
cd tools/ai-review-orchestrator
npm install
```

## Usage

Working tree:

```bash
node src/index.mjs --mode working-tree
```

Branch compare:

```bash
node src/index.mjs --mode branch-compare --base release/2026.01 --head HEAD
```

PR compare (uses gh):

```bash
node src/index.mjs --mode pr-compare --repo includes/builder-5 --pr 8082
```

Auto-discover related PRs from an issue linked in the PR body (default on):

```bash
node src/index.mjs --mode pr-compare --repo includes/builder-5 --pr 8082
```

Disable related PR discovery:

```bash
node src/index.mjs --mode pr-compare --repo includes/builder-5 --pr 8082 --no-related-prs
```

Explicit related PRs (merged into review context):

```bash
node src/index.mjs --mode pr-compare --repo includes/builder-5 --pr 8082 \
  --related-pr elegantthemes/submodule-builder-5#8813 \
  --related-prs elegantthemes/submodule-builder#10675
```

Timeout override (milliseconds):

```bash
node src/index.mjs --mode pr-compare --pr 8082 --repo includes/builder-5 --timeout-ms 900000
```

Preflight gate (default on for `pr-compare`):

```bash
node src/index.mjs --mode pr-compare --pr 8082 --repo includes/builder-5
```

Allow review to proceed with warnings only:

```bash
node src/index.mjs --mode pr-compare --pr 8082 --repo includes/builder-5 --preflight-warn
```

Disable preflight checks entirely:

```bash
node src/index.mjs --mode pr-compare --pr 8082 --repo includes/builder-5 --no-preflight
```

Allow specific issues:

```bash
node src/index.mjs --mode pr-compare --pr 8082 --repo includes/builder-5 \
  --allow-missing-tasks --allow-missing-pr-body --allow-failing-checks \
  --allow-unresolved-threads
```

Force reviewers:

```bash
node src/index.mjs --mode pr-compare --pr 8082 --repo includes/builder-5 \
  --force-reviewer review-security \
  --force-reviewers review-performance,review-architecture-specs
```

Resume summaries from latest run:

```bash
node src/index.mjs --mode pr-compare --pr 8082 --repo includes/builder-5 --resume-latest
```

Refresh summaries even when resuming:

```bash
node src/index.mjs --mode pr-compare --pr 8082 --repo includes/builder-5 \
  --resume-latest --refresh-summaries
```

Summary cache (reuse per-file summaries across runs):

```bash
node src/index.mjs --mode pr-compare --pr 8082 --repo includes/builder-5 \
  --summary-cache-dir tools/ai-review-orchestrator/.cache/summaries
```

Disable summary cache:

```bash
node src/index.mjs --mode pr-compare --pr 8082 --repo includes/builder-5 \
  --no-summary-cache
```

Model overrides (reviewers vs summaries):

```bash
node src/index.mjs --mode pr-compare --pr 8082 --repo includes/builder-5 \
  --reviewer-model gpt-5.4-mini \
  --summary-model gpt-5-nano
```

Judge model override (merging multi-run reviewer outputs):

```bash
node src/index.mjs --mode pr-compare --pr 8082 --repo includes/builder-5 \
  --judge-model gpt-5.4-mini
```

## How Reviews Run (Current Behavior)

The orchestrator is a multi-step pipeline that (1) collects repo facts,
(2) generates summaries, (3) selects reviewers, (4) runs reviewers in
parallel (or sequentially), and (5) aggregates findings into outputs.

### 1) Facts + Inputs

It builds a `facts` payload from the requested mode:
- `working-tree`: uses local `git diff` (staged + unstaged).
- `branch-compare`: uses `git diff base...head`.
- `pr-compare`: uses `gh pr view` + `gh pr diff`, with fallback to local git.
  - Related PRs can be auto-discovered from a linked issue in the PR body.
  - Related PR diffs are merged into the review context and summarized together.

The facts stage also:
- Detects task files (`.cursor/tasks/`, `et/tasks/`, `includes/builder-5/**/tasks/`).
- Pulls task context from `implementation-plan.md` when present.
- Falls back to the PR description as task context when no task files exist.
- Computes change size by counting diff lines (excludes task files, snapshots,
  compiled `module.json`, and `_all_modules_*` dumps).
- Classifies size (`tiny`/`small`/`medium`/`large`/`huge`) via `config.yml`.
- For PR reruns, builds `retroReview` with `review_round`, prior DeepHive
  threads/findings, `waived_items`, and `diff_since_last_run` (from the last
  DeepHive review commit SHA on GitHub, falling back to the cached local run).
  Human replies on those threads are classified by the summary nano model
  (`decline` / `confirm` / `link` / `other`) so a "stop commenting" or
  equivalent in any phrasing becomes a waived path+theme. Those pairs are
  passed to reviewers in discovery and dropped again at aggregation so a
  reworded title cannot re-open the same request. If OpenAI is unavailable,
  only empty replies and URL-only replies are classified structurally.
- Persists run metadata to `output/<run-id>/run.json`, `facts.json`, and `files/index.json`.

### 2) Summarization Pipeline

Summarization is OpenAI-based and uses the `OPENAI_SUMMARY_MODEL` (default
`gpt-5-nano`). If `OPENAI_API_KEY` is missing, summaries are skipped and
recorded as such.

There are three summary layers:

1. **Per-file summaries** (`summaries/files.json`, plus `files/by-path/*.json`)
   - Uses a JSON-schema response with:
     - `summary` (1-2 sentences),
     - `confidence` (0-1),
     - `evidence` (short excerpts from the diff).
  - Each file patch is chunked (default ~220 lines per chunk) and saved under
    `diffs/file-chunks/<path>/chunk-XXXX.patch`.
  - File summaries run concurrently with configurable limits
    (`summaries.file_concurrency`, `summaries.file_stagger_ms` in `config.yml`).
   - Existing summaries are reused unless `--refresh-summaries` is set.
   - If summary cache is enabled, identical prompts reuse cached summaries.

2. **Group summaries** (`summaries/groups.json`)
   - Files are grouped by path prefix (depth 2).
   - Each group is summarized from the per-file summaries (2-3 sentences).

3. **Overall summary** (`summaries/overall.json`)
   - Summarizes the group summaries into a 3-5 sentence overview.

These summaries are also embedded into the reviewer prompts so reviewers can
avoid re-diffing unless necessary.

### 3) Reviewer Selection

The orchestrator loads reviewer definitions from
`tools/ai-review-orchestrator/reviewers/` and runs a **decision agent** that
chooses a reviewer subset. The decision prompt includes:
- Changed files, task files, task context.
- Review size, comment budgets, comment-label caps, and confidence thresholds.

If the decision agent fails, all reviewers are selected. You can always
override with `--force-reviewer` or `--force-reviewers`.

### 4) Reviewer Orchestration

Each reviewer is executed via Cursor CLI `agent` in **read-only** mode:
- Reviewers see the change context, summaries, and the output contract.
- Reviewer input is filtered by `globs` and `keywords` in reviewer frontmatter.
- Each reviewer receives a focused file list (largest relevant diffs first).
  Snapshots, compiled `module.json`, and `_all_modules_*` dumps are excluded
  from that list so they do not crowd out small high-value files.
- Round 1 inspects more files (`review_rounds.first_pass_max_files`) and is told
  to dump every independent finding now.
- Follow-up rounds (`review_round` >= 2) are delta-only: focused files are
  restricted to `diff_since_last_run` plus unresolved thread paths (excluding
  waived/rebutted threads), and findings outside that delta are dropped.
  Empty findings is the success case. Human-declined themes stay dropped even
  when the delta touches the same file.
- Runs in parallel by default; use `--sequential` or `--stagger-ms`.

Multiple model runs per reviewer (optional):
- Configure per-size run counts in `config.yml` (`reviewer_runs_by_size`).
- Optionally specify a model pool with `reviewer_models` (cycled per run).
- For multi-run reviewers, outputs are merged by a separate Cursor CLI judge call.
Reviewer prompts and outputs are stored under:
- `reviewers/prompts/<name>.txt`
- `reviewers/outputs/<name>.json` (or `.error.json`)
 - Multi-run reviewers also write `reviewers/prompts/<name>/run-XX.txt` and
   `reviewers/outputs/<name>/run-XX.json` plus `<name>.judge.txt` when merged.

### 5) Aggregation + PR Outputs

Reviewer outputs are merged into a unified findings set:
- Applies confidence thresholds (drop or downgrade blocking to non-blocking).
- Drops findings that match `waived_items` (author declined / told DeepHive to
  stop), even when the new title is reworded.
- Enforces comment-label caps and size-based comment budgets.
- Produces a PR-safe summary by Conventional Comment labels (defaults to blocking issues only).
- Formats PR findings using Conventional Comments labels and decorations.
- Reviewers can override labels via `comment_label` and `comment_decorations`.

Outputs include:
- `aggregate/findings.json` (full private summary + PR-safe findings).
- `aggregate/summary-comment.md` (permanent PR summary, updated on reruns).
- `aggregate/review-comment.md` (findings for the PR review body).
- `aggregate/private-summary.md` (full list + reviewer stats).
- `aggregate/inline-comments.json` (PR inline comments when possible).
- `aggregate/related-inline-comments.json` (inline comments for companion PRs).
- `aggregate/review-payload.json` (full PR review payload with event + comments).
- `aggregate/related-review-payloads.json` (companion PR review payloads).

For PRs, inline comments are mapped to diff positions using line ranges or
snippets; if no match is found, it falls back to the first hunk line.
Related-repo files (`related/<org>/<repo>/...`) are never attached to this PR's
GitHub review payload; they are listed under Companion PRs in the review body
and posted to the companion PR when CI can.

## GitHub Workflow Integration (DeepHive)

The `includes/builder-5/.github/workflows/deephive-code-review.yml` workflow
triggers on a PR label: **DeepHive Review Requested**. Adding the label starts
the review job, and the workflow removes the label once it begins.

The workflow also enables a GitHub Actions cache for per-file summaries:
- Cache path: `tools/ai-review-orchestrator/.cache/summaries`
- Cache key includes repo + summary model + orchestrator code hash
- This complements the local summary cache and speeds up repeat runs

PR feedback harvesting (closed PRs + trusted comments):

```bash
node src/feedback.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01 --limit 50 \
  --trusted-users dev1,dev2
```

Include all human comments when no trusted list is provided:

```bash
node src/feedback.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01 --include-all
```

Run AI analysis to map comments to reviewers:

```bash
node src/feedback.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01 \
  --trusted-users dev1,dev2 --analyze --analysis-model gpt-5-nano
```

Separate steps (ingest then analyze):

```bash
node src/feedback-ingest.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01
node src/feedback-analyze.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01
```

## Feedback-X (Embeddings + Clustering)

Goal: turn raw feedback into generalized reviewer wisdom by clustering similar
comments and summarizing the themes into actionable reviewer updates.

Embeddings are grounded, not raw comment text. After ingest, nano normalizes each
kept comment into a reusable claim (kind, signal, controlled tags) using the
filename, nearby diff hunk, and a one-line PR summary. Kind/tags are stored on
the row for later filtering; they are **not** embedded. The embedding document is
`CLAIM / CODE_SIGNALS / CODE / FEEDBACK`. Embedding the tags would cluster by our
pre-labels ("all correctness in visual-builder") instead of by defect class.
Full PR descriptions are also not embedded. Cluster rollups still keep tag
histograms so merge can prefer common + high-signal themes.

### Flow

1) Ingest PR comments into SQLite:

```bash
node src/feedback-ingest.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01
```

2) Filter, normalize, and embed comments (writes to SQLite):

```bash
node src/feedback-embed.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01
```

Re-embed already-processed comments (needed after changing the embed document).
Reuses cached claims unless you also pass `--force-normalize`:

```bash
node src/feedback-embed.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01 --all
```

Disable nano filter (path tags + hunk still ground the embedding, but no claim):

```bash
node src/feedback-embed.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01 --no-nano-filter
```

Use a different embedding model or dimensions:

```bash
node src/feedback-embed.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01 \
  --model text-embedding-3-large --dimensions 1024
```

Progress logging interval (default: 50):

```bash
node src/feedback-embed.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01 \
  --progress-every 100
```

3) Cluster similar comments using sqlite-vector (writes to SQLite):

```bash
SQLITE_VECTOR_PATH=/path/to/sqlite-vector.dylib \
SQLITE_VECTOR_ENTRYPOINT=sqlite3_vector_init \
node src/feedback-cluster.mjs --repo elegantthemes/submodule-builder-5 --dimensions 1536
```

Tune clustering:

```bash
SQLITE_VECTOR_PATH=/path/to/sqlite-vector.dylib \
SQLITE_VECTOR_ENTRYPOINT=sqlite3_vector_init \
node src/feedback-cluster.mjs --repo elegantthemes/submodule-builder-5 \
  --dimensions 1536 --k 40 --threshold 880 --min-members 6
```

Replace previous runs for the same repo/model/dimensions:

```bash
SQLITE_VECTOR_PATH=/path/to/sqlite-vector.dylib \
SQLITE_VECTOR_ENTRYPOINT=sqlite3_vector_init \
node src/feedback-cluster.mjs --repo elegantthemes/submodule-builder-5 \
  --dimensions 1536 --k 25 --threshold 880 --min-members 2 --replace
```

4) Summarize clusters into reviewer updates (writes to SQLite + findings):

```bash
node src/feedback-summarize.mjs --repo elegantthemes/submodule-builder-5
```

Specify the cluster run or model:

```bash
node src/feedback-summarize.mjs --repo elegantthemes/submodule-builder-5 \
  --run-id 12 --analysis-model gpt-5.3-codex
```

**Note:** This step is optional if you plan to use `--generate-suggestions` mode during merge, which generates suggestions on-the-fly from the raw cluster data.

Noise filtering (pre-analysis):
- Regex filters remove obvious non-feedback (e.g. "LGTM", "DeepHive Automated Analysis").
- Optional nano classifier screens low-value comments before main analysis.

Disable nano filter:

```bash
node src/feedback-analyze.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01 --no-nano-filter
```

Choose nano model:

```bash
node src/feedback-analyze.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01 --nano-model gpt-5-nano
```

Verbose logging:

```bash
node src/feedback-ingest.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01 --verbose
```

Progress logging interval (default: 25 ingest, 10 analyze):

```bash
node src/feedback-ingest.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01 --progress-every 10
node src/feedback-analyze.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01 --progress-every 5
```

## Persistence (SQLite)

By default, feedback runs persist to:

```
tools/ai-review-orchestrator/data/feedback.sqlite
```

Feedback-X outputs are also stored in the same database (filters, embeddings,
clusters, and summaries). Output files are not required for the embedding flow.

Override the DB path or disable DB writes:

```bash
node src/feedback.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01 --db /tmp/feedback.sqlite
node src/feedback.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01 --no-db
```

Initialize and inspect DB stats:

```bash
node src/feedback-db.mjs --stats
```

Reset the database (destructive):

```bash
rm tools/ai-review-orchestrator/data/feedback.sqlite
```

Merge AI findings into reviewer prompts (interactive):

```bash
node src/feedback-merge.mjs --output-dir tools/ai-review-orchestrator/output/<run-id>_feedback
```

Notes:
- Analysis suggestions include `section_title` and `operation` (`add` or `replace`).
- The merge tool will create missing sections when needed.
- Analysis suggestions include `patch_type` (`reviewer`, `rule`, `docs`, `other`).

Merge cluster findings from the DB (interactive):

```bash
node src/feedback-merge.mjs --from-db --cluster-run-id <run-id>
```

Include already-decided cluster findings:

```bash
node src/feedback-merge.mjs --from-db --cluster-run-id <run-id> --include-decided
```

Preview changes only:

```bash
node src/feedback-merge.mjs --output-dir tools/ai-review-orchestrator/output/<run-id>_feedback --dry-run
```

Rewrite suggestions with AI before editing:

```bash
node src/feedback-merge.mjs --output-dir tools/ai-review-orchestrator/output/<run-id>_feedback \
  --rewrite-ai --rewrite-prompt "Rewrite to be concise and testable"
```

Choose a rewrite model:

```bash
node src/feedback-merge.mjs --output-dir tools/ai-review-orchestrator/output/<run-id>_feedback \
  --rewrite-ai --rewrite-prompt "Use stronger language for critical issues" \
  --rewrite-model gpt-5.4-mini
```

Allow creating new reviewers when suggested:

```bash
node src/feedback-merge.mjs --output-dir tools/ai-review-orchestrator/output/<run-id>_feedback --create-new
```

Record decisions and patches to the DB:

```bash
node src/feedback-merge.mjs --output-dir tools/ai-review-orchestrator/output/<run-id>_feedback --decided-by josh
```

Disable DB writes during review:

```bash
node src/feedback-merge.mjs --output-dir tools/ai-review-orchestrator/output/<run-id>_feedback --no-db
```

### On-the-Fly Suggestion Generation (Experimental)

Instead of using pre-generated suggestions from `feedback-summarize.mjs`, you can generate suggestions dynamically during the merge process. This mode:

- Reads the raw cluster members (PR comments) at merge time.
- Loads the current content of all reviewer files.
- Calls AI to generate contextual suggestions based on both the feedback cluster and current reviewer state.
- Presents suggestions interactively for accept/modify/rewrite/skip.

This is useful when reviewer files may have changed since clustering, or when you want to iterate on suggestions with fresh context.
Decisions made in this mode are stored in the DB, so accepted/skip/defer choices do not reappear unless `--include-decided` is used.

**Generate suggestions on-the-fly:**

```bash
node src/feedback-merge.mjs --generate-suggestions --cluster-run-id <run-id>
```

**With a specific generation model:**

```bash
node src/feedback-merge.mjs --generate-suggestions --cluster-run-id <run-id> \
  --generation-model gpt-5.4-mini
```

**Limit number of comments analyzed per cluster:**

```bash
node src/feedback-merge.mjs --generate-suggestions --cluster-run-id <run-id> \
  --max-comments 10
```

**Combine with other options:**

```bash
node src/feedback-merge.mjs --generate-suggestions --cluster-run-id <run-id> \
  --create-new --decided-by josh --rewrite-ai
```

**Full workflow example:**

```bash
# 1. Cluster comments (after ingest + embed)
SQLITE_VECTOR_PATH=/path/to/sqlite-vector.dylib \
  node src/feedback-cluster.mjs --repo elegantthemes/submodule-builder-5 --dimensions 1536

# 2. Merge with on-the-fly generation (no summarize step needed)
node src/feedback-merge.mjs --generate-suggestions --cluster-run-id <run-id> \
  --create-new --decided-by josh
```

### Re-running Feedback-X (ready checklist)

Prereqs on this machine:
- `gh` authenticated
- `OPENAI_API_KEY` in the environment or `tools/ai-review-orchestrator/.env`
- sqlite-vector at `/Users/joshronk/Library/SQLite/sqlite-vector.dylib`

Ingest is idempotent (comment upserts). Re-run from the same `--since` to pick up
new closed PRs, then embed / cluster / merge. Skip `--trusted-users` to include
all human comments, or pass a comma list to restrict. `DeepHiveET` and
`etstaging` are skipped by default (`feedback_harvest.exclude_authors` in
`config.yml`). Use `--include-excluded-authors` only if you want them, or
`--exclude-authors foo,bar` to add more.

First harvest after this grounded-embed change should use default new-only embed
(no `--all`). If comments were already embedded as raw bodies, re-run embed with
`--all` before clustering so vectors include claims, tags, and code context.

```bash
cd tools/ai-review-orchestrator
export SQLITE_VECTOR_PATH=/Users/joshronk/Library/SQLite/sqlite-vector.dylib
export SQLITE_VECTOR_ENTRYPOINT=sqlite3_vector_init

# 1. Ingest closed-PR comments
node src/feedback-ingest.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01

# 2. Filter + embed
node src/feedback-embed.mjs --repo elegantthemes/submodule-builder-5 --since 2026-01-01

# 3. Cluster (use --replace to overwrite the previous run for this repo/model)
node src/feedback-cluster.mjs --repo elegantthemes/submodule-builder-5 \
  --dimensions 1536 --k 25 --threshold 880 --min-members 2 --replace

# 4. Inspect DB, then merge with on-the-fly reviewer suggestions
node src/feedback-db.mjs --stats
node src/feedback-merge.mjs --generate-suggestions --cluster-run-id <run-id> \
  --create-new --decided-by josh
```

Optional: also ingest `elegantthemes/Divi` / `elegantthemes/submodule-builder`
if you want cross-repo themes in the same sqlite file.

## Environment

If you want the tool to run without exporting vars each time, add a
`tools/ai-review-orchestrator/.env` file:

```bash
GH_TOKEN=your_token_here
OPENAI_API_KEY=your_openai_key_here
OPENAI_SUMMARY_MODEL=gpt-5-nano
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
SQLITE_VECTOR_PATH=/path/to/sqlite-vector.dylib
SQLITE_VECTOR_ENTRYPOINT=sqlite3_vector_init
DEEPHIVE_USAGE_URL=https://deephive.staging.etdevs.com
USAGE_INGEST_TOKEN=your_ingest_token_here
```

PR review usage ingest is fail-open: if `USAGE_INGEST_TOKEN` is unset or the POST fails, the review still succeeds. Cursor CLI calls use `--output-format stream-json` so token counts can be recorded. The workflow ping/auth check is not ingested.

## Config and Reviewers

- Reviewers live in `tools/ai-review-orchestrator/reviewers/`.
- Config lives in `tools/ai-review-orchestrator/config.yml`.
- Output contracts live in:
  - `tools/ai-review-orchestrator/docs/output-contract-reviewer.md`
  - `tools/ai-review-orchestrator/docs/output-contract-orchestrator.md`
- Reviewer frontmatter supports `globs` and `keywords` to target file summaries.
- Reviewer frontmatter supports `model` (use `inherit` to use the default reviewer model).
- Reviewer frontmatter also supports:
  - `max_runs` (cap multi-run reviewers to 1 for straightforward checks).
  - `runs_by_size` (override run counts per size key).
  - `models` (override model pool per reviewer).
- Divi-specific reviewers are anchored to the project rules and BUGBOT guidance under `includes/builder-5/.cursor/` and `includes/builder-5/visual-builder/.cursor/`.

## Output

Artifacts are written to:

```
tools/ai-review-orchestrator/output/<run-id>/
```

This includes `facts.json`, per-file summaries, grouped summaries, and reviewer
prompts/outputs, and `preflight.json`. The directory is git-ignored.

## Notes

- Reviewers are executed via `agent` (Cursor CLI) in read-only mode.
- Reviewers receive name-only diffs and can run `git diff` for files they choose.
- Task files live under `et/tasks/` (or `includes/builder-5/et/tasks/`).
- PR file lists use the GitHub API with pagination to avoid 100-file limits.
- PR per-file summaries use the GitHub API patch field (may be truncated for huge diffs).
- Inline review comments are generated from diff positions when available.
- `facts.json` is metadata-only (no patches); diffs live under `diffs/file-chunks/`.
