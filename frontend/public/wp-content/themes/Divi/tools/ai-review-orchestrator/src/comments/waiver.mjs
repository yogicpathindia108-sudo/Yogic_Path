import { parseJsonSafe } from "../core/utils.mjs";
import { log } from "../core/logging.mjs";

const REPLY_KINDS = new Set(["decline", "confirm", "link", "other"]);
const LINK_ONLY_REGEX = /^\s*https?:\/\/\S+\s*$/i;

const normalizePath = (filePath) =>
  String(filePath || "")
    .trim()
    .replace(/^(\.\/)+/, "");

const getResponseText = (response) =>
  response.output_text ||
  response.output?.map((item) => item.content?.[0]?.text ?? "").join("\n") ||
  "";

export const classifyHumanReplyFallback = (body) => {
  const text = String(body || "").trim();
  if ("" === text) {
    return "other";
  }
  if (true === LINK_ONLY_REGEX.test(text)) {
    return "link";
  }
  return "other";
};

const buildReplyClassificationPrompt = (items) => [
  {
    role: "system",
    content: [
      "You classify author replies to automated PR review comments.",
      "Judge intent, not wording. People decline and confirm in unlimited ways.",
    ].join(" "),
  },
  {
    role: "user",
    content: [
      "For each item, set kind to one of: decline, confirm, link, other.",
      "",
      "decline: the author does not want this finding acted on or repeated.",
      "This includes refusing the change, calling it out of scope or historical practice,",
      "saying stop / don't bug me / already covered this, waving it off, or pointing",
      "back at a previous refusal of the same request. Wording is irrelevant.",
      "",
      "confirm: the author claims they implemented or fixed the finding.",
      "",
      "link: the reply is mainly a pointer to another comment, commit, or discussion.",
      "If that pointer is clearly 'see my previous no', use decline instead.",
      "",
      "other: thanks, questions, clarification, or unrelated chatter.",
      "",
      "When unsure between decline and other, prefer decline if they are pushing back.",
      "When unsure between confirm and other, prefer other unless they clearly claim a fix.",
      "",
      "Items:",
      JSON.stringify(items, null, 2),
    ].join("\n"),
  },
];

export const classifyHumanReplies = async ({ openai, model, items }) => {
  const kinds = new Map();
  (items || []).forEach((item) => {
    kinds.set(String(item.id), classifyHumanReplyFallback(item.reply));
  });
  const toClassify = (items || []).filter((item) => {
    const text = String(item?.reply || "").trim();
    return "" !== text;
  });
  if (0 === toClassify.length) {
    return { kinds, classifier: "none" };
  }
  if (null == openai || null == model || "" === String(model).trim()) {
    log(
      "[retro-review] warning: OpenAI unavailable; human replies used structural fallback only."
    );
    return { kinds, classifier: "fallback" };
  }
  try {
    const response = await openai.responses.create({
      model,
      input: buildReplyClassificationPrompt(
        toClassify.map((item) => ({
          id: String(item.id),
          path: item.path || null,
          bot_finding: item.bot_finding || "",
          reply: item.reply || "",
        }))
      ),
      text: {
        format: {
          type: "json_schema",
          name: "human_reply_kinds",
          strict: true,
          schema: {
            type: "object",
            properties: {
              replies: {
                type: "array",
                items: {
                  type: "object",
                  properties: {
                    id: { type: "string" },
                    kind: {
                      type: "string",
                      enum: ["decline", "confirm", "link", "other"],
                    },
                  },
                  required: ["id", "kind"],
                  additionalProperties: false,
                },
              },
            },
            required: ["replies"],
            additionalProperties: false,
          },
        },
      },
    });
    const parsed = parseJsonSafe(getResponseText(response));
    const replies = Array.isArray(parsed?.replies) ? parsed.replies : [];
    replies.forEach((entry) => {
      const id = String(entry?.id || "");
      const kind = String(entry?.kind || "");
      if ("" === id || false === REPLY_KINDS.has(kind)) {
        return;
      }
      kinds.set(id, kind);
    });
    return { kinds, classifier: "nano" };
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    log(`[retro-review] warning: reply classification failed. ${message}`);
    return { kinds, classifier: "fallback" };
  }
};

export const themeFromReviewSignal = ({ reviewer, title, body }) => {
  const haystack = `${reviewer || ""} ${title || ""} ${body || ""}`.toLowerCase();
  if (
    haystack.includes("test-quality") ||
    /\btests?\b|\bcoverage\b|\bphpunit\b|\bjest\b|\bregression test/.test(
      haystack
    )
  ) {
    return "tests";
  }
  if (
    haystack.includes("architecture") ||
    /\bspec(?:-|\s)?map\b|\bspecs?\b/.test(haystack)
  ) {
    return "specs";
  }
  const reviewerName = String(reviewer || "")
    .trim()
    .toLowerCase();
  return "" === reviewerName ? "other" : reviewerName;
};

const reviewerFromBotBody = (body) => {
  const match = String(body || "").match(/Reviewer:\s*([a-z0-9-]+)/i);
  return match ? match[1].toLowerCase() : "";
};

const normalizeReviewerName = (name) =>
  String(name || "")
    .trim()
    .toLowerCase()
    .replace(/^review-/, "");

const titleFromBotBody = (body) => {
  const text = String(body || "");
  const heading = text.match(/\*\*[^*]+\*\*\s*:?\s*(.+)/);
  if (heading) {
    return heading[1].replace(/^:\s*/, "").trim();
  }
  return text.split("\n")[0].replace(/^\*+|\*+$/g, "").trim();
};

export const buildWaivedItems = (threads) => {
  const items = [];
  (threads || []).forEach((thread) => {
    if ("rebutted" !== thread?.status && "waived" !== thread?.status) {
      return;
    }
    const botBody = Array.isArray(thread.comments)
      ? thread.comments.find((comment) => comment?.body)?.body || ""
      : "";
    const reviewer = reviewerFromBotBody(botBody);
    const title = titleFromBotBody(botBody);
    items.push({
      path: normalizePath(thread.path || thread.comments?.[0]?.path || ""),
      theme: themeFromReviewSignal({
        reviewer,
        title,
        body: botBody,
      }),
      reviewer: normalizeReviewerName(reviewer) || null,
      status: thread.status,
      title: title || thread.comments?.[0]?.body?.slice(0, 80) || "",
      thread_id: thread.thread_id || null,
    });
  });
  return items;
};

export const findingMatchesWaivedItem = (finding, waivedItem) => {
  if (null == finding || null == waivedItem) {
    return false;
  }
  const findingPaths = Array.isArray(finding?.locations)
    ? finding.locations.map((location) => normalizePath(location?.path))
    : [normalizePath("")];
  const waivedPath = normalizePath(waivedItem.path);
  if ("" === waivedPath) {
    return false;
  }
  const samePath = findingPaths.some((findingPath) => {
    if ("" === findingPath) {
      return false;
    }
    return (
      findingPath === waivedPath ||
      findingPath.endsWith(waivedPath) ||
      waivedPath.endsWith(findingPath)
    );
  });
  if (false === samePath) {
    return false;
  }
  const findingReviewer = normalizeReviewerName(finding?.reviewer);
  const waivedReviewer = normalizeReviewerName(waivedItem.reviewer);
  if ("" !== waivedReviewer && "" !== findingReviewer && waivedReviewer === findingReviewer) {
    return true;
  }
  const findingTheme = themeFromReviewSignal({
    reviewer: finding?.reviewer,
    title: finding?.title,
    body: `${finding?.rationale || ""} ${finding?.suggested_fix || ""}`,
  });
  return findingTheme === waivedItem.theme && "other" !== findingTheme;
};
