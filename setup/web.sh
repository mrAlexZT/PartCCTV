#!/bin/bash
# HTTP: Turn on a web server serving static files
#################################################

source setup/functions.sh # load our functions

# Some Ubuntu images start off with Apache. Remove it since we
# will use nginx. Use autoremove to remove any Apache depenencies.
if [ -f /usr/sbin/apache2 ]; then
	echo Removing apache...
	hide_output apt-get -y purge apache2 apache2-*
	hide_output apt-get -y --purge autoremove
fi



# Install nginx and a PHP FastCGI daemon.
#
# Turn off nginx's default website.

echo "Installing Nginx (web server) & PHP7.0 (FPM and CLI)..."
hide_output add-apt-repository -y ppa:nginx/development
apt_install nginx php7.0-fpm php7.0-cli php7.0-json php7.0-pgsql php7.0-opcache php7.0-dev libzmq-dev pkg-config php-pear
echo "Installing ZMQ Binding..."
echo
echo "-----------------------------------------------"
echo
printf "\n" | pecl install zmq-beta
echo "extension=zmq.so" >> /etc/php/7.0/mods-available/zmq.ini
ln -s /etc/php/7.0/mods-available/zmq.ini /etc/php/7.0/fpm/conf.d/20-zmq.ini
ln -s /etc/php/7.0/mods-available/zmq.ini /etc/php/7.0/cli/conf.d/20-zmq.ini
echo
echo "-----------------------------------------------"
echo
echo "Installing PostgreSQL..."
apt_install postgresql
echo "Creating the 'cctv' DB..."
echo
echo "-----------------------------------------------"
echo
sudo -u postgres bash -c "psql -c \"CREATE USER cctv WITH PASSWORD 'cctv';\""
sudo -u postgres bash -c "psql -c \"CREATE DATABASE cctv --owner cctv;\""
echo Restoring DB...
export PGPASSWORD=cctv
psql -U cctv cctv -h localhost < setup/postgre.sql
echo
echo "-----------------------------------------------"
echo

# Open ports.
ufw_allow http
ufw_allow https