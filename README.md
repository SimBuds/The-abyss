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

The stack is being learned locally first, in an Ubuntu 24.04 Docker container
that stands in for a real server: Apache with `php-fpm`, MySQL on the same host,
and WordPress, all installed by hand rather than inherited from a prebuilt image.
Connect to it the way you would connect to a real host:

```bash
docker compose up -d --build
ssh -p 2222 root@localhost
```

**The hosting target is deliberately undecided.** It will be AWS or a
DigitalOcean droplet, chosen at Step 8 once the stack is understood. Nothing
before that step depends on a provider. The candidates, their costs, and the
decision criteria are in [`PLAN.md`](PLAN.md#hosting-undecided).

The lifecycle is local, then staging, then production. Staging is stood up first
and served over HTTP at the server's public IP. Going live means connecting the
domain, TLS, and Cloudflare. Staging is never indexed and never holds real
credentials.

## Current state

The local container is running and reachable over SSH. Next is installing Apache
with `php-fpm` inside it. The full roadmap and its checkboxes are in
[`PLAN.md`](PLAN.md#status).

## Build log

Entries are appended here as each step is verified, newest last. They follow the
shape defined once in [`AGENTS.md`](AGENTS.md) under `## Project-specific rules`.

#### Step 1 — Ubuntu 24.04 container reachable over SSH ✅

**Goal:** an Ubuntu 24.04 container that stands in for the eventual server,
reachable over SSH with key authentication and this repository mounted inside.

**Why it matters:** the stack is learned by installing it by hand, which needs a
Linux box that is free to break. Connecting over SSH rather than
`docker compose exec` means the habit transfers to a real host unchanged. Nothing
is installed in the image beyond a shell and `sshd`, so Apache, PHP, MySQL, and
WordPress are all learned rather than inherited from someone else's Dockerfile.

**Commands:**

```bash
# ON HOST
cd ~/Apps/The-abyss
docker compose up -d --build
docker compose ps
docker compose logs web
ssh -p 2222 root@localhost
```

**Verify:**

`docker compose logs web` reports
`entrypoint: authorized_keys installed from /tmp/host-key.pub`.

From inside the container, `head -2 /etc/os-release` returns
`PRETTY_NAME="Ubuntu 24.04.4 LTS"` and `NAME="Ubuntu"`.

`ls /workspace` lists `AGENTS.md`, `IMPLEMENT.md`, `PLAN.md`, `README.md`,
`compose.yaml`, `design`, `docker`, and `theme`, confirming the bind mount.

SSH connects on key authentication alone, with no password prompt.

**Q&A:**

*What does `docker compose exec web bash` do?* Runs a command inside an
already-running container. Its neighbour `docker compose run` creates a new
container instead, which is the common trap: installed packages and database
contents live in the first container, so `run` makes the work look lost. `web` is
the service name from `compose.yaml`, and `bash` is an interactive shell. You are
root inside, which is why no later command carries `sudo`.

*How do SSH keys work?* Two mathematically linked files. The private key never
leaves the machine, and the public key is copied to the server's
`~/.ssh/authorized_keys`. The server issues a challenge, the client signs it with
the private key, and the server verifies the signature with the public key. The
private key is never transmitted, so a fully compromised server leaks only public
keys, which are useless on their own. `~/.ssh/known_hosts` is the reverse record,
which is why rebuilding the container triggers a host-key warning. Clear it with
`ssh-keygen -R '[localhost]:2222'`.

*Why does `uname` report a zen kernel rather than Ubuntu's?* Containers share the
host kernel and supply only the userland. This is the thinnest part of the mirror
and does not affect the stack being learned.
