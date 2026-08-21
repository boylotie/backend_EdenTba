#!/bin/bash
# ============================================================================
# Script de deploiement — EdenTba API (Laravel + Reverb + SSL)
# VPS: 100.125.195.64 | Port HTTP: 8083 | Port HTTPS: 8443 | Reverb: 8080
# ============================================================================

set -e

# ============================================================================
# CONFIGURATION
# ============================================================================

VPS_IP="102.180.188.28"
PORT="8083"
SSL_PORT="8443"
REVERB_PORT="8080"

REPO_URL="https://github.com/boylotie/backend_EdenTba.git"
DEPLOY_DIR="/var/www/edentba"

DB_NAME="edentba_db"
DB_USER="edentba_user"
DB_PASS="edentba_db123@!"

# ============================================================================
# VERIFICATION ROOT
# ============================================================================

if [ "$EUID" -ne 0 ]; then
    echo "Ce script doit etre execute avec sudo."
    exit 1
fi

echo "=========================================="
echo "   DEPLOIEMENT EDENTBA API"
echo "=========================================="
echo "  VPS (public) : $VPS_IP"
echo "  VPS (tailscale) : 100.125.195.64"
echo "  HTTP       : $PORT"
echo "  HTTPS      : $SSL_PORT"
echo "  Reverb     : $REVERB_PORT"
echo "=========================================="

# ============================================================================
# 1. MISE A JOUR DU SYSTEME
# ============================================================================

echo ""
echo "[1/12] Mise a jour du systeme..."

apt update
apt upgrade -y

# ============================================================================
# 2. INSTALLATION DES DEPENDANCES
# ============================================================================

echo ""
echo "[2/12] Installation de PHP 8.3, MySQL, Nginx, Node.js..."

add-apt-repository --remove ppa:ondrej/php -y 2>/dev/null || true
rm -f /etc/apt/sources.list.d/ondrej-ubuntu-php-*.list 2>/dev/null || true

if ! grep -Rqs "packages.sury.org/php" /etc/apt/sources.list.d/ 2>/dev/null; then
    curl -sSL https://packages.sury.org/php/README.txt | bash
fi

apt update

apt install -y \
    php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-curl \
    php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd \
    php8.3-bcmath php8.3-intl php8.3-readline php8.3-opcache \
    mysql-server mysql-client \
    nginx git unzip curl ca-certificates gnupg

# ============================================================================
# 3. INSTALLATION DE COMPOSER
# ============================================================================

echo ""
echo "[3/12] Installation de Composer..."

if ! command -v composer >/dev/null 2>&1; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
fi
composer --version

# ============================================================================
# 4. INSTALLATION DE NODE.JS
# ============================================================================

echo ""
echo "[4/12] Installation de Node.js 22..."

if ! command -v node >/dev/null 2>&1; then
    mkdir -p /etc/apt/keyrings
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" > /etc/apt/sources.list.d/nodesource.list
    apt update
    apt install -y nodejs
fi
node -v
npm -v

# ============================================================================
# 5. CONFIGURATION MYSQL
# ============================================================================

echo ""
echo "[5/12] Configuration de la base de donnees..."

systemctl enable mysql
systemctl start mysql

mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
echo "Base de donnees ${DB_NAME} prete."

# ============================================================================
# 6. RECUPERATION DU PROJET
# ============================================================================

echo ""
echo "[6/12] Recuperation du depot Git..."

if [ -d "$DEPLOY_DIR/.git" ]; then
    cd "$DEPLOY_DIR"
    git fetch origin
    git reset --hard origin/main
    git clean -fd
else
    git clone "$REPO_URL" "$DEPLOY_DIR"
    cd "$DEPLOY_DIR"
fi
echo "Code source dans $DEPLOY_DIR"

# ============================================================================
# 7. CONFIGURATION .ENV + COMPOSER
# ============================================================================

echo ""
echo "[7/12] Configuration de l'environnement..."

cd "$DEPLOY_DIR"

if [ ! -f .env ]; then
    cp .env.example .env
fi

# --- APP ---
sed -i 's/^APP_NAME=.*/APP_NAME="Eden Tba"/' .env
sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env
sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env
sed -i "s|^APP_URL=.*|APP_URL=https://${VPS_IP}:${SSL_PORT}|" .env
sed -i "s|^APP_LOCALE=.*|APP_LOCALE=fr|" .env

# --- BROADCAST ---
sed -i 's/^BROADCAST_CONNECTION=.*/BROADCAST_CONNECTION=reverb/' .env

# --- REVERB ---
sed -i "s|^REVERB_APP_KEY=.*|REVERB_APP_KEY=k83HFbny15KNCm4ekiYHpQOBmXIm8i7SVkJ1DQh5|" .env
sed -i "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=2KFSlp15MY4xBk44qgXiCONlmLeeKUWXHrgDJGjT|" .env
sed -i "s|^REVERB_APP_ID=.*|REVERB_APP_ID=398079|" .env
sed -i "s|^REVERB_HOST=.*|REVERB_HOST=${VPS_IP}|" .env
sed -i "s|^REVERB_PORT=.*|REVERB_PORT=${REVERB_PORT}|" .env
sed -i 's/^REVERB_SCHEME=.*/REVERB_SCHEME=https/' .env

# --- DATABASE ---
sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/' .env
sed -i 's/^DB_HOST=.*/DB_HOST=127.0.0.1/' .env
sed -i 's/^DB_PORT=.*/DB_PORT=3306/' .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env

# --- LARAVEL ---
sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=database/' .env
sed -i 's/^CACHE_STORE=.*/CACHE_STORE=database/' .env
sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=database/' .env
sed -i 's/^MAIL_MAILER=.*/MAIL_MAILER=log/' .env

# --- COMPOSER INSTALL ---
echo ""
echo "Installation des packages Composer..."
composer install --optimize-autoloader

# --- NPM INSTALL + BUILD ---
echo ""
echo "Installation et build des assets..."
npm install
npm run build

# --- CLAPPLICATION ---
php artisan key:generate --force

# ============================================================================
# 8. MIGRATIONS + SEEDERS + CACHE
# ============================================================================

echo ""
echo "[8/12] Migration et initialisation de la base..."

php artisan config:clear
php artisan migrate --force
php artisan db:seed --force

php artisan optimize:clear
php artisan route:cache
php artisan view:cache
php artisan storage:link || true

# ============================================================================
# 9. CERTIFICAT SSL AUTO-SIGNE
# ============================================================================

echo ""
echo "[9/12] Creation du certificat SSL..."

mkdir -p /etc/nginx/ssl

openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
    -keyout /etc/nginx/ssl/edentba.key \
    -out /etc/nginx/ssl/edentba.crt \
    -subj "/C=BI/ST=Bujumbura/L=Bujumbura/O=EdenTba/CN=${VPS_IP}" \
    2>/dev/null

echo "Certificat SSL cree (auto-signe, 10 ans)."

# ============================================================================
# 10. PERMISSIONS
# ============================================================================

echo ""
echo "[10/12] Configuration des permissions..."

chown -R www-data:www-data "$DEPLOY_DIR"
chmod -R 775 "$DEPLOY_DIR/storage"
chmod -R 775 "$DEPLOY_DIR/bootstrap/cache"

# ============================================================================
# 11. CONFIGURATION NGINX (HTTP + HTTPS)
# ============================================================================

echo ""
echo "[11/12] Configuration de Nginx..."

systemctl stop apache2 2>/dev/null || true
systemctl disable apache2 2>/dev/null || true

cat > /etc/nginx/sites-available/edentba <<EOF
# --- Redirection HTTP -> HTTPS ---
server {
    listen ${PORT};
    listen [::]:${PORT};
    server_name ${VPS_IP};
    return 301 https://\$host:${SSL_PORT}\$request_uri;
}

# --- HTTPS ---
server {
    listen ${SSL_PORT} ssl;
    listen [::]:${SSL_PORT} ssl;
    server_name ${VPS_IP};

    ssl_certificate /etc/nginx/ssl/edentba.crt;
    ssl_certificate_key /etc/nginx/ssl/edentba.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    root ${DEPLOY_DIR}/public;
    index index.php index.html;
    charset utf-8;

    add_header Access-Control-Allow-Origin "*" always;
    add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS" always;
    add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN" always;
    add_header Access-Control-Allow-Credentials "true" always;

    if (\$request_method = OPTIONS) {
        add_header Access-Control-Allow-Origin "*";
        add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS";
        add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN";
        add_header Access-Control-Allow-Credentials "true";
        add_header Access-Control-Max-Age 86400;
        add_header Content-Length 0;
        return 204;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sf /etc/nginx/sites-available/edentba /etc/nginx/sites-enabled/edentba
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl restart php8.3-fpm
systemctl restart nginx
systemctl enable nginx
systemctl enable php8.3-fpm

echo "Nginx configure (HTTP $PORT -> HTTPS $SSL_PORT)."

# ============================================================================
# 12. REVERB (WebSocket) + SYSTEMD
# ============================================================================

echo ""
echo "[12/12] Configuration de Reverb (WebSocket)..."

# Creer le service systemd pour Reverb
cat > /etc/systemd/system/reverb.service <<EOF
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target php8.3-fpm.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=${DEPLOY_DIR}
ExecStart=/usr/bin/php artisan reverb:start --port=${REVERB_PORT}
Restart=always
RestartSec=5
Environment=HOME=${DEPLOY_DIR}
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

# Creer le service queue worker
cat > /etc/systemd/system/edentba-queue.service <<EOF
[Unit]
Description=EdenTba Queue Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=${DEPLOY_DIR}
ExecStart=/usr/bin/php artisan queue:work --tries=3 --max-time=3600
Restart=always
RestartSec=5
Environment=HOME=${DEPLOY_DIR}
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

# Creer le service scheduler (cron)
cat > /etc/systemd/system/edentba-scheduler.service <<EOF
[Unit]
Description=EdenTba Task Scheduler
After=network.target

[Service]
Type=oneshot
User=www-data
WorkingDirectory=${DEPLOY_DIR}
ExecStart=/usr/bin/php artisan schedule:run --no-interaction
EOF

cat > /etc/systemd/system/edentba-scheduler.timer <<EOF
[Unit]
Description=Run EdenTba Scheduler every minute

[Timer]
OnCalendar=*:0/1
Persistent=true

[Install]
WantedBy=timers.target
EOF

# Activer et demarrer tous les services
systemctl daemon-reload

systemctl enable reverb.service
systemctl restart reverb.service

systemctl enable edentba-queue.service
systemctl restart edentba-queue.service

systemctl enable edentba-scheduler.timer
systemctl start edentba-scheduler.timer

# ============================================================================
# FIREWALL
# ============================================================================

echo ""
echo "Configuration du pare-feu..."

if command -v ufw >/dev/null 2>&1; then
    ufw allow "${PORT}/tcp"
    ufw allow "${SSL_PORT}/tcp"
    ufw allow "${REVERB_PORT}/tcp"
    ufw allow ssh
    ufw --force enable
    ufw reload
fi

# ============================================================================
# TEST FINAL
# ============================================================================

echo ""
echo "=========================================="
echo "   TEST FINAL"
echo "=========================================="

echo ""
echo "PHP-FPM  : $(systemctl is-active php8.3-fpm)"
echo "Nginx    : $(systemctl is-active nginx)"
echo "MySQL    : $(systemctl is-active mysql)"
echo "Reverb   : $(systemctl is-active reverb.service)"
echo "Queue    : $(systemctl is-active edentba-queue.service)"
echo "Scheduler: $(systemctl is-active edentba-scheduler.timer)"
echo "Laravel  : $(php artisan --version)"

echo ""
echo "Test API :"
curl -sk "https://${VPS_IP}:${SSL_PORT}/api/v1/" | head -c 200 || echo "Erreur de connexion"

# ============================================================================
# TERMINE
# ============================================================================

echo ""
echo "=========================================="
echo "   DEPLOIEMENT TERMINE !"
echo "=========================================="
echo ""
echo "  HTTP  (redirige vers HTTPS) :"
echo "  http://${VPS_IP}:${PORT}/api/v1/"
echo ""
echo "  HTTPS (API securisee) :"
echo "  https://${VPS_IP}:${SSL_PORT}/api/v1/"
echo ""
echo "  Reverb WebSocket :"
echo "  wss://${VPS_IP}:${REVERB_PORT}"
echo ""
echo "  Base de donnees :"
echo "  Nom  : ${DB_NAME}"
echo "  User : ${DB_USER}"
echo ""
echo "  Services :"
echo "  sudo systemctl status reverb"
echo "  sudo systemctl status edentba-queue"
echo "  sudo systemctl status edentba-scheduler.timer"
echo ""
echo "  Logs :"
echo "  sudo journalctl -u reverb -f"
echo "  sudo journalctl -u edentba-queue -f"
echo ""
echo "=========================================="
