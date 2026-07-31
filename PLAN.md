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

Done at step 6: `theme.json` carries 24 palette entries, 7 font sizes, 6 spacing
sizes, and 3 shadow presets, all traced to the token sheet, plus a `styles` block
mapping the heading scale to elements. `theme/assets/css/main.css` carries the
same values as `--abyss-*` custom properties. The approved divergences are
recorded in `AGENTS.md` under *Design system divergences*, which is where the
fidelity gate requires them.

The component layer is complete as of 2026-07-31. The readme's class list
(`.btn`, `.tag`, `.card`, `.nav`, `.table`, `.dialog`, `.field`, `.input`,
`.radio`, `.seg`) has no markup in the export, so each was written against the
written spec rather than copied. `main.css` now carries all of them plus the
foundations (base type, links, focus, selection, the `.grayscale` wrapper, the
2px rule), the nav, and the article. The one omission is `.tag-accent-2`, which
the ledger explains.

Not every component has a consumer yet, and that is tracked rather than assumed:
`.abyss-card` is used by `index.php`, `.abyss-tag` by `single.php`,
`.abyss-input` and `.abyss-btn` by `searchform.php`, and `.abyss-table` by the
core table block in post content. `.abyss-radio`, `.abyss-seg`, and the dialog
family have none. Radio and seg are waiting on comment forms and filtering. The
dialog has no natural consumer in a reading site at all and is implemented for
completeness of the class map; anything that does need a modal should use the
native `<dialog>` element with `showModal()`, because these classes are
presentation only and provide no focus trap, Escape handling, or `aria-modal`.

**Accessibility drove five divergences from the token sheet**, all measured and
all in the `AGENTS.md` ledger. Four are the same number: accent-coloured text and
accent fills behind text measure 3.76:1, and nothing legible sits on the accent
at interface sizes, so `.abyss-btn--primary`, `.abyss-btn--ghost`,
`.abyss-tag--outline`, and the checked `.abyss-seg__opt` all move to
`accent-700`. The fifth is WCAG 1.4.11: control borders at the divider token
measure 2.38:1 against the surface, so `.abyss-input`, `.abyss-radio__dot`, and
`.abyss-seg` use `neutral-600`. The divider token itself is unchanged, because
decorative rules identify no component.

Two card-layer selectors have no counterpart in the token sheet and are recorded
in the `AGENTS.md` ledger as a documented gap rather than as tokens: `.abyss-grid`
and `.abyss-card__media`. Both come from the readme's prose, which asks for
"equal-width cells" and for photographs to be wrapped in `.grayscale`, but whose
CSS defines neither a grid nor a media box.

The theme is deployed into the container by symlink,
`/var/www/the-abyss/wp-content/themes/the-abyss -> /workspace/theme`, so repo
edits are live without a copy step. The vhost already allows `+FollowSymLinks`.

Locked values, per the regression-locks rule: the token values in `styles.css`
are the source of truth for colour, type, and spacing. Do not hand-pick a hex, a
font stack, or a pixel value that a token already carries.

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

## Hosting: a dedicated DigitalOcean droplet

DigitalOcean decided 2026-07-29, ahead of the Step 8 checkpoint the roadmap
planned. AWS is demoted to the deferred candidate below with its banked work
intact. The criteria below are kept as the record Step 8 would have used, and
they govern any future re-decision.

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

### Deferred candidate: AWS EC2

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

Archived from `AGENTS.md` on 2026-07-29 when hosting was decided for the
droplet. If AWS is ever chosen instead, restore all of the following to the
project rules together, not just the parts that seem relevant at the time.

Command labels: `# IN AWS CONSOLE` is the AWS Management Console. `# AWS CLI`
is the `aws` command on the human's desktop terminal, run under an active
`aws sso login` session. `# ON AWS SERVER` is an SSH session on the EC2
Ubuntu host.

The port 22 divergence, approved 2026-07-29. The architecture decision is SSM
Session Manager with no inbound port 22, and that remains the end state. The
EC2 launch step nevertheless opens port 22 to Casey's current IP, alongside
the SSM instance profile. The reason is recovery, not convenience. An
instance whose SSM agent fails to register is unreachable, and on a fresh
host the only remedy is termination and relaunch. Port 22 makes that failure
diagnosable. The following step confirms the instance appears in Systems
Manager, then removes the rule. Do not "fix" the launch step to match the
architecture table by closing port 22 there. The two are reconciled
deliberately, and the table describes where the build lands rather than how
it starts.

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
- [ ] **Verification gap, carried out of step 7b.** What has rendered in a
      browser is narrower than what has been written: the card grid, both search
      paths, the 404, the header and article, and the responsive drag. Not yet
      seen at all: `.abyss-radio`, `.abyss-seg`, `.abyss-table`, the dialog, and
      the whole comment thread and form. `.abyss-btn` and `.abyss-input` have
      rendered once, in the search form. Two smaller items also open: the
      `filemtime()` cache-busting change has never been confirmed in page
      source, and the `accent-700` primary-button fill is a visual call nobody
      has ruled on now that it is visible. None of this blocks step 9, but it is
      a debt against the theme, not a finished surface.
- [x] **Responsive pass done 2026-07-31.** Dragged 1600px to 380px with no
      horizontal scrollbar at any width, headings visibly smaller at the narrow
      end, and browser zoom confirming the `1rem +` term in each clamp responds
      to user font-size settings, which is the WCAG 1.4.4 requirement. The
      computed overflow floor is a **308px viewport**: the grid's
      `minmax(260px, 1fr)` plus `.site-main`'s 48px of inline padding. Below
      that a scrollbar is expected. Narrowest common device viewport is 320px,
      so there is 12px of margin.

Phase C, hosting:

- [x] **Step 8, hosting decided: DigitalOcean.** Decided ahead of schedule on
      2026-07-29, and revised 2026-07-31 when a dedicated droplet was created
      for this site. Both the decision and what the revision changed are under
      *Hosting* above.
- [ ] Step 9, read-only inventory of the dedicated droplet. Establishes the
      starting point, above all what the chosen image already installed.
      Nothing is installed or changed in this step.
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
