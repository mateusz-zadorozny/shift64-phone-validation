# WordPress Plugin CI/CD Setup Guide

Complete guide for implementing automated releases, code quality checks, and WordPress.org deployment for WordPress plugins.

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [File Structure](#file-structure)
4. [Step 1: Package Configuration](#step-1-package-configuration)
5. [Step 2: Semantic Release Configuration](#step-2-semantic-release-configuration)
6. [Step 3: Version Update Script](#step-3-version-update-script)
7. [Step 4: GitHub Workflows](#step-4-github-workflows)
8. [Step 5: PHPCS Configuration](#step-5-phpcs-configuration)
9. [Step 6: WordPress.org Deployment](#step-6-wordpressorg-deployment)
10. [Usage](#usage)
11. [Private Composer Dependencies](#private-composer-dependencies)
12. [Troubleshooting](#troubleshooting)

---

## Overview

This setup provides:

- **Automated Releases**: Semantic versioning based on commit messages
- **Code Quality**: PHPCS with WordPress Coding Standards on every PR
- **PR Validation**: Enforces conventional commit format in PR titles
- **One Release Package**: a single ZIP built from `.distignore`, attached to the GitHub release and deployed to WordPress.org
- **WordPress.org Deployment**: each release goes to the plugin's WordPress.org SVN repository, which serves the updates; the plugin has no updater of its own
- **Plugin Check**: every PR builds the package and runs Plugin Check on it

### How It Works

```
PR with "feat: add feature" title
        ↓
[PR Lint] Validates title format
[Code Quality] Runs PHPCS checks, builds the package, runs Plugin Check
        ↓
Merge to master
        ↓
[Release Workflow]
  → Analyzes commits
  → Bumps version (feat=minor, fix=patch)
  → Updates version in plugin files
  → Creates GitHub release
  → Builds one package (scripts/build-package.sh)
  → Attaches it to the GitHub release as a zip
  → Deploys the same package to WordPress.org SVN (once the SVN secrets exist)
        ↓
WordPress sites are offered the update by WordPress.org
```

---

## Prerequisites

- GitHub repository (public or private)
- Node.js 18+ (for semantic-release)
- PHP 7.4+ with Composer
- Git
- A WordPress.org account, for the directory submission and the SVN deploys

---

## File Structure

```
your-plugin/
├── .github/
│   └── workflows/
│       ├── release.yml          # Automated releases + WordPress.org deploy
│       ├── wporg-deploy.yml     # Manual rebuild of a tag: SVN deploy, release ZIP
│       ├── wporg-assets.yml     # readme.txt + listing assets sync
│       ├── code-quality.yml     # PHPCS, PHPUnit, package Plugin Check
│       ├── e2e.yml              # Playwright checkout tests (wp-env)
│       └── pr-lint.yml          # PR title validation
├── .wordpress-org/              # Directory icons, banners, screenshots
├── scripts/
│   ├── update-version.sh        # Version bump script
│   ├── sync-readme-changelog.php  # readme.txt changelog from CHANGELOG.md
│   ├── ci-docs-only.sh          # Docs-only pull request classifier
│   └── build-package.sh         # Builds the release package
├── src/                         # Plugin code (no updater)
├── .distignore                  # What stays out of the package
├── .phpcs.xml.dist              # PHPCS configuration
├── .releaserc.json              # Semantic release config
├── composer.json
├── package.json
├── readme.txt
└── your-plugin.php              # Main plugin file
```

---

## Step 1: Package Configuration

### package.json

This configures semantic-release for automated versioning.

```json
{
  "name": "your-plugin-slug",
  "version": "0.1.0",
  "description": "Your plugin description",
  "private": true,
  "scripts": {
    "release": "semantic-release"
  },
  "devDependencies": {
    "@semantic-release/changelog": "^6.0.3",
    "@semantic-release/commit-analyzer": "^13.0.0",
    "@semantic-release/exec": "^6.0.3",
    "@semantic-release/git": "^10.0.1",
    "@semantic-release/github": "^10.0.0",
    "@semantic-release/release-notes-generator": "^14.0.0",
    "semantic-release": "^24.0.0"
  },
  "repository": {
    "type": "git",
    "url": "https://github.com/YOUR_USERNAME/your-plugin-slug.git"
  }
}
```

**Customization:**
- Replace `your-plugin-slug` with your plugin's directory name
- Replace `YOUR_USERNAME` with your GitHub username
- Update description

### composer.json

```json
{
    "name": "your-vendor/your-plugin",
    "description": "Your plugin description",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=7.4"
    },
    "require-dev": {
        "dealerdirect/phpcodesniffer-composer-installer": "^1.0",
        "phpcompatibility/phpcompatibility-wp": "^2.1",
        "squizlabs/php_codesniffer": "^3.10",
        "wp-coding-standards/wpcs": "^3.1"
    },
    "autoload": {
        "psr-4": {
            "YourVendor\\YourPlugin\\": "src/"
        }
    },
    "scripts": {
        "phpcs": "phpcs",
        "phpcbf": "phpcbf"
    },
    "config": {
        "allow-plugins": {
            "dealerdirect/phpcodesniffer-composer-installer": true
        },
        "optimize-autoloader": true,
        "platform": {
            "php": "8.1"
        },
        "sort-packages": true
    }
}
```

**Important:**
- `platform.php: "8.1"` ensures dependencies are compatible with CI (PHP 8.1)
- This prevents issues when your local PHP is newer than CI

---

## Step 2: Semantic Release Configuration

### .releaserc.json

```json
{
  "branches": ["master"],
  "plugins": [
    "@semantic-release/commit-analyzer",
    "@semantic-release/release-notes-generator",
    ["@semantic-release/changelog", { "changelogFile": "CHANGELOG.md" }],
    [
      "@semantic-release/exec",
      {
        "prepareCmd": "./scripts/update-version.sh ${nextRelease.version}"
      }
    ],
    [
      "@semantic-release/git",
      {
        "assets": [
          "CHANGELOG.md",
          "your-plugin.php",
          "readme.txt",
          "package.json",
          "package-lock.json"
        ],
        "message": "chore(release): ${nextRelease.version} [skip ci]\n\n${nextRelease.notes}"
      }
    ],
    "@semantic-release/github"
  ]
}
```

**Customization:**
- Replace `your-plugin.php` with your main plugin filename
- Change `master` to `main` if that's your default branch

### Version Triggers

| Commit Type | Version Bump | Example |
|-------------|--------------|---------|
| `feat:`     | Minor (1.0.0 → 1.1.0) | `feat: add export feature` |
| `fix:`      | Patch (1.0.0 → 1.0.1) | `fix: resolve checkout error` |
| `perf:`     | Patch | `perf: optimize database queries` |
| `docs:`     | No release | `docs: update readme` |
| `style:`    | No release | `style: fix code formatting` |
| `refactor:` | No release | `refactor: reorganize classes` |
| `test:`     | No release | `test: add unit tests` |
| `build:`    | No release | `build: update dependencies` |
| `ci:`       | No release | `ci: fix workflow` |
| `chore:`    | No release | `chore: cleanup files` |
| `BREAKING CHANGE:` footer | Major (1.0.0 → 2.0.0) | `feat: drop PHP 8.2` with `BREAKING CHANGE: requires PHP 8.3` in the commit body |

With the default angular preset, the `!` shorthand (`feat!: ...`) is not parsed and cuts **no release at all**; only the `BREAKING CHANGE:` footer produces a major. Because PRs are squash-merged, the footer has to be in the squash commit message.

---

## Step 3: Version Update Script

### scripts/update-version.sh

```bash
#!/bin/bash
# Script to update version numbers in WordPress plugin files
# Usage: ./scripts/update-version.sh <version>

set -e

VERSION=$1

if [ -z "$VERSION" ]; then
    echo "Usage: $0 <version>"
    exit 1
fi

echo "Updating version to: $VERSION"

# Update plugin header Version:
# Adjust the spacing to match your plugin header format
sed -i "s/^ \* Version:.*/ * Version:         $VERSION/" your-plugin.php

# Update version constant (adjust constant name)
sed -i "s/define( 'YOUR_PLUGIN_VERSION', '[^']*' );/define( 'YOUR_PLUGIN_VERSION', '$VERSION' );/" your-plugin.php

# Update readme.txt Stable tag:
sed -i "s/^Stable tag: .*/Stable tag: $VERSION/" readme.txt

echo "Version updated successfully!"

# Verify changes
echo ""
echo "Verification:"
grep "Version:" your-plugin.php | head -1
grep "YOUR_PLUGIN_VERSION" your-plugin.php
grep "Stable tag:" readme.txt
```

**Customization:**
- Replace `your-plugin.php` with your main plugin filename
- Replace `YOUR_PLUGIN_VERSION` with your version constant name
- Adjust spacing in sed commands to match your file format

In this repository the script also runs `scripts/sync-readme-changelog.php`, which prepends the new version from `CHANGELOG.md` to the `== Changelog ==` section of `readme.txt` (the changelog WordPress.org shows). It never rewrites existing entries, and it trims the section to the 10 most recent versions: older ones, hand-written ones included, are dropped and covered by the "Older releases" link (the 1.5.0 release drops 1.2.0). `@semantic-release/changelog` runs before `@semantic-release/exec`, so `CHANGELOG.md` already contains the new release when the script reads it.

**Make it executable:**
```bash
chmod +x scripts/update-version.sh
```

---

## Step 4: GitHub Workflows

### .github/workflows/release.yml

```yaml
# Automated release workflow
# Triggers on push to master, creates versioned releases with zip artifacts

name: Release

on:
  push:
    branches:
      - master  # Change to 'main' if needed

# Required permissions for creating releases and pushing commits
permissions:
  contents: write
  issues: write
  pull-requests: write

jobs:
  release:
    name: Release
    runs-on: ubuntu-latest
    steps:
      # Checkout with full history (needed for semantic-release)
      - name: Checkout
        uses: actions/checkout@v4
        with:
          fetch-depth: 0
          persist-credentials: false

      # Node.js for semantic-release
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: "lts/*"
          cache: npm

      # PHP for Composer (production dependencies)
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.1"
          tools: composer:v2

      # Cache Composer dependencies for faster builds
      - name: Get Composer cache directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

      - name: Cache Composer dependencies
        uses: actions/cache@v4
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: |
            ${{ runner.os }}-composer-

      # Install dependencies
      - name: Install npm dependencies
        run: npm ci

      # IMPORTANT: --no-dev excludes PHPCS and other dev tools from release
      - name: Install Composer dependencies (production)
        run: composer install --no-dev --optimize-autoloader --prefer-dist

      # Run semantic-release (analyzes commits, bumps version, creates release)
      - name: Run Semantic Release
        id: semantic
        uses: cycjimmy/semantic-release-action@v4
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

      # Build the package only if a new release was created.
      # .distignore decides what ships; the script checks the result
      # and writes dist/your-plugin-slug.zip.
      - name: Build release package
        if: steps.semantic.outputs.new_release_published == 'true'
        run: ./scripts/build-package.sh dist

      # Upload zip to GitHub release
      - name: Upload artifact to release
        if: steps.semantic.outputs.new_release_published == 'true'
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        run: |
          gh release upload "v${{ steps.semantic.outputs.new_release_version }}" \
            dist/your-plugin-slug.zip \
            --clobber
```

**Customization:**
- Replace `your-plugin-slug` with your plugin directory name
- Change `master` to `main` if needed
- Edit `.distignore`, not the workflow, to change what ships

This is the minimal shape. This repository's `.github/workflows/release.yml` adds two guard steps and the WordPress.org deploy described in [Step 6](#step-6-wordpressorg-deployment). "Validate plugin package" builds and checks the package before semantic-release runs, so a broken package stops the run before anything is tagged. "Verify workspace is the release commit" checks that the checked-out `HEAD` is the new tag and carries the new version before "Build plugin package" runs. That second guard runs **after** semantic-release has pushed the tag and published the GitHub release, so when it fails the release already exists, without `shift64-phone-validation.zip`. Fix the cause (for example a file `update-version.sh` changes that is missing from the `@semantic-release/git` assets in `.releaserc.json`), then run `wporg-deploy.yml` for that tag with `upload-zip` on to attach the ZIP (see [Troubleshooting](#zip-not-found-in-release)).

### .github/workflows/code-quality.yml

```yaml
# Code quality checks
# Runs PHPCS with WordPress Coding Standards on PRs and pushes

name: Code Quality

on:
  pull_request:
    branches:
      - master  # Change to 'main' if needed
  push:
    branches:
      - master

jobs:
  phpcs:
    name: PHPCS
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      # PHP with cs2pr for GitHub annotations
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.1"
          tools: composer:v2, cs2pr

      # Cache Composer for faster builds
      - name: Get Composer cache directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

      - name: Cache Composer dependencies
        uses: actions/cache@v4
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: |
            ${{ runner.os }}-composer-

      # Install WITH dev dependencies (includes PHPCS)
      - name: Install Composer dependencies
        run: composer install --prefer-dist

      # Run PHPCS and generate checkstyle report
      - name: Run PHPCS
        run: composer phpcs -- --report-full --report-checkstyle=./phpcs-report.xml

      # Convert checkstyle to GitHub annotations (shows errors inline in PR)
      - name: Show PHPCS results in PR
        if: always()
        run: cs2pr ./phpcs-report.xml
```

This repository's `code-quality.yml` also has two more jobs:

- `phpunit` runs `composer test` on PHP 8.3, 8.4 and 8.5. The step runs in bash with `pipefail`, so a failing PHPUnit fails the step even though its output is piped through `tee`, and it also requires PHPUnit's summary line (`OK (...)`, or `OK, but ...` when tests were skipped or incomplete). A run that exits 0 without printing a result, for example when a file's `ABSPATH` guard runs before `tests/Unit/bootstrap.php` has defined `ABSPATH`, therefore fails instead of looking green.
- `package` builds the release package and runs Plugin Check on it; see [Step 6](#step-6-wordpressorg-deployment).

`e2e.yml` runs the Playwright checkout tests in wp-env with WooCommerce on every PR, every push to `master`, weekly and on demand. It writes the WordPress and WooCommerce versions it ran against to the job summary, so a new release of either is noticed before `Tested up to` (`readme.txt`) or `WC tested up to` (plugin header) falls behind. The WooCommerce version is read from the `WC_VERSION` constant, because wp-env installs WooCommerce from `woocommerce.latest-stable.zip` into a folder of that name, where `wp plugin get woocommerce` finds nothing.

#### Docs-only pull requests

A pull request that changes only documentation skips the code jobs: `phpcs`, `phpunit` and `package` in `code-quality.yml`, and `playwright` in `e2e.yml` show as **Skipped**. Each workflow starts with a `changes` job ("Detect docs-only change") that lists the files the pull request changes since its merge base (`git diff --no-renames --name-only base...head`) and passes them to `scripts/ci-docs-only.sh`. The job summary shows the answer and the file list. `pr-lint.yml` always runs.

The script answers `true` only when every changed path is on its allowlist:

- the root files `AGENTS.md`, `CLAUDE.md`, `SDLC.md`, `CODE_REVIEW.md`, `BACKWARD_COMPATIBILITY.md` and `README.md` (exact names);
- anything under `docs/`, `.claude/`, `.wordpress-org/`, and `.ai/` except `.ai/qa/`.

Everything else runs the full suite. Some files look like documentation but are not on the list on purpose:

- `readme.txt` ships, and Plugin Check validates it.
- `CHANGELOG.md` is read by `tests/Unit/ReadmeChangelogSyncTest.php`.
- `tests/` includes Markdown fixtures that PHPUnit reads.
- `LICENSE` is required by `build-package.sh`.
- A new root `.md` file fails the package build unless `.distignore` excludes it.
- `.ai/qa/test-env.env` is loaded by `playwright.config.ts`.
- Any change under `.github/` runs everything, so a change to this mechanism is always tested.

The skip applies to `pull_request` events only. Pushes to `master`, the weekly E2E run and manual runs always run every job. If the changed files cannot be listed, or the `changes` job fails, the code jobs run: they skip only on an explicit `true`. `tests/Unit/CiDocsOnlyClassifierTest.php` pins the allowlist and the `needs`/`if` lines on each gated job.

To check a branch locally:

```bash
git diff --no-renames --name-only origin/master...HEAD | ./scripts/ci-docs-only.sh
```

A skipped job reports the conclusion `skipped`, which GitHub accepts for a required status check. A workflow excluded with `paths-ignore` would report nothing, and a required check would wait forever. `master` has no required checks today. If you add them, note that a skipped matrix job shows under its unexpanded name, `PHPUnit (PHP ${{ matrix.php }})`, so require the expanded names, or add an aggregate job that depends on the matrix.

### .github/workflows/pr-lint.yml

```yaml
# PR title validation
# Ensures PR titles follow conventional commit format for semantic-release

name: "Lint PR"

on:
  pull_request:
    types:
      - opened
      - edited
      - synchronize
    branches:
      - master  # Change to 'main' if needed

permissions:
  pull-requests: read
  statuses: write

jobs:
  main:
    name: Validate PR title
    runs-on: ubuntu-latest
    steps:
      - uses: amannn/action-semantic-pull-request@v5
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        with:
          # Allowed PR title prefixes
          types: |
            feat
            fix
            docs
            style
            refactor
            perf
            test
            build
            ci
            chore
            revert
          # Set to true to require scope: "feat(api): add endpoint"
          requireScope: false
```

---

## Step 5: PHPCS Configuration

### .phpcs.xml.dist

```xml
<?xml version="1.0"?>
<ruleset name="Your Plugin Name">
    <description>PHPCS ruleset for Your Plugin.</description>

    <!-- What to scan -->
    <file>.</file>

    <!-- Exclude paths - adjust for your structure -->
    <exclude-pattern>/vendor/*</exclude-pattern>
    <exclude-pattern>/node_modules/*</exclude-pattern>
    <exclude-pattern>/dist/*</exclude-pattern>
    <exclude-pattern>/tests/*</exclude-pattern>
    <exclude-pattern>/assets/build/*</exclude-pattern>

    <!-- How to scan -->
    <arg value="sp"/> <!-- Show sniff and progress -->
    <arg name="basepath" value="."/> <!-- Strip paths to relevant bit -->
    <arg name="colors"/>
    <arg name="extensions" value="php"/>
    <arg name="parallel" value="8"/> <!-- Parallel processing -->

    <!-- Rules: WordPress Coding Standards -->
    <rule ref="WordPress-Extra">
        <!-- Allow short array syntax [] instead of array() -->
        <exclude name="Universal.Arrays.DisallowShortArraySyntax"/>
    </rule>

    <!-- Verify text domain matches your plugin -->
    <rule ref="WordPress.WP.I18n">
        <properties>
            <property name="text_domain" type="array">
                <element value="your-plugin-text-domain"/>
            </property>
        </properties>
    </rule>

    <!-- Check function/class prefixes to avoid conflicts -->
    <rule ref="WordPress.NamingConventions.PrefixAllGlobals">
        <properties>
            <property name="prefixes" type="array">
                <element value="your_prefix"/>
                <element value="YourPrefix"/>
                <element value="YOUR_PREFIX"/>
            </property>
        </properties>
    </rule>

    <!-- PHP Compatibility - adjust minimum version -->
    <rule ref="PHPCompatibilityWP"/>
    <config name="testVersion" value="7.4-"/>

    <!-- Allow PSR-4 file naming in src/ directory -->
    <rule ref="WordPress.Files.FileName">
        <exclude-pattern>/src/*</exclude-pattern>
    </rule>

    <!-- Exclude main plugin file from class naming requirement -->
    <rule ref="WordPress.Files.FileName.InvalidClassFileName">
        <exclude-pattern>/your-plugin.php</exclude-pattern>
    </rule>

    <!-- Allow unused parameters required by WordPress hook signatures -->
    <rule ref="Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed">
        <exclude-pattern>/src/*</exclude-pattern>
    </rule>

    <!-- Allow common parameter names that are reserved keywords -->
    <rule ref="Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound">
        <severity>0</severity>
    </rule>
</ruleset>
```

**Customization:**
- Replace `your-plugin-text-domain` with your text domain
- Replace `your_prefix`, `YourPrefix`, `YOUR_PREFIX` with your prefixes
- Replace `your-plugin.php` with your main plugin filename
- Adjust exclude patterns for your project structure

---

## Step 6: WordPress.org Deployment

The plugin is distributed through the WordPress.org plugin directory, and WordPress.org also serves its updates. Every release ships **one package**: the ZIP attached to the GitHub release and the files committed to WordPress.org SVN come from the same built folder. This step uses this repository's names; replace `shift64-phone-validation` with your slug when reusing it.

### No self-updater (Guideline 8)

[Detailed Plugin Guideline 8](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) does not allow a directory plugin to serve updates "from servers other than WordPress.org's", and Plugin Check reports an update checker as an error (`plugin_updater_detected`). This plugin therefore has no updater of its own and makes no outgoing HTTP requests (D05 in `.ai/specs/product-brief.md`). Do not add one: no update-checker library, no `wp_remote_*` call, no hook on `pre_set_site_transient_update_plugins`, `plugins_api` or `upgrader_*`.

### What ships: `.distignore` and `scripts/build-package.sh`

- **`.distignore`** is the single source of truth for what stays out of the package: development and process docs, `docs/`, `scripts/`, `tests/`, `bin/`, tool configs, `package*.json`, `composer.lock`, `node_modules/`, and every hidden file or folder (`.ai/`, `.claude/`, `.github/`, `.wordpress-org/`, `.wp-env.json`, ...). It never excludes `vendor/` or `composer.json`: the plugin cannot run without the production `vendor/`, and Plugin Check warns when `vendor/` ships without `composer.json`. Anything new at the repository root that is not runtime code goes in `.distignore`.
- **`scripts/build-package.sh <out-dir>`** needs a production `vendor/` first (`composer install --no-dev --optimize-autoloader`) and refuses a dev one. It copies the plugin with `rsync --exclude-from=.distignore` to `<out-dir>/shift64-phone-validation/`, checks the result (main file, `readme.txt`, `LICENSE`, `composer.json` and `vendor/autoload.php` present; `Stable tag` equal to the header `Version`; no hidden files, shell scripts, `.phar`/`.dist` files or archives; no stray Markdown at the root) and zips it to `<out-dir>/shift64-phone-validation.zip`, failing above the 10 MB submission limit. `<out-dir>` must be outside the plugin folder or under `dist/`.
- The WordPress.org deploy passes the built folder to the deploy action as `BUILD_DIR`. With `BUILD_DIR` set, the action ignores `.distignore` and `.gitattributes` and commits the folder as it is, which is why the build script, not the action, decides what ships.

### Workflows

| Workflow | Runs | What it does |
|---|---|---|
| `release.yml` | on every push to `master` | Builds and checks the package before releasing, runs semantic-release (which pushes the tag and publishes the GitHub release), verifies that `HEAD` is the new tag, builds the package, uploads `shift64-phone-validation.zip` to the GitHub release, then deploys the same folder to SVN with [`10up/action-wordpress-plugin-deploy`](https://github.com/10up/action-wordpress-plugin-deploy) (`BUILD_DIR`, `VERSION`), in one SVN commit to `trunk/`, `tags/<version>/` and `assets/`. The SVN step runs only when the SVN secrets exist; otherwise it is skipped with a notice and the job stays green. A failure after semantic-release leaves a published release without the ZIP; see [Zip not found in release](#zip-not-found-in-release). |
| `wporg-deploy.yml` | manually (`workflow_dispatch`): input `tag` (for example `v1.5.0`) and the switches `allow-older` (default off), `upload-zip` (default on) and `svn` (default on) | Checks out that tag, installs production dependencies, verifies the tag carries that version and builds the package. With `upload-zip` on it re-attaches `shift64-phone-validation.zip` to that tag's GitHub release, the asset 1.4.x updaters download; with `svn` on it deploys the package to SVN. With `svn` on, a check that runs before anything is built fails the run when the SVN secrets are missing, or when the tag is not the latest GitHub release and `allow-older` is off: an older tag would overwrite SVN `trunk/` with older code and move `Stable tag` back, so WordPress.org would serve the older version, and deploying the newer tag again cannot repair that because its SVN tag already exists. With `svn` off neither check runs, so attaching a ZIP needs no SVN secrets and works for any tag. Used for the first deploy after approval, to retry a failed deploy of the latest release, and to attach a missing release ZIP (with `svn` off before approval). Works for tags from `v1.5.0` on, which contain `scripts/build-package.sh`; the SVN step changes nothing for a version already in SVN `tags/`. |
| `wporg-assets.yml` | after a successful Release run (`workflow_run`), or manually | Pushes `readme.txt` (to `trunk/` and the current `Stable tag` folder) and `.wordpress-org/` (to `assets/`) with [`10up/action-wordpress-plugin-asset-update`](https://github.com/10up/action-wordpress-plugin-asset-update), never code and without a new version. Skipped with a notice without the SVN secrets, and also skipped with a notice until `tags/<Stable tag>` exists in SVN, so no readme-only commit reaches `trunk/` before the first `wporg-deploy.yml` run. Postponed while a Release run is still queued or running. |
| `code-quality.yml`, job `package` | on every PR and push to `master` | Builds the package, uploads the ZIP as a workflow artifact, and runs [`WordPress/plugin-check-action`](https://github.com/WordPress/plugin-check-action) on the built folder with the latest WordPress. Any Plugin Check error fails the job; warnings show as annotations and in a PR comment. |

`release.yml` and `wporg-deploy.yml` share the concurrency group `release`, so two releases, or a release and a manual deploy, never commit to SVN at the same time. GitHub keeps one running and one pending run per group, and a newer pending run cancels the older one. Between two Release runs that loses nothing, because the newer run releases every commit since the last tag. A manual deploy is different: dispatching `wporg-deploy.yml` while a Release run is running and another one is queued cancels the queued Release run, and its commits are not released until the next push to `master`. Dispatch `wporg-deploy.yml` only when no Release run is queued; if a queued Release run was cancelled anyway, re-run it from the Actions tab. The reverse also happens: a push to `master` cancels a manual deploy that is still waiting, so dispatch it again once the release has finished. `wporg-assets.yml` keeps its own group for the same reason, so that it can never cancel a waiting Release run; instead it postpones itself while a Release run is queued or running and runs again when that run completes, so it never puts an older `readme.txt` over the one a release just deployed.

### Secrets

Add both under **Settings → Secrets and variables → Actions**:

| Secret | Value |
|---|---|
| `SVN_USERNAME` | Your WordPress.org username. It is case-sensitive. |
| `SVN_PASSWORD` | The SVN-specific password you set in your WordPress.org account settings, not your login password. |

The account must have commit access to the plugin. WordPress.org grants it to the submitting account on approval.

### Before approval

Without the secrets, the SVN steps in `release.yml` and `wporg-assets.yml` are skipped with a notice, and releases still reach GitHub. If a Release run fails after publishing the GitHub release, attach the missing ZIP with `wporg-deploy.yml` for that tag, `upload-zip` on and `svn` off; that needs no SVN secrets. To submit, upload `shift64-phone-validation.zip` from the latest GitHub release at [wordpress.org/plugins/developers/add](https://wordpress.org/plugins/developers/add/). WordPress.org derives the slug from the `Plugin Name` header; it must come out as `shift64-phone-validation`, the same as the text domain (D06).

### First deploy after approval

1. The approval email names the SVN repository (`https://plugins.svn.wordpress.org/shift64-phone-validation/`).
2. Add `SVN_USERNAME` and `SVN_PASSWORD` as described above.
3. In the Actions tab, run **WordPress.org deploy (manual)** (`wporg-deploy.yml`) with the latest release tag, for example `v1.5.0`, and the switches left at their defaults (`allow-older` off, `upload-zip` and `svn` on). Do it while no Release run is queued (see [Workflows](#workflows)). It commits `trunk/`, `tags/<version>/` and the listing assets from `.wordpress-org/` to `assets/`, and re-attaches the ZIP it built from that tag to the GitHub release. Any `wporg-assets.yml` run between steps 2 and 3 skips with a notice, because `tags/<Stable tag>` is not in SVN yet, so nothing reaches `trunk/` before this deploy.
4. Check the listing at `https://wordpress.org/plugins/shift64-phone-validation/` once WordPress.org has processed the commit.

From then on, every release deploys from `release.yml` without manual steps.

### Readme and listing assets

`readme.txt` is the listing text; `.wordpress-org/` holds the listing images: `icon-128x128.png`, `icon-256x256.png`, `icon.svg`, `banner-772x250.png`, `banner-1544x500.png`, and `screenshot-1.png`, `screenshot-2.png`, ... numbered in the order of the `== Screenshots ==` captions in `readme.txt`. A change to these alone needs no release: merge it with a non-releasing PR title such as `docs:`, and `wporg-assets.yml` pushes it after the Release workflow finishes (once the current `Stable tag` exists in SVN `tags/`).

The `== Changelog ==` section of `readme.txt` is maintained by `scripts/sync-readme-changelog.php` (see Step 3); edit older entries by hand if needed, but let the release add new ones.

### Plugin Check

The `package` job must stay at 0 errors. One warning is expected and accepted: `load_plugin_textdomainFound` for the `load_plugin_textdomain()` call in the main plugin file. WordPress 7.1 registers the bundled `languages/` folder from the `Domain Path` header itself only for plugins activated per site, so a network-activated install needs that call; since WordPress 6.7 it loads no file. The second 1.x warning, for `BlockCheckoutValidator`, is gone with its text-domain reload (issue #32, D07). Any other warning is new and worth reading, even though it does not fail the job. To reproduce a CI failure locally, take the ZIP from the job's `shift64-phone-validation` artifact (or build it), install it on a test site that has the Plugin Check plugin and run `wp plugin check shift64-phone-validation` there. Checking the repository folder itself also reports development files that never ship.

### Release cadence (Guideline 14)

Every SVN commit regenerates the plugin's ZIP on WordPress.org, and Guideline 14 asks authors to avoid frequent commits: many small commits in a row strain the system and can look like gaming the "Recently Updated" list. Batch small fixes into one release instead of merging a string of `fix:` PRs back to back.

### Sites installed from a GitHub ZIP

Sites running 1.4.2 or earlier from a GitHub release ZIP still have the old built-in updater. It looks for a release asset named `verify-phone-number-shift64.zip`, the name of every package up to 2.0.0. From 2.0.1 the plugin is renamed to `shift64-phone-validation` (WordPress.org review asked for the distinctive part at the start of the name) and the asset is `shift64-phone-validation.zip`, so those sites are offered nothing newer and are reinstalled by hand (README, *Moving an existing install to WordPress.org updates*). Only a copy in the folder `shift64-phone-validation` gets updates from WordPress.org, because WordPress.org matches installs by folder name. This was checked on 2026-10-06 against its update API (a POST to `https://api.wordpress.org/plugins/update-check/1.1/` with a `WordPress/7.1.2` User-Agent): `query-monitor/query-monitor.php` was offered an update, the same plugin in `query-monitor-master/`, `query-monitor-main/` or an arbitrary folder was not, and wp-crontrol, user-switching, akismet, woocommerce and wordpress-seo in `-master` folders were not offered one either. The one exception, `classic-editor-master`, looked like a server-side alias. This plugin is not in the directory yet, so it could not be checked itself. A copy installed in a differently named folder (`verify-phone-number-shift64` or `verify-phone-number-shift64-master` from before the rename, or `shift64-phone-validation-master` from a source archive) therefore has to be reinstalled from the directory.

---

## Usage

### Daily Workflow

1. Create feature branch
2. Make changes
3. Create PR with conventional title (e.g., `feat: add new feature`)
4. PR checks run automatically (PHPCS, title validation, Plugin Check on the built package)
5. Merge to master
6. Release workflow runs automatically
7. The same package is deployed to WordPress.org (once the SVN secrets exist), and WordPress offers the update to sites on its next update check

### Manual Commands

```bash
# Run PHPCS locally
composer phpcs

# Auto-fix PHPCS issues
composer phpcbf

# Build the release package locally (see Step 6 for what it checks).
# It needs a production vendor/, so restore the dev tools afterwards.
composer install --no-dev --optimize-autoloader
./scripts/build-package.sh dist
composer install
```

---

## Private Composer Dependencies

If your plugin depends on other private repos via Composer, CI needs a token to install them. Whatever ends up in `vendor/` ships in the package, so a directory plugin's dependencies must still be GPL-compatible.

```yaml
# In workflow file, before composer install
- name: Configure Composer auth
  run: composer config github-oauth.github.com ${{ secrets.COMPOSER_GITHUB_TOKEN }}
```

---

## Troubleshooting

### Release not triggered

- Check PR title follows conventional format
- Only `feat:`, `fix:`, `perf:` (and a `BREAKING CHANGE:` footer) trigger releases; a `feat!:` subject is not parsed and releases nothing
- Check GitHub Actions logs for errors

### PHPCS failing in CI

- Run `composer update` locally to sync lock file
- Check `platform.php` is set in composer.json config
- Run `composer phpcs` locally first

### Zip not found in release

- Ensure release workflow completed successfully
- Check release has `your-plugin-slug.zip` asset (here `shift64-phone-validation.zip`)
- Check the "Validate plugin package" step (runs before semantic-release) and the "Build plugin package" step in `release.yml`: `build-package.sh` stops when a guard rail fails (missing `vendor/autoload.php`, a dev `vendor/`, a hidden file or shell script in the package, a `Stable tag` that differs from the header `Version`). A failure in "Validate plugin package" stops the run before anything is tagged.
- "Verify workspace is the release commit", "Build plugin package" and the upload run **after** semantic-release has pushed the tag and published the GitHub release, so a failure there leaves a published release without the ZIP, and re-running the Release run does not attach it (it finds no new commits to release). Fix the cause; for "Verify workspace is the release commit" that is usually a file `update-version.sh` changes that is missing from the `@semantic-release/git` assets in `.releaserc.json`. Then run `wporg-deploy.yml` for that tag with `upload-zip` on (and `svn` off before approval). Do it promptly: sites still on 1.4.x download exactly that asset through their old updater. If the missed file is `readme.txt` or the main plugin file, the tag carries the old version and `wporg-deploy.yml` stops at "Verify the tag carries this version"; the next release (a `fix:` commit) then replaces the broken one.

### WordPress.org deploy skipped

- The SVN steps run only when both `SVN_USERNAME` and `SVN_PASSWORD` exist as repository secrets; without them the workflow logs a notice and skips them
- After adding the secrets, deploy the release that was skipped with `wporg-deploy.yml` (with `svn` on, the default, a manual deploy fails outright when the secrets are missing). If a newer release has been published since, deploy that one instead: the SVN deploy refuses a tag that is not the latest GitHub release unless `allow-older` is on, because an older tag would put older code on `trunk/` and move `Stable tag` back

### WordPress.org readme sync skipped

- `wporg-assets.yml` skips with a notice until `tags/<Stable tag>` exists in SVN; run `wporg-deploy.yml` for the latest release tag first
- It also postpones itself while a Release run is queued or running, and runs again when that run completes

### A Release run was cancelled

- A manual `wporg-deploy.yml` dispatch replaces a Release run that is waiting in the shared `release` concurrency group (see [Workflows](#workflows)); re-run the cancelled Release run from the Actions tab

### SVN authentication fails

- The username is case-sensitive
- `SVN_PASSWORD` must be the SVN-specific password from your WordPress.org account settings, not your login password
- The account needs commit access to the plugin

### PHPUnit job failing although no test failed

- The "Run unit tests" step also requires PHPUnit's summary line (`OK (...)` or `OK, but ...`). A run that prints no result fails on purpose. The usual cause is a file whose `ABSPATH` guard ran before `tests/Unit/bootstrap.php` defined `ABSPATH`, which ends PHPUnit silently with exit code 0: load plugin files only after the bootstrap

### Plugin Check job failing

- Read the error code in the job log; packaging findings such as the errors `hidden_files` and `application_detected`, or the warning `unexpected_markdown_file`, mean a development file reached the package: add it to `.distignore`
- Do not silence an error in the check configuration; fix the package or the code

---

## Checklist

Before your first release:

- [ ] Replace all placeholders in files
- [ ] Run `npm install` and `composer install`
- [ ] Make `scripts/update-version.sh` executable
- [ ] Test PHPCS passes: `composer phpcs`
- [ ] Verify version constant exists in main plugin file
- [ ] Verify `readme.txt` has `Stable tag:` line
- [ ] Check that `.distignore` keeps `vendor/` and `composer.json` in the package and everything else that is not runtime code out
- [ ] Create initial release manually or push first `feat:` commit

After setup:

- [ ] Create test PR to verify checks work
- [ ] Merge and verify release is created
- [ ] Confirm the `package` job reports 0 Plugin Check errors
- [ ] Install the release ZIP on a test site and confirm it activates
- [ ] After WordPress.org approval: add `SVN_USERNAME` / `SVN_PASSWORD` and run `wporg-deploy.yml` for the latest tag
