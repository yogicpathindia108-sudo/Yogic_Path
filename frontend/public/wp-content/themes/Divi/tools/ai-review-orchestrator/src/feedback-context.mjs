export const CONTROLLED_TAGS = [
  "auth",
  "capability",
  "nonce",
  "csrf",
  "xss",
  "sql",
  "rest",
  "ajax",
  "sanitization",
  "escaping",
  "persistence",
  "attrs",
  "conversion",
  "types",
  "any-cast",
  "tests",
  "snapshots",
  "coverage",
  "performance",
  "n-plus-1",
  "architecture",
  "spec",
  "spec-map",
  "module-json",
  "php",
  "typescript",
  "visual-builder",
  "error-handling",
  "race",
  "redux",
  "module-library",
  "field-library",
];

export const FEEDBACK_KINDS = [
  "security",
  "correctness",
  "tests",
  "types",
  "performance",
  "architecture",
  "nit",
  "process",
];

export const FEEDBACK_SIGNALS = ["high", "medium", "low"];

const CODE_SIGNAL_PATTERNS = [
  "register_rest_route",
  "permission_callback",
  "current_user_can",
  "check_ajax_referer",
  "wp_verify_nonce",
  "wp_kses",
  "sanitize_text_field",
  "esc_html",
  "esc_attr",
  "$wpdb",
  "prepare",
  "unfiltered_html",
  "update_post_meta",
  "update_option",
  "serialize_blocks",
  "asMutable",
  "useSelect",
  "useDispatch",
  "WP_Error",
  "__return_true",
];

const unique = (values) => [...new Set(values.filter(Boolean))];

export const sanitizeApiText = (value) =>
  String(value ?? "")
    .replace(/\u0000/g, "")
    .replace(/[\u2028\u2029]/g, "\n")
    .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F]/g, "")
    .replace(
      /[\uD800-\uDBFF](?![\uDC00-\uDFFF])|(?<![\uD800-\uDBFF])[\uDC00-\uDFFF]/g,
      ""
    );

export const truncateText = (value, limit) => {
  if (null == value) {
    return "";
  }
  const trimmed = sanitizeApiText(value).trim();
  if (trimmed.length <= limit) {
    return trimmed;
  }
  return `${trimmed.slice(0, limit)}\n... (truncated)`;
};

export const areaFromPath = (filePath) => {
  if (null == filePath || "" === String(filePath).trim()) {
    return "";
  }
  const parts = String(filePath)
    .replace(/\\/g, "/")
    .split("/")
    .filter(Boolean);
  return parts.slice(0, 3).join("/");
};

export const derivePathTags = (filePath) => {
  if (null == filePath || "" === String(filePath).trim()) {
    return [];
  }
  const normalized = String(filePath).replace(/\\/g, "/");
  const tags = [];
  if (/(?:^|\/)(?:__tests__|__mocks__)\/|\.(?:test|spec)\.[^.]+$|Test\.php$/i.test(normalized)) {
    tags.push("tests");
  }
  if (/(?:^|\/)__snapshots__\/|\.snap$/i.test(normalized)) {
    tags.push("snapshots");
  }
  if (/\.php$/i.test(normalized)) {
    tags.push("php");
  }
  if (/\.tsx?$/i.test(normalized)) {
    tags.push("typescript");
  }
  if (/\/REST\//i.test(normalized) || /\/rest\//i.test(normalized)) {
    tags.push("rest");
  }
  if (/module-library/.test(normalized)) {
    tags.push("module-library");
  }
  if (/field-library/.test(normalized)) {
    tags.push("field-library");
  }
  if (/visual-builder/.test(normalized)) {
    tags.push("visual-builder");
  }
  if (/\/store\//.test(normalized)) {
    tags.push("redux");
  }
  if (/conversion/.test(normalized)) {
    tags.push("conversion");
  }
  if (/\/types\//.test(normalized)) {
    tags.push("types");
  }
  if (/\/specs?\//i.test(normalized) || /spec\.md$/i.test(normalized)) {
    tags.push("spec");
  }
  return unique(tags);
};

export const trimDiffHunk = (hunk, maxLines = 40) => {
  if (null == hunk || "" === String(hunk).trim()) {
    return "";
  }
  const lines = String(hunk)
    .split("\n")
    .filter((line) => {
      if (line.startsWith("+++") || line.startsWith("---")) {
        return false;
      }
      return line.startsWith("+") || line.startsWith("-") || line.startsWith("@@");
    });
  return lines.slice(0, maxLines).join("\n");
};

export const extractCodeSignals = (...texts) => {
  const haystack = texts.filter(Boolean).join("\n");
  if ("" === haystack) {
    return [];
  }
  return CODE_SIGNAL_PATTERNS.filter((token) => haystack.includes(token));
};

const normalizeTag = (value) =>
  String(value || "")
    .trim()
    .toLowerCase()
    .replace(/\s+/g, "-")
    .replace(/[^a-z0-9._-]/g, "");

export const coerceNormalization = (parsed) => {
  if (null == parsed || "object" !== typeof parsed) {
    return {
      keep: true,
      reason: "",
      claim: "",
      kind: "correctness",
      signal: "medium",
      tags: [],
      reviewer_hint: "",
    };
  }
  const kind = FEEDBACK_KINDS.includes(parsed.kind)
    ? parsed.kind
    : "correctness";
  const signal = FEEDBACK_SIGNALS.includes(parsed.signal)
    ? parsed.signal
    : "medium";
  return {
    keep: true === parsed.keep,
    reason: String(parsed.reason || ""),
    claim: String(parsed.claim || "").trim(),
    kind,
    signal,
    tags: Array.isArray(parsed.tags) ? parsed.tags.map(String) : [],
    reviewer_hint: String(parsed.reviewer_hint || "").trim(),
  };
};

export const parseNormalizationJson = (json) => {
  if (null == json || "" === String(json).trim()) {
    return null;
  }
  try {
    return coerceNormalization(JSON.parse(json));
  } catch (error) {
    return null;
  }
};

export const buildTagRecords = ({ pathTags, nanoTags, kind }) => {
  const allowed = new Set(CONTROLLED_TAGS);
  const records = [];
  const seen = new Set();
  const add = (tag, source) => {
    const normalized = normalizeTag(tag);
    if ("" === normalized || true === seen.has(normalized)) {
      return;
    }
    if ("process" === normalized) {
      return;
    }
    seen.add(normalized);
    records.push({ tag: normalized, source });
  };
  (pathTags || []).forEach((tag) => add(tag, "path"));
  (nanoTags || []).forEach((tag) => {
    if (true === allowed.has(normalizeTag(tag))) {
      add(tag, "nano");
    }
  });
  if (kind && "nit" !== kind && "process" !== kind) {
    add(kind, "kind");
  }
  return records;
};

export const mergeTags = ({ pathTags, nanoTags, kind }) =>
  buildTagRecords({ pathTags, nanoTags, kind }).map((entry) => entry.tag);

export const buildEmbedDocument = ({
  claim,
  hunk,
  body,
  codeSignals,
}) => {
  const trimmedHunk = trimDiffHunk(hunk);
  const signals = unique([
    ...(codeSignals || []),
    ...extractCodeSignals(trimmedHunk, body, claim),
  ]);
  return [
    claim ? `CLAIM: ${claim}` : "",
    signals.length ? `CODE_SIGNALS: ${signals.join(", ")}` : "",
    trimmedHunk ? `CODE:\n${truncateText(trimmedHunk, 1200)}` : "",
    body ? `FEEDBACK:\n${truncateText(body, 1800)}` : "",
  ]
    .filter(Boolean)
    .join("\n");
};

export const buildNormalizePrompt = ({
  body,
  path,
  hunk,
  prTitle,
  prSummary,
}) => [
  {
    role: "system",
    content: [
      "You normalize a single PR review comment into a generalized, reusable claim.",
      "The claim must describe the class of defect, not the specific PR, module, or issue number.",
    ].join(" "),
  },
  {
    role: "user",
    content: [
      "Return JSON only.",
      "",
      "keep=true only when the COMMENT ITSELF states an actionable defect, missing test, or reviewer-worthy pattern.",
      "keep=false for acknowledgements, status updates, bot noise, 'I fixed it' replies, 'I have small feedback' with no details, praise-only, or comments that are only a commit/PR link.",
      "Do not invent a claim from the PR title or PR summary. Those are context. If the comment has no actionable content of its own, keep=false and leave claim empty.",
      "",
      "claim: one sentence, generalized. No issue numbers, no unique module/product names unless the pattern is inherently about that API.",
      `kind: one of ${FEEDBACK_KINDS.join(", ")}.`,
      "signal: high = merge-blocking correctness/security/data-loss; medium = real maintainability or coverage gap; low = nit/style/process.",
      `tags: 1-6 items from this list only: ${CONTROLLED_TAGS.join(", ")}.`,
      "reviewer_hint: one of review-security, review-test-quality, review-types, review-performance, review-architecture-specs, review-change-quality, review-data-lifecycle, or empty string.",
      "",
      `PR title: ${prTitle || "(none)"}`,
      `PR summary: ${prSummary || "(none)"}`,
      `File: ${path || "(none)"}`,
      "",
      "Code hunk:",
      trimDiffHunk(hunk) || "(none)",
      "",
      "Comment:",
      truncateText(body, 4000),
    ].join("\n"),
  },
];

export const normalizeSchema = {
  type: "object",
  properties: {
    keep: { type: "boolean" },
    reason: { type: "string" },
    claim: { type: "string" },
    kind: { type: "string", enum: FEEDBACK_KINDS },
    signal: { type: "string", enum: FEEDBACK_SIGNALS },
    tags: {
      type: "array",
      items: { type: "string" },
    },
    reviewer_hint: { type: "string" },
  },
  required: ["keep", "reason", "claim", "kind", "signal", "tags", "reviewer_hint"],
  additionalProperties: false,
};

export const buildPrSummaryPrompt = ({ title, body }) => [
  {
    role: "system",
    content:
      "Summarize a pull request in one sentence: what changed, not why it is good.",
  },
  {
    role: "user",
    content: [
      "Return JSON only: { \"summary\": string }.",
      "No issue numbers. No marketing. One sentence.",
      "",
      `Title: ${title || "(none)"}`,
      "",
      "Body:",
      truncateText(body, 4000) || "(none)",
    ].join("\n"),
  },
];

export const prSummarySchema = {
  type: "object",
  properties: {
    summary: { type: "string" },
  },
  required: ["summary"],
  additionalProperties: false,
};
