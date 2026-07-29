#!/bin/sh
# Container startup: install the mounted public key, start the web tier, then
# hand off to the container's main process (sshd).
#
# A real Ubuntu host would have systemd start php-fpm and Apache at boot. This
# container has no init system, so the entrypoint does that job explicitly. The
# `service` calls below are the same ones run by hand at build step 2, and are
# `systemctl start <name>` on a real host.

set -eu

# --- SSH key ---------------------------------------------------------------
# Why copy rather than mount straight to /root/.ssh/authorized_keys: a
# bind-mounted file keeps its ownership from the host (your user, uid 1000).
# sshd's StrictModes rejects an authorized_keys file that root does not own, and
# the failure surfaces as "Permission denied (publickey)" with nothing useful in
# the client output. Copying it into place with the right ownership avoids
# turning StrictModes off, which would be disabling a real security control to
# work around a local packaging detail.

KEY_SRC=/tmp/host-key.pub
KEY_DST=/root/.ssh/authorized_keys

if [ ! -f "$KEY_SRC" ]; then
  echo "ERROR: no public key mounted at $KEY_SRC" >&2
  echo "compose.yaml should bind-mount your .pub file there, read-only." >&2
  echo "Refusing to start: sshd would accept no logins and the cause" >&2
  echo "would not be visible from the client side." >&2
  exit 1
fi

install -d -m 700 -o root -g root /root/.ssh
install -m 600 -o root -g root "$KEY_SRC" "$KEY_DST"
echo "entrypoint: authorized_keys installed from $KEY_SRC"

# --- web tier --------------------------------------------------------------
# The php-fpm service name carries the PHP version, so it is discovered rather
# than hardcoded. An empty result is a hard failure: starting Apache without
# php-fpm produces a site that serves PHP source as plain text, which looks like
# a template bug rather than a missing service.

FPM_SVC="$(ls /etc/init.d/ | grep -o 'php[0-9.]*-fpm' | head -1)"

if [ -z "$FPM_SVC" ]; then
  echo "ERROR: no php-fpm init script found in /etc/init.d/" >&2
  echo "Refusing to start Apache without it." >&2
  exit 1
fi

service "$FPM_SVC" start
echo "entrypoint: started $FPM_SVC"

service apache2 start
echo "entrypoint: started apache2"

exec "$@"
