#!/usr/bin/env bash
#
# Prove scripts/provision.sh still provisions a bare Ubuntu 24.04 host, and
# still survives being run a second time on the host it just built.
#
#   # ON HOST
#   bash scripts/test-provision/run.sh
#
# Takes a few minutes: it installs the whole stack and downloads WordPress and
# every plugin in scripts/plugins.txt from wordpress.org, exactly as the real
# thing does. Nothing is cached and nothing is stubbed, which is the point —
# a test that skips the slow parts stops testing the parts that break.
#
# Costs nothing and touches no server. Run it before any deploy, and after any
# change to provision.sh, plugins.txt, docker/the-abyss.conf, or the theme
# directory name.

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
CTX="$(mktemp -d)"
trap 'rm -rf "$CTX"' EXIT

cp "$REPO_DIR"/scripts/test-provision/{Dockerfile,drive.py,verify.sh} "$CTX/"

# Copy the working tree, not HEAD, so uncommitted changes are what actually gets
# tested.
#
# --others --exclude-standard matters more than it looks. `git ls-files` alone
# lists TRACKED files, so a brand-new file that had never been committed was
# silently left out of the build context while the harness claimed to be testing
# the working tree. That surfaced on 2026-08-02 as a fatal error in the
# container — a new theme include existed locally, the site worked, and the test
# provisioned a site that could not boot. Ignored files stay out, so no local
# artefact ends up in the image.
mkdir -p "$CTX/repo"
( cd "$REPO_DIR" && git ls-files -z --cached --others --exclude-standard \
    | tar --null -T - -cf - ) | tar -xf - -C "$CTX/repo"

echo "==> Building. Two full provision runs, then verification."
docker build -t abyss-provision-test "$CTX"

echo
echo "==> Passed: provision.sh built a bare host, re-ran cleanly on it, and the"
echo "    result verified. The image is tagged abyss-provision-test if you want"
echo "    to poke at it:  docker run --rm -it abyss-provision-test bash"
