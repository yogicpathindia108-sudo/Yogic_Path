import fs from "node:fs";
import path from "node:path";

import { parseJsonSafe } from "./utils.mjs";

export const ensureDir = (dirPath) => {
  if (false === fs.existsSync(dirPath)) {
    fs.mkdirSync(dirPath, { recursive: true });
  }
};

export const writeJson = (filePath, payload) => {
  ensureDir(path.dirname(filePath));
  fs.writeFileSync(filePath, `${JSON.stringify(payload, null, 2)}\n`, "utf8");
};

export const writeText = (filePath, contents) => {
  ensureDir(path.dirname(filePath));
  fs.writeFileSync(filePath, contents, "utf8");
};

export const readJson = (filePath) => {
  if (false === fs.existsSync(filePath)) {
    return null;
  }
  const raw = fs.readFileSync(filePath, "utf8");
  return parseJsonSafe(raw);
};
