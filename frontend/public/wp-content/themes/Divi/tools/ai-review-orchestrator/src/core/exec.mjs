import { spawnSync } from "node:child_process";

import { log } from "./logging.mjs";

const MAX_STDIO_BUFFER = 1024 * 1024 * 25;

const truncateOutput = (value, limit = 2000) => {
  if (null == value) {
    return "";
  }
  const trimmed = String(value).trim();
  if (trimmed.length <= limit) {
    return trimmed;
  }
  return `${trimmed.slice(0, limit)}... (truncated)`;
};

export const run = (command, commandArgs, options = {}) => {
  log(`run: ${command} ${commandArgs.join(" ")}`);
  const result = spawnSync(command, commandArgs, {
    encoding: "utf8",
    maxBuffer: MAX_STDIO_BUFFER,
    ...options,
  });
  if (result.error) {
    throw new Error(
      `${command} failed (${result.error.code || "error"}): ${result.error.message}`
    );
  }
  if (0 !== result.status) {
    const output = truncateOutput(result.stderr || result.stdout);
    const status = null == result.status ? "null" : result.status;
    throw new Error(`${command} failed (${status}): ${output}`);
  }
  return result.stdout.trimEnd();
};

export const runJson = (command, commandArgs, options = {}) =>
  JSON.parse(run(command, commandArgs, options));
