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

**Hosting is undecided and deliberately parked**, along with the domain. It
changed three times in four days and each change cost documentation surgery while
the site gained nothing, so it waits for a decision. The stack itself is
provider-neutral — it was built against Ubuntu, not against a host — so the
choice does not block anything currently in flight. The candidates and what each
implies are in [`PLAN.md`](PLAN.md#hosting-undecided-parked-2026-08-01).

Two scripts carry the transfer: `scripts/provision.sh` turns a fresh Ubuntu host
into this stack, and `scripts/inventory.sh` is a strictly read-only survey to run
before it.

The theme is `abyss-theme`: deep navy and amber, Plus Jakarta Sans and Source
Sans 3, with a homepage built around two editorial lanes, a live ticker, a
sortable rate comparison table, tested picks, and newsletter capture. It replaced
an earlier Modernist-based theme on 2026-08-01; that system and its `design/_ds/`
token sheet were removed, and the reasoning is in
[`PLAN.md`](PLAN.md#design-system). The palette is a single scheme, measured
against WCAG rather than assumed.

The lifecycle is local, then staging, then production. Going live means
connecting the domain, TLS, and Cloudflare. Staging is never indexed and never
holds real credentials.

## Current state

WordPress runs locally at `http://localhost:8080` on Apache with `php-fpm` and
MySQL inside the container, the whole stack codified in `docker/Dockerfile`.

The theme is complete and verified by screenshot at three widths: every component
family, the full template set, the block-editor content layer, and the homepage
sections against the reference. Compliance is built in rather than remembered —
affiliate `rel`, a disclosure above the article, and `Article` JSON-LD, all keyed
off one list of monetised domains that is empty until the first programme is
joined.

`scripts/provision.sh` has been proven end to end in a throwaway container: bare
Ubuntu to a working site, with five defects found and fixed in the process. What
it still cannot cover there — systemd service management, the firewall, swap, and
certbot — is reported as `SKIPPED` rather than passed.

**The local database is disposable, decided 2026-08-01.** Only the theme, the
Docker setup, the scripts, and these documents are in Git. Local content and
plugin configuration are fixtures, and plugin setup happens once on the real
server rather than twice. See the delivery sequence in
[`PLAN.md`](PLAN.md#delivery-sequence).

Next is local: plugin configuration waits for a real domain, so work continues on
the theme and content model. The full roadmap is in [`PLAN.md`](PLAN.md#status).

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

#### Step 2 — Apache with `php-fpm` ✅

**Goal:** a working web tier in the container, with PHP running as its own
service rather than embedded in the web server.

**Why it matters:** Apache does not execute PHP here. It forwards `.php`
requests over FastCGI to `php-fpm`, which runs them in a separate process. The
alternative, `mod_php`, embeds an interpreter in every Apache worker whether it
needs one or not, which is what makes memory tight on a small host, and it means
a PHP-level compromise is also a web-server-level one. Three pieces connect them:
`proxy_fcgi` forwards, `setenvif` passes the `Authorization` header through for
the WordPress REST API, and the packaged conf wires the routing rule.

**Commands:** run by hand first, then codified into `docker/Dockerfile`.

```bash
# IN CONTAINER
apt-get update
apt-get install -y apache2 php-fpm
a2enmod proxy_fcgi setenvif
FPM_CONF=$(ls /etc/apache2/conf-available/ | grep -o 'php[0-9.]*-fpm' | head -1)
a2enconf "$FPM_CONF"
service "$FPM_CONF" start
service apache2 start
```

On a real host the last two are `systemctl start php8.3-fpm` and
`systemctl start apache2`. The PHP version is discovered rather than hardcoded,
in both the Dockerfile and the entrypoint, so a distribution upgrade does not
silently produce an image that serves PHP source as plain text.

**Verify:**

```text
Wed Jul 29 19:04:16 UTC 2026
PHP 8.3.6 via fpm-fcgi

HTTP/1.1 200 OK
Server: Apache/2.4.58 (Ubuntu)
```

The `fpm-fcgi` field is the load-bearing one. `apache2handler` there would mean
PHP was running embedded in Apache, the arrangement this build exists to avoid.
The 200 came from the host on port 8080, proving the port map survived a rebuild.
`date` in UTC confirms the timezone is pinned.

**Q&A:**

*Why did installing `php-fpm` open an interactive timezone picker when the
Dockerfile sets `DEBIAN_FRONTEND=noninteractive`?* Because `ENV` does not reach
an SSH session. It applies to the container's main process and to `docker exec`,
but `sshd` builds a fresh login environment from PAM, so Docker's variables are
absent. Fixed by writing the variable to `/etc/environment`, which PAM does read,
and by pinning the timezone at build time so no package can ask.

*Why codify Apache into the Dockerfile at step 2 instead of waiting for step 5?*
Because a rebuild discards anything installed by hand. `/var/www` and
`/var/lib/mysql` are named volumes and survive, but `/etc` and `/usr` are
container layers and do not. Capturing the verified commands first made the
rebuild productive rather than destructive, and that is now the standing pattern.

*Why did SSH refuse to connect after the rebuild?* The rebuild regenerated the
container's host keys, and `~/.ssh/known_hosts` correctly flagged the identity
change. Cleared with `ssh-keygen -R '[localhost]:2222'`. The same thing happens
when a real server is rebuilt.

#### Step 3 — MySQL and the WordPress database ✅

**Goal:** MySQL running, with a database and a least-privilege user for
WordPress.

**Why it matters:** WordPress stores these credentials in a file on disk. If that
file leaks, the blast radius should be one database rather than the whole server,
so WordPress gets a user scoped to its own database and nothing else. The
database is also the part that cannot be recreated. WordPress files reinstall in
a minute, but posts, settings, users, and plugin configuration exist only here,
which is why `/var/lib/mysql` is a separate named volume from `/var/www`.

**Commands:**

```bash
# IN CONTAINER
apt-get update
apt-get install -y mysql-server
service mysql start

read -rsp 'Choose a password for abyss_local: ' DB_PASS; echo

mysql <<SQL
CREATE DATABASE the_abyss_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'abyss_local'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON the_abyss_local.* TO 'abyss_local'@'localhost';
FLUSH PRIVILEGES;
SQL
```

On a real host, `service mysql start` is `systemctl start mysql`. The password is
read into a shell variable rather than typed into the command, so it reaches
neither the screen nor shell history. It is not recorded here, in `PLAN.md`, or
anywhere else in this repository.

**Verify:**

```text
mysql  Ver 8.0.46-0ubuntu0.24.04.3 for Linux on x86_64 ((Ubuntu))
Uptime: 16  Threads: 2  Questions: 10  Slow queries: 0

+-----------------+
| DATABASE()      |
+-----------------+
| the_abyss_local |
+-----------------+
| GRANT USAGE ON *.* TO `abyss_local`@`localhost`                          |
| GRANT ALL PRIVILEGES ON `the_abyss_local`.* TO `abyss_local`@`localhost` |
```

Connecting as `abyss_local` is the check that matters, not the fact that the
`CREATE` statements returned without error. The grant must read
`ON the_abyss_local.*`. Had it read `ON *.*`, WordPress would hold privileges on
every database on the server.

**Q&A:**

*Why does `mysql` connect as root with no password?* Ubuntu configures root with
the `auth_socket` plugin, which authenticates by Unix user identity rather than a
password. No credential exists to leak or store.

*What is `GRANT USAGE ON *.*`?* MySQL's way of recording that an account may
connect and holds no privileges. Every user has it. It is the account's
existence, not a permission, and it is not server-wide access.

*Why `utf8mb4` rather than `utf8`?* MySQL's `utf8` holds only three bytes per
character and silently cannot store emoji or some CJK characters. `utf8mb4` is
real UTF-8. Choosing wrong surfaces months later as posts truncating at the first
emoji, and fixing it then means converting every table.

*`ss` and `netstat` were not installed, so the listening address went
unverified.* The `ubuntu:24.04` image is minimized. `bind_address` can be read
with `mysql -e "SHOW VARIABLES LIKE 'bind_address';"` instead. It is a second
layer regardless: `compose.yaml` publishes only 8080 and 2222, so MySQL has no
route in from the host whatever it binds to.

#### Step 4 — WordPress installed and serving ✅

**Goal:** WordPress running at `http://localhost:8080`, connected to its own
database, with the site URL set by constant rather than stored in the database.

**Why it matters:** `WP_HOME` and `WP_SITEURL` defined in `wp-config.php`
override whatever is in `wp_options`. WordPress normally keeps the site URL in
the database, and absolute URLs spread from there into post content, metadata,
and serialised plugin settings, which is what turns a later domain change into a
database-wide search and replace. Defined in config, the cutover is a one-line
edit.

**Commands:**

```bash
# IN CONTAINER
cd /tmp
curl -fLO https://wordpress.org/latest.tar.gz
tar -xzf latest.tar.gz
mkdir -p /var/www/the-abyss
cp -a /tmp/wordpress/. /var/www/the-abyss/
chown -R www-data:www-data /var/www/the-abyss
rm -rf /tmp/wordpress /tmp/latest.tar.gz

read -rsp 'Password for abyss_local: ' DB_PASS; echo
curl -s https://api.wordpress.org/secret-key/1.1/salt/ > /tmp/salts.txt
# wp-config.php written with DB credentials, WP_HOME, WP_SITEURL, and debug
# logging to file rather than to the page, then:
sed -i '/DB_COLLATE/r /tmp/salts.txt' /var/www/the-abyss/wp-config.php
rm /tmp/salts.txt
chown www-data:www-data /var/www/the-abyss/wp-config.php
chmod 640 /var/www/the-abyss/wp-config.php
```

`curl -fLO` rather than plain `-O`: without `-f`, curl saves an HTTP error page
as the tarball and the failure surfaces at `tar` as an unrecognised archive.
`chmod 640` because this file holds the database password and has no reason to be
world-readable. The vhost was created by hand and then codified into
`docker/Dockerfile`.

**Verify:**

```text
+---------------------------+
| Tables_in_the_abyss_local |
+---------------------------+
| wp_options                |   (12 wp_* tables total)
| wp_posts                  |
| wp_users                  |
+---------------------------+
+-------------+-----------------------+
| blogname    | The-abyss             |
| home        | http://localhost:8080 |
| siteurl     | http://localhost:8080 |
+-------------+-----------------------+
```

`siteurl` and `home` in the database agree with the constants, so there is no
drift between the two. `grep -c '_KEY\|_SALT' wp-config.php` returns 8, one per
authentication key and salt.

**Q&A:**

*WordPress returned a 500 with a completely empty Apache error log. Why?*
`php-fpm` is the FastCGI runtime only, and every PHP extension is a separate
Debian package. Without `php-mysql`, WordPress fails its requirements check and
calls `wp_die()`, which is a controlled stop rather than a PHP error, so nothing
is logged. An empty error log beside a 500 is therefore evidence, not an absence
of it: it means the application stopped itself deliberately.

*Why was the cause invisible for several rounds?* Because `curl -I` fetches
headers only, and the explanation was in the response body the whole time. The
rule is not "always fetch the body" but "fetch whichever half carries the
information for this failure mode". A 500 puts it in the body. A 302 has no body
at all, so headers are the only place to look.

*Why did the vhost and MySQL keep disappearing?* Each rebuild discards
everything in the image, including `/etc`. Three rebuilds each lost a different
hand-made change before the split was audited properly: packages and `/etc` are
image content and must be codified, while `/var/www` and `/var/lib/mysql` are
named volumes and survive. The audit is now recorded in `docker/Dockerfile` and
in `PLAN.md`.

*The vhost build failed with `Cannot access directory
'/etc/apache2/\/var/log/apache2/'`.* Apache's `${APACHE_LOG_DIR}` had to survive
a Dockerfile `RUN`, a shell single-quoted string, and then Apache's own variable
expansion. The escape left a literal backslash in the file, which made the path
relative to `ServerRoot`. Fixed by using the literal `/var/log/apache2` instead,
since a plain path has no layers to survive. The `apache2ctl configtest` guard in
the build caught this rather than shipping a vhost that would fail at start.

*Why does `curl http://localhost/` inside the container return 301?* Because
`WP_SITEURL` is `http://localhost:8080`, and WordPress issues a canonical
redirect to the address it was told is correct. The in-container port 80 view is
the wrong vantage point once the site URL is set. A 301 there is proof WordPress
reached the database, since a credential failure would be a 500 instead.

*The salts were written as blank lines the first time.* `${SALTS}` was expanded
in a shell session where the variable had never been set. Rewritten using
`sed '/DB_COLLATE/r file'`, which reads from a file rather than through a shell
variable and so cannot silently expand to nothing.

#### Step 5 — codification complete ✅

**Goal:** the whole environment reproducible from `docker compose up -d --build`,
with a stable SSH identity across rebuilds.

**Why it matters:** every hand-made change inside `/etc` is destroyed by a
rebuild, and finding that out one component at a time cost three rebuilds during
steps 2 to 4. The split is now audited and recorded: packages and `/etc` are image
content and must be codified, while `/var/www` and `/var/lib/mysql` are named
volumes and must not be. Host keys were the last piece of churn, forcing an
`ssh-keygen -R` after every build.

**Commands:** no hand-run commands. This step is entirely repository files.

`docker/Dockerfile` holds Apache, `php-fpm`, seven PHP extensions,
`mysql-server`, the sshd drop-in, and the timezone pin. `docker/the-abyss.conf`
is the vhost, COPYed in rather than generated, which also fixes the
`${APACHE_LOG_DIR}` escaping problem because Docker never substitutes inside a
copied file. `compose.yaml` adds a `sshkeys` volume at `/etc/ssh/keys`.
`docker/entrypoint.sh` generates host keys on first run only, starts MySQL,
`php-fpm`, and Apache, and provisions WordPress core when `/var/www` is empty.

**Verify:** two consecutive rebuilds, because one proves nothing.

First run, eight lines:

```text
entrypoint: authorized_keys installed from /tmp/host-key.pub
entrypoint: generated ed25519 host key (first run)
entrypoint: generated rsa host key (first run)
entrypoint: generated ecdsa host key (first run)
entrypoint: started mysql
entrypoint: started php8.3-fpm
entrypoint: started apache2
entrypoint: WordPress present at /var/www/the-abyss
```

After `docker compose up -d --build --force-recreate`, six lines, with the three
`generated ... host key` entries absent. Their absence is the proof: the loop
found the keys already in the volume. SSH then connected with no fingerprint
prompt, no warning, and no `ssh-keygen -R`, logging
`Accepted publickey for root`.

`wp-config.php` survived at `-rw-r----- www-data www-data 1319`, timestamp
unchanged, confirming the rebuild did not touch the volume.

**Q&A:**

*Why remove `ssh-keygen -A` from the build instead of keeping it as a fallback?*
Two mechanisms generating host keys makes it ambiguous which set sshd loads, and
the point of the change is that the container has exactly one identity. The
entrypoint generates them before sshd starts, so sshd never sees a missing file.

*Why does the entrypoint provision WordPress core but not `wp-config.php`?* That
file holds the database password. Generating it would mean sourcing a credential
from the image, `compose.yaml`, or an env var beside them in the repository, which
tier 0 forbids. Creating it stays a manual step, documented under step 4 above.

*SSH failed once with `kex_exchange_identification: read: Connection reset by
peer`.* A race, not a fault. Docker publishes the port before sshd finishes
binding to it, so a connection in that window is accepted by the proxy and reset.
The full log showed every startup stage completing. Worth distinguishing from
`Connection refused`, which means nothing is listening at all, and from a
`publickey` rejection, which means sshd is running and declined the key.

*A `grep entrypoint` filter on the logs hid the diagnosis.* Errors in the
entrypoint print `ERROR:` to stderr, not `entrypoint:`, so the filter removed
exactly the line that would have explained a failure. When investigating, read
the whole log rather than the slice a hypothesis expects.

#### Step 6 — prototype stripped, theme.json from the Modernist tokens ✅

**Goal:** replace the inherited prototype's identity, palette, and content model
with the Modernist design system, and prove the tokens reach the rendered page.

**Why it matters:** the prototype was a prefab-building site. Its palette used
`#e11d22`, `#16181d`, and `#f6f6f7` against Modernist's `#ec3013`, `#201e1d`, and
`#f3f2f2`. Those are near-misses, which are more dangerous than obviously wrong
values because they read as correct in a diff. It also registered a
`building_model` post type with two taxonomies, and prefixed everything `fb_`
against the canonical `the_abyss_`.

**Commands:**

```bash
# IN CONTAINER
ln -s /workspace/theme /var/www/the-abyss/wp-content/themes/the-abyss
su -s /bin/sh www-data -c 'head -3 /var/www/the-abyss/wp-content/themes/the-abyss/style.css'
```

```text
# IN WP-ADMIN
Appearance -> Themes -> The-abyss -> Activate
```

The symlink means repo edits are live in WordPress with no copy step. The vhost
already permits `+FollowSymLinks`.

Kept from the prototype: the three Archivo woff2 files and the classic-theme file
scaffold. Deleted: `inc/cpt.php`, the palette, and the two Figtree woff2 files,
which appear nowhere in the design system.

**Verify:**

```bash
# ON HOST
curl -s http://localhost:8080/ | grep -Eo 'the-abyss/assets/css/[a-z]+\.css|wp--preset--color--(base|accent|contrast)|#f3f2f2|#ec3013' | sort -u
```

```text
#ec3013
#f3f2f2
the-abyss/assets/css/fonts.css
the-abyss/assets/css/main.css
wp--preset--color--accent
wp--preset--color--base
wp--preset--color--contrast
```

Reading the rendered page rather than the files is the point: the hex values
appearing in the response prove the tokens reached the output. Activation
completing without a fatal is also what verified the PHP, since `php -l` could
not run.

A widened identifier search returns nothing:
`rg 'FutureBuild|futurebuild|FB_[A-Z]|fb_[a-z]|fb-[a-z]|building_model|building_type|Figtree|e11d22|c4171c|16181d|6a6d75|f6f6f7|e6e6e8' theme/`.
All 23 colour values in the theme trace to the token sheet, and no hex in the
theme is absent from it.

**Q&A:**

*Why did the first verification pattern have to be widened?* The approved pattern
was `building_model|building_type|fb_|e11d22|16181d|f6f6f7`. It matched `fb_setup`
but not `FB_VERSION`, `fb-red`, `futurebuild`, or `FutureBuild`, and named three
of the six stale hex values. It would have reported a clean strip with roughly
forty obsolete identifiers still in place. A check that cannot fail on the real
target verifies nothing.

*Why is Archivo declared as `font-weight: 100 900`?* The three woff2 files are
variable fonts, confirmed by parsing the woff2 table directory for `fvar`, `avar`,
`HVAR`, and `STAT`. `brotli` was unavailable so fontTools could not open them, but
the woff2 table directory is uncompressed and readable directly. The prototype
declared 24 fixed-weight blocks spanning only 600 to 900, leaving Modernist's body
weight of 400 with no matching face, so body copy would have rendered from the 600
face.

*Why do links use `#ae1800` rather than the accent `#ec3013`?* The design readme
states the accent-to-ground pair is tuned to about 3:1, enough for icons, large
text, and interface chrome but not for body copy, and directs paragraph-size
accent text to `--color-accent-700`. Links sit in running text. Recorded as an
approved divergence in `AGENTS.md`.

#### Step 7a — header, nav, and the single-post template ✅

**Goal:** one complete reading experience, so the token mapping is proven against
real content before any component family is built.

**Why it matters:** a single article exercises the whole type scale, the 2px
rules, the accent link colour, and the grid width at once. It is the fastest way
to find out whether step 6's token mapping was right, and it found two things a
component-first order would have delayed.

**Commands:** repository files only. Verified against a published post.

`theme/single.php` is new. `theme/header.php` gains a `nav` element with
`wp_nav_menu` and `fallback_cb` set to false, so an unassigned menu renders
nothing rather than a list of every published page. `theme/theme.json` layout
becomes 960px content and 1440px wide. `theme/assets/css/main.css` gains the nav,
the article, and the prose measure.

Component classes are namespaced `abyss-`, decided 2026-07-29, because the design
readme's bare names (`.btn`, `.card`, `.hr`, `.input`, `.table`) collide with core
block markup. A class map in `main.css` translates readme name to theme name.

**Verify:**

```text
class="abyss-nav"
class="abyss-nav__brand"
class="abyss-article post-11 post type-post ... category-finance"
class="abyss-article__header"
class="abyss-article__kicker"
class="abyss-article__media grayscale"
class="abyss-article__meta"
class="abyss-article__content"
class="... post-template-default single single-post postid-11 ... wp-theme-the-abyss"
```

`post-template-default single single-post` confirms WordPress chose the new
template. `attachment-the-abyss-hero` confirms the custom image size is in use.
The rendered page shows the FINANCE kicker in accent uppercase, an Archivo 800
headline, a muted meta line, the 2px header rule, and the featured image in true
black and white through the `grayscale` wrapper.

WordPress reports the layout it was given, read from the page rather than the
file: `--wp--style--global--content-size: 960px` and
`--wp--style--global--wide-size: 1440px`.

**Not verified.** No responsive pass has been done, on this or any earlier CSS.
The accessibility baseline requires a wide and a narrow viewport with dragging
between them, and only one wide viewport has been observed. Carried into step 7b
as the first item rather than recorded as passing.

**Q&A:**

*The site brand rendered in accent red and underlined instead of ink. Why?*
WordPress emits `theme.json`'s `elements.link` as
`:root :where(a:where(:not(.wp-element-button)))`. The `:where()` wrappers
contribute zero specificity, so that selector scores (0,1,0) from `:root` alone,
tying with a single class and winning on source order because the inline global
styles print after the stylesheet. Fixed by scoping to
`.abyss-nav .abyss-nav__brand`, which is (0,2,0). Every theme rule that styles a
link now carries a parent selector. Recorded in `AGENTS.md`.

*Three paragraphs rendered at three different sizes. Was that a bug?* No. The
markup carries `has-xl-font-size`, `has-lg-font-size`, and `has-md-font-size`,
which are the `theme.json` presets applied by the editor's size picker. Authored
content, not inherited styling. It doubled as unplanned proof that the font-size
scale resolves end to end from `theme.json` to the rendered class.

*Why is 960px the content width when the design system is the locked source of
truth?* Modernist specifies a modular grid but no content width, so this is a
documented gap rather than a token. 960px at 15px runs to roughly 120 characters
per line, so paragraph text inside article content is separately capped at 70ch.
The grid keeps its width, the prose does not.

#### Step 7b (part 1) — the card family and the post grid ✅

**Goal:** turn the blog home and archives from a bare list into Modernist's
"equal-width cells", and add the first component family from the design readme.

**Why it matters:** the card is the site's main unit of navigation. It is also
the first component written against prose rather than copied from CSS: the token
sheet defines `.card` and its children, but defines no grid and no media box, so
those two are project decisions and had to be recorded as a documented gap rather
than passed off as tokens.

**Commands:**

```bash
# IN CONTAINER
php -l /var/www/the-abyss/wp-content/themes/the-abyss/index.php
```

**Result:** `No syntax errors detected`. The listing renders as cards with the
grayscale thumbnail, accent kicker, 17px title, excerpt at 0.8 opacity, and date;
square corners and surface fill throughout.

**Responsive pass, outstanding since step 6, now done.** Dragged 1600px down to
380px: no horizontal scrollbar at any width, cards reflowing three to two to one
with no breakpoints in the stylesheet, and headings visibly smaller at the narrow
end. Browser zoom grew the type, which is what the `1rem +` term in each clamp
exists for and what WCAG 1.4.4 requires. The computed floor is a 308px viewport
(`minmax(260px, 1fr)` plus 48px of inline padding); below that a scrollbar is
correct behaviour, and the narrowest common device is 320px.

**Two defects found by reading the code after the browser check passed, both
fixed:**

1. Cards had ragged bottoms. The `<li>` is the grid item, so `align-items:
   stretch` stretched the `li` and stopped there, leaving the card at its own
   content height and making `.abyss-card__body { flex: 1 }` a no-op. Invisible
   with one post, wrong with three. Fixed with `.abyss-grid > li { display:
   flex }`.
2. A search for "bitcoin" would have been titled "Archives". The archive heading
   was guarded on `! is_front_page() && ! is_home()`, but this template is also
   the fallback for search and, until `404.php` exists, for not-found pages. On
   both, `get_the_archive_title()` falls through its entire conditional chain to
   the literal string `Archives`. Now branches on `is_archive()` and
   `is_search()` explicitly.

Both were then proven against three published posts of differing excerpt length,
two of them without a featured image: the card bottoms end level, the three dates
sit on one line, and the image-less cards start at the kicker with no placeholder
box. The thumbnail measured 711x473 in the rendered page, which is 1.503, so the
3:2 box is applied and the double crop is gone.

A third defect, found because it was distorting the verification itself:
`wp_enqueue_style` was versioning both stylesheets with `THE_ABYSS_VERSION`,
which reads `Version:` from `style.css` and therefore never changes during a
build. Every CSS edit shipped as `?ver=0.1.0`, so a browser could serve a cached
copy and "the fix did not work" was indistinguishable from "you did not see the
fix". Now versioned by `filemtime()` through `the_abyss_asset_version()`, which
mints a new URL on every save and settles to one stable value per deploy in
production.

Both search paths then verified in the browser: `?s=spacex` returns the matching
card under a *Search results for: spacex* heading, and `?s=zzzzqqq` keeps its
heading and reports that no posts matched, which is the path that rendered a bare
sentence with no `h1` at all before this step.

**Still not verified.** The `filemtime()` cache-busting change has not been
confirmed in page source. The search fixes are no evidence either way, because
`index.php` is PHP and was never the thing being cached.

**Q&A:**

*Why does the grid have no media queries?* `repeat(auto-fit, minmax(260px, 1fr))`
lets the column count fall out of the available width, so the breakpoints are
implicit in the 260px minimum. There is nothing to keep in sync when the content
width changes later.

*Why is the card title 17px when it is an `<h2>`?* The token sheet pins
`.card-title` to 17px regardless of heading level. Keeping the size on the class
rather than the element leaves the semantic level free to match the document
outline, which is what a screen reader navigates by.

*Why is the card thumbnail `aspect-ratio: 3 / 2` rather than 16/9?* It has to
track `the-abyss-card`'s registered 640x420 crop. A 16/9 box over a 3:2 file makes
`object-fit: cover` crop an already-cropped image a second time, discarding about
14% more of its height for nothing. Change one and the other has to follow.

#### Step 7b (part 2) — the component layer completed ✅

**Goal:** implement every remaining family in the design readme's component list:
`.btn`, `.tag`, `.field`, `.input`, `.radio`, `.seg`, `.table`, and `.dialog`.

**Why it matters:** these are the last pieces written against prose rather than
copied, because the export ships no markup for them. It is also where the design
system's colour choices met WCAG for the first time at interface sizes, which the
card and article layers had largely avoided by using ink on ground.

**Commands:**

```bash
# IN CONTAINER
php -l /var/www/the-abyss/wp-content/themes/the-abyss/single.php
php -l /var/www/the-abyss/wp-content/themes/the-abyss/searchform.php
```

**Result:** both clean.

**Five measured divergences, all in the `AGENTS.md` ledger.** Four are one
number. Nothing legible sits on the accent `#ec3013` at interface sizes: against
the ground token it is 3.76:1, against ink 3.95:1, against pure white 4.20:1, all
short of the 4.5:1 WCAG 1.4.3 requires below 18.66px bold. So the primary button
fill, the ghost button label, the outline tag label, and the checked segmented
option all use `accent-700`, which measures 6.41:1. This is the readme's own
instruction rather than a departure from it: it calls the accent suitable for
icons, large text and interface chrome, and routes paragraph-size accent text to
`accent-700`.

The fifth is a different rule. Control borders at the divider token composite to
2.38:1 against the surface behind an input and 2.58:1 against the ground. WCAG
1.4.11 requires 3:1 for whatever visually identifies a control, and the border is
the only thing that does: the surface and ground tokens differ by 1.08:1, so the
fill cannot identify the field. `.abyss-input`, `.abyss-radio__dot`, and
`.abyss-seg` use `neutral-600` at 3.85:1. The divider token is unchanged
everywhere else, because the 2px section rules are decorative and identify no
component. A knock-on: the sheet's hover border is *lighter* than its resting
border, which would now step backwards, so hover moves down the same ramp
instead.

A sixth, smaller one: the sheet mutes `.field > label` at 70% and `.table th` at
60%. At 60% the table header is 4.24:1 at 11px, just short. Both now use 70%, so
the system carries one muted-label value rather than two differing by an
invisible amount.

**Q&A:**

*Why is the native radio input moved off-screen instead of hidden?*
`display: none` and `visibility: hidden` remove an element from the tab order and
from the accessibility tree, which is exactly what makes a hand-rolled radio
unusable. `position: absolute; opacity: 0` keeps the real control operable and
announced, and the visible dot is styled as its proxy. Focus has to be drawn on
the proxy, because the real input carries no pixels for a ring to sit on.

*Why does the search field id come from `wp_unique_id()`?* The form can render
more than once on a page. A hard-coded id would duplicate, and every duplicate
`for` attribute resolves to the *first* matching field, so the second form's
label would silently belong to the first form's input.

*Why is `.abyss-table` also bound to `.wp-block-table`?* The consumer is an
author inserting a table into a post, not markup this theme writes. Bound only to
the bare class it would have been correct and never once used. The block also
gets `overflow-x: auto`, so a wide table scrolls inside its own figure rather
than pushing the page sideways, which is what keeps the no-horizontal-scroll
promise honest without an `overflow-x: hidden` anywhere.

*Why implement a dialog a blog will never open?* To finish the class map, and
because writing a modal later under pressure is how one ends up with a default
focus ring and no Escape key. What is there is presentation only, and the comment
says so: anything that genuinely needs a modal should use the native `<dialog>`
with `showModal()`, which supplies focus trapping, Escape, and `aria-modal`.

#### Step 7b (part 3) — templates completed: page, 404, comments ✅

**Goal:** finish the template set. `page.php`, `404.php`, and `comments.php`,
plus the footer structure fix a rendered page exposed.

**Why it matters:** these are the templates a reader hits when something has gone
wrong or when they want to know who is writing. They are also where the theme
stops being a demonstration of the design system and starts being a site.

**Commands:**

```bash
# IN CONTAINER
for f in footer page 404; do php -l /var/www/the-abyss/wp-content/themes/the-abyss/$f.php; done
php -l /var/www/the-abyss/wp-content/themes/the-abyss/comments.php
```

**Result:** all clean. The 404 renders its heading, its explanation, the search
form, and a five-item recent-articles list.

**A structural bug found in a screenshot, not in the code.** The header's 2px
rule ran the full width of the viewport while the footer's stopped at 1440px.
`.site-header` is unconstrained and carries its border with `.site-header__inner`
holding the width cap, but `.site-footer` was doing both jobs on one element.
Added `.site-footer__inner` so both sides split the same way. Two rules of the
same weight disagreeing about where the page ends is what the design's "let the
grid show" is against.

**A bug caught while writing, before it shipped.** The cookie-consent checkbox
was given `.abyss-radio`. That class hides its native input with
`position: absolute; opacity: 0`, which is correct for the radio because a styled
`.abyss-radio__dot` stands in for it. There is no dot on a checkbox, so the
control would have been invisible and untickable beneath a label that looked
fine. Split out as `.abyss-check`, where the native control does its own drawing.
The class name described the intent while the implementation only made sense
alongside its partner element, which is the failure mode of a component library.

**Still not verified.** `.abyss-radio`, `.abyss-seg`, `.abyss-table`, the dialog,
and the entire comment thread and form have never rendered. `.abyss-btn` and
`.abyss-input` have rendered once each, in the search form. The `filemtime()`
cache-busting change has never been confirmed in page source. Recorded as a debt
in `PLAN.md` rather than left implied by an unqualified tick.

**Q&A:**

*Why is `page.php` not `single.php` with the dated parts deleted?* Because a page
is not a dated article. It carries no category kicker, no publication line, no
byline and no tags, and the difference is load-bearing rather than cosmetic: the
FTC and affiliate disclosures this project requires will render through this
template, and a disclosure stamped with an author byline and a review date would
misrepresent what it is. `page.php` also runs `wp_link_pages()`, which
`single.php` does not, so a page split with `<!--nextpage-->` does not dead-end
at part one.

*Why does `404.php` run its own `WP_Query`?* The main query on a 404 has already
run and found nothing, so the recent-posts list needs its own. It is followed by
`wp_reset_postdata()`, so `get_footer()` and anything hooked into it are not left
looking at the last item of that loop.

*Why was the comment website field removed?* It is the most abused field in
WordPress comments and it exists to collect a link, while this project commits to
controlling its outbound links. Removing it takes away the incentive rather than
fighting the symptom with moderation.

*Why style core's comment walker instead of replacing it?* The same reasoning as
binding `.abyss-table` to the core table block. A custom walker means maintaining
a copy of WordPress's markup forever, for a part of the page nobody redesigns.

#### Step 7c — compliance, structured data, and the gaps a real post exposed ✅

**Goal:** close the three live-site requirements that were theme work, then fix
what publishing an actual article revealed about them.

**Why it matters:** `PLAN.md` lists affiliate `rel` attributes, visible FTC and
Competition Bureau disclosures, and author and review-date schema as
requirements. Only the first existed, and it turned out to be wrong.

**Commands:**

```bash
# IN CONTAINER
php -l /var/www/the-abyss/wp-content/themes/the-abyss/functions.php
```

**Result:** clean. The disclosure rendered above the first paragraph of `?p=14`
with the accent rule, and the comment form rendered for a logged-in user.

**The correction that mattered.** The `rel` filter had been treating every
outbound link as an affiliate link, and the new disclosure inherited that test.
The AI Bubble post showed what it meant: citations to Yahoo Finance, The Hustle
and four numbered footnotes were all tagged `rel="sponsored nofollow"`, under an
affiliate disclosure, in an article that earns nothing. Both directions are
misstatements. Marking a citation `sponsored` discards the editorial signal the
link existed to send, and a disclosure that cries wolf trains readers to skip it
on the articles where it is true. Both now key off one list of monetised domains,
empty by default and set through the `the_abyss_affiliate_domains` filter, with
subdomain matching and a dot-boundary check so `amazon.com` covers
`www.amazon.com` but not `notamazon.com`.

**Three further gaps closed in the same pass**, none of which a browser check
would have surfaced:

1. `add_editor_style()` loaded `main.css` but not `fonts.css`. `main.css` asks
   for Archivo while the `@font-face` rules that define it live in `fonts.css`,
   so the editor was rendering every heading in a system fallback. An author
   composing against type the reader never sees is the exact failure editor
   styles exist to prevent.
2. `single.php` had no `wp_link_pages()`, so a long article split with
   `<!--nextpage-->` ended at part one with nothing saying there was more. Long
   comparisons are what this site publishes.
3. `the_posts_pagination()` had no styles at all. It is the blog's primary
   navigation past ten posts and was invisible only because three posts never
   trigger it. The current page is marked with the same inset accent rule the
   nav uses for `aria-current`, because colour alone would fail WCAG 1.4.1.

**Q&A:**

*Why is the affiliate domain list empty by default?* Because an empty list tags
nothing and discloses nothing, which is the correct behaviour for a site with no
programmes yet. The alternative default, "assume every outbound link is paid",
is what produced the misstatement above.

*Does a domain list not just move the discipline problem?* It moves it from once
per link to once per programme, which is a few lines a year against a decision on
every link an author ever writes. That is why it stays structural.

*Why does the JSON-LD carry no `Review` or `aggregateRating`?* An affiliate
comparison marked up as a rating is what Google's self-serving-review policy
prohibits, and the penalty is a manual action rather than a lost rich result.
`Article` claims only what the page actually is.

#### Step 7b (part 2) — the block editor content layer ✅

**Goal:** style the markup the block editor emits, not just the markup the theme
emits.

**Why it matters:** everything built up to here styled the theme's own templates.
Long-form editor content is the product, and a wide image, a caption, a float, a
pull quote, a code block, or a core button all arrived unstyled. The token sheet
says nothing about any of it, so the section is a documented gap held to the
readme's do-not list instead: zero radius, 2px rules rather than hairlines, flush
left, nothing decorated.

**Commands:**

```bash
# ON HOST
docker compose exec -T web bash -c \
  'for f in /var/www/the-abyss/wp-content/themes/the-abyss/*.php; do php -l "$f"; done'
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/
curl -s 'http://localhost:8080/?s=test' | grep -o 'Search results for:[^<]*'
```

**Verify:** `No syntax errors detected` across all nine templates. Home 200,
`/no-such-page/` 404, and `/?s=test` renders *Search results for: test*, which
closes the search-heading fix that had been carried as unproven. Custom-property
sweep finds no undefined `--abyss-*` reference; braces balance at 169/169.

**Q&A:**

*Why does `alignfull` stop at 1440px instead of running edge to edge?* Modernist
opens with "nothing floats and nothing is decorated" and asks that the grid stay
visible. A true viewport bleed erases the column the rest of the page is built
on. Recorded as an interpretation of the system rather than an oversight.

*Why is grayscale not forced on every content image?* The readme's rule is about
photography. Forcing it would also drain the charts and screenshots a finance
article runs on, so it stays opt-in through the `.grayscale` wrapper the author
applies.

*How does full-width bleed avoid the horizontal scrollbar this stylesheet
refuses to hide?* `--abyss-bleed` derives from `(100vw - 100%) / 2` less the
container's padding. `100vw` includes the scrollbar, so the expression
over-reads by about half a scrollbar width, and subtracting the full 24px of
padding leaves more slack than that.

#### Step 8 — WP-CLI and the plugin baseline ✅

**Goal:** get WP-CLI into the image and install the launch plugin set as a
reproducible list rather than a sequence of clicks in wp-admin.

**Why it matters:** the plugin set is part of the build, so it belongs in a
command that can be replayed on the droplet. WP-CLI comes from the official phar
rather than apt, because Debian's package lags and the phar is what upstream
supports. The same two lines work unchanged on the droplet.

**Commands:**

```bash
# IN CONTAINER
wp --path=/var/www/the-abyss plugin install \
  seo-by-rank-math fluent-smtp updraftplus redirection \
  limit-login-attempts-reloaded complianz-gdpr thirstyaffiliates --activate

wp --path=/var/www/the-abyss plugin install wp-super-cache google-site-kit
wp --path=/var/www/the-abyss plugin list --fields=name,status,version
```

Run as `www-data`, never as root, so plugin files land with the ownership Apache
and php-fpm already use.

**Verify:** WordPress 7.0.2. Nine plugins installed, seven active; `wp-super-cache`
and `google-site-kit` deliberately left inactive, the first because a page cache
in development hides template changes, the second because it does nothing until
its Google OAuth is completed against a real domain. Home and a single post both
still return 200 after activation.

**Q&A:**

*Why is there no newsletter plugin in the list?* Because the sending question has
to be settled first. DigitalOcean blocks outbound port 25 on droplets by default,
and even with it open, bulk mail from a droplet IP with no sending reputation
lands in spam. Newsletter delivery goes through an external provider regardless
of which plugin front-ends it, so the provider is the decision and the plugin
follows from it.

*Why FluentSMTP when nothing sends mail yet?* Transactional mail fails silently.
Password resets, comment notifications, and form mail all disappear with no error
in the log, and the failure is usually discovered by a locked-out user rather
than by a check.

*Why not Wordfence or Jetpack?* Both are heavy, and this droplet already serves a
live portfolio site whose resources they would share. Login hardening is covered
by Limit Login Attempts Reloaded plus fail2ban at the OS layer.

#### Step 8c — closing the verification gap ✅

**Goal:** put every component family in front of a browser, rather than
inferring from a 200 response that it looks right.

**Why it matters:** the theme had a recorded debt — `.abyss-radio`,
`.abyss-seg`, `.abyss-table`, the dialog, and the whole comment thread had never
rendered. `php -l` and an HTTP 200 prove a page does not fatal. They prove
nothing about contrast, alignment, or whether an image fills the box it was
given.

**Commands:**

```bash
# IN CONTAINER
wp --path=/var/www/the-abyss post create /tmp/sink.html \
   --post_title="Block layer kitchen sink" --post_status=publish --porcelain
wp --path=/var/www/the-abyss post create /tmp/comp.html --post_type=page \
   --post_title="Component check" --post_status=publish --porcelain

# ON HOST
firefox --headless --screenshot sink-1440.png --window-size=1440,3000 \
        "http://localhost:8080/?p=23"
```

Two fixtures: a post exercising every block type, and a page carrying the
families no template emits. Screenshotted at 1440, 800, and 380.

**Verify:** all four button states, tags, fields, radio, segmented control,
table, cards with elevation, dialog, threaded comments and form, floats stacking
below 600px, and the unbreakable-token wrap all render correctly. Equal-height
cards confirmed visually. No horizontal scrollbar at any of the three widths. No
PHP notices in `debug.log` during any render.

Four defects found by looking, all fixed and re-screenshotted:

1. `.wp-block-button__link` used the raw accent, reintroducing the 3.76:1
   contrast failure `.abyss-btn--primary` already documents a divergence for.
   Now accent-700 (6.41:1).
2. `alignwide` / `alignfull` images did not fill their figure, so a 747px image
   sat flush left in a 1440px box.
3. The code block was commented as scrolling; it wraps, because core ships
   `white-space: pre-wrap`.
4. `single.php` printed a trailing "by" when a post had no author.

**Q&A:**

*Why headless Firefox rather than asking for a manual check?* It is repeatable,
it covers three widths in one command, and it produces an artefact that can be
compared after a change. The manual drag test took two phases to actually
happen; this took one command.

*Do the fixtures stay?* Yes, as regression fixtures. Remove them before launch
with `wp post delete 23 24 --force`.

#### Step 8d — the provisioning script ✅

**Goal:** one script that turns a fresh Ubuntu 24.04 host into this stack.

**Why it matters:** this is why the container was built by hand. Every step was
learned once, in a place that was free to break, so the droplet script could be
written from something proven rather than from documentation. `scripts/plugins.txt`
is read by the script so the two environments cannot drift.

**Commands:**

```bash
# ON HOST
bash -n scripts/provision.sh                      # syntax
while read -r l <&3; do ...; done 3< scripts/plugins.txt   # parser dry run
```

**Verify:** syntax clean. The plugin-list parser, run standalone against the real
file, yields seven activate and two install-only — matching the local install
exactly. The `ServerName` substitution against `docker/the-abyss.conf` produces a
correct vhost. **Not yet run on a host**, so it is written and checked, not
proven.

**Q&A:**

*Where do the passwords come from?* Silent `read -rsp` prompts. Never an
argument, never a default, never in the file — so they stay out of the shell
history and the process list. Same tier-0 reason the container entrypoint refuses
to generate `wp-config.php`.

*What stops it damaging a site already on the host?* It refuses to touch a
`/var/www/the-abyss` that is not a WordPress install, and it never disables a
vhost it did not create — it warns about them instead.

*Why does it not run certbot?* Certbot burns a Let's Encrypt rate limit if DNS
does not already resolve to the host, and pointing DNS is an outward-facing
action that belongs to the human. The script prints the remaining steps instead.

*Why one `while read` on file descriptor 3?* `wp` inherits stdin. A plain
`while read; done < file` lets the first `wp plugin install` swallow the rest of
the list, so one plugin installs and the others are silently skipped.

#### Step 7e — finishing the theme ✅

**Goal:** audit for features the theme declares but never renders, then close
everything the site needs before it can launch.

**Why it matters:** a declared feature that does nothing is worse than an absent
one, because it looks available in wp-admin and fails silently. Three of the six
gaps below were exactly that shape.

**Commands:**

```bash
# ON HOST — the audit that found them
for t in custom-logo the_custom_logo comment-reply "'footer'" wp_nav_menu; do
  printf '%-18s functions:%s header:%s footer:%s\n' "$t" \
    "$(grep -c "$t" theme/functions.php)" \
    "$(grep -c "$t" theme/header.php)" "$(grep -c "$t" theme/footer.php)"
done

# IN CONTAINER
for f in theme/*.php theme/template-parts/*.php; do php -l "$f"; done
```

**Verify:** all templates lint clean; CSS braces balance at 188/188; no undefined
`--abyss-*` reference. Screenshotted at 1100px and 380px: author box, related
posts, newsletter form, and the footer menu all render, and all four hold at
narrow width with no overflow.

Six gaps closed:

1. `comment-reply` never enqueued — threaded replies were filed as new top-level
   comments.
2. `custom-logo` declared, `the_custom_logo()` never called.
3. `footer` menu location registered since step 6, never rendered.
4. No author box, though `Article` schema already claimed an author.
5. No related posts.
6. No newsletter signup, on a newsletter-led site.

One defect found by screenshot and fixed: `.abyss-grid` used `auto-fit`, so a
related row with a single post collapsed the empty tracks and stretched that one
card across the whole 960px column with a 420px-tall thumbnail. Now `auto-fill`,
so a cell is the same width whether the row holds one card or three.

**Q&A:**

*Why does the newsletter form render nothing until an endpoint is configured?*
Because a signup form that posts nowhere loses subscribers without any error.
The provider is still undecided (step 8c), so the theme ships the markup and
takes the endpoint from the `the_abyss_newsletter_action` filter or a
`THE_ABYSS_NEWSLETTER_ACTION` constant, and stays silent until one exists.

*Why does the form post directly to the provider rather than through WordPress?*
Subscriber email addresses then never enter this database. That is one fewer
store to secure, to back up, and to answer for under CASL and GDPR.

*Why related posts by category rather than by tag?* Every post has a category and
tags are optional, so tags would leave many posts with no related row at all.

*Why did `wp config set` fail when adding the newsletter constant?* The
container's `wp-config.php` was written by hand at step 4 and has no "That's all,
stop editing" anchor for WP-CLI to insert before. Not an issue for the droplet:
`provision.sh` creates that file with `wp config create`, which emits the anchor.
Verified locally through the filter instead, which is the path an ESP plugin
would use anyway.

#### Step 8e — provision.sh proven in a throwaway container ✅

**Goal:** run `scripts/provision.sh` end to end, from bare Ubuntu to a working
site, before it is ever pointed at an EC2 instance.

**Why it matters:** the script had never executed. Not once, anywhere. Debugging
it on EC2 would have meant debugging it alongside security groups, Elastic IPs,
and IMDS, with a relaunch as the cost of each mistake. A container tests it for
free.

**Commands:**

```bash
# ON HOST — bare Ubuntu with only the packages provision.sh installs
docker build -t abyss-provtest-base <scratch>/provtest
docker run -d --name abyss-provtest -v "$PWD:/repo:ro" abyss-provtest-base

docker exec abyss-provtest bash -lc '
  cp -r /repo /work && chmod -R u+w /work
  printf "…\n…\n" | SITE_DOMAIN=test.local ADMIN_USER=casey ADMIN_EMAIL=a@b.com \
    REPO_DIR=/work bash /work/scripts/provision.sh'
```

**Verify:** WordPress 7.0.2 installed, `abyss-theme` active, all nine plugins in
their intended states (seven active, `wp-super-cache` and `google-site-kit`
inactive), permalinks `/%category%/%postname%/` with a real `.htaccess`, and
`curl` returning 200 with `<title>The-abyss`. Swap and firewall reported
`SKIPPED` rather than silently passing.

**Five defects found, all fixed:**

1. **`/var/www/the-abyss` created as root**, so `wp core download` running as
   `www-data` died with "is not writable by current user". The `chown` came
   after the download. This would have failed identically on EC2.
2. **`mysql` and `php-fpm` were assumed to be running.** True under systemd,
   false anywhere else, and the symptom was a socket error that reads like a
   permissions problem. Now started explicitly, which is a no-op on EC2.
3. **`/run/mysqld` left `0700`** without systemd-tmpfiles, so `www-data` could
   not traverse it to reach the socket. WordPress reported "Error establishing a
   database connection" while the same credentials worked from a root shell.
   Fixed only on no-systemd hosts, so it never touches what systemd owns.
4. **`wp rewrite flush --hard` wrote empty `.htaccess` markers**, because WP-CLI
   cannot detect Apache from the command line. Every URL except the home page
   would have 404ed on a fresh host, with an `.htaccess` that looks right at a
   glance. Now written explicitly.
5. **WP-CLI cache warning on every call**, because `www-data` cannot write its
   own home. Silenced at the source by creating the directory with the right
   owner.

**Two guards fired correctly during the run**, which is worth recording as
working rather than only as written: the script refused a `/var/www/the-abyss`
that existed without WordPress in it, and refused to continue when the database
existed but `wp-config.php` did not, rather than guessing a password.

**Q&A:**

*Why not test on EC2 directly?* Because the first execution found five defects,
three of which would have failed the run outright. Each would have been diagnosed
on a live instance alongside unfamiliar AWS surface area.

*What does the container still not cover?* Service management under systemd, the
firewall, swap, and certbot. Those stay untested until EC2 and are reported as
`SKIPPED` rather than passed.

*Why did `mysql-server` install fine in a built image but fail via `docker exec`?*
Its post-install script starts the server and then cannot stop it when
`policy-rc.d` denies the call. During an image build the sequence completes; run
interactively it leaves the package half-configured. The test image installs the
packages at build time for exactly that reason.

#### Step 7f — self-hosted fonts, and the article page verified ✅

**Goal:** stop fetching webfonts from a third party, and put the new theme's
single-post view in front of a browser for the first time.

**Why it matters:** `PLAN.md` has listed "self-hosted fonts and no unnecessary
third-party font requests" as a live-site requirement since the beginning, and
`abyss-theme` arrived loading both families from the Google Fonts CDN. That is
three problems in one: the endpoint receives every visitor's IP address, which is
a consent question on a site that ships Complianz precisely because consent is
being taken seriously; it costs a DNS lookup, connection, and TLS handshake to
another origin before any render-blocking CSS arrives; and the type only renders
while someone else's CDN is up.

**Commands:**

```bash
# ON HOST
curl -s http://localhost:8080/ | grep -oE 'https?://[a-z0-9.-]*(googleapis|gstatic)[^"]*'
curl -s -o /dev/null -w '%{http_code} %{size_download}\n' \
  http://localhost:8080/wp-content/themes/abyss-theme/assets/fonts/plus-jakarta-sans-normal-latin.woff2
```

**Verify:** the grep returns nothing — no third-party font request remains. The
woff2 serves 200 at 27,348 bytes, and `fonts.css` carries a `filemtime` version.
Both families render correctly in a screenshot of a single post.

Both are variable fonts, so one file per style covers the whole weight range
rather than one per weight: six files, ~226KB, split by `unicode-range` so an
English page never downloads the latin-ext subsets.

**Two defects found by screenshotting the article page, which had never been
looked at on this theme:**

1. The ported disclosure reused `.disclose`, which is the theme's site-wide
   footer bar. That class puts all its padding on an inner `.disclose__in`
   element the markup does not have, so the notice rendered as a tinted block
   with no spacing, running straight into the article's first sentence. It now
   has its own `.art-disclose`, marked with the accent rule rather than a tinted
   panel so it reads as editorial apparatus rather than as an ad.
2. **The disclosure was being baked into the excerpt.** `abyss_dek()` calls
   `get_the_excerpt()`, which runs `the_content` through `wp_trim_excerpt()` and
   then strips tags — so the notice was flattened to plain text and glued to the
   front of the dek, directly under the headline. The guard inherited from the
   previous theme (`is_singular`, `in_the_loop`, `is_main_query`) was written for
   a theme that never generated an excerpt on a singular page. Now also guarded
   with `doing_filter( 'get_the_excerpt' )`.

**Q&A:**

*Why self-host rather than keep the CDN and add a consent gate?* Because the
requirement predates the question, and a gate would mean text that does not
render until someone clicks. Self-hosting removes the problem instead of managing
it.

*Does shared CDN caching not make Google Fonts faster?* It did until browsers
partitioned their HTTP caches by origin. A visitor now downloads the font again
on your site regardless, so the third-party round trip buys nothing.

#### Step 7g — block editor layer for abyss-theme, and one disclosure ✅

**Goal:** finish the theme's content layer and remove a duplicate disclosure.

**Why it matters:** `abyss-theme` declares `add_theme_support( 'align-wide' )` but
shipped no CSS for it, so `alignwide` and `alignfull` rendered at the 44rem prose
measure. The support was a claim the front end did not honour. Floats, captions,
galleries, separators, pull quotes, and core buttons were all unstyled too.

**Verify:** alignwide now drops the prose cap and fills the article column, and
captions render as chrome flush left under the image. Every page type still
returns its expected status (`/` 200, `/articles/` 200, `/category/finance/` 200,
`/?s=broker` 200, `/nope/` 404), with no PHP notices during render.

Both alignments fill the column rather than bleeding to the viewport, and that is
deliberate: on `single.php` the prose shares a grid row with a sticky sidebar
rail, so there is no viewport to bleed into without running underneath it.

**The duplicate disclosure is resolved.** `single.php` rendered its own notice
from the `_abyss_post_affiliate` checkbox while `inc/compliance.php` prepended
another from the monetised-domain list. Ticking the box produced two disclosures,
differently worded, one above the other. There is now one piece of markup in
`abyss_compliance_disclosure_markup()` with two triggers: a link to a monetised
domain, or the author's checkbox. The checkbox still earns its place, because it
covers the case the domain list cannot see — a post monetised by a discount code
rather than by a link.

**Q&A:**

*Why is the figure radius moved onto the image?* `.prose figure` had
`border-radius` with `overflow: hidden`, which clipped any caption sitting inside
the figure. The radius belongs to the image; the wrapper has to stay visible.

#### Step 8f — plugin dry run, four configured ✅

**Goal:** configure the plugins that can be configured without a domain, and
remove the ones that cannot.

**Why it matters:** an installed, unconfigured plugin is a surface with no
benefit. Five of the nine are blocked on a real domain — Complianz bakes the URL
and region into its consent config, FluentSMTP needs a verified sending domain,
Site Kit needs Google OAuth against a live host, WP Super Cache hides template
changes in development, and UpdraftPlus without remote storage writes backups to
the same disk it is meant to protect. All five were removed locally.
`scripts/plugins.txt` still lists all nine, because it drives `provision.sh`.

**Commands:**

```bash
# IN CONTAINER
wp --path=/var/www/the-abyss option update ta_link_prefix go
wp --path=/var/www/the-abyss option update limit_login_allowed_retries 4
wp --path=/var/www/the-abyss option update rank_math_modules --format=json \
  '["link-counter","seo-analysis","sitemap"]'
```

**Verify:**

Link cloaking works end to end — a `thirstylink` published at `/go/test-broker/`
returns `302` to its destination, so the prefix change took effect and the
rewrite rules registered.

Rank Math's `rich-snippet` module is off and the article page now emits exactly
**one** `"@type":"Article"` block. That was the misconfiguration most likely to
bite: the theme already emits `Article` JSON-LD from `inc/compliance.php`, and
Google treats duplicate schema as an error rather than ignoring it.

Limit Login is set to 4 retries, a 20-minute lockout, 3 lockouts before the long
24-hour block, and IP anonymisation on.

**Two findings, one expected and one not:**

*Sitemaps are gated on `blog_public`, which is correct.* With `blog_public = 0`
every sitemap URL 404s and every page carries `noindex, nofollow`. Confirmed by
flipping it to `1`, re-testing, and restoring it to `0` — this is the switch that
gets flipped at launch, and it is per-environment.

*Rank Math is not serving its own sitemap.* With indexing on, `/wp-sitemap.xml`
(WordPress core's) returns 200 while Rank Math's `/sitemap_index.xml` still 404s,
and `rank_math_options_general` is empty. **Its setup wizard has not been run**,
and the parts of Rank Math that matter most for SEO — titles, canonicals,
sitemaps — come from that wizard rather than from options that can be set
blindly from the command line. It needs a pass in wp-admin.

**Q&A:**

*Why disable Rank Math's schema module rather than the theme's JSON-LD?* The
theme's is deliberately conservative: `Article` only, `dateModified` only when
the post was really revised, and no `aggregateRating` on affiliate comparisons,
which is what Google's self-serving-review policy prohibits. Rank Math would
happily offer the rating markup that earns a manual action.

*Why 302 and not 301 on cloaked links?* A 301 tells a search engine the resource
has permanently moved to the merchant, which is not what an affiliate link means.
302 is ThirstyAffiliates' default and the correct signal.
