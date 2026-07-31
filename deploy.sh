#!/usr/bin/env bash
#
# Mirror site/ onto the CoreWave Credit hosting over FTP, then check the result.
#
# The password is never passed on the command line and never printed: curl reads
# it from ~/.netrc, so it stays out of your shell history, out of `ps`, and out
# of any transcript. Set that file up once:
#
#   printf 'machine 216.172.171.98\n  login deploy@corewavecredit.com\n  password YOUR_PASSWORD\n' >> ~/.netrc
#   chmod 600 ~/.netrc
#
# Usage:
#   ./deploy.sh probe     show where the FTP login lands, and what's there
#   ./deploy.sh dry-run   list what would be uploaded, upload nothing
#   ./deploy.sh           upload, then verify over HTTPS
#   ./deploy.sh verify    just re-run the checks against the live site
#
# Override the defaults with environment variables if they're wrong:
#   CW_FTP_HOST=ftp.corewavecredit.com CW_FTP_ROOT=. ./deploy.sh
#
set -euo pipefail

HOST="${CW_FTP_HOST:-216.172.171.98}"
ROOT="${CW_FTP_ROOT:-public_html}"          # set to "." if the login already lands in public_html
SITE="$(cd "$(dirname "$0")" && pwd)/site"
BASE_URL="${CW_BASE_URL:-https://corewavecredit.com}"

# TLS is required, not merely preferred: plain FTP sends the password in the
# clear over the wire. cPanel hosts run Pure-FTPd with AUTH TLS, so this should
# just work. If your host genuinely can't do it, CW_ALLOW_PLAINTEXT=1 downgrades
# — but then treat that password as disposable and delete the account after.
if [ "${CW_ALLOW_PLAINTEXT:-0}" = "1" ]; then
    echo "warning: uploading over PLAINTEXT FTP — your password crosses the network unencrypted" >&2
    CURL=(curl --netrc --connect-timeout 20 --max-time 120 -sS)
else
    CURL=(curl --netrc --ftp-ssl-reqd --connect-timeout 20 --max-time 120 -sS)
fi

die() { echo "error: $*" >&2; exit 1; }

[ -d "$SITE" ] || die "no site/ directory next to this script"

# Only the commands that actually talk to FTP need credentials; dry-run and
# verify are useful without them.
need_credentials() {
    [ -f "$HOME/.netrc" ] || die "no ~/.netrc — see the header of this script for the one-line setup"
    if [ "$(stat -c %a "$HOME/.netrc")" != "600" ]; then
        echo "warning: ~/.netrc was not chmod 600; fixing" >&2
        chmod 600 "$HOME/.netrc"
    fi
    grep -q "$HOST" "$HOME/.netrc" || die "~/.netrc has no entry for $HOST"
}

remote_url() {   # remote_url <relative path>
    if [ "$ROOT" = "." ] || [ -z "$ROOT" ]; then
        printf 'ftp://%s/%s' "$HOST" "$1"
    else
        printf 'ftp://%s/%s/%s' "$HOST" "$ROOT" "$1"
    fi
}

# .htaccess goes last: a half-uploaded one can 500 the whole site, and this way
# that window is as small as possible.
files() {
    ( cd "$SITE" && find . -type f ! -name '.htaccess' | sed 's|^\./||' | sort
      cd "$SITE" && find . -type f -name '.htaccess' | sed 's|^\./||' )
}

case "${1:-deploy}" in

probe)
    need_credentials
    echo "Listing $(remote_url '') ..."
    "${CURL[@]}" "$(remote_url '')" || die "FTP login or listing failed.
  - wrong password?           check ~/.netrc
  - account deleted?          cPanel -> FTP Accounts, recreate it
  - no TLS on this host?      re-run with CW_ALLOW_PLAINTEXT=1 (last resort)"
    echo
    echo "If you don't see index.html above, the login lands somewhere else."
    echo "Re-run with the right root, e.g.:  CW_FTP_ROOT=. ./deploy.sh probe"
    ;;

dry-run)
    echo "Would upload to $(remote_url '<file>'):"
    files | sed 's/^/  /'
    echo
    files | wc -l | xargs echo "Total files:"
    ;;

verify)
    ;&                                      # fall through to the checks below

deploy)
    if [ "${1:-deploy}" = "deploy" ]; then
        need_credentials
        n=0
        while IFS= read -r rel; do
            n=$((n + 1))
            printf '  %-42s' "$rel"
            if "${CURL[@]}" --ftp-create-dirs -T "$SITE/$rel" "$(remote_url "$rel")"; then
                echo "ok"
            else
                echo "FAILED"
                die "upload stopped at $rel — nothing after it was sent"
            fi
        done < <(files)
        echo "Uploaded $n files."
        echo
    fi

    echo "Checking the live site:"
    fail=0
    check() {  # check <path> <expected code> <label>
        code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 25 "$BASE_URL/$1" || echo 000)
        if [ "$code" = "$2" ]; then
            printf '  %-28s %s  %s\n' "$1" "$code" "$3"
        else
            printf '  %-28s %s  %s  <-- expected %s\n' "$1" "$code" "$3" "$2"
            fail=1
        fi
    }
    check ""                  200 "home"
    check get-started.html    200 "enrolment wizard"
    check thank-you.html      200 "PayPal return page"
    check assets/js/enroll.js 200 "wizard + PayPal button"
    check enroll.php          405 "signup handler (405 = alive, refusing GET)"
    check cw-config.php       403 "config blocked from the web"
    check nope-not-here.html  404 "custom 404"

    echo
    hdr=$(curl -sSI --max-time 25 "$BASE_URL/get-started.html" | tr -d '\r')
    if grep -qi 'permissions-policy:.*paypal' <<<"$hdr"; then
        echo "  Permissions-Policy allows PayPal — good"
    else
        echo "  Permissions-Policy does NOT mention PayPal — .htaccess didn't upload."
        echo "  The PayPal button may not render. cPanel hides dotfiles: turn on"
        echo "  Settings -> Show Hidden Files to confirm .htaccess is in public_html."
        fail=1
    fi

    [ "$fail" = 0 ] && echo && echo "All checks passed."
    exit "$fail"
    ;;

*)
    die "unknown command '$1' — try: probe | dry-run | deploy | verify"
    ;;
esac
