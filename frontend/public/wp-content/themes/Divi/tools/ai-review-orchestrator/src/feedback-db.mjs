import fs from "fs";
import path from "path";
import { getDefaultDbPath, getStats, openDb } from "./db.mjs";

const args = process.argv.slice(2);

const getArgValue = (name) => {
  const index = args.indexOf(name);
  if (-1 === index) {
    return null;
  }
  return args[index + 1] ?? null;
};

const hasArg = (name) => args.includes(name);

const getRepoRoot = () => {
  let current = path.resolve(process.cwd());
  while (current !== path.dirname(current)) {
    if (true === fs.existsSync(path.join(current, ".git"))) {
      return current;
    }
    current = path.dirname(current);
  }
  throw new Error("Could not locate git repo root.");
};

const main = () => {
  const repoRoot = getRepoRoot();
  const dbArg = getArgValue("--db");
  const dbContext = openDb({ repoRoot, dbPath: dbArg });
  console.log(`db: ${dbContext.dbPath ?? getDefaultDbPath(repoRoot)}`);

  if (true === hasArg("--stats")) {
    const stats = getStats(dbContext.db);
    console.log(JSON.stringify(stats, null, 2));
  }
};

main();
