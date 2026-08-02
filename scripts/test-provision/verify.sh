#!/usr/bin/env bash
# Read-only checks against whatever provision.sh actually produced.
# Prints PASS/FAIL per check and exits non-zero if any failed.

FAILS=0

# Each Docker RUN is a fresh container, so nothing provision.sh started is still
# running here. Bring the services up before checking anything that needs them.
# A real host has systemd and would already have them running.
FPM_CONF="$(basename "$(ls /etc/apache2/conf-available/php*-fpm.conf | head -n1)" .conf)"

# Docker freezes /var/run into the image layer, so the pid files written when
# provision.sh started these services are still here, pointing at pids that
# belong to unrelated processes in this new container. The init scripts read
# them, conclude the service is already up, and exit 0 without starting
# anything. A real host has /run on tmpfs and boots with it empty, so this is
# purely an artifact of testing in a container. Cleared, not worked around.
rm -f /var/run/apache2/apache2.pid /run/php/*.pid /var/run/mysqld/*.pid

service mysql start        >/dev/null 2>&1 || true
[ -d /run/mysqld ] && chmod 0755 /run/mysqld
service "$FPM_CONF" start  >/dev/null 2>&1 || true
service apache2 start      >/dev/null 2>&1 || true
sleep 4

ck() {
	local label="$1" expected="$2" actual="$3"
	if [ "$expected" = "$actual" ]; then
		printf '  PASS  %-42s %s\n' "$label" "$actual"
	else
		printf '  FAIL  %-42s got=%s want=%s\n' "$label" "$actual" "$expected"
		FAILS=$((FAILS+1))
	fi
}

W() { sudo -u www-data -- wp --path=/var/www/the-abyss "$@" 2>/dev/null; }

echo "== WordPress =="
ck "core installed"        "1"            "$(W core is-installed && echo 1 || echo 0)"
ck "active theme"          "abyss-theme"  "$(W theme list --status=active --field=name)"
ck "permalink structure"   "/%postname%/" "$(W option get permalink_structure)"
ck "WP_HOME"               "https://abyss.test" "$(W eval 'echo WP_HOME;')"
ck "DISALLOW_FILE_EDIT"    "1"            "$(W eval 'echo DISALLOW_FILE_EDIT ? 1 : 0;')"

echo "== Plugins (expected from scripts/plugins.txt) =="
while read -r line <&3; do
	line="${line%%#*}"; line="$(echo "$line" | xargs || true)"
	[ -n "$line" ] || continue
	slug="${line%%:*}"
	want_status="active"
	[ "$line" = "${slug}:inactive" ] && want_status="inactive"
	got="$(W plugin get "$slug" --field=status)"
	ck "$slug" "$want_status" "${got:-MISSING}"
done 3< /repo/scripts/plugins.txt

echo "== Filesystem =="
ck "docroot owner"         "www-data"     "$(stat -c %U /var/www/the-abyss)"
ck "theme is a symlink"    "1"            "$([ -L /var/www/the-abyss/wp-content/themes/abyss-theme ] && echo 1 || echo 0)"
ck "theme symlink resolves" "1"           "$([ -f /var/www/the-abyss/wp-content/themes/abyss-theme/style.css ] && echo 1 || echo 0)"
ck ".htaccess has rules"   "1"            "$(grep -qc 'RewriteEngine On' /var/www/the-abyss/.htaccess 2>/dev/null && echo 1 || echo 0)"
ck "wp-config.php present" "1"            "$([ -f /var/www/the-abyss/wp-config.php ] && echo 1 || echo 0)"
ck "wp-config.php owner"   "www-data"     "$(stat -c %U /var/www/the-abyss/wp-config.php)"
printf '  INFO  %-42s %s\n' "wp-config.php mode" "$(stat -c %a /var/www/the-abyss/wp-config.php)"

echo "== Apache =="
ck "configtest"            "Syntax OK"    "$(apache2ctl configtest 2>&1 | tail -n1)"
ck "the-abyss enabled"     "1"            "$([ -L /etc/apache2/sites-enabled/the-abyss.conf ] && echo 1 || echo 0)"
ck "000-default disabled"  "0"            "$([ -e /etc/apache2/sites-enabled/000-default.conf ] && echo 1 || echo 0)"
ck "ServerName substituted" "1"           "$(grep -qc 'ServerName abyss.test' /etc/apache2/sites-available/the-abyss.conf && echo 1 || echo 0)"
ck "ServerAlias substituted" "1"          "$(grep -qc 'ServerAlias www.abyss.test' /etc/apache2/sites-available/the-abyss.conf && echo 1 || echo 0)"
for m in proxy_fcgi setenvif rewrite headers; do
	ck "mod_$m enabled" "1" "$(apache2ctl -M 2>/dev/null | grep -qc "${m}_module" && echo 1 || echo 0)"
done

echo "== HTTP =="
H() { curl -s -o /dev/null -w '%{http_code}' -H 'Host: abyss.test' "http://127.0.0.1$1"; }
# A 200 here proves the whole chain: Apache routed to PHP-FPM, PHP ran, and
# WordPress bootstrapped against the database.
ck "GET /"                 "200"          "$(H /)"
# These two are the pretty-permalink test. Without a working .htaccess rewrite
# they 404 while the home page keeps returning 200, which is exactly the failure
# mode the explicit .htaccess write in provision.sh exists to prevent.
ck "GET /hello-world/ (post permalink)"  "200" "$(H /hello-world/)"
ck "GET /sample-page/ (page permalink)"  "200" "$(H /sample-page/)"
ck "GET /definitely-not-here/ is a 404"  "404" "$(H /definitely-not-here/)"
# The theme is served through the symlink, so this fails if the link dangles.
assets="$(curl -s -H 'Host: abyss.test' http://127.0.0.1/ \
	| grep -oc 'wp-content/themes/abyss-theme/' || true)"
ck "theme assets served via symlink" "1" "$([ "${assets:-0}" -gt 0 ] && echo 1 || echo 0)"

echo
if [ "$FAILS" -eq 0 ]; then
	echo "ALL CHECKS PASSED"
else
	echo "$FAILS CHECK(S) FAILED"
fi
exit "$FAILS"
