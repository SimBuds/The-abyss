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

# --- host keys --------------------------------------------------------------
# The container's own identity, as opposed to the key that authenticates you to
# it. These live in a named volume so they outlive a rebuild. Keys written into
# /etc/ssh would be image content, changing on every build, and the client is
# right to refuse a server whose identity changed. Generating them once here is
# the container equivalent of a real server keeping its host keys across a
# reprovision.
KEYDIR=/etc/ssh/keys
install -d -m 700 -o root -g root "$KEYDIR"

for t in ed25519 rsa ecdsa; do
  if [ ! -f "$KEYDIR/ssh_host_${t}_key" ]; then
    ssh-keygen -q -t "$t" -f "$KEYDIR/ssh_host_${t}_key" -N ''
    echo "entrypoint: generated $t host key (first run)"
  fi
done

# --- database ---------------------------------------------------------------
# /var/lib/mysql is a named volume, so its contents outlive the image. A rebuild
# can therefore hand MySQL a data directory created by a previous container. The
# chown guards against the mysql user's uid differing between image builds, which
# fails as a refusal to start with the reason buried in /var/log/mysql/error.log
# rather than on stdout. Cheap on a small database, and idempotent.
chown -R mysql:mysql /var/lib/mysql
service mysql start
echo "entrypoint: started mysql"

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

# --- WordPress core ---------------------------------------------------------
# /var/www is a named volume, so on a fresh machine it starts empty and the site
# would 404 with no indication why. Provisioning core files here makes the
# environment reproducible with one command.
#
# wp-config.php is deliberately NOT created. It holds the database password, and
# generating it would mean sourcing that credential from the image, this file, or
# an env var sitting next to them in the repository. Tier 0 forbids secrets at
# rest, so creating it stays a manual step, documented in the README build log
# under step 4.
WP_DIR=/var/www/the-abyss

if [ -f "$WP_DIR/index.php" ]; then
  echo "entrypoint: WordPress present at $WP_DIR"
else
  echo "entrypoint: no WordPress at $WP_DIR, downloading core"
  TMP="$(mktemp -d)"

  # -f so an HTTP error is a failure rather than an error page saved as a
  # tarball, which would surface later as an unrecognised archive.
  if ! curl -fsSL https://wordpress.org/latest.tar.gz | tar -xz -C "$TMP"; then
    echo "ERROR: could not download or extract WordPress." >&2
    echo "Check network access from the container. Refusing to start Apache" >&2
    echo "over an empty document root, which would look like a routing bug." >&2
    rm -rf "$TMP"
    exit 1
  fi

  install -d -o www-data -g www-data "$WP_DIR"
  cp -a "$TMP/wordpress/." "$WP_DIR/"
  rm -rf "$TMP"

  # Apache and php-fpm run as www-data, which needs to own these to write
  # uploads, install plugins, and self-update.
  chown -R www-data:www-data "$WP_DIR"
  echo "entrypoint: WordPress core installed"
  echo "entrypoint: wp-config.php NOT created (holds credentials, see README step 4)"
fi

exec "$@"
