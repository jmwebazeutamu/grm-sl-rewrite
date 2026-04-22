#!/bin/sh
# Sync specific env vars from the container's environment (compose env_file
# and `environment:` block) into /app/.env before Laravel boots.
#
# Why this exists:
#   - `docker compose environment:` and `env_file:` set process-level env
#     vars, visible to `getenv()`.
#   - Laravel's phpdotenv loader reads /app/.env at bootstrap and overwrites
#     the process env with its values. So `env()` / `config()` calls inside
#     HTTP handlers see whatever /app/.env contains, NOT what compose set.
#   - Rewriting /app/.env on container start lets compose stay the single
#     source of truth without us baking secrets into the image.
#
# Idempotent — only touches vars that are non-empty in the container's env.

set -e

ENV_FILE="/app/.env"

if [ ! -f "${ENV_FILE}" ]; then
    echo "docker-entrypoint: ${ENV_FILE} missing, skipping sync"
    exec "$@"
fi

# Vars to pull from env into .env. Add more here as the deployment grows.
VARS="APP_URL AUTH_MODEL \
      MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD \
      MAIL_ENCRYPTION MAIL_FROM_ADDRESS MAIL_FROM_NAME"

for var in $VARS; do
    # Read without failing on missing var.
    value=$(printenv "$var" 2>/dev/null || true)
    [ -z "$value" ] && continue

    # Escape '/', '&', and '|' for sed; quote value on write so spaces are safe.
    escaped=$(printf '%s' "$value" | sed -e 's/[\/&|]/\\&/g')
    quoted_value="\"${value}\""

    if grep -q "^${var}=" "${ENV_FILE}"; then
        sed -i "s|^${var}=.*|${var}=${escaped}|" "${ENV_FILE}"
    else
        echo "${var}=${quoted_value}" >> "${ENV_FILE}"
    fi
done

# Invalidate any config cache so the next request re-reads .env.
php artisan config:clear >/dev/null 2>&1 || true

exec "$@"
