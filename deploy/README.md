# GRM Sierra Leone — Production Deployment

Single-script deploy for a fresh **Ubuntu 22.04 LTS** host with Apache
already installed.

## What it sets up

| Layer | Details |
|---|---|
| System | curl, git, ufw, sqlite3 |
| Docker | Engine + Compose plugin from docker.com |
| App | Repo clone to `/opt/grm-sl-rewrite`, image build, container `grm-sl` |
| DB | SQLite file at `/opt/grm-sl-rewrite-data/database/database.sqlite` (bind-mounted) |
| Storage | Attachments at `/opt/grm-sl-rewrite-data/storage` (bind-mounted) |
| Proxy | Apache vhost on :80 → container on :8081 |
| Firewall | ufw: SSH + "Apache Full" (80/443) |
| Backups | `/etc/cron.daily/grm-sl-backup` → `/var/backups/grm-sl`, 14-day retention |

Data and app code live in separate directories so `git pull && rebuild`
never touches user data.

## First deploy (with dev database)

### 1. On your dev machine — snapshot the DB

```bash
cd <this-repo>/deploy
./snapshot-dev-db.sh                       # writes /tmp/grm-sl-snapshot-YYYYMMDD-HHMMSS.tar.gz
scp /tmp/grm-sl-snapshot-*.tar.gz user@<PROD_IP>:/tmp/
```

### 2. On the prod server

```bash
# Add an SSH deploy key to the GitHub repo so the server can clone:
ssh-keygen -t ed25519 -N '' -f /root/.ssh/id_ed25519   # (as root, or use sudo)
cat /root/.ssh/id_ed25519.pub
# → paste as a "Deploy key" on https://github.com/jmwebazeutamu/grm-sl-rewrite/settings/keys

# Then, while still logged in to prod:
git clone git@github.com:jmwebazeutamu/grm-sl-rewrite.git /tmp/grm-sl-bootstrap
sudo /tmp/grm-sl-bootstrap/deploy/deploy.sh --snapshot=/tmp/grm-sl-snapshot-*.tar.gz
```

Running the script clones the repo properly into `/opt/grm-sl-rewrite`
and runs the full provisioning. After the first success you can delete
`/tmp/grm-sl-bootstrap`.

The server will be live at `http://<PROD_IP>/` within ~2 minutes.

## Subsequent deploys (code updates)

Push changes to `main` on GitHub, then on prod:

```bash
sudo /opt/grm-sl-rewrite/deploy/deploy.sh
```

This pulls the latest main, rebuilds the image, replaces the container,
and re-runs Apache config. The database and attachments are untouched.

## Post-deploy: adding TLS (when you have a domain)

```bash
sudo apt-get install certbot python3-certbot-apache
sudo certbot --apache -d grm.example.sl
```

Certbot edits the vhost to add a `:443` section and auto-redirect.
Renewal runs via a systemd timer.

## Operations

```bash
# Logs
docker logs -f grm-sl

# Shell into app container
docker exec -it grm-sl bash

# Open a SQLite shell on prod
sudo sqlite3 /opt/grm-sl-rewrite-data/database/database.sqlite

# Manual backup
sudo /etc/cron.daily/grm-sl-backup
ls -lh /var/backups/grm-sl
```

## Restoring from a backup

```bash
sudo docker stop grm-sl
sudo cp /var/backups/grm-sl/db-YYYYMMDD-HHMMSS.sqlite \
       /opt/grm-sl-rewrite-data/database/database.sqlite
sudo docker start grm-sl
```
