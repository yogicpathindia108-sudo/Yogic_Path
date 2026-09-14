import { RETRO_DUPE_MODEL } from "../core/constants.mjs";
import { log } from "../core/logging.mjs";
import { getOpenAIClient } from "../core/openai.mjs";
import { parseJsonSafe } from "../core/utils.mjs";
import { findingMatchesWaivedItem } from "./waiver.mjs";

const normalizeFindingKey = (finding) => {
  const title = String(finding?.title || "")
    .trim()
    .toLowerCase()
    .replace(/\s+/g, " ");
  const location = Array.isArray(finding?.locations)
    ? finding.locations[0]?.path ?? ""
    : "";
  const normalizedLocation = location ? location.replace(/^(\.\/)+/, "") : "";
  return `${title}::${normalizedLocation}`.trim();
};

const hasDiffEvidence = (diff, locationPath) => {
  if (null == diff || "" === diff || null == locationPath) {
    return false;
  }
  const normalized = locationPath.replace(/^(\.\/)+/, "");
  return (
    diff.includes(`diff --git a/${normalized} b/${normalized}`) ||
    diff.includes(`b/${normalized}`) ||
    diff.includes(`/${normalized}`)
  );
};

const applyWaivedFindingFilter = ({ retroReview, findings }) => {
  const waivedItems = Array.isArray(retroReview?.waived_items)
    ? retroReview.waived_items
    : [];
  if (0 === waivedItems.length) {
    return { filtered: findings || [], dropped: [] };
  }
  const dropped = [];
  const filtered = [];
  (findings || []).forEach((finding) => {
    const matched = waivedItems.some((item) =>
      findingMatchesWaivedItem(finding, item)
    );
    if (true === matched) {
      dropped.push(finding);
      return;
    }
    filtered.push(finding);
  });
  return { filtered, dropped };
};

const applyExactDuplicateFilter = ({ retroReview, findings }) => {
  const priorFindings = Array.isArray(retroReview?.prior_findings)
    ? retroReview.prior_findings
    : [];
  const diff = retroReview?.diff_since_last_run || "";
  if (0 === priorFindings.length || "" === diff) {
    return { filtered: findings || [], dropped: [], report: null };
  }
  const priorKeys = new Set(priorFindings.map((entry) => entry.key).filter(Boolean));
  const dropped = [];
  const filtered = [];
  findings.forEach((finding) => {
    const key = normalizeFindingKey(finding);
    const matches = "" !== key && priorKeys.has(key);
    if (false === matches) {
      filtered.push(finding);
      return;
    }
    const locations = Array.isArray(finding?.locations) ? finding.locations : [];
    const hasEvidence = locations.some((location) =>
      hasDiffEvidence(diff, location?.path)
    );
    if (true === hasEvidence) {
      filtered.push(finding);
      return;
    }
    dropped.push(finding);
  });
  return {
    filtered,
    dropped,
    report: {
      reason: "exact_duplicate_without_new_diff",
      dropped_count: dropped.length,
    },
  };
};

const applyLaterRoundDeltaFilter = ({ retroReview, findings }) => {
  const round = Number(retroReview?.review_round || 1);
  if (round < 2) {
    return { filtered: findings || [], dropped: [] };
  }
  const keepRetro = (finding) => "review-retro-feedback" === String(finding?.reviewer || "");
  if (true === retroReview?.same_sha_as_last_review) {
    const dropped = [];
    const filtered = [];
    (findings || []).forEach((finding) => {
      if (true === keepRetro(finding)) {
        filtered.push(finding);
        return;
      }
      dropped.push(finding);
    });
    return { filtered, dropped };
  }
  const deltaPaths = Array.isArray(retroReview?.delta_paths)
    ? retroReview.delta_paths
    : [];
  if (0 === deltaPaths.length) {
    return { filtered: findings || [], dropped: [] };
  }
  const deltaSet = new Set(
    deltaPaths.map((filePath) => String(filePath || "").replace(/^(\.\/)+/, ""))
  );
  const dropped = [];
  const filtered = [];
  findings.forEach((finding) => {
    const reviewer = String(finding?.reviewer || "");
    if ("review-retro-feedback" === reviewer) {
      filtered.push(finding);
      return;
    }
    const locations = Array.isArray(finding?.locations) ? finding.locations : [];
    const inDelta = locations.some((location) => {
      const locationPath = String(location?.path || "").replace(/^(\.\/)+/, "");
      if ("" === locationPath) {
        return false;
      }
      if (deltaSet.has(locationPath)) {
        return true;
      }
      for (const deltaPath of deltaSet) {
        if (
          locationPath.endsWith(deltaPath) ||
          deltaPath.endsWith(locationPath)
        ) {
          return true;
        }
      }
      return false;
    });
    if (true === inDelta) {
      filtered.push(finding);
      return;
    }
    dropped.push(finding);
  });
  return { filtered, dropped };
};

const buildRetroDupePrompt = ({ retroReview, findings }) => [
  {
    role: "system",
    content: [
      "You are a dedupe filter for automated PR review findings.",
      "Your job is to remove findings that are true duplicates of prior feedback",
      "that has already been resolved, waived, or rebutted by the author.",
      "Always drop findings that match waived_items.",
      "Only keep a finding when it is clearly new.",
    ].join(" "),
  },
  {
    role: "user",
    content: [
      "Return JSON only with:",
      "{",
      '  "drop_finding_ids": ["finding_1", "..."],',
      '  "notes": "short explanation"',
      "}",
      "",
      "Rules:",
      "- drop_finding_ids should only include findings that are already addressed",
      "  in prior feedback and do not have new evidence in the diff since last run.",
      "- If a thread is rebutted or waived by the author, drop matching findings even when the title is reworded.",
      "- Rewording the same request (for example another 'add tests' comment on the same file) is not new nuance.",
      "- Use Prior Review Feedback to compare against resolved threads, waived_items, and rebuttals.",
      "",
      "Prior Review Feedback (facts.retroReview):",
      JSON.stringify(retroReview || {}, null, 2),
      "",
      "New Findings:",
      JSON.stringify(findings || [], null, 2),
    ].join("\n"),
  },
];

const getResponseText = (response) =>
  response.output_text ||
  response.output?.map((item) => item.content?.[0]?.text ?? "").join("\n") ||
  "";

export const applyRetroDupeFilter = async ({ facts, findings }) => {
  const retroReview = facts?.retroReview || null;
  if (null == retroReview || true !== retroReview.enabled) {
    return { filtered: findings || [], dropped: [], report: null };
  }
  if (false === Array.isArray(findings) || 0 === findings.length) {
    return { filtered: findings || [], dropped: [], report: null };
  }
  const waivedFilter = applyWaivedFindingFilter({ retroReview, findings });
  const baseline = applyExactDuplicateFilter({
    retroReview,
    findings: waivedFilter.filtered,
  });
  const deltaFilter = applyLaterRoundDeltaFilter({
    retroReview,
    findings: baseline.filtered,
  });
  const preDropped = [
    ...waivedFilter.dropped,
    ...baseline.dropped,
    ...deltaFilter.dropped,
  ];
  const baselineReport = {
    waived: {
      dropped_count: waivedFilter.dropped.length,
    },
    exact_duplicate: baseline.report
      ? { ...baseline.report, dropped_count: baseline.dropped.length }
      : null,
    later_round_outside_delta: {
      dropped_count: deltaFilter.dropped.length,
    },
  };
  if (waivedFilter.dropped.length > 0) {
    log(
      `[retro-dupe] waived-theme filter dropped ${waivedFilter.dropped.length} finding${
        waivedFilter.dropped.length === 1 ? "" : "s"
      }.`
    );
  }
  if (baseline.dropped.length > 0) {
    log(
      `[retro-dupe] exact dedupe dropped ${baseline.dropped.length} finding${
        baseline.dropped.length === 1 ? "" : "s"
      }.`
    );
  }
  if (deltaFilter.dropped.length > 0) {
    log(
      `[retro-dupe] later-round delta filter dropped ${deltaFilter.dropped.length} finding${
        deltaFilter.dropped.length === 1 ? "" : "s"
      }.`
    );
  }
  const client = getOpenAIClient();
  if (null == client) {
    log("[retro-dupe] warning: OPENAI_API_KEY missing; skipping retro dupe filter.");
    return {
      filtered: deltaFilter.filtered,
      dropped: preDropped,
      report: baselineReport,
    };
  }
  const indexed = deltaFilter.filtered.map((finding, index) => ({
    id: `finding_${index + 1}`,
    finding,
  }));
  const payload = indexed.map(({ id, finding }) => {
    const meta = resolveConventionalMeta(finding);
    return {
      id,
      title: finding?.title || "Finding",
      comment_label: meta.label || null,
      comment_decorations: meta.decorations || [],
      confidence: finding?.confidence ?? null,
      reviewer: finding?.reviewer || null,
      rationale: finding?.rationale || null,
      suggested_fix: finding?.suggested_fix || null,
      locations: Array.isArray(finding?.locations)
        ? finding.locations.map((location) => ({
            path: location?.path ?? null,
            lines: location?.lines ?? null,
            snippet: location?.snippet ?? null,
          }))
        : [],
    };
  });
  try {
    const response = await client.responses.create({
      model: RETRO_DUPE_MODEL,
      input: buildRetroDupePrompt({ retroReview, findings: payload }),
      text: {
        format: {
          type: "json_schema",
          name: "retro_dupe_filter",
          strict: true,
          schema: {
            type: "object",
            properties: {
              drop_finding_ids: {
                type: "array",
                items: { type: "string" },
              },
              notes: { type: "string" },
            },
            required: ["drop_finding_ids", "notes"],
            additionalProperties: false,
          },
        },
      },
    });
    const outputText = getResponseText(response);
    const parsed = parseJsonSafe(outputText);
    const drops = Array.isArray(parsed?.drop_finding_ids)
      ? parsed.drop_finding_ids
      : [];
    if (0 === drops.length) {
      return {
        filtered: deltaFilter.filtered,
        dropped: preDropped,
        report: baselineReport,
      };
    }
    const dropSet = new Set(drops);
    const filtered = indexed
      .filter(({ id }) => false === dropSet.has(id))
      .map(({ finding }) => finding);
    const modelDropped = indexed
      .filter(({ id }) => true === dropSet.has(id))
      .map(({ finding }) => finding);
    const dropped = [...preDropped, ...modelDropped];
    const report = {
      prefilter: baselineReport,
      model: {
        dropped_count: modelDropped.length,
        notes: parsed?.notes ?? null,
      },
    };
    log(
      `[retro-dupe] dropped ${dropped.length} duplicate findings (${modelDropped.length} model, ${preDropped.length} prefilter).`
    );
    return { filtered, dropped, report };
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    log(`[retro-dupe] warning: failed to apply retro dupe filter. ${message}`);
    return {
      filtered: deltaFilter.filtered,
      dropped: preDropped,
      report: baselineReport,
    };
  }
};
