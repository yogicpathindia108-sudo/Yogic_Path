import { spawn, spawnSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";

import { log } from "../core/logging.mjs";
import { recordCursorAgentUsage } from "../core/usage.mjs";
import { parseJsonSafe } from "../core/utils.mjs";

export const buildAgentSpawn = ({ args, prompt, hasCursorKey }) => {
  if (true === hasCursorKey) {
    return {
      command: "env",
      commandArgs: [
        `CURSOR_API_KEY=${process.env.CURSOR_API_KEY}`,
        "agent",
        ...args,
        prompt,
      ],
    };
  }
  return { command: "agent", commandArgs: [...args, prompt] };
};

export const assertAgentAuth = (model) => {
  const modelArg = "string" === typeof model ? model.trim() : "";
  const args = ["--mode", "ask", "--print", "--output-format", "text", "--trust"];
  if ("" !== modelArg) {
    args.push("--model", modelArg);
  }
  const hasCursorKey =
    "string" === typeof process.env.CURSOR_API_KEY &&
    "" !== process.env.CURSOR_API_KEY.trim();
  if (false === hasCursorKey) {
    throw new Error(
      "Agent auth check failed: CURSOR_API_KEY is empty."
    );
  }
  const { command, commandArgs } = buildAgentSpawn({
    args,
    prompt: "ping",
    hasCursorKey,
  });
  const maxAttempts = 3;
  let lastOutput = "";
  for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
    log(
      `agent: auth check ${modelArg || "default"} (attempt=${attempt + 1})`,
      "auth"
    );
    const result = spawnSync(command, commandArgs, {
      encoding: "utf8",
      env: process.env,
    });
    if (0 === result.status) {
      return;
    }
    lastOutput = `${result.stderr || result.stdout}`.trim();
    if (attempt >= maxAttempts - 1) {
      break;
    }
    log("agent: auth check failed, retrying", "auth");
    spawnSync("sleep", [String(2 * (attempt + 1))], { stdio: "ignore" });
  }
  throw new Error(`Agent auth check failed: ${lastOutput}`.trim());
};

export const normalizeReviewerOutput = (rawText) => {
  const text = String(rawText || "").trim();
  if ("" === text) {
    return { cleanText: text, parsed: null };
  }
  const fenceMatch = text.match(/```(?:json)?\s*([\s\S]*?)\s*```/i);
  let candidate = text;
  let extras = "";
  if (fenceMatch) {
    const start = fenceMatch.index ?? 0;
    const end = start + fenceMatch[0].length;
    const before = text.slice(0, start).trim();
    const after = text.slice(end).trim();
    extras = [before, after].filter(Boolean).join("\n\n");
    candidate = String(fenceMatch[1] || "").trim();
  }
  const parsed = parseJsonSafe(candidate);
  if (null == parsed) {
    return { cleanText: text, parsed: null };
  }
  if (extras) {
    parsed.notes = parsed.notes
      ? `${parsed.notes}\n\n${extras}`
      : extras;
  }
  return { cleanText: JSON.stringify(parsed), parsed };
};

export const resolveConcurrency = (value, fallback) => {
  const parsed = Number(value);
  if (false === Number.isFinite(parsed) || parsed < 1) {
    return fallback;
  }
  return Math.max(1, Math.floor(parsed));
};

export const mapWithConcurrency = async (items, limit, iterator, staggerMs = 0) => {
  const results = new Array(items.length);
  if (0 === items.length) {
    return results;
  }
  const concurrency = Math.max(1, Math.min(limit, items.length));
  let nextIndex = 0;
  let nextLaunchAt = Date.now();
  const workers = Array.from({ length: concurrency }, async () => {
    while (true) {
      const current = nextIndex;
      if (current >= items.length) {
        return;
      }
      nextIndex += 1;
      if (staggerMs > 0) {
        const now = Date.now();
        const waitMs = Math.max(0, nextLaunchAt - now);
        nextLaunchAt = Math.max(now, nextLaunchAt) + staggerMs;
        if (waitMs > 0) {
          await new Promise((resolve) => setTimeout(resolve, waitMs));
        }
      }
      results[current] = await iterator(items[current], current);
    }
  });
  await Promise.all(workers);
  return results;
};

export const callAgent = (prompt, { model, workspace, timeoutMs, scope }) =>
  new Promise((resolve, reject) => {
    const modelArg = "string" === typeof model ? model.trim() : "";
    const hasCursorKey =
      "string" === typeof process.env.CURSOR_API_KEY &&
      "" !== process.env.CURSOR_API_KEY.trim();
    const args = [
      "--mode",
      "ask",
      "--print",
      "--output-format",
      "stream-json",
      "--force",
      "--trust",
    ];
    if ("" !== modelArg) {
      args.push("--model", modelArg);
    }
    if (null !== workspace) {
      args.push("--workspace", workspace);
    }
    const promptFile = path.join(
      os.tmpdir(),
      `ai-review-orch-prompt-${Date.now()}.txt`
    );
    fs.writeFileSync(promptFile, prompt, "utf8");
    // log(`agent: prompt saved ${promptFile}`, scope);
    // log(`agent: cmd agent ${args.join(" ")} "$(cat ${promptFile})"`, scope);
    args.push(prompt);
    const envForAgent = { ...process.env };
    const outputLogMs = Number.parseInt(
      process.env.AI_REVIEW_AGENT_OUTPUT_LOG_MS || "60000",
      10
    );
    let timeoutHandle = null;
    let lastOutputLogAt = 0;
    const clearTimers = () => {
      if (timeoutHandle) {
        clearTimeout(timeoutHandle);
      }
      timeoutHandle = null;
    };
    let settled = false;
    const settle = (handler) => {
      if (true === settled) {
        return;
      }
      settled = true;
      clearTimers();
      handler();
    };
    const startAgent = (attempt) => {
      const startTime = Date.now();
      let timedOut = false;
      log(
        `agent: start ${modelArg || "default"} (attempt=${attempt + 1})`,
        scope
      );
      const { command, commandArgs } = buildAgentSpawn({
        args,
        prompt,
        hasCursorKey,
      });
      const child = spawn(command, commandArgs, {
        encoding: "utf8",
        env: envForAgent,
      });
      let stdout = "";
      let stderr = "";
      const logOutputActivity = (label) => {
        if (!Number.isFinite(outputLogMs) || outputLogMs <= 0) {
          return;
        }
        const now = Date.now();
        if (now - lastOutputLogAt < outputLogMs) {
          return;
        }
        lastOutputLogAt = now;
        log(
          `agent: output ${label} bytes=${stdout.length + stderr.length}`,
          scope
        );
      };
      const finishAgent = (status, durationMs) => {
        const parsed = recordCursorAgentUsage({
          stdout,
          fallbackModel: modelArg,
          durationMs,
          subtaskName: scope,
          status,
        });
        return parsed;
      };
      timeoutHandle =
        null !== timeoutMs
          ? setTimeout(() => {
            timedOut = true;
            child.kill("SIGKILL");
          }, timeoutMs)
          : null;
      child.stdout.on("data", (data) => {
        stdout += data.toString();
        logOutputActivity("stdout");
      });
      child.stderr.on("data", (data) => {
        stderr += data.toString();
        logOutputActivity("stderr");
      });
      child.on("error", (error) => {
        log(`agent: spawn error ${error.message}`, scope);
        settle(() => reject(error));
      });
      child.on("close", (code) => {
        const durationMs = Date.now() - startTime;
        if (true === timedOut) {
          finishAgent("timeout", durationMs);
          log(`agent: timeout (${durationMs}ms)`, scope);
          settle(() =>
            reject(new Error(`agent timed out after ${timeoutMs}ms.`))
          );
          return;
        }
        if (0 !== code) {
          const output = stderr || stdout;
          const authError =
            output.includes("cursor-access-token") ||
            output.includes("Security command failed") ||
            output.includes("Authentication required");
          if (true === authError && 0 === attempt) {
            log("agent: auth error, retrying once", scope);
            clearTimers();
            startAgent(1);
            return;
          }
          finishAgent("error", durationMs);
          log(`agent: exit ${code}`, scope);
          settle(() => reject(new Error(`agent exited ${code}: ${output}`)));
          return;
        }
        const parsed = finishAgent("success", durationMs);
        log(`agent: done (${durationMs}ms)`, scope);
        settle(() => resolve(parsed.text.trim()));
      });
    };
    startAgent(0);
  });
