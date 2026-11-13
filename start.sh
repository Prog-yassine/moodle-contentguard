#!/bin/sh
set -eu
cd "$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
for tool in docker curl tar python3; do
    command -v "$tool" >/dev/null || { echo "Commande requise : $tool" >&2; exit 1; }
done
docker compose version >/dev/null
mkdir -p runtime artifacts
python3 - <<'PY'
from pathlib import Path
import secrets
env = Path('.env')
if not env.exists():
    env.write_text('MOODLE_DB_PASSWORD=' + secrets.token_urlsafe(28) + '\n'
        + 'MOODLE_ADMIN_PASSWORD=Admin!' + secrets.token_urlsafe(16) + '\n'
        + 'CONTENTGUARD_STUDENT_PASSWORD=Test!' + secrets.token_hex(5) + '\n'
        + 'MOODLE_WWWROOT=http://localhost:8080\n')
    env.chmod(0o600)
Path('runtime/php-demo.ini').write_text('max_input_vars=5000\nmemory_limit=512M\n'
    'upload_max_filesize=20M\npost_max_size=24M\n')
PY
if [ ! -f runtime/moodle51/public/version.php ]; then
    echo "Téléchargement de Moodle 5.1+ (Build: 20251107)…"
    curl -fL --retry 2 'https://codeload.github.com/moodle/moodle/tar.gz/41a6ad6cd21cc2932b832de940a35830a975c6c8' -o runtime/moodle51.tar.gz
    mkdir -p runtime/moodle51
    tar -xzf runtime/moodle51.tar.gz --strip-components=1 -C runtime/moodle51
    chmod -R a+rX runtime/moodle51
fi
chmod -R a+rX plugin scripts
chmod a+r runtime/php-demo.ini
docker compose build web
docker compose up -d --wait
# Keep credentials outside source code and normalize configuration on every start.
docker compose exec -T web sh -c 'cp /opt/contentguard/config.php /var/www/html/config.php && chmod 640 /var/www/html/config.php && mkdir -p /var/moodledata'
tables=$(docker compose exec -T db psql -U moodle -d moodle -Atc "SELECT to_regclass('public.mdl_config')")
if [ -z "$tables" ]; then
    docker compose exec -T web php83 /opt/contentguard/install.php > runtime/install.log 2>&1 || {
        tail -40 runtime/install.log >&2; exit 1;
    }
fi
docker compose exec -T web php83 admin/cli/upgrade.php --non-interactive > runtime/upgrade.log 2>&1 || {
    tail -40 runtime/upgrade.log >&2; exit 1;
}
if [ ! -f runtime/fr51.zip ]; then
    curl -fL --retry 2 'https://download.moodle.org/download.php/direct/langpack/5.1/fr.zip' -o runtime/fr51.zip || true
fi
if [ -f runtime/fr51.zip ]; then
    docker compose cp runtime/fr51.zip web:/tmp/contentguard-fr.zip
    docker compose exec -T web php83 -r '$z=new ZipArchive(); if ($z->open("/tmp/contentguard-fr.zip")!==true) exit(1); @mkdir("/var/moodledata/lang",0775,true); if (!$z->extractTo("/var/moodledata/lang")) exit(2);'
fi
docker compose exec -T web php83 /opt/contentguard/seed-demo.php > runtime/seed.json
docker compose exec -T web php83 /opt/contentguard/seed-quiz.php > runtime/quiz.json
docker compose exec -T web php83 /opt/contentguard/seed-calendar.php > runtime/calendar.json
docker compose exec -T web php83 admin/cli/purge_caches.php
python3 scripts/credentials.py
echo "Cours et liens : runtime/seed.json"
