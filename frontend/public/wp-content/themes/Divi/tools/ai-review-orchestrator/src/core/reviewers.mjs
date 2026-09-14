import { unique } from "./utils.mjs";

export const parseForcedReviewers = (rawValues = []) => {
  const list = rawValues
    .flatMap((value) =>
      String(value || "")
        .split(",")
        .map((entry) => entry.trim())
        .filter(Boolean)
    )
    .filter(Boolean);
  return unique(list);
};

export const normalizeFrontmatterList = (value) => {
  if (Array.isArray(value)) {
    return value.map((entry) => String(entry).trim()).filter(Boolean);
  }
  if (null == value) {
    return [];
  }
  return String(value)
    .split(",")
    .map((entry) => entry.trim())
    .filter(Boolean);
};

export const normalizeFrontmatterSizeMap = (value) => {
  if (null == value || "object" !== typeof value) {
    return null;
  }
  const normalized = {};
  Object.entries(value).forEach(([key, entry]) => {
    const trimmedKey = String(key || "").trim();
    const numeric = Number(entry);
    if ("" !== trimmedKey && Number.isFinite(numeric)) {
      normalized[trimmedKey] = numeric;
    }
  });
  return 0 < Object.keys(normalized).length ? normalized : null;
};
