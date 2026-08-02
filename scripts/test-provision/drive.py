#!/usr/bin/env python3
"""Run provision.sh under a pty so its silent password prompts behave as they
do for a human at an SSH session.

The passwords are generated here, in the container, and never leave it: they
are not arguments, not environment, not echoed, and not written to disk.
"""
import os
import pty
import secrets
import sys

password = secrets.token_urlsafe(18)

env = dict(
    os.environ,
    SITE_DOMAIN="abyss.test",
    SITE_TITLE="The-abyss",
    ADMIN_USER="casey",
    ADMIN_EMAIL="casey@abyss.test",
    REPO_DIR="/repo",
    DEBIAN_FRONTEND="noninteractive",
    TERM="dumb",
)

pid, fd = pty.fork()
if pid == 0:
    os.execvpe("bash", ["bash", "/repo/scripts/provision.sh"], env)

buf = b""
answered = 0
while True:
    try:
        data = os.read(fd, 4096)
    except OSError:
        break
    if not data:
        break
    sys.stdout.buffer.write(data)
    sys.stdout.buffer.flush()
    buf += data
    # A silent read leaves the cursor sitting after "...': " with no newline.
    if b"assword" in buf and buf.endswith(b": "):
        os.write(fd, (password + "\n").encode())
        answered += 1
        buf = b""

_, status = os.waitpid(pid, 0)
code = os.waitstatus_to_exitcode(status)
print(f"\n[drive] answered {answered} password prompt(s); provision.sh exit={code}")
sys.exit(code)
