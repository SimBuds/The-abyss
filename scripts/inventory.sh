#!/usr/bin/env bash
#
# Step 9: read-only inventory of the target EC2 instance.
#
# Retargeted 2026-08-01 from DigitalOcean to AWS EC2. Three things changed:
# the metadata service (IMDSv2 needs a token), the SSM agent check (new, because
# the architecture depends on it), and the manual list at the end.
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
# EC2 instance metadata. IMDSv2 requires a token from a PUT before anything can
# be read, and newer AMIs disable the unauthenticated v1 path entirely, so a
# plain GET against 169.254.169.254 silently returns nothing. Asking for the
# token first works on both.
if have curl; then
	echo
	IMDS_TOKEN="$(curl -s --max-time 2 -X PUT \
		-H 'X-aws-ec2-metadata-token-ttl-seconds: 60' \
		http://169.254.169.254/latest/api/token 2>/dev/null || true)"

	if [ -n "$IMDS_TOKEN" ]; then
		for field in instance-id instance-type placement/region placement/availability-zone ami-id; do
			printf '  %-28s %s\n' "$field" \
				"$(curl -s --max-time 2 -H "X-aws-ec2-metadata-token: $IMDS_TOKEN" \
					"http://169.254.169.254/latest/meta-data/$field" 2>/dev/null)"
		done
	else
		echo "  (EC2 metadata service unreachable — not an EC2 instance, or IMDS is disabled)"
	fi
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

hr "SSM agent"
# The architecture decision is SSM Session Manager with no inbound port 22. If
# the agent is not running and registered, that end state is not reachable yet
# and port 22 is still the only way in. See the port 22 divergence in AGENTS.md.
if systemctl is-active --quiet snap.amazon-ssm-agent.amazon-ssm-agent 2>/dev/null \
   || systemctl is-active --quiet amazon-ssm-agent 2>/dev/null; then
	echo "  running"
else
	echo "  NOT running — the instance will not appear in Systems Manager"
fi

hr "Cannot be answered from the shell"
cat <<'EOF'
  Check these in the AWS console and record them by hand:

    - Security group: which ports are open, and to which sources. This is the
      real firewall on EC2. ufw above is a second layer, and the two can
      disagree without either reporting a problem.
    - Elastic IP: whether one is associated. An auto-assigned public IPv4 is
      released on stop/start, and both DNS and TLS need a stable address.
    - EBS snapshots: whether Data Lifecycle Manager or AWS Backup is configured.
      EC2 has no equivalent of the droplet's automatic backups, so unless one of
      those was set up, there are none.
    - IAM instance profile: whether the SSM role is attached, and whether an S3
      role exists for off-instance backups.
    - SES: whether the account is still in the sandbox, which only permits mail
      to verified addresses. Outbound port 25 is blocked on EC2 regardless, so
      SES or another provider is required, not optional. This is the newsletter
      decision in PLAN.md step 8c.
    - Billing: confirm the expected ~$18/month baseline and that nothing else is
      running in the account.
EOF

echo
echo "Inventory complete. Nothing was changed."
