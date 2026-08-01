# The-abyss local environment.
#
# Mirrors an Ubuntu 24.04 server closely enough that commands transfer. The one
# structural difference is that a plain container has no init system, so services
# start with `service <name> start` rather than `systemctl start <name>`.
#
# What is here and why: Apache and php-fpm were installed by hand inside the
# running container at build step 2, verified, and then codified here. That order
# is deliberate, per PLAN.md "learn first, codify second". A Dockerfile written
# before the commands were understood produces a working site and no
# understanding of it.
#
# What lives where, audited at step 4 after three rebuilds each lost a different
# hand-made change. Anything not in this list and not in a volume does not
# survive `docker compose up --build`:
#
#   IMAGE (lost on rebuild, so it must be codified here)
#     packages, /etc/apache2, /etc/php, /etc/ssh, the vhost, enabled modules
#
#   NAMED VOLUME (survives, so it must NOT be codified)
#     /var/www        WordPress files and wp-config.php, which holds credentials
#     /var/lib/mysql  the database, including the_abyss_local and its user
#
#   BIND MOUNT
#     /workspace      this repository, live in both directions
#
# WordPress itself is deliberately absent: it is data in a volume, not image
# content, and wp-config.php must never be baked into an image. Making a
# from-scratch machine self-provisioning is a step 5 item.

FROM ubuntu:24.04

# Stops apt from opening interactive configuration prompts that would hang a
# non-interactive build. This covers the build itself and `docker exec`.
ENV DEBIAN_FRONTEND=noninteractive TZ=Etc/UTC

# ENV does NOT reach an SSH session. sshd builds a fresh login environment from
# PAM, so Docker's variables are absent and apt reverts to interactive prompts.
# Observed at step 2: installing php-fpm opened the tzdata region picker.
# /etc/environment is read by PAM, so it reaches SSH logins as well.
RUN echo 'DEBIAN_FRONTEND=noninteractive' >> /etc/environment

# Pin the timezone before any package can ask about it. UTC because servers run
# UTC: logs, cron, and database timestamps from different machines then line up
# without arithmetic. WordPress sets the reader-facing timezone in application
# settings, which is where a display concern belongs.
RUN ln -snf /usr/share/zoneinfo/Etc/UTC /etc/localtime \
 && echo 'Etc/UTC' > /etc/timezone

# ca-certificates so HTTPS downloads work, curl for verification checks, an
# editor because configuration is edited in place, openssh-server for access,
# then the web tier itself.
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      ca-certificates \
      curl \
      less \
      nano \
      openssh-server \
      apache2 \
      php-fpm \
      php-mysql \
      php-curl \
      php-xml \
      php-mbstring \
      php-zip \
      php-gd \
      php-intl \
      mysql-server \
 && rm -rf /var/lib/apt/lists/*

# On the extensions above: php-fpm is the FastCGI runtime ONLY. Every extension
# is a separate package. Observed at step 4: without php-mysql, WordPress served
# a 500 with an EMPTY Apache error log, because its requirements check calls
# wp_die() rather than raising a PHP error, so nothing is logged. The page title
# read "Requirements Not Met" and the message was in the response body, which
# `curl -I` does not fetch.
#
# php-mysql is the only one strictly required. The rest are WordPress's own
# recommended set and each would otherwise surface as its own failure later:
# curl for outbound HTTP and updates, xml for RSS and oEmbed, mbstring for
# multibyte strings, zip for plugin and theme installs, gd for image resizing on
# upload, intl for locale-aware formatting. php-imagick is deliberately omitted:
# it pulls in ImageMagick, and gd covers what this site needs.

# Apache does not execute PHP. It forwards .php requests over FastCGI to php-fpm,
# which runs them in its own process. proxy_fcgi does the forwarding, setenvif
# passes the Authorization header through (the WordPress REST API needs it), and
# the packaged conf wires the routing rule.
#
# The conf name carries the PHP version, so it is discovered rather than
# hardcoded. `test -n` makes an empty result fail the build loudly here instead
# of silently producing an image that serves raw PHP source.
RUN a2enmod proxy_fcgi setenvif \
 && FPM_CONF="$(ls /etc/apache2/conf-available/ | grep -o 'php[0-9.]*-fpm' | head -1)" \
 && test -n "$FPM_CONF" \
 && a2enconf "$FPM_CONF" \
 && echo "enabled $FPM_CONF"

# The site vhost. Written here rather than by hand because /etc is a container
# layer, not a volume: a rebuild discards anything created by hand in it.
# Observed at step 4, the hard way. /var/www and /var/lib/mysql survive a rebuild
# because they are named volumes. /etc/apache2 does not.
#
# DocumentRoot is /var/www/the-abyss, deliberately not Apache's default
# /var/www/html, so the vhost is doing real work rather than coincidentally
# matching a default. 000-default must be explicitly disabled: with no
# ServerName match, Apache serves whichever vhost it loaded first, and leaving
# both enabled shows the Apache welcome page instead of the site.
#
# The vhost is COPYed from docker/the-abyss.conf rather than generated here, and
# that is deliberate on two counts. It stays a readable Apache config file, and
# on a real host it is the same file in the same place with only ServerName
# changing. It also fixes an escaping problem: Docker substitutes ${...} in RUN
# instructions before the shell sees them, so generating this inline meant
# getting ${APACHE_LOG_DIR} through a RUN, a single-quoted shell string, and
# Apache's own expansion. The escape left a literal backslash in the file and the
# build failed with "Cannot access directory /etc/apache2/\/var/log/apache2/".
# Docker never substitutes inside a COPYed file, so the variable arrives intact.
COPY the-abyss.conf /etc/apache2/sites-available/the-abyss.conf

# WP-CLI. Added 2026-08-01, when plugin installation became the next real task
# and doing it through wp-admin meant clicking rather than a reproducible list.
#
# Installed from the official phar rather than apt: Debian's wp-cli package lags
# badly, and the phar is the upstream-supported distribution. The same two lines
# work unchanged on the droplet, which is the point.
#
# --info is run at build time as a smoke test, so a broken download fails the
# build here rather than surfacing as a confusing error during a later step.
RUN curl -fsSL -o /usr/local/bin/wp \
      https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
 && chmod +x /usr/local/bin/wp \
 && wp --allow-root --info

RUN mkdir -p /var/www/the-abyss \
 && a2enmod rewrite \
 && a2dissite 000-default \
 && a2ensite the-abyss \
 && apache2ctl configtest

# sshd refuses to start without its privilege-separation directory, and the
# package does not create it in a container build.
#
# No host keys are generated here on purpose. Keys written to /etc/ssh are image
# content, so every rebuild produced a new server identity and the client
# correctly refused to connect with REMOTE HOST IDENTIFICATION HAS CHANGED. The
# entrypoint now generates them into /etc/ssh/keys, which compose mounts as a
# named volume, so the container keeps one identity across rebuilds exactly as a
# real server keeps its identity across reprovisioning.
RUN mkdir -p /run/sshd

# Ubuntu's sshd_config includes /etc/ssh/sshd_config.d/*.conf from its first
# line, and the first value obtained for a keyword wins, so a drop-in here
# overrides the shipped defaults without editing the packaged file.
#
# Key auth only. No password is ever set on this container, so there is no
# credential at rest and nothing to guess. Root login matches what a fresh
# droplet gives you. Creating a deploy user and disabling root is deliberate
# later hardening, at PLAN.md step 5, so that it is learned as hardening.
# The HostKey paths point into the named volume mounted at /etc/ssh/keys. The
# entrypoint creates them before sshd starts, so sshd never sees a missing file.
RUN printf '%s\n' \
      'PermitRootLogin prohibit-password' \
      'PasswordAuthentication no' \
      'PubkeyAuthentication yes' \
      'AuthorizedKeysFile .ssh/authorized_keys' \
      'HostKey /etc/ssh/keys/ssh_host_ed25519_key' \
      'HostKey /etc/ssh/keys/ssh_host_rsa_key' \
      'HostKey /etc/ssh/keys/ssh_host_ecdsa_key' \
    > /etc/ssh/sshd_config.d/10-the-abyss.conf

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www

# 22 for ssh, 80 for Apache. EXPOSE documents intent. compose.yaml is what
# actually publishes them to the host.
EXPOSE 22 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# sshd in the foreground is the container's main process. The entrypoint starts
# php-fpm and Apache before handing over. -e sends sshd's log to stderr so
# `docker compose logs web` shows authentication failures instead of hiding them.
CMD ["/usr/sbin/sshd", "-D", "-e"]
