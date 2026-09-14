import path from "node:path";
import { fileURLToPath } from "node:url";

export const TASK_PATH_PREFIXES = [
  ".cursor/tasks/",
  "et/tasks/",
  "includes/builder-5/.et/tasks/",
  "includes/builder-5/et/tasks/",
];
export const OUTPUT_DIR = "tools/ai-review-orchestrator/output";
export const SUMMARY_MODEL = "gpt-5.4-nano";
export const RETRO_DUPE_MODEL = "gpt-5.4-nano";
export const SUMMARY_CACHE_VERSION = 1;
export const DEFAULT_SUMMARY_CACHE_DIR = path.join(
  path.dirname(fileURLToPath(import.meta.url)),
  "..",
  "..",
  ".cache",
  "summaries"
);
