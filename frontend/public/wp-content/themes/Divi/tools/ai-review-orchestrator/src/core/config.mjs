import fs from "node:fs";
import path from "node:path";

import yaml from "js-yaml";

import { log } from "./logging.mjs";

export const loadConfig = (repoRoot) => {
  const configPath = path.join(
    repoRoot,
    "tools/ai-review-orchestrator/config.yml"
  );
  if (false === fs.existsSync(configPath)) {
    log(`config: missing ${configPath}`);
    return null;
  }
  const contents = fs.readFileSync(configPath, "utf8");
  log(`config: loaded ${configPath}`);
  return yaml.load(contents);
};
