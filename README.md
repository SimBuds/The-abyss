# The-abyss

The-abyss is a production WordPress affiliate site covering finance and AI. This
repository tracks its infrastructure, custom theme, deployment workflow, and
operational runbook.

The project is worked one verified step at a time. The human runs each build step,
and confirmed commands, evidence, and Q&A are added here afterward.

## Where things live

| Document | Holds |
|---|---|
| [`PLAN.md`](PLAN.md) | The blueprint. Canonical names, architecture decisions and their reasons, cost baseline, requirements, and the step-by-step roadmap with current state. |
| [`AGENTS.md`](AGENTS.md) | The working agreement. The teach rule, command labels, the build-log entry shape, and the verify rule. |
| `README.md` | This file. What the project is, and the build log below. |
| `IMPLEMENT.md` | The agent's working file. Untracked and gitignored, so a fresh clone has none. |

`PLAN.md` is the single source of truth for naming, architecture, and step state.
Where this file and `PLAN.md` disagree, `PLAN.md` wins.

## At a glance

The build runs on AWS EC2 in `us-east-1`: a `t4g.small` ARM64 instance on Ubuntu
Server 24.04 LTS, with Apache and `php-fpm`, MySQL on the same host, Cloudflare at
the edge, and shell access through SSM Session Manager. Staging and production run
as two vhosts on the one host. The reasoning behind each of those choices, and the
approximate monthly cost, are in [`PLAN.md`](PLAN.md#current-architecture-decisions).

The lifecycle is local, then staging, then production. Staging is stood up first
and served over HTTP at the server's public IP. Going live means connecting the
domain, TLS, and Cloudflare. Staging is never indexed and never holds real
credentials.

## Current state

The AWS account exists and nothing billable has been launched. Account security
comes first, so the next step is root MFA and an IAM Identity Center admin user.
The full roadmap and its checkboxes are in
[`PLAN.md`](PLAN.md#status).

## Build log

Entries are appended here as each step is verified, newest last. They follow the
shape defined once in [`AGENTS.md`](AGENTS.md) under `## Project-specific rules`.
