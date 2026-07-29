# PLAN.md: the blueprint

What The-abyss is, why each architecture decision was made, and where the build
stands. `README.md` is the developer-facing and user-facing entry point and links
here. `AGENTS.md` holds the working agreement, including the teach rule, the
command labels, the build-log entry shape, and the verify rule.

Section headings here are deliberately unnumbered so that anchors such as
`#status` stay stable when sections are added or removed.

## Project

**The-abyss** is a real WordPress affiliate site covering finance and AI. The
immediate goal is to learn the WordPress stack against a local container that
mirrors a real server, then build the custom theme against it. The hosting target
is deliberately undecided and is chosen later, once the stack is understood.

The lifecycle is local, then staging, then production. Local is a container. What
staging and production run on is an open question recorded under *Hosting*.

### Content scope

Finance and AI articles, guides, comparisons, and roundups.

### Canonical names

| Use | Value |
|---|---|
| Project and public WordPress name | `The-abyss` |
| Machine slug, theme directory, text domain | `the-abyss` |
| PHP function prefix | `the_abyss_` |
| PHP constant prefix | `THE_ABYSS_` |
| Local WordPress path in the container | `/var/www/the-abyss` |
| Local database and user | `the_abyss_local` / `abyss_local` |
| Production WordPress path | `/var/www/the-abyss` |
| Production database and user | `the_abyss_wp` / `abyss_wp` |
| Staging WordPress path | `/var/www/the-abyss-staging` |
| Staging database and user | `the_abyss_stg` / `abyss_stg` |

Use these names for every new resource. Passwords, private keys, credentials,
live addresses, and unnecessary provider resource IDs never belong in the
repository. See `AGENTS.md` under `## Project-specific rules` for the full rule.

### Live-site requirements

These apply to the eventual public site regardless of who hosts it.

- TLS before the site is public, real credentials are used, or content is
  indexed.
- Automated database and file backups with tested restore instructions.
- `rel="sponsored nofollow"` on every affiliate link.
- Visible FTC and Competition Bureau disclosures.
- Author and review-date schema for finance content.
- Least-privilege access, firewall rules, OS hardening, and monitoring.
- No public MySQL port.
- Self-hosted fonts and no unnecessary third-party font requests.

## Current architecture decisions

These are settled and hosting-independent. Anything that depends on the provider
lives under *Hosting* below.

| Decision | Reason |
|---|---|
| Learn against a local container before provisioning a server | Feedback is faster, mistakes are free, and nothing is billing while the stack is still unfamiliar |
| Ubuntu 24.04 LTS in the container, not a WordPress image | The point is to learn the stack. An official `wordpress` image hides Apache, PHP, and MySQL configuration behind someone else's Dockerfile |
| Apache with `mpm_event` and `php-fpm` | Lower memory than `mod_php` under `prefork`, and it keeps PHP in a separate process the web server cannot leak into |
| MySQL, not MariaDB | WordPress supports both. Picking one and staying with it avoids subtle SQL-mode and collation differences between local and production |
| Install by hand first, codify into a Dockerfile second | A Dockerfile written up front produces a working site and no understanding. Capturing verified commands produces both |
| Classic theme with `theme.json` | Direct PHP markup control with a governed block-editor palette |
| Fresh hand-built theme | Avoids carrying obsolete content models and identifiers into production |
| Plain CSS initially | Keeps the first build simple |
| Design tokens come from the design system, never hand-picked | The Modernist token sheet is the source of truth for colour, type, and spacing. A literal hex in a template is drift waiting to happen |
| ACF field definitions in PHP | Versioned, reviewable, and deployable |
| Code edited locally and deployed through Git | Production code is never edited in place |
| Affiliate `rel` applied by a theme filter | A rule that depends on authors tagging every link by hand will eventually be missed. A `the_content` filter makes it structural |
| Backups and caching handled outside WordPress where practical | They must still work when PHP or WordPress fails |

## The local environment

A single Ubuntu 24.04 container running Apache with `php-fpm`, MySQL, and
WordPress, all installed by hand. The container mirrors the eventual server
closely enough that the commands transfer.

**Stated limitation: no systemd.** A plain container has no init system, so
service management uses `service apache2 start` rather than
`systemctl start apache2`. Every service command in the build log records both
forms, so moving to a real host is a one-word substitution. Everything else, the
package installs, the vhost configuration, the `php-fpm` pool, the MySQL setup,
and the WordPress install, is identical to what a real Ubuntu host needs.

Running a privileged systemd-enabled container is the alternative and was
declined for now, because the quirks cost more than the one substitution saves.
Revisit if service management itself becomes the thing being learned.

## Design system

The Modernist design system is present at
`design/_ds/modernist-747f06d4-a5fd-4b38-bebe-4878e82695b0/`.

Modernist is flat and architectural, set entirely in Archivo, a near-mono red on
a light ground, with a visible modular grid, zero corner radius, and strong 2px
rules. Photography prints in black and white. Everything is flush left, including
labels inside buttons.

**What is present and usable:**

- `styles.css`, 252 lines. The token sheet and component layer. Colour roles with
  100 to 900 OKLCH ramps, `--font-heading` and `--font-body` both Archivo, a
  4px-based `--space-*` scale, and `--radius-md` deliberately 0.
- `readme.md`, the written spec, including the component class list, the
  interaction-state rules, and the do and do-not list.
- `_ds_manifest.json` and `_adherence.oxlintrc.json`.

**What the readme documents but the export does not contain:**

- `theme.json`, described as the parameters the system was derived from.
- `foundations/*.html` and `components/*.html`, the reference pages the readme
  says to copy markup from.
- `templates/landing/` and `assets/photo.jpg`.

The token layer is complete, so the theme can be built. The WordPress `theme.json`
is derived from `styles.css` rather than read from the missing file, and the
missing component pages mean markup is written against the readme's class list
rather than copied. Record any divergence this forces as an intentional one.

Locked values, per the regression-locks rule: the token values in `styles.css`
are the source of truth for colour, type, and spacing. Do not hand-pick a hex, a
font stack, or a pixel value that a token already carries.

## Repository state

- `theme/` is an obsolete prefab-building prototype, not the production
  `the-abyss` theme.
- Do not deploy or cosmetically rename that prototype. Its post types, API
  contracts, palette, typography, and prefixes are the wrong product.
- Create the production theme as a fresh, separately verified step.
- `design/` is present, roughly 17M, and currently untracked and un-ignored.
  About 15.5M is PNG imagery, including screenshots under `design/uploads/` that
  appear to be working scratch. What gets committed needs a deliberate decision
  before any commit touches the directory, because binaries in Git history are
  permanent.
- `README.md` still describes an AWS build under *At a glance*. It is corrected
  after the local environment is proven, not before.

## Hosting: undecided

No provider is chosen. Nothing in the roadmap before Step 8 depends on one.

### Decision criteria

Decide at Step 8, against the stack as actually built:

- Monthly cost at the real resource size, after any trial period ends.
- How much of the learning already done transfers.
- Backup, snapshot, and restore story.
- Whether a managed database is wanted later.
- Time to a working host from a standing start.

### Candidate: AWS EC2

Fuller reasoning, retained from when this was the settled plan.

| Decision | Reason |
|---|---|
| `t4g.small` | 2 vCPU and 2 GiB is a practical floor for Ubuntu, Apache with PHP, WordPress, and MySQL together |
| Region `us-east-1` | Widest service availability and lowest list prices |
| Ubuntu Server 24.04 LTS ARM64 | Stable LTS release with native Graviton support |
| 30 GiB encrypted `gp3` | Enough initial space with inexpensive, general-purpose SSD performance |
| MySQL on the same host initially | Proportional for zero traffic, and it avoids a second always-on service |
| RDS later if justified | Move when recovery objectives, availability, memory pressure, or scale require it |
| 2 GiB swap file with low `vm.swappiness` | MySQL, PHP workers, and image processing sharing 2 GiB is where a WordPress host runs out of memory |
| Elastic IP from launch | An auto-assigned public IPv4 is released on stop and start, and DNS and TLS both need a stable address |
| SSM Session Manager for shell access | Removes inbound port 22 entirely and gives IAM-controlled audited access |
| IAM Identity Center rather than long-lived access keys | Short-lived credentials mean no static secret sits in `~/.aws/credentials` |
| Off-instance backups to S3 through an instance role | Backups on the instance do not survive the failure they exist for |
| CloudWatch agent for memory metrics | EC2 publishes no memory metric by default, and memory is the constraint |
| Amazon SES for outbound mail | EC2 blocks outbound port 25, so password resets fail silently without it |

At `us-east-1` list prices the baseline was approximately $12.26 for `t4g.small`
compute, $2.40 for 30 GiB `gp3`, and $3.65 for one public IPv4 address, totalling
about **$18.31 per month**, excluding snapshots, backup storage, excess data
transfer, surplus CPU, tax, and domain registration.

Authoritative references:

- [EC2 Free Tier instance eligibility and credit model](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ec2-free-tier-usage.html)
- [EC2 T4g specifications and current trial](https://aws.amazon.com/ec2/instance-types/t4/)
- [Detailed T4g trial terms](https://aws.amazon.com/ec2/faqs/#t4g-instances)
- [Official Ubuntu ARM64 images](https://documentation.ubuntu.com/aws/en/latest/aws-how-to/instances/find-ubuntu-images/)
- [EBS pricing](https://aws.amazon.com/ebs/pricing/)
- [Public IPv4 pricing](https://aws.amazon.com/vpc/pricing/)

**Work already completed and verified on this candidate**, banked in case AWS is
chosen at Step 8:

- Root user MFA enabled.
- No IAM access key exists for the root user.
- IAM Identity Center enabled, Primary Region `us-east-1`, AWS access portal URL
  issued.
- An AWS Organization exists, created automatically by enabling Identity Center.
- The account moved from the Free plan to the Paid plan as a result. No billable
  resource is running, so the account costs nothing while idle. Confirm $0 in
  Billing rather than assuming it.

Not done: the Identity Center admin group, user, permission set, and assignment.
Root is still the only usable identity.

### Candidate: DigitalOcean droplet

Not yet researched. To fill in at Step 8: droplet size and price, Ubuntu 24.04
availability, backup and snapshot pricing, whether a managed database is wanted,
and how shell access and firewalling are handled.

## Status

This section is the single source of truth for step state. `README.md` describes
the project and links here. It does not keep its own checklist.

**Current objective: a working WordPress site in a local Ubuntu container, built
by hand.**

Phase A, the local environment:

- [x] **Step 0, Git repository and ignore rules.**
- [ ] Step 1, bare Ubuntu 24.04 container running, repository mounted, port
      mapped.
- [ ] Step 2, install and configure Apache with `php-fpm` inside it.
- [ ] Step 3, install MySQL, create the WordPress database and user.
- [ ] Step 4, install WordPress and complete the setup.
- [ ] Step 5, codify the verified commands into a Dockerfile and a compose file.

Phase B, the theme:

- [ ] Step 6, derive `theme.json` from the Modernist tokens and reconcile the
      missing component references.
- [ ] Step 7, build and prove the fresh `the-abyss` theme against the container.

Phase C, hosting:

- [ ] Step 8, decide the hosting target against the criteria above.
- [ ] Step 9 onward, provision, deploy, TLS, backups, and launch. Planned once
      Step 8 lands, because the steps depend on the provider.

Step 6 gates Step 7, because template implementation needs the token layer
settled first. Step 5 gates nothing but should not be skipped, since an
uncodified environment is one laptop away from being unreproducible.

### Promotion between environments

The eventual staging and production environments are separate document roots,
databases, and MySQL users. Theme code travels one way, from local to Git to
staging to production, and is never edited in place. Database and uploads travel
the other way, from production to staging, to refresh staging with real content
once the site is live.

A promotion is a WP-CLI export, a URL rewrite, and an import, rehearsed with
`--dry-run` first:

```bash
wp db export /tmp/stg.sql --path=/var/www/the-abyss-staging
wp search-replace 'http://staging.example' 'https://the-abyss.tld' \
  --path=/var/www/the-abyss-staging --export=/tmp/prod.sql --dry-run
```

`wp search-replace` is used rather than `sed` because WordPress stores serialized
arrays in `wp_options` and `wp_postmeta`, where a byte-length prefix accompanies
each string. Editing the text without fixing those lengths corrupts the row, and
the symptom is silently lost widget, menu, and plugin settings rather than an
error.

Always take a database export of the destination immediately before overwriting
it, and keep it until the promotion is verified. A promotion is the one routine
operation that destroys a working environment on purpose, so it is the one that
most needs a restore point.

### Staging constraints

These apply once a staging environment exists on a real host. They are recorded
now because each is cheap to honour early and expensive to retrofit.

**Set the site URL by constant, not in the database.** WordPress stores `siteurl`
and `home` in `wp_options`, and absolute URLs spread into content and metadata
from there, which is what makes a later domain migration a search-and-replace
chore. Defining them in `wp-config.php` instead makes the cutover a one-line
edit:

```php
define( 'WP_HOME',    'http://localhost:8080' );
define( 'WP_SITEURL', 'http://localhost:8080' );
```

This applies to the local container too, which is why it appears now rather than
at staging.

**Staging serves `noindex` permanently, not just until launch.** This site's
value is its search position. A staging copy that gets indexed competes with
production for it, and duplicate-content and canonical problems are avoidable by
never letting them happen.

**Do not use real credentials over plaintext HTTP.** Any `wp-admin` login without
TLS sends the password in the clear, and the session cookie with it. Treat local
and staging admin passwords as throwaway. Production gets its own credentials,
never a copy. Publish no real content and connect no affiliate accounts outside
production.
