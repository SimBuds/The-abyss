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

**Hosting is decided: the existing DigitalOcean droplet.** It already serves a
live portfolio site, which makes every mutating step there risky tier and puts a
read-only inventory first. AWS is a deferred candidate with its completed work
banked. Both are in [`PLAN.md`](PLAN.md#hosting-the-existing-digitalocean-droplet).

The theme is built on the Modernist design system, whose token sheet in
`design/_ds/` is the locked source of truth for every colour, font, and spacing
value. Approved divergences from it are recorded in [`AGENTS.md`](AGENTS.md).

The lifecycle is local, then staging, then production. Going live means
connecting the domain, TLS, and Cloudflare. Staging is never indexed and never
holds real credentials.

## Current state

Build steps 0 to 7a and step 8 are complete. WordPress runs locally at
`http://localhost:8080` on Apache with `php-fpm` and MySQL inside the container,
the whole stack is codified in `docker/Dockerfile`, and the `the-abyss` theme is
active. A single post renders through `single.php` with the Modernist tokens
verified in the page, not just in the files.

Next is step 7b, the component layer, which opens with the responsive pass that
no CSS in this theme has had yet. Then step 9, the read-only droplet inventory.
The full roadmap and its checkboxes are in [`PLAN.md`](PLAN.md#status).

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
