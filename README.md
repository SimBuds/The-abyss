# The-abyss

The-abyss is a production WordPress affiliate site covering finance and AI. This
repository tracks its infrastructure, custom theme, deployment workflow, and
operational runbook.

The project is worked one verified step at a time. The human runs each build step,
and confirmed commands, evidence, and Q&A are added here afterward. See
[`AGENTS.md`](AGENTS.md) for the working agreement and [`PLAN.md`](PLAN.md) for the
blueprint and current state.

The build follows a local → staging → production lifecycle. Staging is stood up first
and served over HTTP at the server's public IP; going live means connecting the
domain, TLS, and Cloudflare. Staging is never indexed and never holds real
credentials.

## Production target

| Item | Initial choice |
|---|---|
| Platform | AWS EC2 |
| Instance | `t4g.small` — 2 vCPU / 2 GiB, ARM64 |
| OS | Canonical Ubuntu Server 24.04 LTS ARM64 |
| Storage | 30 GiB encrypted `gp3` EBS |
| Web stack | Apache `mpm_event` + `php-fpm` |
| Application | WordPress |
| Database | MySQL on the same EC2 host initially |
| Edge | Cloudflare free tier, origin locked to Cloudflare |
| Shell access | SSM Session Manager, no inbound port 22 |
| Addressing | Elastic IP |
| Mail | Amazon SES |
| Server name | `the-abyss-web-01` |
| Environments | Staging and production as two vhosts on the one host |
| Production path | `/var/www/the-abyss` |
| Staging path | `/var/www/the-abyss-staging` |

### Why `t4g.small`

A 1 GiB micro instance is tight when Ubuntu, Apache/PHP, WordPress, plugins, image
processing, and MySQL share memory. `t4g.small` doubles that memory while retaining
burstable, low-cost compute. The open-source WordPress stack supports ARM64; use
`t3.small` only if a required dependency is x86-only.

AWS currently includes 750 aggregate `t4g.small` compute hours per month through
December 31, 2026. This is separate from the new-account $100 credit. Compute is the
only part covered by that special trial; storage, public IPv4, snapshots, data, and
surplus CPU can still use credits. The Free account plan ends after six months or
when credits run out, so a live site must move to the Paid plan before that deadline.

Authoritative references:

- [EC2 Free Tier instance eligibility and credit model](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ec2-free-tier-usage.html)
- [EC2 T4g specifications and current trial](https://aws.amazon.com/ec2/instance-types/t4/)
- [Detailed T4g trial terms](https://aws.amazon.com/ec2/faqs/#t4g-instances)
- [Official Ubuntu ARM64 images](https://documentation.ubuntu.com/aws/en/latest/aws-how-to/instances/find-ubuntu-images/)
- [EBS pricing](https://aws.amazon.com/ebs/pricing/)
- [Public IPv4 pricing](https://aws.amazon.com/vpc/pricing/)

At current US East list prices after the special compute trial, the simple baseline
is approximately:

| Item | Approximate monthly list price |
|---|---:|
| `t4g.small` compute | $12.26 |
| 30 GiB `gp3` | $2.40 |
| One public IPv4 address | $3.65 |
| **Baseline** | **$18.31** |

Prices vary by region and exclude snapshots, backup storage, excess data transfer,
surplus CPU, tax, and domain registration. Check Billing and the AWS calculator
before launch and again before the trial ends.

### Initial database choice

WordPress needs MySQL or MariaDB; this plan uses MySQL on the same EC2 host while
traffic is near zero. MySQL must bind to localhost, with no public port 3306 rule.

RDS becomes worthwhile when managed recovery, independent scaling, availability, or
database isolation justifies a second always-on service. Until then, use automated
logical database backups, encrypted EBS snapshots, off-instance copies, and a tested
restore procedure.

## Canonical naming

| Use | Value |
|---|---|
| Project and WordPress public name | `The-abyss` |
| Machine slug, theme directory, text domain | `the-abyss` |
| PHP function / constant prefixes | `the_abyss_` / `THE_ABYSS_` |
| Production database / user | `the_abyss_wp` / `abyss_wp` |
| Staging database / user | `the_abyss_stg` / `abyss_stg` |

Secrets, private keys, credentials, live addresses, and unnecessary AWS resource IDs
must not be committed.

## Product requirements

- Finance and AI articles, guides, comparisons, and roundups.
- `rel="sponsored nofollow"` on every affiliate link, applied by a theme filter
  rather than by hand so it cannot be forgotten.
- Visible FTC and Competition Bureau disclosures.
- Author and review-date schema for finance content.
- TLS, backups, monitoring, least privilege, and a documented restore process.
- Self-hosted fonts and no unnecessary third-party font requests.

## Repository state

The existing `theme/` directory is an obsolete prototype with the wrong content
model and visual system. It is not a deployable The-abyss theme and will not be
blind-renamed. The production `the-abyss` theme will be created fresh after the AWS
foundation is verified.

The design-system export is not present in this workspace snapshot and must be
restored before template implementation.

## Status

Step state is tracked in one place: [`PLAN.md` section Status](PLAN.md#status).
Naming and initial architecture are settled, and the build has not started.

## Build log

Entries are appended here as each step is verified, newest last. They follow the
shape defined once in [`AGENTS.md`](AGENTS.md) under `## Project-specific rules`.

#### Step 0 — initialise the Git repository and ignore rules ✅

**Goal:** put the project under version control with ignore rules that make
committing a credential difficult before any infrastructure work begins.

**Why it matters:** the build log is the deliverable of every later step, and it
lives in these files. Without a repository there is nothing to record into and no
way to revert a bad step. The ignore rules come first rather than later because a
secret committed once stays in history even after it is deleted, and the fix is
rewriting history rather than editing a file.

**Commands:**

```bash
# ON HOST
cd ~/Apps/The-abyss
git init
git add .
git commit -m "First Commit"
```

**Verify:** commit `9eee935` tracks 18 files, 956 insertions.

`git ls-tree -r --name-only HEAD` returns `.gitignore`, `AGENTS.md`, `README.md`,
and the 14 `theme/` prototype files. Nothing else.

`git ls-files` filtered against the credential patterns in `.gitignore` returns no
matches, so no key, environment file, `wp-config.php`, or Terraform state is
tracked.

`git check-ignore` confirms each rule bites:

```text
test.pem               ignored
.env                   ignored
wp-config.php          ignored
secrets.json           ignored
terraform.tfvars       ignored
IMPLEMENT.md           ignored
dump.sql               ignored
```

**Q&A:**

*Why does the commit message differ from the one this repository's plan suggested?*
The plan proposed `"Initial commit: project plan and working agreement"` and the
commit reads `"First Commit"`. Recorded as it happened rather than as it was
planned. The message has no functional effect and the commit is not being amended,
because rewriting history is reserved for the repository owner.

*Why was the original "clean working tree" check dropped?* It only holds in the
instant after a commit. Any phase in flight leaves the tree dirty, so it tests
whether work is currently in progress rather than whether Step 0 was ever done. The
durable evidence is what `HEAD` tracks and what the ignore rules catch.

*Does `PLAN.md` appear in this evidence?* No. It did not exist at commit `9eee935`.
The project state lived in the 314-line `AGENTS.md` of that commit and was split
into `PLAN.md` and the current `AGENTS.md` afterward, so `PLAN.md` is untracked
until the next commit.
