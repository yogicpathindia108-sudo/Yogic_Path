import { minimatch } from "minimatch";

export const classifySize = (lineCount, config) => {
  if (null == config?.review_size) {
    return "medium";
  }
  const {
    tiny_max_lines: tinyMax,
    small_max_lines: smallMax,
    medium_max_lines: mediumMax,
    large_max_lines: largeMax,
  } = config.review_size;
  if (lineCount <= tinyMax) {
    return "tiny";
  }
  if (lineCount <= smallMax) {
    return "small";
  }
  if (lineCount <= mediumMax) {
    return "medium";
  }
  if (lineCount <= largeMax) {
    return "large";
  }
  return "huge";
};

export const rankFilesBySize = (files) =>
  [...files].sort((a, b) => {
    const sizeA = (a.additions ?? 0) + (a.deletions ?? 0);
    const sizeB = (b.additions ?? 0) + (b.deletions ?? 0);
    return sizeB - sizeA;
  });

const normalizeFilePath = (filePath) =>
  String(filePath || "").replace(/^(\.\/)+/, "");

const pathMatchesSet = (filePath, pathSet) => {
  const normalized = normalizeFilePath(filePath);
  if ("" === normalized) {
    return false;
  }
  if (pathSet.has(normalized)) {
    return true;
  }
  for (const candidate of pathSet) {
    if (
      normalized.endsWith(candidate) ||
      candidate.endsWith(normalized)
    ) {
      return true;
    }
  }
  return false;
};

export const hasPathMatch = (filePath, patterns) =>
  patterns.some((pattern) => minimatch(filePath, pattern, { dot: true }));

export const DEFAULT_LOW_SIGNAL_GLOBS = [
  "**/__snapshots__/**",
  "**/*.snap",
  "**/module.json",
  "**/module-default-printed-style-attributes.json",
  "**/module-default-render-attributes.json",
  "**/conversion-outline.json",
  "**/_all_modules_metadata.php",
  "**/_all_modules_default_render_attributes.php",
  "**/_all_modules_default_printed_style_attributes.php",
];

export const isLowSignalReviewFile = (
  filePath,
  patterns = DEFAULT_LOW_SIGNAL_GLOBS
) => null != filePath && hasPathMatch(filePath, patterns);

export const buildRequiredReviewers = (facts) => {
  const reviewers = new Set();
  const codeFiles = Array.isArray(facts.codeFiles) ? facts.codeFiles : [];
  const hasRetroFeedback =
    true === facts?.retroReview?.enabled &&
    (0 < Number(facts?.retroReview?.thread_count || 0) ||
      Number(facts?.retroReview?.review_round || 1) >= 2);

  const hasJsTs = codeFiles.some((filePath) =>
    hasPathMatch(filePath, ["**/*.js", "**/*.jsx", "**/*.ts", "**/*.tsx"])
  );
  const hasMigrations = codeFiles.some((filePath) =>
    hasPathMatch(filePath, ["**/migrations/**", "**/database/**", "**/schema/**"])
  );
  const hasAttrIntegritySignals = codeFiles.some((filePath) =>
    /(attrs|attrs-map|attrsmap|attr-map|attrmap|group-preset|grouppreset|renderattrs|styleattrs|dynamicoptiongroups|clipboard|right-click-options|modal-library|update-attribute|parse-serialized|serialize|module-utils|module-library)/i.test(
      filePath
    )
  );

  [
    "review-change-quality",
    "review-architecture-specs",
    "review-security",
    "review-performance",
    "review-test-quality",
  ].forEach((name) => reviewers.add(name));

  if (hasJsTs) {
    reviewers.add("review-types");
  }
  if (hasMigrations || hasAttrIntegritySignals) {
    reviewers.add("review-data-lifecycle");
  }
  if (hasRetroFeedback) {
    reviewers.add("review-retro-feedback");
  }

  return reviewers;
};

export const selectReviewerFiles = ({
  reviewer,
  summaries,
  maxFiles = 12,
  deltaPaths = null,
  keepPaths = null,
  deltaOnly = false,
  noNewDelta = false,
  preferKeepPaths = false,
  lowSignalGlobs = DEFAULT_LOW_SIGNAL_GLOBS,
}) => {
  const files = summaries?.files || [];
  if (0 === files.length) {
    return [];
  }
  if (true === deltaOnly && true === noNewDelta && false === preferKeepPaths) {
    return [];
  }
  const excludeLowSignal = (file) =>
    false === isLowSignalReviewFile(file.path, lowSignalGlobs);
  if (true === preferKeepPaths && Array.isArray(keepPaths) && 0 < keepPaths.length) {
    const keepSet = new Set(
      keepPaths.map((filePath) => normalizeFilePath(filePath)).filter(Boolean)
    );
    const kept = rankFilesBySize(
      files.filter((file) => pathMatchesSet(file.path, keepSet))
    );
    if (0 < kept.length) {
      return kept.slice(0, maxFiles);
    }
  }
  const lowerKeywords = (reviewer.keywords || []).map((keyword) =>
    keyword.toLowerCase()
  );
  const matched = files.filter((file) => {
    if (false === excludeLowSignal(file)) {
      return false;
    }
    const pathMatch = (reviewer.globs || []).some((glob) =>
      minimatch(file.path, glob, { dot: true })
    );
    if (pathMatch) {
      return true;
    }
    if (0 === lowerKeywords.length) {
      return false;
    }
    const haystack = `${file.path} ${file.summary || ""}`.toLowerCase();
    return lowerKeywords.some((keyword) => haystack.includes(keyword));
  });
  const ranked = rankFilesBySize(matched);
  const restrictToDelta =
    true === deltaOnly && Array.isArray(deltaPaths) && 0 < deltaPaths.length;
  let selected = ranked;
  if (true === restrictToDelta) {
    const deltaSet = new Set(
      deltaPaths.map((filePath) => normalizeFilePath(filePath)).filter(Boolean)
    );
    const keepSet = new Set(
      (keepPaths || [])
        .map((filePath) => normalizeFilePath(filePath))
        .filter(Boolean)
    );
    selected = ranked.filter(
      (file) =>
        pathMatchesSet(file.path, deltaSet) || pathMatchesSet(file.path, keepSet)
    );
  }
  if (0 < selected.length) {
    return selected.slice(0, maxFiles);
  }
  if (true === restrictToDelta) {
    return [];
  }
  return rankFilesBySize(files.filter(excludeLowSignal)).slice(
    0,
    Math.min(maxFiles, 5)
  );
};

export const resolveReviewerRuns = ({ reviewer, sizeKey, config }) => {
  const configRuns = config?.reviewer_runs_by_size?.[sizeKey];
  const reviewerRuns = reviewer?.runsBySize?.[sizeKey];
  let resolved = Number.isFinite(reviewerRuns)
    ? reviewerRuns
    : Number.isFinite(configRuns)
      ? configRuns
      : 1;
  if (Number.isFinite(reviewer?.maxRuns)) {
    resolved = Math.min(resolved, reviewer.maxRuns);
  }
  if (!Number.isFinite(resolved) || resolved < 1) {
    return 1;
  }
  return Math.max(1, Math.floor(resolved));
};

export const resolveReviewerModels = ({ reviewer, reviewerModel, config, runCount }) => {
  const preferred =
    Array.isArray(reviewer?.models) && reviewer.models.length
      ? reviewer.models
      : config?.reviewer_models;
  const baseModels =
    Array.isArray(preferred) && preferred.length
      ? preferred
      : null != reviewerModel
        ? [reviewerModel]
        : [];
  if (0 === baseModels.length) {
    return Array.from({ length: runCount }, () => null);
  }
  return Array.from({ length: runCount }, (_, index) =>
    baseModels[index % baseModels.length]
  );
};
