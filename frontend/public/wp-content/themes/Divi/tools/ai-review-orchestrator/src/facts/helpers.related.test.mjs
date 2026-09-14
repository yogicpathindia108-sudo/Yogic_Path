import assert from "node:assert/strict";
import test from "node:test";

import {
  buildRelatedPath,
  isRelatedReviewFile,
  parseRelatedRepoPath,
  resolveFindingReviewTarget,
  stripRelatedPrefix,
} from "./helpers.mjs";

test("parseRelatedRepoPath splits decorated companion paths", () => {
  const parsed = parseRelatedRepoPath(
    "related/elegantthemes/ai-server/src/services/divi-generate-layout/compile-prompt-to-ir.ts"
  );
  assert.deepEqual(parsed, {
    repoSlug: "elegantthemes/ai-server",
    originalPath:
      "src/services/divi-generate-layout/compile-prompt-to-ir.ts",
  });
  assert.equal(
    parseRelatedRepoPath("visual-builder/packages/ai-agent/src/index.ts"),
    null
  );
});

test("isRelatedReviewFile uses source_repo or related/ prefix", () => {
  assert.equal(
    isRelatedReviewFile({
      path: "related/elegantthemes/ai-server/src/index.ts",
      source_repo: "elegantthemes/ai-server",
    }),
    true
  );
  assert.equal(
    isRelatedReviewFile({
      path: "visual-builder/packages/ai-agent/src/index.ts",
    }),
    false
  );
});

test("stripRelatedPrefix and buildRelatedPath round-trip", () => {
  const original = "src/services/open-router/models/service.ts";
  const decorated = buildRelatedPath("elegantthemes/ai-server", original);
  assert.equal(
    decorated,
    "related/elegantthemes/ai-server/src/services/open-router/models/service.ts"
  );
  assert.equal(stripRelatedPrefix(decorated), original);
  assert.equal(stripRelatedPrefix(original), original);
});

test("resolveFindingReviewTarget keeps this-PR files on the originating PR", () => {
  const target = resolveFindingReviewTarget(
    {
      locations: [
        {
          path: "visual-builder/packages/ai-agent/src/index.ts",
        },
      ],
    },
    {
      repoSlug: "elegantthemes/submodule-builder-5",
      prMeta: { number: 9973 },
      fileMetadata: [
        { path: "visual-builder/packages/ai-agent/src/index.ts" },
      ],
    }
  );
  assert.equal(target.kind, "this_pr");
  assert.equal(target.prNumber, 9973);
});

test("resolveFindingReviewTarget routes related files to the companion PR", () => {
  const target = resolveFindingReviewTarget(
    {
      locations: [
        {
          path: "related/elegantthemes/ai-server/src/services/divi-generate-layout/compile-prompt-to-ir.ts",
        },
      ],
    },
    {
      repoSlug: "elegantthemes/submodule-builder-5",
      prMeta: { number: 9973 },
      relatedPrs: [{ repoSlug: "elegantthemes/ai-server", prNumber: 469 }],
      fileMetadata: [
        {
          path: "related/elegantthemes/ai-server/src/services/divi-generate-layout/compile-prompt-to-ir.ts",
          source_repo: "elegantthemes/ai-server",
          source_pr: 469,
          original_path:
            "src/services/divi-generate-layout/compile-prompt-to-ir.ts",
        },
      ],
    }
  );
  assert.equal(target.kind, "related");
  assert.equal(target.repoSlug, "elegantthemes/ai-server");
  assert.equal(target.prNumber, 469);
  assert.equal(
    target.originalPath,
    "src/services/divi-generate-layout/compile-prompt-to-ir.ts"
  );
});
