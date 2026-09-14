const { fetchPR } = require("./fetchData");

const REPOSITORY_OWNER = "elegantthemes";
const REPOSITORY_NAME = "Divi";

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const getPRLinks = async (issue) => {
  let { number, title, body, bodyText } = issue;

  console.log("--------------------------------------");
  console.log("title", title);
  console.log("number", number);
  console.log(`https://github.com/${REPOSITORY_OWNER}/${REPOSITORY_NAME}/issues/${number}`);

  if (!bodyText) {
    bodyText = body;
  }

  if (!bodyText) {
    console.log(`⚠️  No body text found for issue #${number}`);
    return null;
  }

  let prLinks = [];

  // Try to match both "Attached PR(s)" and "Attached PR" headers.
  const prSectionMatch = bodyText.match(/Attached PR(?:\(s\)|s)?\s*\n+((?:.*\S.*\n*)+)/);
  if (prSectionMatch) {
    const lines = prSectionMatch[1].split("\n");
    for (const line of lines) {
      const trimmed = line.trim();
      if (!trimmed) {
        break;
      }
      if (
        trimmed.startsWith("* ") ||
        /elegantthemes\/[\w-]+\/(pull\/\d+|#\d+)/.test(trimmed) ||
        /elegantthemes\/[\w-]+#\d+/.test(trimmed) ||
        /^#\d+$/.test(trimmed)
      ) {
        let link = trimmed.replace(/^\* /, "");
        prLinks.push(link);
      } else {
        break;
      }
    }
  }

  if (prLinks.length === 0) {
    console.log(`\n\n==================== WARNING ====================`);
    console.log(`NO PR LINKS FOUND FOR ISSUE #${number}: ${title}`);
    console.log(`URL: https://github.com/${REPOSITORY_OWNER}/${REPOSITORY_NAME}/issues/${number}`);
    console.log(`==================== WARNING ====================\n\n`);
  }

  if (prLinks) {
    let prDataObjects = [];

    for (const prLink of prLinks) {
      let cleanedPrLink = prLink.replace(/elegantthemes\//, "");

      cleanedPrLink = cleanedPrLink.trim();
      cleanedPrLink = cleanedPrLink.replace(/^\* /, "");

      if (cleanedPrLink.match("github.com")) {
        cleanedPrLink = cleanedPrLink.split("github.com")[1];
        cleanedPrLink = cleanedPrLink.replace(/\//, "");

        cleanedPrLink = cleanedPrLink.replace(/\/pull\//, "#");
      }

      const prLinkParts = cleanedPrLink.split("#");
      let repoName = prLinkParts[0];
      const prNumber = parseInt(prLinkParts[1]);

      if (!repoName) {
        repoName = REPOSITORY_NAME;
      }

      if (repoName.startsWith("Divi Beta ")) {
        console.log(`Skipping PR data fetch for Divi Beta issue: ${repoName}#${prNumber}`);
        continue;
      }

      console.log(`Fetching PR data for ${repoName}#${prNumber}...`);

      await sleep(500);
      let prData;
      try {
        prData = await fetchPR(repoName, prNumber);
      } catch (error) {
        console.log(`⚠️  Skipping PR ${repoName}#${prNumber} due to fetch error: ${error.message}`);
        continue;
      }

      const files = prData.repository.pullRequest.files?.nodes || [];
      let filteredAdditions = 0;
      let filteredDeletions = 0;

      files.forEach((file) => {
        if (file.path.startsWith(".et/")) {
          return;
        }
        if (file.path.startsWith(".cursor/tasks/")) {
          return;
        }
        filteredAdditions += file.additions || 0;
        filteredDeletions += file.deletions || 0;
      });

      const linesChanged = files.length > 0
        ? filteredAdditions + filteredDeletions
        : prData.repository.pullRequest.additions + prData.repository.pullRequest.deletions;

      prData._parsedData = {
        title: prData.repository.pullRequest.title,
        link: prData.repository.pullRequest.url,
        commits: prData.repository.pullRequest.commits.nodes,
        comments: prData.repository.pullRequest.comments.nodes,
        state: prData.repository.pullRequest.state,
        repoName,
        prNumber,
        linesChanged,
        user: prData.repository.pullRequest.author.login,
      };

      prDataObjects.push(prData);
    }

    return prDataObjects;
  }

  return null;
};

module.exports = { getPRLinks };
