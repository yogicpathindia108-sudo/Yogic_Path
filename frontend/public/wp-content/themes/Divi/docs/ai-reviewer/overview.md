---
title: AI Reviewer Overview
status: draft
---

# AI Reviewer Overview

This system implements the ADR-001 architecture with a Node-based orchestrator
that spawns Cursor CLI reviewers in read-only mode and aggregates the results.

## Components

- Orchestrator (Node + LangGraph): collects facts, selects reviewers, enforces
  budgets, aggregates output.
- Reviewer runs (Cursor CLI `agent`): domain-specific judgments with independent
  silence, executed in read-only mode.
- Summarization (OpenAI): per-file, grouped, and overall summaries used to keep
  reviewers fast and focused.

## Entrypoints

The CLI entrypoint is the orchestrator script:

```
cd tools/ai-review-orchestrator
node src/index.mjs --mode auto
```

Supported modes:
- `working-tree`: local staged + unstaged changes.
- `branch-compare`: local branch compared to base branch.
- `pr-compare`: PR diff via `gh` in CI.

Task workflow awareness:
- Task files are detected under `.cursor/tasks/`, `et/tasks/`,
  `includes/builder-5/.et/tasks/`, and `includes/builder-5/et/tasks/`.
- Review sizing and reviewer payloads exclude task files from diff line counts.
- When present, `implementation-plan.md` excerpts are passed to the
  change-intent reviewer.

## Typical Flows

- Local dev before commit: `node src/index.mjs --mode working-tree`.
- Local dev on feature branch: `node src/index.mjs --mode branch-compare --base <ref>`.
- CI on PR: `node src/index.mjs --mode pr-compare --pr 123`.

## Configuration

Defaults live in `tools/ai-review-orchestrator/config.yml`. Override values via
CLI flags or environment variables as needed.

Reviewer definitions live in `tools/ai-review-orchestrator/reviewers/`.
Outputs are written to `tools/ai-review-orchestrator/output/<run-id>/`.
