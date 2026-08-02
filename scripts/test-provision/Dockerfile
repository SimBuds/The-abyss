# A throwaway stand-in for a freshly created Ubuntu 24.04 host.
#
# Deliberately bare: only what a provider image already has (ca-certificates,
# curl, sudo, python3). Everything else must come from provision.sh, or the
# test is not testing provision.sh.
FROM ubuntu:24.04

RUN apt-get update -qq \
 && apt-get install -y -qq --no-install-recommends ca-certificates curl sudo python3 \
 && rm -rf /var/lib/apt/lists/*

COPY repo /repo
COPY drive.py /drive.py
COPY verify.sh /verify.sh

# Run 1: the first-ever provision of an empty host.
RUN python3 /drive.py

# Run 2: the same script again, unchanged, on the host it just built.
# This is the redeploy case. It must not prompt, must not duplicate, must not
# fail, and must leave the host in the same state.
RUN python3 /drive.py

RUN bash /verify.sh
