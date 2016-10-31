#!/bin/bash
# This is the entry point for configuring the system.
#####################################################

source setup/functions.sh # load our functions
PRIVATE_IP=ifconfig | sed -En 's/127.0.0.1//;s/.*inet (addr:)?(([0-9]*\.){3}[0-9]*).*/\2/p'


# Check system setup: Are we running as root on Ubuntu 16.04 on a
# machine with enough memory? Is /tmp mounted with exec.
# If not, this shows an error and exits.
source setup/preflight.sh

# Fix so line drawing characters are shown correctly in Putty on Windows.
export NCURSES_NO_UTF8_ACS=1

source setup/questions.sh

# Create the STORAGE_ROOT directory if they don't already exist.
if [ ! -d $STORAGE_ROOT ]; then
	mkdir -p $STORAGE_ROOT
fi

# Start service configuration.
source setup/system.sh
source setup/web.sh
source setup/ffmpeg.sh

# ...and then have it write the nginx configuration files and start those
# services.

cwd=$(pwd)

# Nginx conf
echo Configuring nginx
sed -i "s#/home/cctv/PartCCTV#$cwd#g" setup/nginx.conf
rm -f /etc/nginx/conf.d/default.conf
cp setup/nginx.conf /etc/nginx/conf.d/default.conf

# Composer
echo Installing Composer dependencies...
hide_output php composer.phar install

# PartCCTV.ini
echo Creating PartCCTV.ini file...
cp PartCCTV.ini.example PartCCTV.ini
sed -i "s#root#cctv#g" PartCCTV.ini
sed -i "s#mysql#pgsql#g" PartCCTV.ini
sed -i "s#run_as_systemd_service = false#run_as_systemd_service = true#g" PartCCTV.ini

# Start services.
echo Restarting services...
restart_service nginx
restart_service php7.0-fpm
restart_service postgresql

# PartCCTV Service
echo Setting-up PartCCTV service
sed -i "s#/home/cctv/PartCCTV#$cwd#g" setup/partcctv.service
cp setup/partcctv.service /etc/systemd/system/partcctv.service
systemctl enable partcctv
systemctl start partcctv

# Done.
echo
echo "-----------------------------------------------"
echo
echo Your PartCCTV instance is running.
echo
echo Please log in to the control panel
echo
# echo You will be alerted that the website has an invalid certificate.
# echo
