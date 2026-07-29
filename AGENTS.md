# AGENTS.md — how to work in this repo

Read this before acting. `README.md` is the public runbook; this file is the current
project state and working agreement.

## 1. Teach, do not take over

This is a learning project with a real deliverable. The human performs each
infrastructure and WordPress step.

- Explain what the step does and why it matters.
- Give exact instructions labelled by where they run.
- Stop after one step and let the human run it.
- Answer questions before moving on.
- Use read-only checks to verify the result.
- Update `README.md` only after verification.
- Do not SSH into the server or configure it for the human. Verification commands
  in section 7 that touch the server are run by the human, who shares the output.
- Do not generate the complete theme, site, or infrastructure in one pass.

Editing files in this repository is the assistant's work. Running infrastructure,
Git, WordPress, and database commands is the human's.

Record completed steps in this shape:

```text
#### Step N — <title> ✅
**Goal:** one line.
**Why it matters:** the reasoning.
**Commands:** the commands that worked, labelled by location.
**Verify:** the evidence.
**Q&A:** the human's questions and answers.
```

The Q&A block is required, even when it says `none`.

## 2. Project

**The-abyss** is a real WordPress affiliate site covering finance and AI. The
immediate goal is a secure, low-cost production foundation on AWS, followed by a
fresh custom theme and launch.

### Canonical names

| Use | Value |
|---|---|
| Project and public WordPress name | `The-abyss` |
| Machine slug, theme directory, text domain | `the-abyss` |
| PHP function prefix | `the_abyss_` |
| PHP constant prefix | `THE_ABYSS_` |
| EC2 Name tag / hostname | `the-abyss-web-01` |
| Production WordPress path | `/var/www/the-abyss` |
| Production database / user | `the_abyss_wp` / `abyss_wp` |
| Staging WordPress path | `/var/www/the-abyss-staging` |
| Staging database / user | `the_abyss_stg` / `abyss_stg` |
| Staging address | the Elastic IP, then `staging.the-abyss.tld` |

Use these names for every new resource. Passwords, private keys, credentials, live
addresses, and unnecessary AWS resource IDs never belong in the repository.

### Live-site requirements

- TLS before the site is public, real credentials are used, or content is indexed.
  The interim IP-only phase in section 5 is explicitly not a public launch.
- Automated database and file backups with tested restore instructions.
- `rel="sponsored nofollow"` on every affiliate link.
- Visible FTC and Competition Bureau disclosures.
- Author and review-date schema for finance content.
- Least-privilege access, security groups, OS hardening, and monitoring.
- No public MySQL port.

## 3. Current architecture decisions

| Decision | Reason |
|---|---|
| AWS EC2 on Ubuntu | The selected production platform and current learning target |
| `t4g.small` | 2 vCPU / 2 GiB is a practical floor for Ubuntu, Apache/PHP, WordPress, and MySQL together |
| Ubuntu Server 24.04 LTS ARM64 | Stable LTS release with native Graviton support |
| 30 GiB encrypted `gp3` | Enough initial space with inexpensive, general-purpose SSD performance |
| MySQL on the same EC2 host initially | Proportional for zero traffic; avoids a second always-on service |
| RDS later if justified | Move when recovery objectives, availability, memory pressure, or scale require it |
| `php-fpm` with Apache `mpm_event` | Lower memory than `mod_php` under `prefork`, which matters on 2 GiB, and keeps PHP in a separate process the web server cannot leak into |
| 2 GiB swap file, low `vm.swappiness` | MySQL, PHP workers, and image processing sharing 2 GiB is where a WordPress host runs out of memory |
| Elastic IP from launch | An auto-assigned public IPv4 is released on stop/start; DNS and TLS both need a stable address, and an attached Elastic IP costs the same |
| SSM Session Manager for shell access | Removes inbound port 22 entirely, gives IAM-controlled audited access, and avoids editing a security group each time the owner's residential IP rotates |
| Origin locked to Cloudflare | Cloudflare only mitigates what it fronts; an origin reachable on 80/443 from anywhere is bypassed by anyone who finds its address |
| Off-instance backups to S3 via instance role | Backups on the instance do not survive the failure they exist for; an IAM role avoids storing keys on the host |
| CloudWatch agent for memory metrics | EC2 publishes no memory metric by default, and memory is the constraint on this instance |
| Amazon SES for outbound mail | EC2 blocks outbound port 25, so password resets and admin notices fail silently without it |
| Affiliate `rel` applied by a theme filter | A documented rule that depends on authors tagging every link by hand will eventually be missed; a `the_content` filter makes it structural |
| Staging and production as two vhosts on one host | Separate document roots, databases, and users give a real promotion path at no extra cost; a second instance is the upgrade if isolation ever justifies the spend |
| Staging built first, production at domain cutover | Mirrors the normal lifecycle and means the production build is a rehearsed repeat rather than a first attempt |
| Classic theme + `theme.json` | Direct PHP markup control with a governed block-editor palette |
| Fresh hand-built theme | Avoid carrying obsolete content models and identifiers into production |
| Plain CSS initially | Keeps the first production build simple |
| ACF field definitions in PHP | Versioned, reviewable, and deployable |
| Code edited locally and deployed through Git | Production code is never edited in place |
| Cloudflare free tier planned | CDN, edge protection, and DDoS mitigation |
| Backups and caching handled outside WordPress where practical | They must still work when PHP or WordPress fails |

For the initial all-in-one server, bind MySQL to localhost and never add a security
group rule for port 3306. Use `t3.small` only if an x86-only dependency blocks ARM64.

SES sandbox removal requires an AWS review, so request it early rather than
discovering the delay at launch. Locking the origin to Cloudflare means either
restricting 80/443 to Cloudflare's published prefixes or using a Cloudflare Tunnel
and opening no inbound web ports at all; decide which at the Cloudflare step.

## 4. Repository state

- `theme/` is an obsolete prefab-building prototype, not the production
  `the-abyss` theme.
- Do not deploy or cosmetically rename that prototype. Its post types, API
  contracts, palette, typography, and prefixes are the wrong product.
- Create the production theme as a fresh, separately verified step.
- The design-system export referenced in earlier planning is not present in this
  workspace snapshot. Restore it before visual implementation.

## 5. Status

This section is the single source of truth for step state. `README.md` describes the
project and links here; it does not keep its own checklist.

- [x] Canonical project naming established.
- [x] AWS instance and initial SQL topology recommended.
**Current objective: a working WordPress staging site reachable at the server's
public IP over HTTP.** The lifecycle is local → staging → production. Staging is built
and proven first; going live means standing up the production vhost and connecting
the domain, TLS, and Cloudflare. DNS, TLS, Cloudflare, and SES therefore all belong to
phase C. Read the staging constraints below before step 3.

Phase A — staging on the IP:

- [ ] **Step 0 — initialise the Git repository and ignore rules.**
- [ ] Step 1 — launch and verify the Ubuntu EC2 host.
- [ ] Step 2 — allocate the Elastic IP, then establish the Ubuntu baseline, swap
      file, and deployment user.
- [ ] Step 3 — install Apache with `php-fpm` and MySQL, then WordPress into the
      staging vhost and database.
- [ ] Step 4 — harden the OS, web server, PHP, database, and WordPress.
- [ ] Step 5 — backups to S3, real cron, and monitoring.

Phase B — the theme, built and proven on staging:

- [ ] Step 6 — restore the design-system export.
- [ ] Step 7 — establish the local WordPress environment and the Git deployment
      workflow into staging.
- [ ] Step 8 — build and deploy the fresh `the-abyss` theme to staging.

Phase C — production cutover, once the domain is purchased:

- [ ] Step 9 — create the production vhost, database, and WordPress install.
- [ ] Step 10 — DNS, TLS, Cloudflare, and the origin lock.
- [ ] Step 11 — promote staging to production and verify the URL rewrite.
- [ ] Step 12 — SES and outbound mail.
- [ ] Step 13 — rotate credentials, remove `noindex` from production only, and open
      the site publicly.
- [ ] Step 14 — capture the verified infrastructure with Terraform's AWS provider.

Step 6 gates step 8: template implementation cannot start without the design system.
Terraform is captured last so it describes verified reality, but the longer console
work runs unrecorded the further it drifts, so import each phase's resources as that
phase completes rather than saving it all for the end.

### Promotion between environments

Staging and production are separate document roots, databases, and MySQL users on one
host. Theme code travels one way — local → Git → staging → production — and is never
edited in place. Database and uploads travel the other way, production → staging, to
refresh staging with real content once the site is live.

The promotion at step 11 is a WP-CLI export, a URL rewrite, and an import, rehearsed
with `--dry-run` first:

```bash
# ON AWS SERVER
wp db export /tmp/stg.sql --path=/var/www/the-abyss-staging
wp search-replace 'http://<elastic-ip>' 'https://the-abyss.tld' \
  --path=/var/www/the-abyss-staging --export=/tmp/prod.sql --dry-run
```

`wp search-replace` is used rather than `sed` because WordPress stores serialized
arrays in `wp_options` and `wp_postmeta`, where a byte-length prefix accompanies each
string. Editing the text without fixing those lengths corrupts the row, and the
symptom is silently lost widget, menu, and plugin settings rather than an error.

Always take a database export of the destination immediately before overwriting it,
and keep it until the promotion is verified. A promotion is the one routine operation
that destroys a working environment on purpose, so it is the one that most needs a
restore point.

After any promotion, verify that production is reachable over TLS, that staging still
serves `noindex`, and that no production URL points back at the staging address.

### Staging constraints

Serving on a bare IP over HTTP is a build environment, not a launch. Four things
follow from that, and each is cheap now and expensive to retrofit.

**The Elastic IP moves to step 2, before WordPress is installed.** In this phase the
address *is* the site. An auto-assigned public IPv4 is released on stop/start, which
would change the URL WordPress has recorded and break the installation.

**Set the site URL by constant, not in the database.** WordPress stores `siteurl` and
`home` in `wp_options`, and absolute URLs spread into content and metadata from there,
which is what makes a later domain migration a search-and-replace chore. Defining them
in `wp-config.php` instead makes the cutover a one-line edit:

```php
define( 'WP_HOME',    'http://203.0.113.10' );
define( 'WP_SITEURL', 'http://203.0.113.10' );
```

Content added during this phase can still contain absolute URLs, so keep a
`wp search-replace --dry-run` in step 9 regardless.

**Staging serves `noindex` permanently, not just until launch.** This site's value is
its search position. A staging copy that gets indexed competes with production for it,
and if the IP is indexed before the domain exists the domain inherits duplicate-content
and canonical problems that are avoidable by never letting them happen. Set
Settings → Reading → "Discourage search engines", and confirm with
`curl -sI http://<ip>/ | grep -i x-robots`. Add HTTP basic auth to the staging vhost at
step 4 so the setting is not the only thing standing between staging and a crawler.

**Do not use real credentials over plaintext HTTP.** Every `wp-admin` login in this
phase sends the password in the clear, and the session cookie with it. Restrict port
80 to the owner's IP in the security group until TLS exists, and treat the staging
admin password as throwaway — production gets its own credentials at step 13, never a
copy of staging's. Publish no real content and connect no affiliate accounts on
staging.

TLS on a bare IP is possible but not worth it here: Let's Encrypt does not issue
certificates for IP addresses, so it would mean adopting a certificate authority the
project does not otherwise use, to be replaced weeks later anyway. Cloudflare and SES
both require a domain, which is why they sit in phase B.

### Step 0 — Git repository

The workflow in section 3 assumes code is versioned locally and deployed through Git,
and both documents forbid committing secrets, but no repository exists yet. The build
log lives in these files, so this comes before any AWS work.

`.gitignore` is already written. The human runs:

```bash
# ON HOST
cd ~/Apps/The-abyss
git init
git add .
git commit -m "Initial commit: project plan and working agreement"
```

Verification: `git status` reports a clean tree, and `git ls-files` lists `AGENTS.md`,
`README.md`, `.gitignore`, and the `theme/` prototype, with no credentials present.

### Step 1 — launch the EC2 host

Unchanged and still current; step 0 is paperwork that precedes it. Awaiting the human
to launch:

- `t4g.small` — 2 vCPU / 2 GiB, ARM64
- official Canonical Ubuntu Server 24.04 LTS ARM64 image with $0 software charge
- 30 GiB encrypted `gp3`
- public IPv4 for initial access
- SSH from the owner's current IP only
- Name tag `the-abyss-web-01`
- no HTTP, HTTPS, or MySQL rule until the corresponding service exists

The auto-assigned public IPv4 is fine for step 1 alone. It is released on stop/start,
so the Elastic IP is allocated at step 2, before WordPress records the address.

AWS currently provides 750 aggregate `t4g.small` compute hours per month through
2026-12-31. The offer does not cover storage, IPv4, snapshots, data, Marketplace
software, or surplus CPU credits. The new-account Free plan also ends after six
months or when its credits are exhausted.

Verification required before marking the step complete:

- EC2 state is `Running`.
- Both EC2 status checks pass.
- SSH reaches the Ubuntu host.
- `uname -m` reports `aarch64`.
- The OS reports Ubuntu 24.04 LTS.
- The root disk and memory match the selected configuration.

## 6. Command labels

- `# ON HOST` — the human's desktop terminal.
- `# IN AWS CONSOLE` — the AWS Management Console.
- `# ON AWS SERVER` — an SSH session on the EC2 Ubuntu host.
- `# IN WP-ADMIN` — the WordPress dashboard.
- `# IN MYSQL` — the `mysql>` prompt.
- `# WP-CLI` — the `wp` command running as the web user.

Once both environments exist, every server, MySQL, and WP-CLI instruction must name
the environment it targets, and WP-CLI must always carry an explicit `--path`. The
common way to damage a live WordPress site is to run a correct command in the wrong
environment.

## 7. Verify before documenting

Tool output is not enough when the filesystem or live service can be checked.
Confirm with appropriate read-only evidence such as:

- EC2 state and status checks
- `ssh`, `hostnamectl`, `uname`, `lsb_release`, `free`, and `lsblk`
- `systemctl status`
- `curl`
- `ls`, `stat`, and `rg`
- WP-CLI list/get commands
- MySQL read-only queries

When a prediction and the live system disagree, correct the plan plainly and trust
the live evidence.
