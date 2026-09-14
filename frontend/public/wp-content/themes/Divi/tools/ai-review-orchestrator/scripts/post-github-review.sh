#!/usr/bin/env bash
set -euo pipefail

REPO_SLUG="${1:?repo slug required}"
PR_NUMBER="${2:?pr number required}"
OUTPUT_ROOT="${3:?output root required}"

PAYLOAD_PATH="$OUTPUT_ROOT/aggregate/review-payload.json"
REVIEW_PATH="$OUTPUT_ROOT/aggregate/review-comment.md"
RELATED_PAYLOADS="$OUTPUT_ROOT/aggregate/related-review-payloads.json"

post_review() {
  local repo="$1"
  local pr="$2"
  local payload="$3"
  if gh api "repos/${repo}/pulls/${pr}/reviews" --input "$payload"; then
    return 0
  fi
  echo "Review post failed for ${repo}#${pr}; retrying without inline comments."
  local stripped
  stripped="$(mktemp)"
  jq 'del(.comments) | . + {comments: []}' "$payload" > "$stripped"
  if gh api "repos/${repo}/pulls/${pr}/reviews" --input "$stripped"; then
    rm -f "$stripped"
    return 0
  fi
  rm -f "$stripped"
  return 1
}

if [ ! -f "$PAYLOAD_PATH" ]; then
  echo "Review payload not found: $PAYLOAD_PATH"
  exit 1
fi

AUTHOR_LOGIN="$(gh api "repos/${REPO_SLUG}/pulls/${PR_NUMBER}" --jq '.user.login')"

if ! post_review "$REPO_SLUG" "$PR_NUMBER" "$PAYLOAD_PATH"; then
  echo "Review post failed, falling back to PR comment."
  if [ ! -f "$REVIEW_PATH" ]; then
    echo "Review comment not found: $REVIEW_PATH"
    exit 1
  fi
  {
    if [ "DeepHiveET" = "$AUTHOR_LOGIN" ]; then
      echo "@DeepHiveET here's a self-review, please confirm and address the feedback noted in the findings section. <!-- dh:slack-feedback -->"
      echo ""
    fi
    cat "$REVIEW_PATH"
  } | gh pr comment "$PR_NUMBER" --repo "$REPO_SLUG" --body-file -
fi

if [ ! -f "$RELATED_PAYLOADS" ]; then
  echo "No related review payloads."
  exit 0
fi

related_count="$(jq 'length' "$RELATED_PAYLOADS")"
echo "Related review payloads: ${related_count}"
if [ "$related_count" -le 0 ]; then
  exit 0
fi

while IFS= read -r item; do
  related_repo="$(jq -r '.repoSlug' <<< "$item")"
  related_pr="$(jq -r '.prNumber' <<< "$item")"
  related_file="$(mktemp)"
  jq '.payload' <<< "$item" > "$related_file"
  echo "Posting related review to ${related_repo}#${related_pr}"
  if ! post_review "$related_repo" "$related_pr" "$related_file"; then
    echo "Related review post failed for ${related_repo}#${related_pr} (continuing)."
  fi
  rm -f "$related_file"
done < <(jq -c '.[]' "$RELATED_PAYLOADS")
