#!/bin/bash
#
# BOM/BOQ Application - Quick Setup Script
# Run this on your Ubuntu/Debian server
#

set -e  # Exit on error

echo "=========================================="
echo "BOM/BOQ Application - Quick Setup"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root (use sudo)${NC}"
    exit 1
fi

echo -e "${YELLOW}Step 1: Installing Required Packages...${NC}"
apt update
apt install -y nginx mysql-server php8.1-fpm php8.1-mysql php8.1-mbstring php8.1-json php8.1-cli

echo -e "${GREEN}✓ Packages installed${NC}"
echo ""

echo -e "${YELLOW}Step 2: Creating Application Directory...${NC}"
mkdir -p /var/www/electrical/wordpress/bom
echo -e "${GREEN}✓ Directory created${NC}"
echo ""

echo -e "${YELLOW}Step 3: Setting Up MySQL Database...${NC}"
read -p "Enter MySQL root password: " -s MYSQL_ROOT_PASS
echo ""

mysql -u root -p"$MYSQL_ROOT_PASS" <<EOF
CREATE DATABASE IF NOT EXISTS bom_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON bom_db.* TO 'bom_user'@'localhost' IDENTIFIED BY 'BomApp2026!@#';
FLUSH PRIVILEGES;
EOF

echo -e "${GREEN}✓ Database created${NC}"
echo ""

echo -e "${YELLOW}Step 4: Importing Database Schema...${NC}"
if [ -f "./db-schema.sql" ]; then
    mysql -u root -p"$MYSQL_ROOT_PASS" bom_db < ./db-schema.sql
    echo -e "${GREEN}✓ Schema imported${NC}"
else
    echo -e "${RED}✗ db-schema.sql not found. Please import manually.${NC}"
fi
echo ""

echo -e "${YELLOW}Step 5: Copying Application Files...${NC}"
if [ -d "./billofmaterials-main" ] || [ -f "./login.php" ]; then
    cp -r ./* /var/www/electrical/wordpress/bom/ 2>/dev/null || true
    echo -e "${GREEN}✓ Files copied${NC}"
else
    echo -e "${YELLOW}⚠ Please manually copy files to /var/www/electrical/wordpress/bom/${NC}"
fi
echo ""

echo -e "${YELLOW}Step 6: Setting Permissions...${NC}"
chown -R www-data:www-data /var/www/electrical/wordpress/bom
find /var/www/electrical/wordpress/bom -type d -exec chmod 755 {} \;
find /var/www/electrical/wordpress/bom -type f -exec chmod 644 {} \;
echo -e "${GREEN}✓ Permissions set${NC}"
echo ""

echo -e "${YELLOW}Step 7: Configuring Nginx...${NC}"
if [ -f "./nginx-bom.conf" ]; then
    cp ./nginx-bom.conf /etc/nginx/sites-available/bom
    ln -sf /etc/nginx/sites-available/bom /etc/nginx/sites-enabled/bom
    nginx -t && systemctl reload nginx
    echo -e "${GREEN}✓ Nginx configured${NC}"
else
    echo -e "${YELLOW}⚠ nginx-bom.conf not found. Please configure manually.${NC}"
fi
echo ""

echo -e "${YELLOW}Step 8: Starting Services...${NC}"
systemctl enable nginx php8.1-fpm mysql
systemctl start nginx php8.1-fpm mysql
echo -e "${GREEN}✓ Services started${NC}"
echo ""

echo "=========================================="
echo -e "${GREEN}Setup Complete!${NC}"
echo "=========================================="
echo ""
echo "Next Steps:"
echo "1. Update /var/www/electrical/wordpress/bom/db-config.php with database credentials"
echo "   DB_USER: bom_user"
echo "   DB_PASSWORD: BomApp2026!@#"
echo ""
echo "2. Visit: http://serverx.ratfish-regulus.ts.net/bom/migrate_to_db.php"
echo "   to migrate existing data"
echo ""
echo "3. DELETE the migration file after completion:"
echo "   sudo rm /var/www/electrical/wordpress/bom/migrate_to_db.php"
echo ""
echo "4. Access your application:"
echo "   http://serverx.ratfish-regulus.ts.net/bom/"
echo ""
echo "Default Login:"
echo "  Username: Admin"
echo "  Password: T0ms1234"
echo ""
echo -e "${YELLOW}⚠ IMPORTANT: Change the SALT in db-config.php!${NC}"
echo ""
