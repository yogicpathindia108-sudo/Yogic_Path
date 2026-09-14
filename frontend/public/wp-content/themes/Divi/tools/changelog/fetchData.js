const axios = require("axios");

const REPOSITORY_OWNER = "elegantthemes";
const REPOSITORY_NAME = "Divi";

const GITHUB_TOKEN = process.env.GITHUB_TOKEN;
const GITHUB_GRAPHQL_URL = "https://api.github.com/graphql";

// GraphQL Query to fetch PR.
const getPRQuery = `
    query ($repositoryOwner: String!, $repositoryName: String!, $prNumber: Int!) {
        repository(owner: $repositoryOwner, name: $repositoryName) {
            pullRequest(number: $prNumber) {
                title
                state
                bodyText
                url
                files(first: 100) {
                    nodes {
                        path
                        additions
                        deletions
                    }
                }
                commits(last: 100) {
                    nodes {
                        commit {
                            committedDate
                            oid
                            message
                            deletions
                            additions
                        }
                    }
                }
                comments(last: 100) {
                    nodes {
                        author {
                            login
                        }
                        createdAt
                        body
                    }
                }
                author {
                    login
                }
                additions
                deletions
            }
        }
    }
`;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const shouldRetry = (error) => {
  const status = error?.response?.status;
  return [429, 502, 503, 504].includes(status);
};

// Function to fetch data from GitHub GraphQL API.
async function fetchData(query, variables, options = {}) {
  const {
    retries = 6,
    baseDelayMs = 500,
    maxDelayMs = 5000,
  } = options;

  let attempt = 0;
  while (attempt <= retries) {
    try {
      const response = await axios({
        url: GITHUB_GRAPHQL_URL,
        method: "post",
        headers: {
          Authorization: `Bearer ${GITHUB_TOKEN}`,
        },
        data: {
          query,
          variables,
        },
      });

      if (response.data.errors) {
        throw new Error(response.data.errors[0].message);
      }

      return response.data.data;
    } catch (error) {
      attempt++;
      const retryable = shouldRetry(error);
      if (!retryable || attempt > retries) {
        console.error("Error fetching data from GitHub", error.message);
        throw error;
      }

      const delay = Math.min(baseDelayMs * Math.pow(2, attempt - 1), maxDelayMs);
      console.log(`⚠️  GitHub API error ${error.response?.status || ""}. Retrying in ${delay}ms...`);
      await sleep(delay);
    }
  }
}

// Function to fetch PR.
async function fetchPR(repoName, prNumber) {
  const variables = {
    repositoryOwner: REPOSITORY_OWNER,
    repositoryName: repoName,
    prNumber: prNumber,
  };

  return fetchData(getPRQuery, variables);
}

module.exports = { fetchData, fetchPR };
