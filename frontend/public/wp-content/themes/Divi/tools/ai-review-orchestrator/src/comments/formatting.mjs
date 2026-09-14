const normalizeLabel = (value) =>
  null == value || "" === String(value).trim()
    ? null
    : String(value).trim().toLowerCase();

const normalizeDecorations = (value) =>
  Array.isArray(value)
    ? value
        .map((entry) => normalizeLabel(entry))
        .filter(Boolean)
    : null;

export const resolveConventionalMeta = (finding) => {
  const fallback = { label: "issue", decorations: ["blocking"] };
  const label = normalizeLabel(finding?.comment_label) || fallback.label;
  const explicitDecorations = normalizeDecorations(finding?.comment_decorations);
  const defaultDecorations =
    label === "issue"
      ? fallback.decorations
      : label === "suggestion"
        ? ["non-blocking"]
        : [];
  const decorations =
    explicitDecorations ?? defaultDecorations;
  return {
    label: label || fallback.label,
    decorations: Array.isArray(decorations) ? decorations : [],
  };
};

export const buildConventionalHeaderFromFinding = (finding) => {
  const meta = resolveConventionalMeta(finding);
  const decorationText = meta.decorations.length
    ? ` (${meta.decorations.join(", ")})`
    : "";
  const header = `${meta.label}${decorationText}:`;
  return `**${header}** ${finding?.title || "Finding"}`;
};
