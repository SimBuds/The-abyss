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

# Copy the working tree, not HEAD, so uncommitted changes to provision.sh are
# what actually gets tested. Tracked files only, so the build context stays
# small and no stray local artefact ends up inside the image.
mkdir -p "$CTX/repo"
( cd "$REPO_DIR" && git ls-files -z | tar --null -T - -cf - ) | tar -xf - -C "$CTX/repo"

echo "==> Building. Two full provision runs, then verification."
docker build -t abyss-provision-test "$CTX"

echo
echo "==> Passed: provision.sh built a bare host, re-ran cleanly on it, and the"
echo "    result verified. The image is tagged abyss-provision-test if you want"
echo "    to poke at it:  docker run --rm -it abyss-provision-test bash"
