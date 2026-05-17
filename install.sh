#!/bin/bash
set -e

if [ "$EUID" -ne 0 ]; then
    echo "Ejecuta como root: sudo bash install.sh"
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
sed -i 's/\r//' "$SCRIPT_DIR/install.sh"

echo "Instalando paquetes"
apt-get update -qq
apt-get install -y apache2 mariadb-server php php-mysql php-mbstring php-zip libapache2-mod-php

echo "Arrancando servicios"
systemctl enable --now apache2 mariadb

echo "Creando base de datos"
mysql -u root < "$SCRIPT_DIR/niddo_schema.sql"
mysql -u root -e "
    CREATE USER IF NOT EXISTS 'niddo'@'localhost' IDENTIFIED BY 'niddo';
    GRANT ALL PRIVILEGES ON niddo.* TO 'niddo'@'localhost';
    FLUSH PRIVILEGES;
"

echo "Copiando archivos"
rm -rf /var/www/html/niddo
cp -r "$SCRIPT_DIR" /var/www/html/niddo
chown -R www-data:www-data /var/www/html/niddo

echo "Configurando credenciales de base de datos"
cat > /var/www/html/niddo/config/db.php << 'EOF'
<?php
$host = 'localhost';
$db   = 'niddo';
$user = 'niddo';
$pass = 'niddo';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'DB connection failed']));
}
EOF

echo "Creando directorio de las copias de srguidad"
mkdir -p /var/niddo/backups
chown -R www-data:www-data /var/niddo
chmod 750 /var/niddo

echo "Configurando Apache"
a2enmod rewrite > /dev/null
cat > /etc/apache2/conf-available/niddo.conf << 'EOF'
<Directory /var/www/html/niddo>
    AllowOverride All
    Options -Indexes
</Directory>
EOF
a2enconf niddo > /dev/null
systemctl restart apache2

IP=$(hostname -I | awk '{print $1}')
echo ""
echo "Instalacion completada! :)"
echo "Disfruta!!!"
echo "Panel: http://$IP/niddo/panel/login.php"
