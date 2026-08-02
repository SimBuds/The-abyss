#!/usr/bin/env bash
#
# Step 9: read-only inventory of the target droplet.
#
# Retargeted back to DigitalOcean 2026-08-01, the same day it was pointed at EC2.
#
# READ THIS BEFORE RUNNING IT ANYWHERE: the intended target is now a droplet that
# already serves a live portfolio site. Every command in this file is a read, and
# that is the whole contract — but it is worth re-reading the audit below rather
# than trusting the claim, because the cost of being wrong is somebody else's
# site.
#
# Establishes the starting point before anything is installed. Nothing in this
# file writes, installs, enables, disables, or restarts anything — every command
# is a read. That is the whole contract, and it is why this is a script rather
# than a list of commands to paste: a script can be read once and audited, and it
# cannot drift between the version that was reviewed and the version that ran.
#
#   # ON DROPLET
#   sudo bash scripts/inventory.sh
#
# sudo is only for the two checks that need it (listening sockets with process
# names, and sshd's effective config). It runs fine without sudo, with those two
# sections reduced.
#
# Paste the whole output back. It is the deliverable for Step 9 in PLAN.md.

set -uo pipefail   # deliberately no -e: a missing tool should skip a section,
                   # not abandon the inventory.

hr()  { printf '\n\033[1m--- %s %s\033[0m\n' "$*" "$(printf '%.0s-' $(seq 1 $((60 - ${#1}))))"; }
have() { command -v "$1" >/dev/null 2>&1; }

echo "The-abyss droplet inventory — $(date -u '+%Y-%m-%d %H:%M UTC')"

hr "Host, size, and resources"
# Droplet size and region are metadata-service answers; the shell only knows
# what the kernel was told.
hostnamectl 2>/dev/null || uname -a
echo
echo "CPU:    $(nproc) vCPU"
free -h
echo
df -h /
# DigitalOcean metadata. Unauthenticated, unlike EC2's IMDSv2, so a plain GET
# works. Fails quietly on anything that is not a droplet.
if have curl; then
	echo
	for field in region size_slug hostname; do
		printf '  %-14s %s\n' "$field" \
			"$(curl -s --max-time 2 "http://169.254.169.254/metadata/v1/$field" 2>/dev/null)"
	done
	echo
fi

hr "Ubuntu version"
# The container is 24.04. A mismatch means parts of what the Dockerfile codified
# do not transfer as written, above all the php-fpm conf name.
lsb_release -a 2>/dev/null || cat /etc/os-release

hr "What the image already installed (the load-bearing question)"
# A plain Ubuntu image should report every one of these as absent. Anything
# present means a marketplace image, which would arrive with the whole stack
# already configured and skip what this build was structured around.
for c in apache2 nginx php php-fpm mysql mariadb wp docker certbot; do
	if have "$c"; then
		printf '  PRESENT  %-10s %s\n' "$c" "$(command -v "$c")"
	else
		printf '  absent   %s\n' "$c"
	fi
done
echo
echo "WordPress on disk:"
find /var/www /srv /usr/share -maxdepth 4 -name wp-includes -type d 2>/dev/null | head || true
[ -d /var/www ] && { echo; echo "/var/www:"; ls -la /var/www 2>/dev/null; }

hr "Web server packages and services"
dpkg -l 2>/dev/null | grep -iE '^ii\s+(apache2|nginx|php|mysql-server|mariadb-server)' | awk '{printf "  %-34s %s\n", $2, $3}' || echo "  none"
echo
systemctl list-units --type=service --state=running --no-pager --no-legend 2>/dev/null \
	| grep -iE 'apache|nginx|php|mysql|maria' || echo "  no web or database services running"

hr "Listening on 80 and 443"
if have ss; then
	ss -tlnp 2>/dev/null | awk 'NR==1 || /:80 |:443 /'
	echo
	echo "All listening TCP:"
	ss -tlnp 2>/dev/null
else
	netstat -tlnp 2>/dev/null
fi

hr "SSH as delivered"
# Effective config, not the file: sshd -T resolves Include directives, which is
# where Ubuntu 24.04 puts most of the interesting defaults.
if have sshd; then
	sshd -T 2>/dev/null | grep -iE '^(port|permitrootlogin|passwordauthentication|pubkeyauthentication|permitemptypasswords)' \
		|| echo "  (needs root to read effective config)"
else
	grep -iE '^\s*(Port|PermitRootLogin|PasswordAuthentication|PubkeyAuthentication)' \
		/etc/ssh/sshd_config /etc/ssh/sshd_config.d/*.conf 2>/dev/null
fi

hr "Firewall (host level)"
# The droplet firewall and the DigitalOcean cloud firewall are two separate
# things. This only sees the first.
if have ufw; then ufw status verbose 2>/dev/null || echo "  (needs root)"; else echo "  ufw not installed"; fi
echo
if have iptables; then
	echo "iptables INPUT policy: $(iptables -S INPUT 2>/dev/null | head -1 || echo '(needs root)')"
fi
if have fail2ban-client; then
	echo "fail2ban: $(fail2ban-client status 2>/dev/null | head -3 | tr '\n' ' ' || echo 'installed, not running')"
else
	echo "fail2ban: not installed"
fi

hr "Unattended upgrades"
if [ -f /etc/apt/apt.conf.d/20auto-upgrades ]; then cat /etc/apt/apt.conf.d/20auto-upgrades; else echo "  not configured"; fi

hr "Swap"
# The smaller droplet sizes ship without swap, and MySQL plus php-fpm on 1GB
# will OOM under a build without it.
swapon --show 2>/dev/null || echo "  no swap configured"

hr "What else is already on this host"
# The load-bearing question now. This droplet serves a live portfolio site, so
# the inventory is no longer "what did the image install" but "what is already
# here that must not be disturbed".
echo "Enabled vhosts:"
ls -1 /etc/apache2/sites-enabled/ 2>/dev/null || echo "  (no apache2)"
ls -1 /etc/nginx/sites-enabled/ 2>/dev/null || echo "  (no nginx)"
echo
echo "Document roots in use:"
grep -rhoP '(DocumentRoot|root)\s+\K\S+' /etc/apache2/sites-enabled/ /etc/nginx/sites-enabled/ 2>/dev/null | sort -u || true
echo
echo "Databases present:"
mysql -N -B -e 'SHOW DATABASES;' 2>/dev/null || echo "  (cannot read; needs root or no MySQL)"
echo
echo "Existing TLS certificates:"
certbot certificates 2>/dev/null | grep -E 'Certificate Name|Domains|Expiry' || echo "  (none, or certbot absent)"
echo
echo "Docker:"
if have docker; then
	docker --version
	docker ps --format '  {{.Names}}  {{.Image}}  {{.Status}}' 2>/dev/null || echo "  (cannot list; needs group membership)"
else
	echo "  not installed"
fi

hr "Cannot be answered from the shell"
cat <<'EOF'
  Check these in the DigitalOcean control panel and record them by hand:

    - Automatic backups: enabled or not, and the schedule. This matters more
      than usual now: the portfolio site is on the same disk.
    - Cloud firewall: whether one is attached and which ports it allows. It is
      separate from ufw above and either can block without the other saying so.
    - Reserved IP: whether one is assigned, since DNS should point at that.
    - Whether outbound SMTP (port 25) is blocked. It is by default. This decides
      the newsletter provider question in PLAN.md step 8c.
    - Droplet plan and monthly cost, and whether a resize is needed to run a
      second site alongside the portfolio.
EOF

echo
echo "Inventory complete. Nothing was changed."
