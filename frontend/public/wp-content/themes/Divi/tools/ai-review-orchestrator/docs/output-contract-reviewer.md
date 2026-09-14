---
title: AI Reviewer Output Contract (Reviewer)
status: draft
---

# AI Reviewer Output Contract (Reviewer)

This document standardizes the payloads exchanged between the orchestrator and
domain reviewer subagents.

## Diff Payload (Input)

All reviewers receive a normalized diff payload from the orchestrator.

```
{
  "mode": "working-tree | branch-compare | pr-compare",
  "repo_root": "/path/to/repo",
  "base_ref": "release/2026.01",
  "head_ref": "feature/my-branch",
  "changed_files": ["src/foo.ts", "src/bar.php"],
  "code_files": ["src/foo.ts", "src/bar.php"],
  "task_files": ["includes/builder-5/.et/tasks/47600/47608/implementation-plan.md"],
  "patch": "... unified diff ...",
  "code_patch": "... unified diff without task files ...",
  "task_context": {
    "issue_numbers": ["47608"],
    "primary_issue_number": "47608",
    "implementation_plan_path": "includes/builder-5/.et/tasks/47600/47608/implementation-plan.md",
    "implementation_plan_excerpt": "## Problem Analysis ..."
  },
  "companion_context": {
    "status": "confirmed | not_confirmed | unknown",
    "reason": "same_issue_same_branch_companion_detected | companion_not_found | missing_issue_context | missing_branch_context",
    "has_confirmed_companion": true,
    "branch_name": "issue/47608",
    "issue_refs": [{"repoSlug": "org/repo", "issueNumber": 47608}],
    "confirmed_companions": [{"repoSlug": "org/other-repo", "prNumber": 123}]
  },
  "metadata": {
    "pr_url": "https://github.com/org/repo/pull/123",
    "pr_number": 123,
    "commit_count": 5
  }
}
```

Notes:
- `base_ref` and `head_ref` are optional for `working-tree`.
- `patch` may be a unified diff string or a list of file diffs.
- `code_patch` excludes task workflow files under `includes/builder-5/.et/tasks/**`.
- `task_context` is optional and used for scope alignment in change-intent review.
- `companion_context` is optional and indicates whether same-issue/same-branch companion PR context is confirmed.

## Reviewer Output (Subagents)

Each reviewer must return structured JSON. Silence is allowed and preferred
when no findings exist.

```
{
  "reviewer": "security-reviewer",
  "findings": [
    {
      "title": "Missing capability check on admin action",
      "comment_label": "issue | suggestion | question | note | ... (optional)",
      "comment_decorations": ["blocking", "non-blocking", "..."],
      "confidence": 0.0,
      "locations": [
        {
          "path": "includes/admin.php",
          "lines": "L120-L145",
          "snippet": "if ( $action === 'delete' ) { ... }"
        }
      ],
      "rationale": "Action is gated only by a UI check.",
      "suggested_fix": "Add current_user_can() check before deletion.",
      "tags": ["auth", "privilege"]
    }
  ],
  "notes": "Optional short context or caveats."
}
```

Rules:

# Highest Priority Main Rule
This system should **maximize real feedback**. Do not miss regressions, logic
flaws, correctness bugs, type unsafety, or contract violations. Be quiet only
about low-value polish or subjective preferences. Suggestions should be **rare**
and only for material correctness, safety, or maintainability wins. The CI
workflow may still omit non-blocking findings from PR comments, so if something
is truly important, mark it **issue (blocking)**.

# Round Completeness
Round 1 must dump every independent high-impact finding. Do not stop after one
or two. Later rounds (`review_round` >= 2) only review the delta since the last
DeepHive review. Empty findings on a follow-up round is the successful outcome
when prior feedback was addressed and the delta introduces no new defects.

# Satisficing
Do not raise "more tests" or "add a spec map" when coverage/docs already exist
and would catch a revert of the change. Do not escalate assertion strictness
across re-review rounds.

- `findings` can be empty.
- `confidence` is 0.0 to 1.0.
- Aggregate locations by theme instead of per-line spam.
- `comment_label` and `comment_decorations` optionally override default formatting.
- **Default to blocking issues.** Suggestions are allowed only when high-confidence and truly high-value.
- Avoid `note`-level feedback unless it changes how the author should interpret a blocking issue.
- **Companion / related PRs**: never raise findings whose only concern is that a same-issue companion or related PR must also be merged, or merge-order coordination across those PRs. They are always merged together. Do not use the `companion-dependency-order` tag; omit the finding.

### Conventional Comments Guidance


Reviewers may choose `comment_label` and `comment_decorations` to control the
rendered PR comment. Use these labels and intent rules:

- `issue` + `blocking`: the default and expected label for merge-blocking problems.
- `suggestion` + `non-blocking`: allowed only when the suggestion materially improves correctness, safety, or maintainability, something that the author would really be thankful for and not annoyed by you raising it.
- Never emit companion/related-PR merge reminders (`companion-dependency-order`, "make sure both PRs are merged", merge-order coordination). The orchestrator drops them if they appear.

If `comment_label` is omitted, the orchestrator defaults to `issue` +
`blocking`. If `comment_label=suggestion` and `comment_decorations` is omitted,
the orchestrator defaults to `non-blocking`. Use `comment_decorations` for
qualifiers like `blocking` or `non-blocking`.
