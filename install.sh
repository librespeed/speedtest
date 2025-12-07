#!/bin/bash
# LibreSpeed Speedtest Installer for Ubuntu
# Author: Md. Sohag Rana (adapted for your workflow)

set -e

# Colors for output
GREEN="\e[32m"
RED="\e[31m"
NC="\e[0m"

echo -e "${GREEN}Updating system packages...${NC}"
sudo apt update && sudo apt upgrade -y

echo -e "${GREEN}Installing Apache, PHP, and required packages...${NC}"
sudo apt install -y apache2 php libapache2-mod-php unzip git

# Optional: MariaDB for results logging
# sudo apt install -y mariadb-server php-mysql

echo -e "${GREEN}Downloading LibreSpeed repository...${NC}"
cd /tmp
git clone https://github.com/librespeed/speedtest.git

echo -e "${GREEN}Deploying files to Apache web root...${NC}"
sudo mkdir -p /var/www/html/speedtest
sudo cp speedtest/index.html speedtest/speedtest.js speedtest/speedtest_worker.js speedtest/favicon.ico /var/www/html/speedtest/
sudo cp -r speedtest/backend /var/www/html/speedtest/
sudo cp -r speedtest/results /var/www/html/speedtest/

echo -e "${GREEN}Setting permissions...${NC}"
sudo chown -R www-data:www-data /var/www/html/speedtest
sudo chmod -R 755 /var/www/html/speedtest

echo -e "${GREEN}Restarting Apache...${NC}"
sudo systemctl restart apache2

echo -e "${GREEN}Installation complete!${NC}"
echo -e "Access LibreSpeed at: ${RED}http://<your-server-ip>/speedtest${NC}"
