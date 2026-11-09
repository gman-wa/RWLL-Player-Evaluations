#!/usr/bin/env bash
# Local dev on Apple containers (https://github.com/apple/container)
#
#   scripts/local-dev.sh up        build the image if needed and start the app on http://localhost:8082
#   scripts/local-dev.sh down      stop and remove the app container
#   scripts/local-dev.sh build     rebuild the image (Dockerfile, docker/*, composer.json changes)
#   scripts/local-dev.sh logs      follow the app's logs
#   scripts/local-dev.sh shell     sh inside the app container
#   scripts/local-dev.sh db-user   one time: create the RWLL table (db/schema.sql) and the app's db user from local.env (asks for the mariadb root password)
#   scripts/local-dev.sh sql       mariadb client on RWLL as the app user
#   scripts/local-dev.sh status    app and database container IPs
#
# The database is the RWLL schema in Football-NG's MariaDB container (football-db) - start that stack first.
# The project directory is mounted into the app container, so PHP, CSS and JS edits are live.
set -euo pipefail
cd "$(dirname "$0")/.."

APP=rwll-evals
APP_IMAGE=rwll-evals:local
DB=${DB_CONTAINER:-football-db}
ENV_FILE=local.env
APP_PORT=${APP_PORT:-8082}
CONTAINER_APP_ROOT=${CONTAINER_APP_ROOT:-/Users/Shared/container}

ip_of() {
    container inspect "$1" | python3 -c '
import json, sys
nets = json.load(sys.stdin)[0].get("status", {}).get("networks", [])
print(nets[0]["ipv4Address"].split("/")[0] if nets else "")'
}

exists() { container inspect "$1" >/dev/null 2>&1; }
running() { container ls | grep -q "^$1 "; }

ensure_system() {
    container system status >/dev/null 2>&1 || container system start --app-root "$CONTAINER_APP_ROOT"
}

env_value() { sed -n "s/^$1=//p" "$ENV_FILE" | tail -1; }

build() {
    container build -t "$APP_IMAGE" .
}

up() {
    ensure_system
    [ -f "$ENV_FILE" ] || { cp local.env.dist "$ENV_FILE"; echo "Created $ENV_FILE - set DB_PASSWORD and ADMIN_PIN, then run db-user"; }
    container image inspect "$APP_IMAGE" >/dev/null 2>&1 || build

    exists "$DB" || { echo "No $DB container - run Football-NG's scripts/local-dev.sh up first"; exit 1; }
    running "$DB" || container start "$DB" >/dev/null
    echo -n "Waiting for mariadb"
    until container exec "$DB" healthcheck.sh --connect >/dev/null 2>&1; do echo -n "."; sleep 2; done
    echo

    local env_tmp
    env_tmp=$(mktemp)
    grep -v '^DB_HOST=' "$ENV_FILE" > "$env_tmp"
    {
        echo "DB_HOST=$(ip_of "$DB")"
        # no Datadog agent locally
        echo "DD_TRACE_ENABLED=false"
    } >> "$env_tmp"

    # recreate every time - the db IP can change when it restarts
    if exists "$APP"; then
        container stop "$APP" >/dev/null 2>&1 || true
        container rm "$APP" >/dev/null
    fi
    container run -d --name "$APP" \
        -p 127.0.0.1:${APP_PORT}:80 \
        -v "$PWD":/var/www/rwll \
        --env-file "$env_tmp" \
        "$APP_IMAGE" >/dev/null
    rm -f "$env_tmp"

    # the mount hides the image's vendor/ - the S3 SDK is only needed with PHOTO_STORAGE=s3
    [ -d vendor ] || container exec "$APP" sh -c 'cd /var/www/rwll && composer install --no-interaction --quiet'
    container exec "$APP" sh -c 'mkdir -p /var/www/rwll/storage/photos'

    status
    echo "http://localhost:${APP_PORT}"
}

down() {
    if exists "$APP"; then
        container stop "$APP" >/dev/null || true
        container rm "$APP" >/dev/null
        echo "removed $APP"
    fi
}

status() {
    for c in "$APP" "$DB"; do
        if exists "$c" && running "$c"; then
            printf '%-14s %s\n' "$c" "$(ip_of "$c")"
        else
            printf '%-14s %s\n' "$c" "not running"
        fi
    done
}

case "${1:-}" in
    up)      up ;;
    down)    down ;;
    build)   ensure_system; build ;;
    logs)    container logs -f "$APP" ;;
    shell)   container exec -it "$APP" sh ;;
    status)  status ;;
    sql)     container exec -it -e MYSQL_PWD="$(env_value DB_PASSWORD)" "$DB" mariadb -u"$(env_value DB_USER)" "$(env_value DB_NAME)" ;;
    # MARIADB_ROOT_PASSWORD=... skips the prompt; the prompt reads the terminal, so it works from `! cmd` too
    db-user) pw=${MARIADB_ROOT_PASSWORD:-}
             if [ -z "$pw" ]; then read -r -s -p "mariadb root password: " pw </dev/tty; echo; fi
             user=$(env_value DB_USER); pass=$(env_value DB_PASSWORD); db=$(env_value DB_NAME)
             [ "$pass" != "change-me" ] || { echo "Set DB_PASSWORD in $ENV_FILE first"; exit 1; }
             # the schema first (creates RWLL.evaluations if it's missing), then the user
             # the app only reads and upserts - no DELETE or DDL
             { cat db/schema.sql; cat <<SQL
CREATE USER IF NOT EXISTS '$user'@'%' IDENTIFIED BY '$pass';
ALTER USER '$user'@'%' IDENTIFIED BY '$pass';
GRANT SELECT, INSERT, UPDATE ON \`$db\`.* TO '$user'@'%';
SQL
             } | container exec -i -e MYSQL_PWD="$pw" "$DB" mariadb -uroot
             echo "$user can use $db" ;;
    *)       sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'; exit 1 ;;
esac
