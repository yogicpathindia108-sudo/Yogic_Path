# Divi Changelog Tools

Generates `changelog.txt`, `changelog.json`, and `changelog-internal.md` by
pulling changelog copy from PRs attached to a GitHub milestone in the
`elegantthemes/Divi` repository.

## How It Works

- Fetches a milestone and its closed issues.
- Reads each issue's **Attached PR(s)** section to find PR links.
- Extracts **Changelog Copy** from each PR body.
- Writes a new version entry to:
  - `changelog.txt`
  - `changelog.json`
  - `changelog-internal.md` (includes PR links)

## Requirements

- Node.js (20+ recommended).
- `GITHUB_TOKEN` with access to `elegantthemes/Divi`.

## Run Locally

```bash
cd tools/changelog
npm ci
GITHUB_TOKEN=ghp_... node changelog-from-milestone-auto.js \
  --target-dir=/path/to/Divi
```

### Optional Flags

- `--milestone` (`-m`): Milestone number. Optional; defaults to latest open
  **5.x.x** milestone by semver.
- `--release-version` (`-v`): Override version (otherwise derived from milestone
  title).
- `--target-dir` (`-d`): Repo root containing changelog files (default: repo root).
- `--version-output`: Write resolved version to a file path.
- `--dry-run`: Show actions without writing files.

> Local runs only update files. They do not create PRs.

## Run via GitHub Actions

Workflow: **Actions → Update Changelog**

- The milestone input is optional. When omitted, the workflow picks the latest
  open **5.x.x** milestone.
- The workflow creates a PR targeting the latest `release-5.x` branch.
- PR branch name: `chore/changelog-<version>`.
- The workflow updates submodules, runs `grunt release:<version>`, creates PRs in
  `elegantthemes/submodule-builder` and `elegantthemes/submodule-core`, and then
  updates the submodule refs in the main PR.

## Expected Issue / PR Format

- Issues must include an **Attached PR(s)** section with PR links.
- PRs must include a **Changelog Copy** section in the body.

