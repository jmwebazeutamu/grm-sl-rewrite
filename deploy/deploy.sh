#!/usr/bin/env bash
#
# GRM Sierra Leone — production deploy script.
# Target: fresh Ubuntu 22.04 LTS with Apache already installed.
#
# Run as a user with sudo (NOT as root directly).
#
# USAGE:
#   sudo ./deploy.sh                         # normal deploy / update
#   sudo ./deploy.sh --snapshot=/path/db.tgz # first deploy, seed from dev snapshot
#
# IDEMPOTENT: re-runnable. Only changes things that aren't already in the
# desired state.

set -euo pipefail

# ─── Config ──────────────────────────────────────────────────────────────────
APP_DIR="/opt/grm-sl-rewrite"
DATA_DIR="/opt/grm-sl-rewrite-data"
BACKUP_DIR="/var/backups/grm-sl"
REPO_URL="git@github.com:jmwebazeutamu/grm-sl-rewrite.git"
CONTAINER_NAME="grm-sl"
IMAGE_NAME="grm-sl:prod"
CONTAINER_PORT="8000"   # inside-container port (artisan serve)
HOST_PORT="8081"        # host port the Apache vhost proxies to
VHOST_NAME="grm-sl"
SERVER_IP="$(curl -4 -s https://ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')"

SNAPSHOT=""
for arg in "$@"; do
  case "$arg" in
    --snapshot=*) SNAPSHOT="${arg#*=}" ;;
    *) echo "Unknown arg: $arg"; exit 1 ;;
  esac
done

# ─── Helpers ─────────────────────────────────────────────────────────────────
say() { echo -e "\n\033[1;34m==>\033[0m \033[1m$*\033[0m"; }
ok()  { echo -e "   \033[1;32m✓\033[0m $*"; }
note(){ echo -e "   \033[0;33m!\033[0m $*"; }

need_root() {
  if [[ $EUID -ne 0 ]]; then
    echo "Run with sudo." >&2
    exit 1
  fi
}

need_root

# ─── 1. System prep ──────────────────────────────────────────────────────────
say "Step 1/8 — Updating apt + installing base packages"
apt-get update -qq
apt-get install -y -qq curl git ufw ca-certificates gnupg lsb-release apache2
ok "base packages ready"

# ─── 2. Docker install ───────────────────────────────────────────────────────
say "Step 2/8 — Installing Docker engine + compose plugin"
if ! command -v docker >/dev/null 2>&1; then
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
    | gpg --dearmor --yes -o /etc/apt/keyrings/docker.gpg
  chmod a+r /etc/apt/keyrings/docker.gpg
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" \
    > /etc/apt/sources.list.d/docker.list
  apt-get update -qq
  apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-compose-plugin
  systemctl enable --now docker
  ok "Docker installed"
else
  ok "Docker already installed — $(docker --version | head -c 40)"
fi

# ─── 3. Clone or pull repo ───────────────────────────────────────────────────
say "Step 3/8 — Fetching the app from GitHub"
if [[ ! -d "$APP_DIR/.git" ]]; then
  note "First-time clone → $APP_DIR"
  note "If this fails with a key error, add this server's SSH key as a deploy key on GitHub:"
  note "    cat /root/.ssh/id_ed25519.pub  (or generate one with ssh-keygen -t ed25519)"
  git clone "$REPO_URL" "$APP_DIR"
else
  git -C "$APP_DIR" fetch --quiet origin
  git -C "$APP_DIR" reset --hard origin/main --quiet
  ok "repo updated to latest main"
fi

# ─── 4. Data dirs + snapshot restore ─────────────────────────────────────────
say "Step 4/8 — Preparing persistent data volumes"
mkdir -p "$DATA_DIR/database" "$DATA_DIR/storage"
chmod 755 "$DATA_DIR" "$DATA_DIR/database" "$DATA_DIR/storage"

if [[ -n "$SNAPSHOT" ]]; then
  if [[ ! -f "$SNAPSHOT" ]]; then
    echo "Snapshot file not found: $SNAPSHOT" >&2
    exit 1
  fi
  say "Step 4b/8 — Restoring from snapshot: $SNAPSHOT"
  if [[ -f "$DATA_DIR/database/database.sqlite" ]]; then
    backup="$DATA_DIR/database/database.sqlite.before-restore.$(date +%s)"
    cp "$DATA_DIR/database/database.sqlite" "$backup"
    note "existing DB backed up to $backup"
  fi
  tar -xzf "$SNAPSHOT" -C "$DATA_DIR"
  ok "snapshot extracted"
fi

if [[ ! -f "$DATA_DIR/database/database.sqlite" ]]; then
  touch "$DATA_DIR/database/database.sqlite"
  note "fresh empty SQLite file created (no snapshot provided)"
fi

# ─── 5. Build + run container ────────────────────────────────────────────────
say "Step 5/8 — Building image and starting container"
docker build --quiet -t "$IMAGE_NAME" "$APP_DIR" >/dev/null
ok "image built: $IMAGE_NAME"

# Stop any previous container
if docker ps -a --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
  docker stop "$CONTAINER_NAME" >/dev/null 2>&1 || true
  docker rm   "$CONTAINER_NAME" >/dev/null 2>&1 || true
fi

docker run -d \
  --name "$CONTAINER_NAME" \
  --restart unless-stopped \
  -p "127.0.0.1:${HOST_PORT}:${CONTAINER_PORT}" \
  -v "$DATA_DIR/database/database.sqlite:/app/database/database.sqlite" \
  -v "$DATA_DIR/storage:/app/storage/app/public" \
  -e "APP_URL=http://${SERVER_IP}" \
  -e "APP_ENV=production" \
  -e "APP_DEBUG=false" \
  "$IMAGE_NAME" >/dev/null
ok "container running: ${CONTAINER_NAME} (host:${HOST_PORT} → container:${CONTAINER_PORT})"

# Apply migrations (idempotent — skips ones already run)
sleep 3
docker exec "$CONTAINER_NAME" php artisan migrate --force >/dev/null 2>&1 || note "migrate skipped or failed — check manually"
docker exec "$CONTAINER_NAME" php artisan config:cache >/dev/null 2>&1 || true
docker exec "$CONTAINER_NAME" php artisan route:cache  >/dev/null 2>&1 || true

# ─── 6. Apache reverse proxy ─────────────────────────────────────────────────
say "Step 6/8 — Configuring Apache reverse proxy"
a2enmod proxy proxy_http rewrite headers >/dev/null 2>&1
cat >/etc/apache2/sites-available/${VHOST_NAME}.conf <<CONF
<VirtualHost *:80>
    ServerAdmin admin@localhost
    # ServerName grm.example.sl
    # ServerAlias www.grm.example.sl

    ProxyPreserveHost On
    ProxyRequests Off

    <Proxy *>
        Require all granted
    </Proxy>

    ProxyPass        / http://127.0.0.1:${HOST_PORT}/
    ProxyPassReverse / http://127.0.0.1:${HOST_PORT}/

    RequestHeader set X-Forwarded-Proto "http"
    RequestHeader set X-Forwarded-Port  "80"

    ErrorLog  \${APACHE_LOG_DIR}/${VHOST_NAME}-error.log
    CustomLog \${APACHE_LOG_DIR}/${VHOST_NAME}-access.log combined
</VirtualHost>
CONF

# Disable the default "It works" page if present, enable ours.
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite "${VHOST_NAME}" >/dev/null 2>&1
apache2ctl configtest >/dev/null
systemctl reload apache2
ok "Apache vhost enabled → http://${SERVER_IP}/"

# ─── 7. Firewall ─────────────────────────────────────────────────────────────
say "Step 7/8 — Firewall (ufw)"
if ! ufw status | grep -q "Status: active"; then
  ufw allow OpenSSH >/dev/null
  ufw allow "Apache Full" >/dev/null
  ufw --force enable >/dev/null
  ok "ufw enabled (SSH + Apache Full)"
else
  ufw allow OpenSSH >/dev/null 2>&1 || true
  ufw allow "Apache Full" >/dev/null 2>&1 || true
  ok "ufw already active — rules ensured"
fi

# ─── 8. Backup cron ──────────────────────────────────────────────────────────
say "Step 8/8 — Daily backup cron"
mkdir -p "$BACKUP_DIR"
cat >/etc/cron.daily/grm-sl-backup <<'CRON'
#!/usr/bin/env bash
set -e
STAMP=$(date +%Y%m%d-%H%M%S)
DEST=/var/backups/grm-sl
DATA=/opt/grm-sl-rewrite-data
mkdir -p "$DEST"

# SQLite: use .backup so we get a consistent copy even if the app is writing.
sqlite3 "$DATA/database/database.sqlite" ".backup '$DEST/db-$STAMP.sqlite'" 2>/dev/null \
  || cp "$DATA/database/database.sqlite" "$DEST/db-$STAMP.sqlite"

# Storage (attachments)
tar -czf "$DEST/storage-$STAMP.tgz" -C "$DATA" storage

# Retain 14 days
find "$DEST" -type f -mtime +14 -delete
CRON
chmod +x /etc/cron.daily/grm-sl-backup
# Ensure sqlite3 client is available for consistent backups
apt-get install -y -qq sqlite3 >/dev/null
ok "daily backup installed at /etc/cron.daily/grm-sl-backup → $BACKUP_DIR"

# ─── Smoke test ──────────────────────────────────────────────────────────────
say "Smoke test"
sleep 2
code=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:${HOST_PORT}/login" || echo "000")
if [[ "$code" == "200" || "$code" == "302" ]]; then
  ok "app responds on container port ${HOST_PORT} (HTTP ${code})"
else
  note "container returned HTTP ${code} — check: docker logs ${CONTAINER_NAME} --tail 50"
fi

code=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1/login" || echo "000")
if [[ "$code" == "200" || "$code" == "302" ]]; then
  ok "Apache proxy working on port 80 (HTTP ${code})"
else
  note "Apache returned HTTP ${code} — check /var/log/apache2/${VHOST_NAME}-error.log"
fi

echo
echo -e "\033[1;32m────────────────────────────────────────────\033[0m"
echo -e "\033[1;32m  Deploy complete.\033[0m"
echo -e "\033[1;32m  → http://${SERVER_IP}/\033[0m"
echo -e "\033[1;32m────────────────────────────────────────────\033[0m"
echo
echo "Logs:       docker logs -f ${CONTAINER_NAME}"
echo "Shell:      docker exec -it ${CONTAINER_NAME} bash"
echo "Backups:    ${BACKUP_DIR}"
echo "Update:     sudo ./deploy.sh      (pulls latest main + rebuilds)"
