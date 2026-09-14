import { log } from "./logging.mjs";

const MAX_EVENTS_PER_POST = 200;

let runContext = null;
const events = [];

const toIso = (value) => {
  if (value instanceof Date) {
    return value.toISOString();
  }
  if ("string" === typeof value && "" !== value.trim()) {
    return value;
  }
  return new Date().toISOString();
};

const toNumber = (value) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
};

const toInt = (value) => Math.round(toNumber(value));

const compact = (value) => {
  if (null == value || "" === value) {
    return undefined;
  }
  if (true === Array.isArray(value) || "object" !== typeof value) {
    return value;
  }
  const next = {};
  Object.entries(value).forEach(([key, item]) => {
    const compacted = compact(item);
    if (undefined !== compacted) {
      next[key] = compacted;
    }
  });
  return 0 < Object.keys(next).length ? next : undefined;
};

export const codebaseFromRepo = ({ repoSlug, repoArg }) => {
  const slug = String(repoSlug || "").toLowerCase();
  const arg = String(repoArg || "").toLowerCase();
  if (slug.includes("builder-5") || arg.includes("builder-5")) {
    return "builder-5";
  }
  if (slug.includes("submodule-builder") || arg === "includes/builder") {
    return "builder";
  }
  if (slug.includes("submodule-core") || arg === "core") {
    return "core";
  }
  return "divi";
};

export const startUsageRun = (context) => {
  runContext = {
    ...(runContext || {}),
    ...context,
    startedAt:
      context.startedAt || runContext?.startedAt || new Date().toISOString(),
  };
};

export const recordUsageEvent = (event) => {
  if (null == event || "string" !== typeof event.provider || "string" !== typeof event.model) {
    return;
  }
  events.push(
    compact({
      occurred_at: toIso(event.occurredAt),
      subtask_name: event.subtaskName
        ? String(event.subtaskName).slice(0, 64)
        : undefined,
      provider: event.provider,
      model: event.model,
      tokens_in: toInt(event.tokensIn),
      tokens_out: toInt(event.tokensOut),
      cache_read_tokens: toInt(event.cacheReadTokens),
      cache_write_tokens: toInt(event.cacheWriteTokens),
      reasoning_tokens: toInt(event.reasoningTokens),
      token_source: event.tokenSource || "estimated",
      duration_ms:
        undefined === event.durationMs ? undefined : toInt(event.durationMs),
      status: event.status || "success",
      idempotency_key: event.idempotencyKey,
      raw_meta: event.rawMeta,
    })
  );
};

export const extractCursorUsageFromStdout = (stdout) => {
  const eventsFromStream = [];
  for (const line of String(stdout || "").split("\n")) {
    const trimmed = line.trim();
    if (!trimmed.startsWith("{")) {
      continue;
    }
    try {
      eventsFromStream.push(JSON.parse(trimmed));
    } catch {
      // Ignore non-JSON stream noise.
    }
  }
  let resultEvent = null;
  let model = null;
  for (const event of eventsFromStream) {
    if ("system" === event.type && "init" === event.subtype && event.model) {
      model = event.model;
    }
    if ("result" === event.type) {
      resultEvent = event;
    }
  }
  const usage = resultEvent?.usage || {};
  let text =
    "string" === typeof resultEvent?.result
      ? resultEvent.result
      : resultEvent?.result
        ? JSON.stringify(resultEvent.result)
        : "";
  if ("" === text) {
    const assistant = [...eventsFromStream]
      .reverse()
      .find((event) => "assistant" === event.type);
    const content = assistant?.message?.content;
    if (true === Array.isArray(content)) {
      text = content
        .map((part) => ("string" === typeof part?.text ? part.text : ""))
        .join("");
    }
  }
  return {
    text: text || String(stdout || "").trim(),
    hasResult: null != resultEvent,
    model: model || null,
    usage: {
      tokensIn: toInt(usage.inputTokens ?? usage.input_tokens),
      tokensOut: toInt(usage.outputTokens ?? usage.output_tokens),
      cacheReadTokens: toInt(usage.cacheReadTokens ?? usage.cache_read_tokens),
      cacheWriteTokens: toInt(
        usage.cacheWriteTokens ?? usage.cache_write_tokens
      ),
    },
    durationMs: resultEvent?.duration_ms ? toInt(resultEvent.duration_ms) : 0,
    requestId: resultEvent?.request_id || null,
    sessionId: resultEvent?.session_id || null,
    isError: true === resultEvent?.is_error,
  };
};

export const extractOpenAIResponseUsage = (response) => {
  const usage = response?.usage;
  if (null == usage) {
    return null;
  }
  const inputDetails = usage.input_tokens_details || {};
  return {
    tokensIn: toNumber(usage.input_tokens),
    tokensOut: toNumber(usage.output_tokens),
    cacheReadTokens: toNumber(inputDetails.cached_tokens),
    cacheWriteTokens: toNumber(inputDetails.cache_write_tokens),
    reasoningTokens: toNumber(usage.output_tokens_details?.reasoning_tokens),
    requestId: response.id || null,
    model: "string" === typeof response.model ? response.model : null,
  };
};

export const recordCursorAgentUsage = ({
  stdout,
  fallbackModel,
  durationMs,
  subtaskName,
  status,
}) => {
  const parsed = extractCursorUsageFromStdout(stdout);
  if (true !== parsed.hasResult) {
    return parsed;
  }
  recordUsageEvent({
    provider: "cursor",
    model: parsed.model || fallbackModel || "unknown",
    subtaskName,
    tokensIn: parsed.usage.tokensIn,
    tokensOut: parsed.usage.tokensOut,
    cacheReadTokens: parsed.usage.cacheReadTokens,
    cacheWriteTokens: parsed.usage.cacheWriteTokens,
    tokenSource: "estimated",
    durationMs: parsed.durationMs || durationMs,
    status: parsed.isError ? "error" : status || "success",
    idempotencyKey: parsed.requestId ? `cursor:${parsed.requestId}` : undefined,
    rawMeta: {
      type: "result",
      session_id: parsed.sessionId,
      request_id: parsed.requestId,
      duration_ms: parsed.durationMs || durationMs,
    },
  });
  return parsed;
};

export const recordOpenAIUsage = ({
  response,
  fallbackModel,
  durationMs,
  subtaskName,
  status,
}) => {
  const parsed = extractOpenAIResponseUsage(response);
  if (null == parsed) {
    return;
  }
  recordUsageEvent({
    provider: "openai",
    model: parsed.model || fallbackModel || "unknown",
    subtaskName: subtaskName || "summary",
    tokensIn: parsed.tokensIn,
    tokensOut: parsed.tokensOut,
    cacheReadTokens: parsed.cacheReadTokens,
    cacheWriteTokens: parsed.cacheWriteTokens,
    reasoningTokens: parsed.reasoningTokens,
    tokenSource: "exact",
    durationMs,
    status: status || "success",
    idempotencyKey: parsed.requestId ? `openai:${parsed.requestId}` : undefined,
    rawMeta: {
      object: "response",
      request_id: parsed.requestId,
    },
  });
};

const ingestEndpoint = () => {
  const base = (
    process.env.DEEPHIVE_USAGE_URL ||
    process.env.USAGE_INGEST_URL ||
    "https://deephive.staging.etdevs.com"
  ).replace(/\/$/, "");
  return `${base}/api/usage/ingest`;
};

const ingestToken = () =>
  process.env.USAGE_INGEST_TOKEN ||
  process.env.DEEPHIVE_USAGE_INGEST_TOKEN ||
  "";

export const flushUsage = async ({ status = "success", errorMessage } = {}) => {
  const token = ingestToken().trim();
  if ("" === token || null == runContext) {
    return;
  }
  const runPayload = {
    codebase: runContext.codebase || "divi",
    task_name: "pr_review",
    task_object_id: runContext.prNumber ? String(runContext.prNumber) : undefined,
    caller: "ai-review-orchestrator",
    git_repo: runContext.repoSlug || undefined,
    git_branch: runContext.headRef || undefined,
    idempotency_key: `pr-review:${runContext.repoSlug || "repo"}:${runContext.prNumber || "none"}:${runContext.runId || "run"}`,
    status,
    started_at: toIso(runContext.startedAt),
    ended_at: toIso(new Date()),
    outcome: errorMessage ? "error" : undefined,
  };
  const batches = [];
  if (0 === events.length) {
    batches.push([]);
  } else {
    for (let index = 0; index < events.length; index += MAX_EVENTS_PER_POST) {
      batches.push(events.slice(index, index + MAX_EVENTS_PER_POST));
    }
  }
  try {
    for (let index = 0; index < batches.length; index += 1) {
      const isLast = index === batches.length - 1;
      const response = await fetch(ingestEndpoint(), {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          run: {
            ...runPayload,
            status: isLast ? status : "running",
          },
          events: batches[index],
        }),
      });
      if (false === response.ok) {
        const body = await response.text();
        log(
          `usage: ingest ${response.status} ${body.slice(0, 300)}`,
          "usage"
        );
        return;
      }
    }
    log(
      `usage: ingested events=${events.length} task=pr_review object=${runPayload.task_object_id || "-"}`,
      "usage"
    );
  } catch (error) {
    log(`usage: ingest failed ${error.message}`, "usage");
  }
};
