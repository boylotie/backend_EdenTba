#!/bin/bash
# ============================================================================
# Script de deploiement — EdenTba API (Laravel)
# VPS: 100.125.195.64 | Port: 8083
#
# Utilisation :
#   1. git clone https://github.com/TON_USER/edentba.git /var/www/edentba
#   2. cd /var/www/edentba/backend
#   3. chmod +x deploy.sh
#   4. sudo ./deploy.sh
# ============================================================================

set -e

# --- Configuration -----------------------------------------------------------
VPS_IP="100.125.195.64"
PORT="8083"
REPO_URL="https://github.com/boylotie/backend_EdenTba.git"
CLONE_DIR="/var/www/edentba"
DEPLOY_DIR="/var/www/edentba/"
DB_NAME="edentba_db"
DB_USER="edentba_user"
DB_PASS="edentba_db123@!"
# ============================================================================

echo "=========================================="
echo "  Deploiement EdenTba API"
echo "  VPS: $VPS_IP | Port: $PORT"
echo "=========================================="

# --- 1. Mise a jour du systeme -----------------------------------------------
echo ""
echo "[1/10] Mise a jour du systeme..."

# Supprimer le PPA ondrej/php s'il est present (incompatible Resolute)
add-apt-repository --remove ppa:ondrej/php -y 2>/dev/null || true
rm -f /etc/apt/sources.list.d/ondrej-ubuntu-php-*.list 2>/dev/null || true

apt update && apt upgrade -y

# --- 2. Installation des dependances systeme ---------------------------------
echo ""
echo "[2/10] Installation de PHP 8.3, MySQL, Apache..."
apt install -y software-properties-common

# Ajouter le repo Sury PHP (compatible Ubuntu Resolute) si absent
if ! grep -q "packages.sury.org/php" /etc/apt/sources.list.d/*.list 2>/dev/null; then
    echo "Ajout du repo Sury PHP..."
    curl -sSL https://packages.sury.org/php/README.txt | bash
fi
apt update

apt install -y \
    php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-curl \
    php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd \
    php8.3-bcmath php8.3-intl php8.3-readline php8.3-opcache \
    mysql-server mysql-client \
    apache2 libapache2-mod-php8.3 \
    git unzip curl

# --- 3. Installation de Composer ---------------------------------------------
echo ""
echo "[3/10] Installation de Composer..."
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
fi
composer --version

# --- 4. Configuration de MySQL -----------------------------------------------
echo ""
echo "[4/10] Configuration de la base de donnees..."
systemctl enable mysql
systemctl start mysql

mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
echo "Base de donnees ${DB_NAME} prete."

# --- 5. Verification du depot ------------------------------------------------
echo ""
echo "[5/10] Verification du depot..."
if [ -d "$CLONE_DIR/.git" ]; then
    echo "Depot existant a $CLONE_DIR. Pull en cours..."
    cd "$CLONE_DIR"
    git pull origin main
else
    echo "Clonage du depot..."
    git clone "$REPO_URL" "$CLONE_DIR"
    cd "$CLONE_DIR"
fi
cd "$DEPLOY_DIR"

# --- 6. Installation des dependances PHP -------------------------------------
echo ""
echo "[6/10] Installation des dependances Composer..."
composer install --no-dev --optimize-autoloader

# --- 7. Configuration .env --------------------------------------------------
echo ""
echo "[7/10] Configuration de l'environnement..."
if [ ! -f .env ]; then
    cp .env.example .env
fi

sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|" .env
sed -i "s|APP_URL=.*|APP_URL=http://${VPS_IP}:${PORT}|" .env
sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
sed -i "s|#* *DB_HOST=.*|DB_HOST=127.0.0.1|" .env
sed -i "s|#* *DB_PORT=.*|DB_PORT=3306|" .env
sed -i "s|#* *DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
sed -i "s|#* *DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
sed -i "s|#* *DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
sed -i "s|BROADCAST_CONNECTION=.*|BROADCAST_CONNECTION=log|" .env
sed -i "s|SESSION_DRIVER=.*|SESSION_DRIVER=database|" .env
sed -i "s|CACHE_STORE=.*|CACHE_STORE=database|" .env
sed -i "s|QUEUE_CONNECTION=.*|QUEUE_CONNECTION=database|" .env
sed -i "s|MAIL_MAILER=.*|MAIL_MAILER=log|" .env

php artisan key:generate --force

# --- 8. Migrations & cache ---------------------------------------------------
echo ""
echo "[8/10] Mise en base et optimisation..."
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

# --- 9. Permissions ----------------------------------------------------------
echo ""
echo "[9/10] Configuration des permissions..."
chown -R www-data:www-data "$DEPLOY_DIR"
chmod -R 775 "$DEPLOY_DIR/storage" "$DEPLOY_DIR/bootstrap/cache"

# --- 10. Configuration Apache (port 8083) ------------------------------------
echo ""
echo "[10/10] Configuration d'Apache sur le port ${PORT}..."

cat > /etc/apache2/sites-available/edentba.conf <<EOF
<VirtualHost *:${PORT}>
    ServerName ${VPS_IP}
    DocumentRoot ${DEPLOY_DIR}/public

    <Directory ${DEPLOY_DIR}/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/edentba_error.log
    CustomLog \${APACHE_LOG_DIR}/edentba_access.log combined
</VirtualHost>
EOF

if ! grep -q "Listen ${PORT}" /etc/apache2/ports.conf; then
    echo "Listen ${PORT}" >> /etc/apache2/ports.conf
fi

a2dissite 000-default.conf
a2ensite edentba.conf
a2enmod rewrite

systemctl restart apache2
systemctl enable apache2

# --- Ouverture du pare-feu --------------------------------------------------
if command -v ufw &> /dev/null; then
    ufw allow ${PORT}/tcp
    ufw reload
fi

# --- Termine -----------------------------------------------------------------
echo ""
echo "=========================================="
echo "  DEPLOIEMENT TERMINE !"
echo "=========================================="
echo ""
echo "  API accessible sur :"
echo "  http://${VPS_IP}:${PORT}/api/v1/"
echo ""
echo "  Base de donnees :"
echo "  Nom:     ${DB_NAME}"
echo "  User:    ${DB_USER}"
echo "  MDP:     ${DB_PASS}"
echo ""
echo "  IMPORTANT :"
echo "  - Change DB_PASS dans ${DEPLOY_DIR}/.env"
echo "=========================================="
