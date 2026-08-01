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
- `rel="sponsored nofollow"` on every affiliate link. **Built** in the theme as
  a `the_content` filter, so it does not depend on an author tagging links.
- Visible FTC and Competition Bureau disclosures. **Built** as a companion
  filter: an article containing a link to a monetised domain gets a disclosure
  prepended above its first paragraph. Both regimes want a disclosure the reader
  meets before acting on the link, so placement is the requirement, not merely
  presence. The wording is a starting point and has had no legal review.
- **Both key off one list of monetised domains**, empty by default and set
  through the `the_abyss_affiliate_domains` filter. Corrected 2026-07-31 after a
  real post exposed the first version: it treated every outbound link as an
  affiliate link, which tagged editorial citations to news sites as paid
  placements and printed an affiliate disclosure on an article that earned
  nothing. Both are misstatements. Marking a citation `sponsored` throws away
  the editorial signal the link was there to give, and a disclosure that cries
  wolf trains readers to skip it on the articles where it is true. The list
  costs one line per programme joined, which is far weaker discipline than
  per-link tagging and is the reason this stays structural.
  **The list is currently empty**, so no link is tagged and no disclosure
  renders until the first programme is added.
- Author and review-date schema for finance content. **Built** as `Article`
  JSON-LD on single posts and pages, carrying author, `datePublished`, and
  `dateModified` only when the post really was revised, matching the visible
  line exactly. Deliberately no `Review` or `aggregateRating`: marking up an
  affiliate comparison as a rating is what Google's self-serving-review policy
  prohibits, and the penalty is a manual action rather than a lost rich result.
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
| Codify per step, not all at the end | Established at step 2. Rebuilding to pick up a fix discards anything installed by hand, so verified commands go into the Dockerfile before the next rebuild rather than after all of them |
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

**Access is over SSH, not `docker exec`.** `ssh -p 2222 root@localhost` with key
authentication, so the connection habit matches the eventual host. Only the
public key is mounted, read-only. `docker compose exec web bash` remains the
fallback for when SSH itself is the thing that is broken.

**The container's host keys live in a named volume**, mounted at
`/etc/ssh/keys`, and the entrypoint generates them on first run only. Keys
written into `/etc/ssh` are image content and therefore new on every rebuild,
which made the client refuse to connect with `REMOTE HOST IDENTIFICATION HAS
CHANGED` and required `ssh-keygen -R` each time. A real server keeps its identity
across a reprovision, so the container does too.

**The entrypoint provisions WordPress core files when `/var/www` is empty**, so
the environment is reproducible from scratch with one command. It deliberately
does not create `wp-config.php`. That file holds the database password, and
generating it would mean sourcing a credential from the image, the compose file,
or an env var beside them, which tier 0 forbids. Creating it is a manual step,
recorded in the `README.md` build log under step 4.

**What survives a rebuild, audited at step 4.** This cost three rebuilds to
learn, one component at a time, so it is recorded rather than rediscovered. The
image holds the packages and everything under `/etc`, and a rebuild discards all
of it, so it must be codified in the Dockerfile. The named volumes hold
`/var/www`, which is the WordPress files and `wp-config.php`, and
`/var/lib/mysql`, which is the database. Those survive, and must therefore never
be baked into the image. `wp-config.php` in particular holds credentials and has
no business in an image layer.

**Two further places the mirror is thin, observed at step 1.** The container
shares the host kernel rather than running Ubuntu's, so `uname -r` reports the
host. And the `ubuntu:24.04` image is minimized, so documentation and some
utilities a real server carries are absent until `unminimize` is run. Neither
affects Apache, PHP, MySQL, or WordPress.

## Design system

**Replaced 2026-08-01.** The Modernist system, and the `design/` directory that
held it, were removed. `abyss-theme` ships its own design and its own tokens, and
is now the source of truth for both.

The history matters, because everything written before this date assumed
otherwise. Modernist was flat and architectural: Archivo throughout, near-mono
red on a light ground, zero corner radius, strong 2px rules, black-and-white
photography, everything flush left. `abyss-theme` is none of those things. It is
dark-ground, teal-accented, set in Plus Jakarta Sans and Source Sans 3, and uses
a 16px radius. The two are not variants of each other, so a rule carried over
from the old system is almost certainly wrong rather than merely dated.

Consequences, recorded rather than discovered later:

- **The Modernist `design/` contents are gone and stay gone**, decided
  2026-08-01. The token sheet is recoverable from Git history at commit
  `73a4e40` if it is ever wanted, and nothing in the current build reads it.
  `design/` was then reused the same day for reference screenshots of the
  intended homepage. It is now a visual target, not a token source: nothing
  reads it, and it is not the source of truth for any value.
- **The `AGENTS.md` divergence ledger no longer exists.** Its seven approved
  divergences were all measured against Modernist tokens, so it retired with the
  system. Any new divergence is measured against `abyss-theme`'s own tokens.
- **The accessibility findings did not retire with it.** They were measurements,
  not preferences, and they apply to any palette. Done 2026-08-01, see below.
- **The palette was normalised to one scheme**, deep navy + amber, on
  2026-08-01. `abyss-theme` shipped with four selectable schemes and defaulted to
  a mint one that did not match the reference screenshots. The other three were
  removed rather than left unused, and the Customizer picker went with them,
  because a selectable palette and a contrast guarantee cannot both hold: every
  measurement would be valid only for whichever scheme happened to be active.
  The single scheme is defined in one place, `abyss_palettes()`, with
  `style.css`, `theme.json`, and `editor.css` carrying the same values as
  fallbacks instead of a different theme's.

### Contrast, measured 2026-08-01

All against `--color-bg` `#151a2a`. This is the check the Modernist ledger used
to hold, redone for the new palette:

| Pair | Ratio | Needs | |
|---|---|---|---|
| body text | 15.31:1 | 4.5 | pass |
| neutral-800 | 11.01:1 | 4.5 | pass |
| accent text | 9.44:1 | 4.5 | pass |
| on-accent on the accent fill | 9.35:1 | 4.5 | pass |
| neutral-700 | 6.79:1 | 4.5 | pass |
| negative | 5.98:1 | 4.5 | pass |

Navy and amber is a decisively better starting point than what it replaced:
Modernist needed five separate divergences because its accent measured 3.76:1,
and amber on navy is 9.44:1 without any adjustment.

One fix was required. `--color-neutral-400` was `#48526e` at **2.23:1**, and it
is the border on `.btn--secondary` and on form inputs, which makes it a UI
component boundary where WCAG 1.4.11 requires 3:1. Raised to `#67739a`: 3.71:1
on the background, 3.33:1 on card, 3.00:1 on surface, since inputs appear on all
three.

`--color-divider` stays at 1.49:1 deliberately. It draws decorative rules between
sections and identifies no control, which is the 1.4.11 exemption. Raising it to
match would make every section rule shout.

### Carried forward into `abyss-theme/inc/compliance.php`

Six things existed in the old theme and not in the new one. They were ported on
2026-08-01 and verified rendering. They are kept in one file, separable from the
rest of the theme, because they are the part a future theme change must not
silently drop:

1. `rel="sponsored nofollow"` on affiliate links written into article prose.
   `abyss-theme` already handles links built by `abyss_affiliate_link()`, which
   covers offers and picks but not a link typed into a paragraph.
2. A per-article disclosure above the first paragraph. `abyss-theme`'s site-wide
   footer bar is kept and is not a substitute: it sits below every link on the
   page, and both the FTC and the Competition Bureau ask for a notice the reader
   meets before acting on a link.
3. `Article` JSON-LD.
4. The `comment-reply` script, without which threaded replies are filed as new
   top-level comments.
5. `filemtime` asset versioning.

Both 1 and 2 key off one filterable list of monetised domains rather than off
"any outbound link", which is a distinction that was learned the expensive way:
the first version tagged editorial citations as paid placements and printed a
disclosure on articles that earned nothing.

The sixth, fluid type, was **not** ported. The old theme used `clamp()` with a
`rem` term so headings respond to a reader's browser font-size setting;
`abyss-theme` uses fixed `px` with breakpoints. Converting its 40-plus font-size
declarations is a decision about its type scale rather than a gap to patch, so it
is left open and recorded here.

### Open design questions

- **Fonts load from the Google Fonts CDN.** With Complianz installed for consent,
  that is a live compliance issue rather than a preference: German courts have
  held that the endpoint transfers a visitor's IP address without consent.
  Self-hosting is the fix.
- **Fixed-`px` type**, as above.


## Repository state

- `theme/` is an obsolete prefab-building prototype, not the production
  `the-abyss` theme.
- Do not deploy or cosmetically rename that prototype. Its post types, API
  contracts, palette, typography, and prefixes are the wrong product.
- Create the production theme as a fresh, separately verified step.
- `design/` is roughly 17M. Decided 2026-07-29: `design/_ds/` is tracked, about
  29KB across five files, because it holds the token sheet this file calls the
  locked source of truth and a clone must be able to read it. The remaining
  15.5M of PNG imagery, the screenshots under `design/uploads/`, and the
  708KB state JSON stay ignored, because binaries in Git history are permanent.
  The rule is `design/*` and not `design/`: Git never descends into an excluded
  directory, so a bare `design/` makes the `!design/_ds/` negation a silent
  no-op.
- `README.md` still describes an AWS build under *At a glance*. It is corrected
  after the local environment is proven, not before.

## Hosting: AWS EC2

**Decided 2026-08-01, reversing the 2026-07-29 decision for DigitalOcean.** AWS
EC2 is the host. DigitalOcean is demoted to the deferred candidate, and the
droplet section that follows is kept as the record rather than deleted.

Casey's stated framing: this is the last environment move, so the transfer and
its testing are the remaining work once the theme is finished. The theme was
confirmed finished and running the same day, which was the condition set on
starting server work.

**What the reversal costs, stated plainly rather than smoothed over.** The
2026-07-31 revision already withdrew the original reason for choosing
DigitalOcean: the droplet stopped being free the moment a dedicated one was
created for this site, and the decision was re-justified on operational
simplicity instead. Moving to EC2 trades that simplicity for a more capable
platform and reuses the AWS identity work already banked below. It also means
the two remaining DigitalOcean artefacts, the droplet itself and its billing,
are Casey's to shut down; nothing in this plan does that automatically.

**What transfers unchanged.** Everything the container taught. Ubuntu 24.04,
Apache with php-fpm, MySQL, WP-CLI, and the vhost are identical on EC2, and
`scripts/provision.sh` was written against Ubuntu rather than against
DigitalOcean. What changes is the surrounding platform, not the stack: the login
user, the firewall model, the metadata service, the backup mechanism, and the
mail path.

**What does not relax.** An EC2 instance is a public internet-facing machine from
the hour it boots. SSH hardening, a restrictive security group, and no password
authentication are day-one work, not go-live work.

### Deferred candidate: DigitalOcean droplet

Retained from when this was the settled plan, 2026-07-29 to 2026-08-01. A
dedicated droplet was created for this site on 2026-07-31 and never provisioned.
It and its billing are Casey's to shut down.

Archived command labels, to restore together rather than piecemeal if
DigitalOcean is ever chosen again: `# ON DROPLET` is an SSH session on the
droplet, and `# IN DO CONSOLE` is the DigitalOcean control panel.

The original reasoning follows.

**Revised 2026-07-31: The-abyss gets its own droplet.** A second droplet was
created for this site, so it no longer shares a host with the live portfolio.
Two consequences, both recorded rather than quietly absorbed:

- **The stated reason for the decision no longer holds.** It was chosen because
  a paid-for droplet already existed and marginal cost was therefore zero. A
  second droplet is a new monthly line item, so cost is now a real comparison
  against the deferred AWS candidate rather than a walkover. The decision is
  still judged correct on the criteria below, particularly operational
  simplicity and the direct transfer of everything the container taught, but it
  now rests on those criteria instead of on being free.
- **The shared-host constraint is withdrawn.** It described a host whose ports,
  vhosts, TLS, and MySQL belonged to another product, which made ordinary
  provisioning commands destructive. On a dedicated droplet none of that is
  true: ports 80 and 443 are free, no vhost belongs to anyone else, and MySQL
  holds nothing. Provisioning steps there are ordinary tier under `AGENTS.md`
  while the droplet serves no live traffic, and become risky tier the moment it
  does.

**The portfolio droplet is out of scope.** It is not this project's host, and no
step in this plan touches it. Its existence remains relevant only as the reason
the account and the DigitalOcean workflow were already familiar.

**What does not relax.** A dedicated droplet is still a public internet-facing
machine from the hour it boots. SSH hardening, a firewall, and no password
authentication are day-one work, not go-live work, and the absence of a live
site on it is not a reason to defer them.

### Decision criteria

Kept as the record Step 8 would have used. These no longer decide anything, and
they govern any future re-decision:

- Monthly cost at the real resource size, after any trial period ends.
- How much of the learning already done transfers.
- Backup, snapshot, and restore story.
- Whether a managed database is wanted later.
- Time to a working host from a standing start.

### The chosen architecture: AWS EC2

Promoted back from deferred candidate 2026-08-01. This is now the build target.

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

On the database, if AWS is chosen: RDS becomes worthwhile when managed recovery,
independent scaling, availability, or database isolation justifies a second
always-on service. Until then, use automated logical database backups, encrypted
EBS snapshots, off-instance copies, and a tested restore procedure.

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

**Restored to `AGENTS.md` on 2026-08-01**, all together as the archive
instructed: the three command labels (`# IN AWS CONSOLE`, `# AWS CLI`,
`# ON AWS SERVER`) and the port 22 divergence. They are no longer duplicated
here; `AGENTS.md` is now their home again.

### What EC2 changes that the container did not teach

The stack transfers unchanged. The platform around it does not, and these are the
five places `scripts/provision.sh` and `scripts/inventory.sh` had to diverge from
their droplet versions:

| Concern | Droplet | EC2 |
|---|---|---|
| Login | `root` directly | `ubuntu` with `sudo`; root SSH disabled in the AMI |
| Firewall | `ufw` plus an optional DO cloud firewall | Security group is the real control; `ufw` is a second layer that can lock you out |
| Metadata | Unauthenticated `169.254.169.254` | IMDSv2, requiring a `PUT` for a token first |
| Backups | DigitalOcean automatic backups | EBS snapshots, via Data Lifecycle Manager or AWS Backup |
| Stable address | Reserved IP | Elastic IP, and the architecture table already requires one from launch |

Two carried over unchanged, and both still bite:

- **Outbound port 25 is blocked.** True on both platforms. It is why the
  architecture table specifies Amazon SES, and why the newsletter provider
  question in step 8c is a real decision rather than a plugin choice.
- **No swap by default.** The architecture table calls for a 2 GiB swap file with
  low `vm.swappiness`, because MySQL, PHP workers, and image processing sharing
  2 GiB is exactly where a WordPress host runs out of memory.

### What the Step 9 inventory must record

Rewritten 2026-07-31, when the host became a dedicated droplet. The original
list assumed a shared host and was mostly about what the portfolio site already
occupied. On a droplet of this project's own, most of those questions answer
themselves, and the inventory shrinks to establishing the starting point:

- Droplet size and region, and the disk and memory the plan has to fit inside.
- Ubuntu version, and whether it matches the container's 24.04. A mismatch
  means some of what the Dockerfile codified does not transfer as written.
- **What the image already installed.** This is the load-bearing question. A
  plain Ubuntu image arrives with none of the stack, which is what this project
  wants: the container exists to make the by-hand install transferable. A
  DigitalOcean marketplace WordPress image arrives with Apache, PHP, MySQL,
  WordPress, and a configured vhost already in place, which would skip the
  entire thing the build was structured to teach.
- Whether anything already listens on 80 or 443.
- How SSH is configured as delivered: root login, password authentication, port.
- Whether a firewall is active, at the droplet and at the DigitalOcean cloud
  firewall layer, which are two separate things.
- Whether automatic backups are enabled. This is a control panel setting and is
  not visible from the shell.

Recording the answers is Step 9's deliverable. Nothing is changed while taking
them.

## Delivery sequence

Set by Casey 2026-08-01 and revised the same day after an audit of what actually
transfers to the server. It supersedes the numbered step roadmap below for
ordering; the steps below remain the record of what was done.

### The decision that shapes the order: the local database is disposable

Decided 2026-08-01. Only `abyss-theme/`, `docker/`, `scripts/`, and the docs are
in Git. Everything else the local site knows lives in the MySQL volume and
transfers via nothing: 7 posts and pages, 9 offers, 4 picks, 6 attachments, the
menus, the custom logo, the permalink structure, and every plugin option.
`scripts/provision.sh` builds a *fresh* WordPress — it installs plugins, and
configures none of them.

So local plugin configuration would be work done twice, and the second time is
the one that counts. Four of the nine cannot be configured meaningfully here in
any case: Site Kit needs OAuth against a live host, FluentSMTP needs SES with a
verified domain, Complianz's consent configuration depends on what analytics
actually loads, and Rank Math bakes absolute URLs into sitemaps and canonicals.

The local content is entirely fixtures — there is not one real article — which
makes this the cheapest possible moment to decide the database is throwaway.
Consequences, accepted deliberately:

- **Local is a development environment**, for theme code and for testing the
  provisioning scripts. It is not a staging copy of production.
- **Plugin configuration and real content happen once, on the server.**
- **No database migration path is needed to proceed.** One is still needed
  eventually, for backups and restores, but it no longer gates this work.
- **Local fixtures may be deleted at any time.** Nothing depends on them beyond
  exercising templates.

### Order

1. **Integrate and test the new theme.** Done. `abyss-theme` installed and
   activated, every page type 200, and the six things it was missing ported into
   `inc/compliance.php` and verified rendering.
2. **Clean up: old theme out, new theme in.** Done. `theme/` removed, the
   Modernist documentation retired, the palette normalised to one scheme, and the
   header, footer, menus, and homepage sections built against the reference
   screenshots.
3. **Test `scripts/provision.sh` in a throwaway container.** Next, and moved
   ahead of everything server-side deliberately. The script has never executed,
   not once, anywhere. It is the largest untested risk between here and a working
   server, and a container tests it for free. Doing this on EC2 first would mean
   debugging the script at the same time as security groups, Elastic IPs, and
   IMDS, with a relaunch as the cost of each mistake.

   Guarded for this on 2026-08-01: `has_systemd()` now gates the `systemctl`,
   `ufw`, and swap steps, and a `skipped()` helper reports each one loudly. The
   guards skip and never fake, so a container run cannot be mistaken for a full
   one. What the container **cannot** cover, and what therefore stays untested
   until EC2: service management under systemd, the firewall, swap, and certbot.
4. **Launch the EC2 instance, inventory it, then run `provision.sh` for real.**
5. **Configure the plugins once, on the real domain**, each one explained as it
   is set up.
6. **Write real content.**
7. **Connect the domain and TLS, then fix indexing.** Two opposite requirements
   that get confused with each other: production must be indexable, and staging
   must never be. `blog_public` is the switch and it is per-environment. It is
   currently `0` locally, which is correct.

## Status

This section is the single source of truth for step state. `README.md` describes
the project and links here. It does not keep its own checklist.

**Current objective: a working WordPress site in a local Ubuntu container, built
by hand.**

Phase A, the local environment:

- [x] **Step 0, Git repository and ignore rules.**
- [x] **Step 1, Ubuntu 24.04 container running over SSH, repository mounted,
      ports mapped.**
- [x] **Step 2, Apache with `php-fpm` installed, configured, and codified.**
- [x] **Step 3, MySQL installed, WordPress database and least-privilege user
      created.**
- [x] **Step 4, WordPress installed, configured by constant, and serving at
      `http://localhost:8080`.**
- [x] **Step 5, codification complete. The whole stack is in the Dockerfile, SSH
      host keys persist across rebuilds, and the entrypoint provisions WordPress
      core when the volume is empty.**

Phase B, the theme:

- [x] **Step 6, prototype stripped and `theme.json` derived from the Modernist
      tokens. Theme deployed by symlink, activated, and tokens verified in the
      rendered page.**
- [x] **Step 7a, header, nav, and `single.php` built and proven against a real
      post.** Component families remain: see step 7b.
- [x] **Step 7b, the component layer, written 2026-07-31.** Every family in the
      design readme is implemented, plus `searchform.php`, `page.php`,
      `404.php`, and `comments.php`. `index.php` covers search acceptably, so a
      dedicated `search.php` is a refinement rather than a gap. See the
      verification gap below before treating any of it as proven.
- [x] **Step 7c, compliance and structured data, 2026-07-31.** The three
      live-site requirements that were theme work: affiliate `rel`, the visible
      disclosure, and `Article` JSON-LD. All three now key off one list of
      monetised domains rather than off "any outbound link", after a published
      post showed the first version tagging editorial citations as paid
      placements. Also closed in the same pass: `fonts.css` missing from the
      editor styles, `wp_link_pages()` missing from `single.php`, and
      `the_posts_pagination()` having no styles at all.
- [x] **Step 7d, the block editor content layer, 2026-08-01.** Everything before
      this styled markup the theme emits. This styles markup the *editor* emits,
      which the theme cannot rename: `alignwide`, `alignfull`, floats, captions,
      galleries, separators, pull quotes, code, core buttons, and sticky posts.
      All of it was previously unstyled, and long-form editor content is the
      product. The token sheet covers none of this markup, so the section is a
      documented gap held to the readme's do-not list instead. Two calls worth
      keeping: `alignfull` stops at the 1440px wide width rather than bleeding
      edge to edge, because a viewport bleed erases the grid the readme asks to
      keep visible; and grayscale stays opt-in, because forcing it would drain
      the charts a finance article runs on.
- [x] **Verification gap closed 2026-08-01.** Every component family has now
      rendered and been looked at. Method: two fixture entries created with
      WP-CLI — a "Block layer kitchen sink" post exercising every block type and
      a "Component check" page carrying the families no template emits — then
      screenshotted headless with the host's Firefox at 1440px, 800px, and 380px
      and read. This is repeatable and is how theme changes should be checked
      from here; it is far cheaper than asking for a manual drag test and it
      catches things a 200 response cannot.

      Confirmed working: buttons in all four states, tags, fields, radio,
      segmented control, table, cards with elevation, dialog, the threaded
      comment list and form, floats stacking below 600px, the unbreakable-token
      wrap, and equal-height cards.

      Four real defects were found by looking, all fixed and re-verified:
      1. `.wp-block-button__link` was filled with the raw accent, reintroducing
         the exact 3.76:1 contrast failure `.abyss-btn--primary` already carries
         a documented divergence for. Now accent-700 throughout.
      2. `alignwide` and `alignfull` images did not fill the box the figure
         claimed, so a 747px image sat flush left inside a 1440px box and read as
         broken. Now `width: 100%`, with the consequence that full-width sources
         want to be at least 1440px on the long edge.
      3. The code block was commented as scrolling. It wraps: core ships
         `white-space: pre-wrap` on `.wp-block-code code`. Comment corrected.
      4. `single.php` rendered "Published <date> by" with a trailing "by" when a
         post had no author. Now the byline is dropped rather than padded.
- [x] **Responsive pass done 2026-07-31.** Dragged 1600px to 380px with no
      horizontal scrollbar at any width, headings visibly smaller at the narrow
      end, and browser zoom confirming the `1rem +` term in each clamp responds
      to user font-size settings, which is the WCAG 1.4.4 requirement. The
      computed overflow floor is a **308px viewport**: the grid's
      `minmax(260px, 1fr)` plus `.site-main`'s 48px of inline padding. Below
      that a scrollbar is expected. Narrowest common device viewport is 320px,
      so there is 12px of margin.

Phase C, hosting:

- [x] **Step 8, hosting decided: AWS EC2.** Decided 2026-08-01, reversing the
      2026-07-29 decision for DigitalOcean and the 2026-07-31 revision to a
      dedicated droplet. The AWS identity work banked in July is reused rather
      than redone. Under *Hosting* above, along with the table of what actually
      differs between the two platforms and what the reversal costs.

      Consequence not to lose: the dedicated droplet created 2026-07-31 was
      never provisioned, and it and its billing are Casey's to shut down.
      Nothing in this plan does that.
- [x] **Step 8b, WP-CLI and the plugin baseline, 2026-08-01.** WP-CLI added to
      the image from the official phar, so the plugin set is a replayable command
      rather than a click path, and the same two lines work on the droplet.
      Installed and active locally: Rank Math (SEO and schema), FluentSMTP
      (transactional mail), UpdraftPlus (backups), Redirection, Limit Login
      Attempts Reloaded, Complianz (Canadian and GDPR consent), ThirstyAffiliates
      (link cloaking, complementing the `rel="sponsored"` filter already in
      `functions.php`). Installed but inactive: WP Super Cache, because a page
      cache in development hides template changes, and Site Kit, because it does
      nothing until its Google OAuth completes against a real domain. Rejected:
      Jetpack and Wordfence, both too heavy for the droplet, and any page
      builder, since the theme is custom.
- [x] **Step 7e, the theme finished, 2026-08-01.** An audit for
      declared-but-unused features found six gaps, all closed and all verified by
      screenshot:
      1. `comment-reply` was never enqueued. Functional, not cosmetic: with
         threading on, the Reply link jumped to the form anchor and the reply was
         filed as a new top-level comment.
      2. `custom-logo` support was declared but `the_custom_logo()` was never
         called, so uploading a logo in the Customizer did nothing.
      3. The `footer` menu location was registered at step 6 and never rendered.
         The privacy policy and affiliate disclosure links belong there, and both
         are live-site requirements.
      4. No author box, despite a named author on finance content being a
         requirement and already being emitted into `Article` schema.
      5. No related posts.
      6. No newsletter signup, on a site whose plan is newsletter-led.

      New partials under `theme/template-parts/`: `newsletter.php`,
      `author-box.php`, `related.php`. Each renders nothing when it has nothing
      to show, so a new site does not display three empty headings.

      The newsletter form takes its endpoint from the `the_abyss_newsletter_action`
      filter or a `THE_ABYSS_NEWSLETTER_ACTION` constant and **renders nothing
      until one is set**, because a form posting nowhere loses subscribers
      silently. It posts straight to the provider, so subscriber addresses never
      enter this database: one fewer store to secure, back up, and answer for
      under CASL and GDPR.

      Also fixed here: `.abyss-grid` moved from `auto-fit` to `auto-fill`. With
      `auto-fit`, a related row holding one post collapsed the empty tracks and
      stretched that card across the full 960px column with a 420px thumbnail.
      Cell width no longer depends on how many posts happen to match.
- [ ] **Step 8c, email and newsletter delivery. Blocked on a decision, not on
      work.** The site is blog-led with a newsletter, so the list is the asset.
      DigitalOcean blocks outbound port 25 on droplets by default, and bulk mail
      from a droplet IP with no sending reputation lands in spam even when the
      port is open. Delivery therefore goes through an external provider whatever
      plugin front-ends it, which makes the provider the decision and the plugin
      a consequence. Two shapes: a hosted list (Kit, MailerLite) where the
      provider owns deliverability and the list stays portable, or self-hosted
      FluentCRM relaying through Amazon SES, which is far cheaper per thousand
      but puts warmup, bounce handling, and CASL/GDPR compliance on this project.
      FluentSMTP is already installed and handles the relay leg either way.
- [x] **Step 8d, the provisioning script, written 2026-08-01.**
      `scripts/provision.sh` is the droplet-side mirror of `docker/Dockerfile`
      and `docker/entrypoint.sh`. Building the container by hand first is what
      made it writable from something proven rather than from a guess. It is
      idempotent, takes no password as an argument or default (silent prompts
      only, so nothing reaches the shell history or the process list), reuses
      `docker/the-abyss.conf` with only `ServerName` substituted, and reads its
      plugin list from `scripts/plugins.txt` so the droplet and the container
      cannot drift.

      It refuses to touch a `/var/www/the-abyss` that is not a WordPress install
      and never disables a vhost it did not create, which is the guard against
      the one genuinely destructive thing it could do on a host that has shared
      space with a live portfolio site.

      TLS is deliberately not run: certbot burns a rate limit if DNS does not
      already resolve, and pointing DNS is an outward-facing action that belongs
      to the human. The script ends by printing the remaining manual steps.

      **Untested end to end.** Bash syntax checks clean and the plugin-list
      parser was run standalone against the real file, matching the local
      install exactly. Nothing has executed it on a host.
- [ ] **Step 8e, AWS identity.** The banked work stopped at "root is still the
      only usable identity". Before an instance is launched: create the Identity
      Center admin group, user, permission set, and assignment, then stop using
      root. Launching an instance as root is the thing the identity work exists
      to avoid.
- [ ] **Step 9a, launch the instance.** Per the architecture table above:
      `t4g.small`, `us-east-1`, Ubuntu Server 24.04 LTS ARM64, 30 GiB encrypted
      `gp3`, Elastic IP from launch, SSM instance profile attached, and port 22
      open to Casey's current IP only. That last one is the deliberate divergence
      in `AGENTS.md`; it is recovery, not the end state.
- [ ] **Step 9b, read-only inventory.** `scripts/inventory.sh`, retargeted to
      EC2 on 2026-08-01: IMDSv2 token handling, an SSM agent check, and an AWS
      console list at the end. Establishes the starting point and confirms the
      launch matched the table. Nothing is installed or changed in this step.

      `scripts/inventory.sh` covers every question in *What the Step 9 inventory
      must record* and is written to be audited before it runs: every
      redirection targets `/dev/null`, and it contains no `apt-get`, `install`,
      `rm`, `mv`, `cp`, `chmod`, `chown`, `systemctl start/stop/enable`, `a2en*`,
      or `sed -i`. Smoke-tested by running it on the development machine, where
      it produced every section and degraded cleanly on the checks that need
      root or a DigitalOcean metadata service. The human runs it on the droplet
      and pastes the output back; that output is the deliverable.
- [ ] Step 10 onward, planned against the inventory once it exists. On a
      dedicated droplet this is expected to follow the container's sequence
      closely, which is what the container was built to make possible.

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
