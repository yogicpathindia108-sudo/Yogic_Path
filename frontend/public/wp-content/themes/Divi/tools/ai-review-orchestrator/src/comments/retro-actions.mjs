import { run } from "../core/exec.mjs";
import { log } from "../core/logging.mjs";
import { parseRepoSlug } from "../facts/helpers.mjs";

const resolveThreadById = (threads, threadId) =>
  (threads || []).find((thread) => threadId === thread?.thread_id) || null;

const resolveReplyCommentId = ({ action }) => action?.comment_id ?? null;

const buildReplyBody = (action) => {
  const statusLabel =
    "confirm_resolved" === action?.action
      ? "Confirmed"
      : "reject_resolved" === action?.action
        ? "Needs attention"
        : "Update";
  return [
    `**${statusLabel}:** ${action?.message || "Follow-up from DeepHive."}`,
    "",
    "<!-- dh:retro-feedback -->",
  ].join("\n");
};

const postReply = ({ owner, repo, commentId, body }) => {
  if (null == commentId) {
    return;
  }
  run("gh", [
    "api",
    `repos/${owner}/${repo}/pulls/comments/${commentId}/replies`,
    "-f",
    `body=${body}`,
  ]);
};

const unresolveThread = ({ threadId }) => {
  if (null == threadId) {
    return;
  }
  const query = [
    "mutation($threadId:ID!) {",
    "  unresolveReviewThread(input: {threadId: $threadId}) {",
    "    thread { id }",
    "  }",
    "}",
  ].join("\n");
  run("gh", [
    "api",
    "graphql",
    "-f",
    `query=${query}`,
    "-f",
    `threadId=${threadId}`,
  ]);
};

export const applyRetroActions = ({ facts, retroResult }) => {
  const retroReview = facts?.retroReview || null;
  if (null == retroReview || true !== retroReview.enabled) {
    return;
  }
  const actions = Array.isArray(retroResult?.retro_actions)
    ? retroResult.retro_actions
    : [];
  if (0 === actions.length) {
    return;
  }
  const { owner, repo } = parseRepoSlug(facts.repoSlug || "");
  if (null == owner || null == repo) {
    log("[retro-review] warning: unable to resolve repo slug.");
    return;
  }
  actions.forEach((action) => {
    const thread = resolveThreadById(retroReview.threads, action?.thread_id);
    if (null == thread) {
      return;
    }
    const commentId = resolveReplyCommentId({ action });
    if (null == commentId) {
      const threadId = action?.thread_id || "unknown";
      const botIds = Array.isArray(thread?.bot_comment_ids)
        ? thread.bot_comment_ids.join(", ")
        : "";
      const botIdLabel = "" === botIds ? "(none)" : botIds;
      log(
        `[retro-review] ERROR: retro action missing comment_id for thread ${threadId}. bot_comment_ids=${botIdLabel}`
      );
      return;
    }
    const body = buildReplyBody(action);
    try {
      postReply({ owner, repo, commentId, body });
      if ("reject_resolved" === action?.action) {
        unresolveThread({ threadId: thread.thread_id });
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      log(`[retro-review] warning: failed to apply action. ${message}`);
    }
  });
};
