import chalk from "chalk";

const START_TIME = Date.now();

const supportsColor =
  true === process.stderr.isTTY && "1" !== process.env.NO_COLOR;

const formatTag = (label, colorFn) => {
  if (false === supportsColor) {
    return label;
  }
  return colorFn(label);
};

const scopeColors = [
  chalk.cyan,
  chalk.green,
  chalk.yellow,
  chalk.blue,
  chalk.magenta,
  chalk.white,
];

const specialScopes = {
  auth: chalk.yellow,
  decide: chalk.green,
};

const hashScope = (value) => {
  let hash = 0;
  for (let i = 0; i < value.length; i += 1) {
    hash = (hash * 31 + value.charCodeAt(i)) >>> 0;
  }
  return hash;
};

const colorForScope = (scope) => {
  if (null == scope || "" === scope) {
    return chalk.cyan;
  }
  const special = specialScopes[scope];
  if (special) {
    return special;
  }
  return scopeColors[hashScope(scope) % scopeColors.length];
};

export const log = (message, scope = null) => {
  const elapsed = ((Date.now() - START_TIME) / 1000).toFixed(1);
  const baseTag = `[ai-review-orch +${elapsed}s]`;
  const scopeTag = scope ? ` [${scope}]` : "";
  const renderedBase = formatTag(baseTag, chalk.dim);
  const renderedScope = scope
    ? formatTag(scopeTag, colorForScope(scope))
    : "";
  process.stderr.write(`${renderedBase}${renderedScope} ${message}\n`);
};
