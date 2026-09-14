import fs from "node:fs";
import path from "node:path";

import yaml from "js-yaml";

import { log } from "../core/logging.mjs";
import {
  normalizeFrontmatterList,
  normalizeFrontmatterSizeMap,
} from "../core/reviewers.mjs";

export const loadReviewerDefinitions = (repoRoot) => {
  const agentsDir = path.join(repoRoot, "tools/ai-review-orchestrator/reviewers");
  if (false === fs.existsSync(agentsDir)) {
    log(`reviewers: missing ${agentsDir}`);
    return [];
  }
  const files = fs
    .readdirSync(agentsDir)
    .filter((file) => file.startsWith("review-") && file.endsWith(".md"));
  log(`reviewers: found ${files.length} reviewers`);
  const reviewers = files.map((file) => {
    const fullPath = path.join(agentsDir, file);
    const contents = fs.readFileSync(fullPath, "utf8");
    const parts = contents.split("---");
    let frontmatter = {};
    let body = contents.trim();
    if (parts.length >= 3 && "" === parts[0].trim()) {
      frontmatter = yaml.load(parts[1]) || {};
      body = parts.slice(2).join("---").trim();
    }
    return {
      name: frontmatter.name || file.replace(".md", ""),
      description: frontmatter.description || "",
      model: frontmatter.model || "inherit",
      models: normalizeFrontmatterList(frontmatter.models),
      runsBySize: normalizeFrontmatterSizeMap(frontmatter.runs_by_size),
      maxRuns:
        null == frontmatter.max_runs || Number.isNaN(Number(frontmatter.max_runs))
          ? null
          : Number(frontmatter.max_runs),
      globs: normalizeFrontmatterList(frontmatter.globs),
      keywords: normalizeFrontmatterList(frontmatter.keywords),
      body,
    };
  });
  const filtered = reviewers.filter(
    (reviewer) => "review-orchestrator" !== reviewer.name
  );
  if (filtered.length !== reviewers.length) {
    log("reviewers: skipped review-orchestrator (reserved)");
  }
  return filtered;
};
