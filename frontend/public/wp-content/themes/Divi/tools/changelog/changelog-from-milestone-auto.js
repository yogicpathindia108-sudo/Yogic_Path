const fs = require("fs");
const path = require("path");
const { getPRLinks } = require("./getPRLinks");
const { fetchData } = require("./fetchData");

// Configuration.
const REPOSITORY_OWNER = "elegantthemes";
const REPOSITORY_NAME = "Divi";

// File paths - can be overridden by command line args.
const DEFAULT_TARGET_DIR = path.resolve(__dirname, "../..");
const CHANGELOG_FILES = {
  txt: "changelog.txt",
  json: "changelog.json",
  internal: "changelog-internal.md"
};

// Parse command line arguments.
const yargs = require("yargs/yargs");
const { hideBin } = require("yargs/helpers");
const argv = yargs(hideBin(process.argv))
  .option("milestone", {
    alias: "m",
    description: "Milestone ID to process (defaults to latest open 5.x.x milestone)",
    type: "number"
  })
  .option("release-version", {
    alias: "v",
    description: "Override version (extracted from milestone title by default)",
    type: "string"
  })
  .option("target-dir", {
    alias: "d",
    description: "Target directory containing changelog files",
    type: "string",
    default: DEFAULT_TARGET_DIR
  })
  .option("version-output", {
    description: "Write resolved version to the specified file path",
    type: "string"
  })
  .option("dry-run", {
    description: "Show what would be done without making changes",
    type: "boolean",
    default: false
  })
  .help()
  .argv;

const parsedMilestone = Number.parseInt(argv.milestone, 10);
let MILESTONE_ID = Number.isInteger(parsedMilestone) && parsedMilestone > 0
  ? parsedMilestone
  : null;
const TARGET_DIR = argv["target-dir"];
const VERSION_OUTPUT = argv["version-output"];
const DRY_RUN = argv["dry-run"];

if (DRY_RUN) {
  console.log("🔍 DRY RUN MODE - No files will be modified");
}

// GraphQL Query to fetch milestone info and issues.
const getMilestoneAndIssuesQuery = `
  query ($repositoryOwner: String!, $repositoryName: String!, $milestoneId: Int!, $cursor: String) {
    repository(owner: $repositoryOwner, name: $repositoryName) {
      milestone(number: $milestoneId) {
        title
        description
        issues(states: CLOSED, first: 100, after: $cursor) {
          pageInfo {
            endCursor
            hasNextPage
          }
          nodes {
            number
            title
            bodyText
            body
          }
        }
      }
    }
  }
`;

const getOpenMilestonesQuery = `
  query ($repositoryOwner: String!, $repositoryName: String!, $cursor: String) {
    repository(owner: $repositoryOwner, name: $repositoryName) {
      milestones(first: 100, states: OPEN, after: $cursor) {
        pageInfo {
          endCursor
          hasNextPage
        }
        nodes {
          number
          title
        }
      }
    }
  }
`;

// Function to fetch milestone info and issues.
async function fetchMilestoneAndIssues(cursor = null, allIssues = [], milestoneInfo = null) {
  const variables = {
    repositoryOwner: REPOSITORY_OWNER,
    repositoryName: REPOSITORY_NAME,
    milestoneId: MILESTONE_ID,
    cursor: cursor,
  };

  const data = await fetchData(getMilestoneAndIssuesQuery, variables);
  const milestone = data.repository.milestone;
  if (!milestone) {
    throw new Error(`Milestone ${MILESTONE_ID} not found`);
  }

  if (!milestoneInfo) {
    milestoneInfo = {
      title: milestone.title,
      description: milestone.description
    };
  }

  const issues = milestone.issues.nodes;
  const pageInfo = milestone.issues.pageInfo;

  // Combine new issues with previously fetched issues.
  allIssues = allIssues.concat(issues);

  // If there's another page, fetch the next set of issues.
  if (pageInfo.hasNextPage) {
    return fetchMilestoneAndIssues(pageInfo.endCursor, allIssues, milestoneInfo);
  } else {
    return { milestoneInfo, issues: allIssues };
  }
}

async function fetchOpenMilestones(cursor = null, allMilestones = []) {
  const variables = {
    repositoryOwner: REPOSITORY_OWNER,
    repositoryName: REPOSITORY_NAME,
    cursor: cursor,
  };

  const data = await fetchData(getOpenMilestonesQuery, variables);
  const milestones = data.repository.milestones.nodes;
  const pageInfo = data.repository.milestones.pageInfo;

  allMilestones = allMilestones.concat(milestones);

  if (pageInfo.hasNextPage) {
    return fetchOpenMilestones(pageInfo.endCursor, allMilestones);
  }

  return allMilestones;
}

// Function to extract version from milestone title.
function extractVersionFromMilestone(milestoneTitle) {
  // Prefer full semver in milestone title.
  // "5.0.1", "v5.0.1", "Divi 5.0.1", "5.1.0-rc.1".
  const semverMatch = milestoneTitle.match(/(\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?)/);
  if (semverMatch) {
    return semverMatch[1];
  }

  // Backward/transition-friendly fallback.
  // Allow "5.0" style milestones and normalize to "5.0.0".
  const majorMinorMatch = milestoneTitle.match(/(\d+\.\d+(?:-[a-zA-Z0-9.-]+)?)/);
  if (majorMinorMatch) {
    const value = majorMinorMatch[1];
    const [numericPart, prerelease] = value.split("-");
    const normalized = `${numericPart}.0`;
    return prerelease ? `${normalized}-${prerelease}` : normalized;
  }

  return null;
}

function parseSemver(version) {
  const [core, ...prereleaseParts] = version.split("-");
  const prerelease = prereleaseParts.length ? prereleaseParts.join("-") : "";
  const [major, minor, patch] = core.split(".").map((part) => parseInt(part, 10));

  if ([major, minor, patch].some((value) => Number.isNaN(value))) {
    return null;
  }

  return {
    major,
    minor,
    patch,
    prerelease: prerelease ? prerelease.split(".") : []
  };
}

function compareSemverParts(aParts, bParts) {
  const maxLength = Math.max(aParts.length, bParts.length);
  for (let i = 0; i < maxLength; i++) {
    const aPart = aParts[i];
    const bPart = bParts[i];

    if (undefined === aPart) {
      return -1;
    }

    if (undefined === bPart) {
      return 1;
    }

    if (aPart === bPart) {
      continue;
    }

    const aIsNumber = /^[0-9]+$/.test(aPart);
    const bIsNumber = /^[0-9]+$/.test(bPart);

    if (aIsNumber && bIsNumber) {
      return parseInt(aPart, 10) > parseInt(bPart, 10) ? 1 : -1;
    }

    if (aIsNumber && !bIsNumber) {
      return -1;
    }

    if (!aIsNumber && bIsNumber) {
      return 1;
    }

    return aPart > bPart ? 1 : -1;
  }

  return 0;
}

function compareSemver(a, b) {
  if (a.major !== b.major) {
    return a.major > b.major ? 1 : -1;
  }

  if (a.minor !== b.minor) {
    return a.minor > b.minor ? 1 : -1;
  }

  if (a.patch !== b.patch) {
    return a.patch > b.patch ? 1 : -1;
  }

  if (0 === a.prerelease.length && 0 === b.prerelease.length) {
    return 0;
  }

  if (0 === a.prerelease.length) {
    return 1;
  }

  if (0 === b.prerelease.length) {
    return -1;
  }

  return compareSemverParts(a.prerelease, b.prerelease);
}

async function resolveDefaultMilestone() {
  console.log("🔎 No milestone provided. Looking for latest open 5.x.x milestone...");
  const milestones = await fetchOpenMilestones();

  const candidates = milestones
    .map((milestone) => {
      const version = extractVersionFromMilestone(milestone.title);
      if (!version) {
        return null;
      }

      const parsed = parseSemver(version);
      if (!parsed || 5 !== parsed.major) {
        return null;
      }

      return { ...milestone, version, parsed };
    })
    .filter(Boolean);

  if (0 === candidates.length) {
    throw new Error("No open 5.x.x milestones found");
  }

  candidates.sort((a, b) => compareSemver(a.parsed, b.parsed));
  const selected = candidates[candidates.length - 1];
  console.log(`📌 Using milestone ${selected.number} (${selected.title})`);

  return selected.number;
}

// Function to get formatted date (MM-DD-YYYY).
function getFormattedDate() {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const day = String(now.getDate()).padStart(2, "0");
  const year = now.getFullYear();
  return `${month}-${day}-${year}`;
}

// Function to extract changelog copy from PR description.
function extractChangelogCopy(prBody, prData) {
  const oldFormatRegex = /Changelog copy\s*\n([\s\S]*?)(?=\nAffected [A|a]reas)/;
  const newFormatRegex = /Changelog(?: Copy)?\s*\n([\s\S]*?)(?=\n\s*\n|$)/;

  let match = prBody.match(oldFormatRegex);
  if (!match) {
    match = prBody.match(newFormatRegex);
  }

  if (!match) {
    console.log("⚠️  NO CHANGELOG COPY FOUND IN PR BODY!");
    if (prData && prData.repository && prData.repository.pullRequest) {
      console.log(`   PR Link: ${prData.repository.pullRequest.url}`);
      console.log(`   PR Number: ${prData.repository.pullRequest.number}`);
      console.log(`   PR Title: ${prData.repository.pullRequest.title}`);
    }
    return null;
  }

  if (match) {
    const results = match[1].split("\n");

    // Clean up the results.
    for (let i = results.length - 1; i >= 0; i--) {
      const placeholderRegex = /(Customer-friendly|past-tense|one-liner|describing the fix\.)/i;
      const cursorAttributionRegex = /made with cursor/i;
      if (
        results[i] === "" ||
        results[i].startsWith("Affected areas") ||
        results[i].startsWith("REPLACE THIS") ||
        results[i].startsWith("N/A") ||
        results[i].startsWith("Customer friendly one liner explaining what was Fixed/Added etc for use in the changelog") ||
        results[i].startsWith("Provide a customer-friendly, past-tense one-liner explaining what was fixed or added for the changelog entry.") ||
        placeholderRegex.test(results[i]) ||
        cursorAttributionRegex.test(results[i])
      ) {
        results.splice(i, 1);
      }
    }

    // Clean up formatting.
    results.forEach((line, index) => {
      line = line.replace("New: ", "");
      line = line.replace("Fixed - ", "Fixed ");
      line = line.replace("Fixed: ", "Fixed ");
      line = line.replace("Update: ", "Updated ");
      line = line.replace("Added:", "Added ");
      line = line.replace("Added - ", "Added ");
      line = line.replace("Changed:", "Changed ");
      line = line.replace("Removed:", "Removed ");
      line = line.replace("Updated:", "Updated ");
      results[index] = line;

      // Add period at the end if missing.
      if (!line.endsWith(".")) {
        results[index] = line + ".";
      }
    });

    return results;
  }
}

// Function to compile changelog entries.
async function compileChangelogEntries() {
  console.log("📥 Fetching milestone and issues...");
  const { milestoneInfo, issues } = await fetchMilestoneAndIssues();

  console.log(`📋 Milestone: ${milestoneInfo.title}`);
  console.log(`📝 Found ${issues.length} closed issues`);

  const prDataPromises = issues.map((issue) => getPRLinks(issue));
  const prDataResults = await Promise.all(prDataPromises);

  const changelogCopyLines = [];

  prDataResults.forEach((prDataObjects) => {
    if (!prDataObjects) return;

    prDataObjects.forEach((prData) => {
      const prBody = prData.repository.pullRequest.bodyText;
      const changelogCopy = extractChangelogCopy(prBody, prData);

      if (changelogCopy) {
        changelogCopyLines.push({
          entries: changelogCopy,
          prData: prData
        });
      }
    });
  });

  // Remove duplicates.
  const seen = new Set();
  const filteredEntries = changelogCopyLines.filter((item) => {
    const key = JSON.stringify(item.entries);
    const duplicate = seen.has(key);
    seen.add(key);
    return !duplicate;
  });

  return { milestoneInfo, changelogEntries: filteredEntries };
}

// Function to remove existing version entries from content.
function removeExistingVersion(content, version) {
  const lines = content.split("\n");
  const newLines = [];
  let skipUntilNextVersion = false;

  for (const line of lines) {
    const isVersionLine = line.match(/^version\s+[\d.]+.*/) ||
      line.match(/^###\s+version\s+[\d.]+.*/) ||
      line.match(/^\s*{\s*"version":/);

    if (isVersionLine) {
      if (line.includes(version)) {
        skipUntilNextVersion = true;
        continue;
      } else {
        skipUntilNextVersion = false;
      }
    }

    if (!skipUntilNextVersion) {
      newLines.push(line);
    }
  }

  return newLines.join("\n");
}

// Function to update changelog.txt.
async function updateChangelogTxt(version, date, entries, targetPath) {
  console.log(`📝 Updating ${targetPath}...`);

  let content = "";
  if (fs.existsSync(targetPath)) {
    content = fs.readFileSync(targetPath, "utf8");
    content = removeExistingVersion(content, version);
  }

  const newSection = [`version ${version} ( updated ${date} )`];
  entries.forEach((item) => {
    item.entries.forEach((entry) => {
      newSection.push(`- ${entry}`);
    });
  });

  const newContent = newSection.join("\n") + "\n\n" + content;

  if (DRY_RUN) {
    console.log(`   Would write ${newSection.length} lines to ${targetPath}`);
  } else {
    fs.writeFileSync(targetPath, newContent, "utf8");
    console.log(`   ✅ Updated ${targetPath}`);
  }
}

// Function to update changelog.json.
async function updateChangelogJson(version, date, entries, targetPath) {
  console.log(`📝 Updating ${targetPath}...`);

  let jsonData = [];
  if (fs.existsSync(targetPath)) {
    const content = fs.readFileSync(targetPath, "utf8");
    try {
      jsonData = JSON.parse(content);
    } catch (error) {
      console.log("   ⚠️  Invalid JSON, starting fresh");
      jsonData = [];
    }
  }

  // Remove existing version.
  jsonData = jsonData.filter((item) => item.version !== version);

  // Prepare new entries.
  const changelogEntries = [];
  entries.forEach((item) => {
    item.entries.forEach((entry) => {
      let cleanEntry = entry.replace(/"/g, '\\"');
      changelogEntries.push(cleanEntry);
    });
  });

  // Add new version at the beginning.
  const newVersionData = {
    version: version,
    updated: date,
    entries: changelogEntries
  };

  jsonData.unshift(newVersionData);

  const newContent = JSON.stringify(jsonData, null, "\t");

  if (DRY_RUN) {
    console.log(`   Would write ${changelogEntries.length} entries to ${targetPath}`);
  } else {
    fs.writeFileSync(targetPath, newContent, "utf8");
    console.log(`   ✅ Updated ${targetPath}`);
  }
}

// Function to update changelog-internal.md.
async function updateChangelogInternal(version, date, entries, targetPath) {
  console.log(`📝 Updating ${targetPath}...`);

  let content = "";
  if (fs.existsSync(targetPath)) {
    content = fs.readFileSync(targetPath, "utf8");
    content = removeExistingVersion(content, version);
  }

  const newSection = [`### version ${version} - updated ${date}`];
  entries.forEach((item) => {
    item.entries.forEach((entry) => {
      const prUrl = `https://github.com/${REPOSITORY_OWNER}/${item.prData._parsedData.repoName}/pull/${item.prData._parsedData.prNumber}`;
      newSection.push(`- [${entry}](${prUrl})`);
    });
  });

  const newContent = newSection.join("\n") + "\n\n" + content;

  if (DRY_RUN) {
    console.log(`   Would write ${newSection.length} lines to ${targetPath}`);
  } else {
    fs.writeFileSync(targetPath, newContent, "utf8");
    console.log(`   ✅ Updated ${targetPath}`);
  }
}

// Main function.
async function main() {
  try {
    if (!fs.existsSync(TARGET_DIR)) {
      console.error(`❌ Target directory does not exist: ${TARGET_DIR}`);
      process.exit(1);
    }

    if (!Number.isFinite(MILESTONE_ID)) {
      MILESTONE_ID = await resolveDefaultMilestone();
    }

    if (!Number.isFinite(MILESTONE_ID)) {
      console.error("❌ No milestone ID provided or resolved.");
      process.exit(1);
    }

    console.log(`🚀 Processing milestone ${MILESTONE_ID}`);

    const { milestoneInfo, changelogEntries } = await compileChangelogEntries();

    if (changelogEntries.length === 0) {
      console.log(`⚠️  No changelog entries found for milestone ${MILESTONE_ID}`);
      return;
    }
    let version = argv["release-version"];
    if (!version) {
      version = extractVersionFromMilestone(milestoneInfo.title);
      if (!version) {
        console.error(`❌ Could not extract version from milestone title: "${milestoneInfo.title}"`);
        console.error("   Please provide version manually with --release-version flag");
        process.exit(1);
      }
    }

    if (VERSION_OUTPUT) {
      fs.writeFileSync(VERSION_OUTPUT, version, "utf8");
      console.log(`📝 Wrote version to ${VERSION_OUTPUT}`);
    }
    const date = getFormattedDate();
    console.log(`📅 Version: ${version}, Date: ${date}`);
    console.log(`📊 Found ${changelogEntries.length} changelog entries`);

    const txtPath = path.join(TARGET_DIR, CHANGELOG_FILES.txt);
    const jsonPath = path.join(TARGET_DIR, CHANGELOG_FILES.json);
    const internalPath = path.join(TARGET_DIR, CHANGELOG_FILES.internal);

    await updateChangelogTxt(version, date, changelogEntries, txtPath);
    await updateChangelogJson(version, date, changelogEntries, jsonPath);
    await updateChangelogInternal(version, date, changelogEntries, internalPath);

    console.log("\n🎉 Successfully updated all changelog files!");
    console.log(`   Target directory: ${TARGET_DIR}`);
    console.log(`   Version: ${version}`);
    console.log(`   Entries: ${changelogEntries.length}`);
  } catch (error) {
    console.error("❌ Error:", error.message);
    process.exit(1);
  }
}

// Run the script.
main();
