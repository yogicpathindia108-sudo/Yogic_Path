import { run } from "./exec.mjs";

export const getRepoRoot = () => run("git", ["rev-parse", "--show-toplevel"]);
