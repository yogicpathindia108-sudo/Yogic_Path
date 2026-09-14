ADR-001: AI Code Reviewer Architecture (Subagents + Anti-Nitpicking Controls)
Status: Accepted
Date: 2026-01-14
Decision Owner: Engineering

## Context
We are building an AI-powered code reviewer that performs multiple focused review passes over code changes (PRs), producing actionable feedback while avoiding reviewer fatigue and low-signal nitpicking.

The system must:
- Perform opinionated, risk-weighted reviews (not linting).
- Run multiple domain-specific review passes (security, i18n, performance, etc.).
- Allow each pass to exercise independent judgment, including silence.
- Aggregate findings into developer-facing PR comments and manager or private summaries.
- Explicitly control noise, repetition, and overreach.

We evaluated whether to implement review passes as Skills or Subagents.

Relevant docs:
- Agent Skills concept: https://agentskills.io/what-are-skills
- Cursor Skills: https://cursor.com/docs/context/skills
- Cursor Subagents: https://cursor.com/docs/context/subagents

## Decision
We will implement each review pass as a Cursor Subagent, coordinated by a single parent Orchestrator Agent.
Skills will be used only for shared, mechanical tasks (diff parsing, context loading, formatting), not for judgment.

## Rationale
### Why Subagents (Not Just Skills)
Each review pass:
- Has its own definition of risk and failure.
- Applies domain-specific standards.
- Requires independent confidence thresholds.
- Must be allowed to say nothing.
- Produces findings that may conflict or overlap with other passes.

These are characteristics of autonomous judgment, not deterministic execution.
Using Skills alone would force a single agent to simulate multiple opinions, leading to brittle prompts and excessive nitpicking.

### Correct Mental Model
- Subagents = Judges (opinionated, risk-aware, allowed to disagree).
- Skills = Tools (deterministic helpers used by subagents).
- Parent Orchestrator = Arbiter (controls budgets, aggregation, output).

## Architecture Overview
### Parent Agent: Review Orchestrator
Responsibilities:
- Compute PR risk profile (size, files, domains touched).
- Decide which subagents to run.
- Enforce global comment budgets and severity caps.
- Deduplicate and aggregate findings.
- Apply anti-nitpicking rules.
- Decide what is posted to PR vs private summary.

The Orchestrator has no opinions about code quality. It only arbitrates.

### Implementation Notes
- The current implementation is a Node-based orchestrator (`tools/ai-review-orchestrator/src/index.mjs`)
  that uses LangGraph for control flow.
- Reviewer passes are executed via Cursor CLI `agent` runs in read-only mode,
  which act as the "subagents" described here.
- The orchestrator performs aggregation, comment budgets, and severity caps
  directly rather than delegating those steps to a Cursor subagent.

### Subagents: Domain Reviewers
Each subagent:
- Receives only relevant diff and context.
- Applies its own rubric.
- Assigns severity and confidence.
- May return zero findings.
- Does not know about other subagents.

Common output contract (all subagents):
- Finding title.
- Severity: Blocker | Concern | Nit.
- Confidence score (0.0-1.0).
- Aggregated locations (not per-line spam).
- Rationale.
- Suggested remediation (if applicable).

## Review Passes (Subagent Specs)
1. Change Intent and Scope Reviewer
   Judges:
   - PR matches stated intent.
   - No unrelated refactors.
   - No commented-out or dead code.
   - Deletions are justified.

2. Types and Structural Correctness Reviewer
   Judges:
   - Proper typing.
   - No guessy or implicit code.
   - File structure consistency (stores, selectors, utils, hooks).
   - Correct import patterns.
   - Redux or state management conventions.
   - DRY and KISS (not clever).

3. i18n Reviewer
   Judges:
   - No hard-coded user-facing strings.
   - Correct use of translation keys.
   - Proper pluralization.
   - Locale-aware dates, numbers, currency.
   - RTL-safe layout considerations.

4. API and Contract Integrity Reviewer
   Judges:
   - Public API changes.
   - Backward compatibility.
   - DTO or schema parity.
   - FE to BE contract alignment.
   - Versioning discipline.

5. Error Handling and Failure Modes Reviewer
   Judges:
   - Errors surfaced appropriately.
   - No swallowed exceptions.
   - Logging adequacy.
   - Retry logic.
   - Edge-case handling (nulls, empties, timeouts).

6. Security Reviewer
   Judges:
   - Auth or role enforcement.
   - CSRF, XSS, sanitization.
   - Injection risks.
   - Secrets handling.
   - Privilege escalation.
   This reviewer is conservative and low-frequency but high-severity.

7. State, Concurrency and Async Safety Reviewer
   Judges:
   - Race conditions.
   - Async ordering assumptions.
   - Stale closures.
   - Idempotency.
   - Double-submit risks.

8. Performance and Complexity Reviewer
   Judges:
   - N+1 queries.
   - Accidental quadratic behavior.
   - Heavy work in render paths.
   - Avoidable recomputation.
   - Big-O regressions.

9. UX and Accessibility Reviewer
   Judges:
   - Loading states.
   - Empty states.
   - Error messaging clarity.
   - Disabled states.
   - Keyboard or ARIA regressions.

10. Test Quality Reviewer
    Judges:
    - Tests assert behavior, not implementation.
    - Missing edge cases.
    - Over-mocking.
    - Brittle snapshots.
    - Flakiness patterns.

11. Rollout and Migration Safety Reviewer
    Judges:
    - Feature flags.
    - Backward-compatible migrations.
    - Safe defaults.
    - Rollback paths.

12. Dependency and Supply Chain Reviewer
    Judges:
    - New dependency justification.
    - License compatibility.
    - Bundle size impact.
    - Duplicate dependencies.
    - Known vulnerabilities.

## Anti-Nitpicking Controls (Single Source of Truth)
1. Severity Buckets (Hard Caps)
   - Blocker: Must fix before merge (max 3).
   - Concern: Worth discussion (max 5).
   - Nit: Optional (collapsed, summary-only).
   Overflow findings are summarized, not listed.

2. Aggregate by Idea, Not Line
   - One comment per theme.
   - Multiple locations referenced together.
   - No per-line spam.

3. Diff-Aware Restraint
   Subagents may comment only on:
   - Changed files.
   - Code within plus or minus N lines of diffs.
   Exceptions:
   - Security.
   - Broken contracts.
   - Dead code caused by change.

4. Comment Budgets Based on PR Size
   - Tiny PR: 3 or fewer findings.
   - Small: 5 or fewer.
   - Medium: 8 or fewer.
   - Large: 10 or fewer.
   - Huge: summary-only.

5. Confidence Thresholds
   - Below 0.6: drop.
   - 0.6-0.75: Nit only.
   - 0.75: Concern eligible.
   - 0.9: Blocker possible.

6. Precedent-Aware Silence
   If code matches existing repo patterns, do not comment.
   Exceptions:
   - Security.
   - Explicit tech-debt PRs.

7. Ask, Do Not Tell
   - Concerns phrased as questions.
   - Directives reserved for Blockers only.

8. No Praise, No Fluff
   - Silence implies approval.
   - No "looks good" comments.

9. Review Memory
   - Do not repeat waived issues.
   - Do not re-raise unchanged findings.
   - Track discussion history per PR.

10. Dual Outputs
    PR Comments:
    - Blockers.
    - Top Concerns.
    - Aggregated, budgeted.
    Private Summary (Manager or Cron Job):
    - All findings.
    - Nits.
    - Patterns across devs.
    - Trends over time.

11. Summary-Only Mode
    Triggered when:
    - PR too large.
    - Low confidence overall.
    - Refactor-heavy.
    - Fatigue signals detected.
    PR message:
    "Reviewed changes - no blockers found. See summary for details."

## Consequences
### Positive
- High signal-to-noise reviews.
- Developer trust preserved.
- Scales across teams and repos.
- Matches senior human review behavior.

### Tradeoffs
- More initial setup.
- Requires orchestration logic.
- Slightly higher compute cost.

## Final Rule
Humans decide. AI triages.
This ADR is the single source of truth for implementing the AI Code Reviewer system in Cursor using Subagents and Skills.
