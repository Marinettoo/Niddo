#!/bin/bash
# Niddo Home Backup Server — Desinstalador

set -e

if [ "$EUID" -ne 0 ]; then
    echo "Ejecuta como root: sudo bash uninstall.sh"
    exit 1
fi

echo "Eliminando archivos del panel"
rm -rf /var/www/html/niddo

echo "Eliminando backups y datos"
rm -rf /var/niddo

echo "Eliminando configuracion de Apache"
a2disconf niddo > /dev/null 2>&1 || true
rm -f /etc/apache2/conf-available/niddo.conf
systemctl restart apache2

echo "Eliminando base de datos y usuario"
mysql -u root <<SQL
DROP DATABASE IF EXISTS niddo;
DROP USER IF EXISTS 'niddo'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "Desinstalando paquetes"
apt-get remove -y apache2 mariadb-server php php-mysql php-mbstring libapache2-mod-php
apt-get autoremove -y
apt-get purge -y apache2 mariadb-server

echo ""
echo "Niddo desinstalado!"
