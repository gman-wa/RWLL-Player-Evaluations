#!/bin/bash
# Deploy one image on the EC2 - run as root by the GitHub workflow through SSM, or by hand:
#
#   sudo bash /opt/rwll-evals/deploy.sh <ecr image:tag> <secret id>
#
# Writes the Secrets Manager JSON to /etc/rwll-evals/settings.json, pulls and starts the image with
# compose.yaml, waits for the site to answer, and goes back to the previous image if it doesn't.
set -euo pipefail

IMAGE=${1:?usage: deploy.sh <image> <secret id>}
SECRET_ID=${2:?usage: deploy.sh <image> <secret id>}
DIR=/opt/rwll-evals
SETTINGS=/etc/rwll-evals/settings.json
HEALTH_URL=http://127.0.0.1:8082/api/player/1
# www-data in the alpine php image - the container reads the settings as this user
APP_GID=82

cd "$DIR"
export AWS_DEFAULT_REGION=${AWS_REGION:-us-west-2}

echo "== settings from Secrets Manager ($SECRET_ID)"
mkdir -p /etc/rwll-evals
tmp=$(mktemp /etc/rwll-evals/.settings.XXXXXX)
trap 'rm -f "$tmp"' EXIT
aws secretsmanager get-secret-value --secret-id "$SECRET_ID" --query SecretString --output text > "$tmp"
jq -e 'type == "object" and length > 0' "$tmp" > /dev/null || { echo "secret $SECRET_ID isn't a JSON object" >&2; exit 1; }
for key in DB_HOST DB_USER DB_PASSWORD ADMIN_PIN; do
    jq -e --arg k "$key" '.[$k] | type == "string" and length > 0' "$tmp" > /dev/null || { echo "secret $SECRET_ID has no $key" >&2; exit 1; }
done
echo "keys: $(jq -r 'keys | join(", ")' "$tmp")"
chown root:$APP_GID "$tmp"
chmod 0440 "$tmp"
mv -f "$tmp" "$SETTINGS"

echo "== pull $IMAGE"
aws ecr get-login-password | docker login --username AWS --password-stdin "${IMAGE%%/*}" > /dev/null
IMAGE=$IMAGE docker compose pull --quiet

# compose reads IMAGE from .env, so docker compose ps / logs work on the server without it
previous=$(sed -n 's/^IMAGE=//p' "$DIR/.env" 2>/dev/null || true)

start() {
    # VERSION (the tag - the commit sha, shortened) is Datadog's version tag
    tag=${1##*:}
    printf 'IMAGE=%s\nVERSION=%s\n' "$1" "${tag:0:7}" > "$DIR/.env"
    docker compose up -d --remove-orphans
}

healthy() {
    # through the port the host's Apache proxies to - a player lookup also checks RDS
    for _ in $(seq 1 30); do
        if curl -fsS -o /dev/null --max-time 5 "$HEALTH_URL"; then
            return 0
        fi
        sleep 2
    done
    return 1
}

echo "== start"
start "$IMAGE"
if healthy; then
    docker image prune -f > /dev/null
    echo "== deployed $IMAGE"
    exit 0
fi

# no container logs here - this output ends up in the Actions log, which is public (visitor IPs, DB errors)
echo "!! $IMAGE didn't answer on $HEALTH_URL - see 'docker compose logs app' in $DIR on the server, or Datadog" >&2
if [ -n "$previous" ] && [ "$previous" != "$IMAGE" ]; then
    echo "!! going back to $previous" >&2
    start "$previous"
    healthy && echo "!! back on $previous" >&2
fi
exit 1
