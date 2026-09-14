---
title: Retro Feedback Output Contract (Reviewer)
status: draft
---

# Retro Feedback Output Contract (Reviewer)

This document defines the output for the `review-retro-feedback` reviewer.

## Output

```
{
  "reviewer": "review-retro-feedback",
  "findings": [],
  "retro_actions": [
    {
      "thread_id": "PRRT_lAHOAAAN...",
      "comment_id": 123456789,
      "action": "confirm_resolved | reject_resolved",
      "message": "Short confirmation or rejection note."
    }
  ],
  "notes": "Optional short context or caveats."
}
```

Rules:
- `thread_id` must match a thread from the "Prior review feedback" context.
- `comment_id` is required and must reference the prior DeepHiveET comment
  (use `bot_comment_id` from the prior feedback context).
- `action=reject_resolved` will programmatically unresolve the thread.
- `retro_actions` can be empty when no updates are needed.
