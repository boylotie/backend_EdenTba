#!/bin/bash
# ============================================================================
# Script de déploiement — EdenTba API (Laravel)
# VPS: 100.125.195.64 | Port: 8083
#
# Utilisation :
#   1. git clone https://github.com/boylotie/backend_EdenTba.git /var/www/edentba
#   2. cd /var/www/edentba
#   3. chmod +x deploy.sh
#   4. sudo ./deploy.sh
# ============================================================================

set -e

# ============================================================================
# CONFIGURATION
# ============================================================================

VPS_IP="100.125.195.64"
PORT="8083"

REPO_URL="https://github.com/boylotie/backend_EdenTba.git"
DEPLOY_DIR="/var/www/edentba"

DB_NAME="edentba_db"
DB_USER="edentba_user"
DB_PASS="edentba_db123@!"

# ============================================================================
# VÉRIFICATION ROOT
# ============================================================================

if [ "$EUID" -ne 0 ]; then
    echo "❌ Ce script doit être exécuté avec sudo."
    echo "   Exemple : sudo ./deploy.sh"
    exit 1
fi

echo "=========================================="
echo "   DEPLOIEMENT EDENTBA API"
echo "=========================================="
echo "  VPS  : $VPS_IP"
echo "  Port : $PORT"
echo "=========================================="

# ============================================================================
# 1. MISE À JOUR DU SYSTÈME
# ============================================================================

echo ""
echo "[1/10] Mise à jour du système..."

apt update
apt upgrade -y

# ============================================================================
# 2. INSTALLATION DES DÉPENDANCES
# ============================================================================

echo ""
echo "[2/10] Installation de PHP 8.3, MySQL et Nginx..."

# Supprimer l'ancien PPA Ondrej s'il existe
add-apt-repository --remove ppa:ondrej/php -y 2>/dev/null || true

rm -f /etc/apt/sources.list.d/ondrej-ubuntu-php-*.list 2>/dev/null || true

# Ajouter Sury PHP si nécessaire
if ! grep -Rqs "packages.sury.org/php" /etc/apt/sources.list.d/ 2>/dev/null; then
    echo "Ajout du dépôt PHP Sury..."
    curl -sSL https://packages.sury.org/php/README.txt | bash
fi

apt update

apt install -y \
    php8.3 \
    php8.3-cli \
    php8.3-fpm \
    php8.3-mysql \
    php8.3-curl \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-zip \
    php8.3-gd \
    php8.3-bcmath \
    php8.3-intl \
    php8.3-readline \
    php8.3-opcache \
    mysql-server \
    mysql-client \
    nginx \
    git \
    unzip \
    curl

echo ""
echo "PHP installé :"
php8.3 -v

# ============================================================================
# 3. INSTALLATION DE COMPOSER
# ============================================================================

echo ""
echo "[3/10] Installation de Composer..."

if ! command -v composer >/dev/null 2>&1; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
fi

composer --version

# ============================================================================
# 4. CONFIGURATION MYSQL
# ============================================================================

echo ""
echo "[4/10] Configuration de la base de données..."

systemctl enable mysql
systemctl start mysql

mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost'
IDENTIFIED BY '${DB_PASS}';

ALTER USER '${DB_USER}'@'localhost'
IDENTIFIED BY '${DB_PASS}';

GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';

FLUSH PRIVILEGES;
EOF

echo "✅ Base de données ${DB_NAME} prête."

# ============================================================================
# 5. RÉCUPÉRATION DU PROJET
# ============================================================================

echo ""
echo "[5/10] Récupération du dépôt Git..."

if [ -d "$DEPLOY_DIR/.git" ]; then

    echo "Dépôt existant."
    cd "$DEPLOY_DIR"

    git fetch origin
    git reset --hard origin/main
    git clean -fd

else

    echo "Clonage du dépôt..."

    mkdir -p "$(dirname "$DEPLOY_DIR")"

    git clone "$REPO_URL" "$DEPLOY_DIR"

    cd "$DEPLOY_DIR"
fi

echo "✅ Code source disponible dans $DEPLOY_DIR"

# ============================================================================
# 6. CONFIGURATION ENVIRONNEMENT + COMPOSER
# ============================================================================

echo ""
echo "[6/10] Installation des dépendances PHP..."

cd "$DEPLOY_DIR"

# Créer le .env s'il n'existe pas
if [ ! -f .env ]; then
    cp .env.example .env
fi

# --------------------------------------------------------------------------
# Configuration minimale AVANT Composer
# --------------------------------------------------------------------------

sed -i 's/^APP_NAME=.*/APP_NAME="Eden Tba"/' .env
sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env
sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env
sed -i "s|^APP_URL=.*|APP_URL=http://${VPS_IP}:${PORT}|" .env

sed -i 's/^BROADCAST_CONNECTION=.*/BROADCAST_CONNECTION=log/' .env

# --------------------------------------------------------------------------
# Base de données
# --------------------------------------------------------------------------

sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/' .env
sed -i 's/^DB_HOST=.*/DB_HOST=127.0.0.1/' .env
sed -i 's/^DB_PORT=.*/DB_PORT=3306/' .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env

# --------------------------------------------------------------------------
# Laravel
# --------------------------------------------------------------------------

sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=database/' .env
sed -i 's/^CACHE_STORE=.*/CACHE_STORE=database/' .env
sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=database/' .env
sed -i 's/^MAIL_MAILER=.*/MAIL_MAILER=log/' .env

# ============================================================================
# COMPOSER
# ============================================================================

echo ""
echo "Installation des packages Composer..."

# IMPORTANT :
# Les seeders utilisent Faker.
# On installe donc les dépendances dev pour permettre le seeding.
composer install \
    --optimize-autoloader

# ============================================================================
# CLÉ APPLICATION
# ============================================================================

php artisan key:generate --force

# ============================================================================
# 7. VÉRIFICATION DE LA CONNEXION DB
# ============================================================================

echo ""
echo "[7/10] Vérification de la base de données..."

php artisan config:clear

php artisan about --only=environment

# ============================================================================
# 8. MIGRATIONS + SEEDERS + CACHE
# ============================================================================

echo ""
echo "[8/10] Migration et initialisation de la base..."

# --force évite la confirmation interactive en production
php artisan migrate --force

# Les seeders utilisent Faker
php artisan db:seed --force

# Nettoyage des anciens caches
php artisan optimize:clear

# Optimisation Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Lien storage
php artisan storage:link || true

echo "✅ Base de données et cache configurés."

# ============================================================================
# 9. PERMISSIONS
# ============================================================================

echo ""
echo "[9/10] Configuration des permissions..."

chown -R www-data:www-data "$DEPLOY_DIR"

chmod -R 775 "$DEPLOY_DIR/storage"
chmod -R 775 "$DEPLOY_DIR/bootstrap/cache"

echo "✅ Permissions configurées."

# ============================================================================
# 10. CONFIGURATION NGINX
# ============================================================================

echo ""
echo "[10/10] Configuration de Nginx sur le port ${PORT}..."

# Désactiver Apache s'il est installé
systemctl stop apache2 2>/dev/null || true
systemctl disable apache2 2>/dev/null || true

cat > /etc/nginx/sites-available/edentba <<EOF
server {
    listen ${PORT};
    listen [::]:${PORT};

    server_name ${VPS_IP};

    root ${DEPLOY_DIR}/public;

    index index.php index.html;

    charset utf-8;

    # Laravel
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Favicon
    location = /favicon.ico {
        access_log off;
        log_not_found off;
    }

    # Robots
    location = /robots.txt {
        access_log off;
        log_not_found off;
    }

    # PHP-FPM
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;

        fastcgi_pass unix:/run/php/php8.3-fpm.sock;

        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
    }

    # Bloquer les fichiers cachés
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Activer le site
ln -sf \
    /etc/nginx/sites-available/edentba \
    /etc/nginx/sites-enabled/edentba

# Désactiver le site Nginx par défaut
rm -f /etc/nginx/sites-enabled/default

# Vérification Nginx
nginx -t

# Redémarrage des services
systemctl restart php8.3-fpm
systemctl restart nginx

systemctl enable nginx
systemctl enable php8.3-fpm

echo "✅ Nginx configuré."

# ============================================================================
# FIREWALL
# ============================================================================

echo ""
echo "Configuration du pare-feu..."

if command -v ufw >/dev/null 2>&1; then

    ufw allow "${PORT}/tcp"

    # SSH
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
echo "PHP-FPM :"
systemctl is-active php8.3-fpm

echo ""
echo "Nginx :"
systemctl is-active nginx

echo ""
echo "MySQL :"
systemctl is-active mysql

echo ""
echo "Laravel :"
php artisan --version

# ============================================================================
# TERMINÉ
# ============================================================================

echo ""
echo "=========================================="
echo "   DEPLOIEMENT TERMINE !"
echo "=========================================="
echo ""
echo "API accessible sur :"
echo "http://${VPS_IP}:${PORT}/api/v1/"
echo ""
echo "Projet :"
echo "${DEPLOY_DIR}"
echo ""
echo "Base de données :"
echo "Nom  : ${DB_NAME}"
echo "User : ${DB_USER}"
echo ""
echo "=========================================="
echo "   IMPORTANT"
echo "=========================================="
echo ""
echo "Le mot de passe MySQL n'est volontairement"
echo "pas affiché pour des raisons de sécurité."
echo ""
echo "Pense à changer DB_PASS et à utiliser un"
echo "secret sécurisé pour un environnement réel."
echo ""
echo "=========================================="
