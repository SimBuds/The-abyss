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
# MySQL and WordPress are deliberately NOT here yet. They are steps 3 and 4, to
# be installed by hand and codified the same way.

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
 && rm -rf /var/lib/apt/lists/*

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

# sshd refuses to start without its privilege-separation directory, and the
# package does not create it in a container build. ssh-keygen -A generates the
# host keys that identify this container to your client. Those keys change on
# every rebuild, so your client will warn that the host identity changed. Clear
# the old entry with: ssh-keygen -R '[localhost]:2222'
RUN mkdir -p /run/sshd \
 && ssh-keygen -A

# Ubuntu's sshd_config includes /etc/ssh/sshd_config.d/*.conf from its first
# line, and the first value obtained for a keyword wins, so a drop-in here
# overrides the shipped defaults without editing the packaged file.
#
# Key auth only. No password is ever set on this container, so there is no
# credential at rest and nothing to guess. Root login matches what a fresh
# droplet gives you. Creating a deploy user and disabling root is deliberate
# later hardening, at PLAN.md step 5, so that it is learned as hardening.
RUN printf '%s\n' \
      'PermitRootLogin prohibit-password' \
      'PasswordAuthentication no' \
      'PubkeyAuthentication yes' \
      'AuthorizedKeysFile .ssh/authorized_keys' \
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
