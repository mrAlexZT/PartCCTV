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
hide_output printf "\n" | pecl install zmq-beta

echo "Installing PostgreSQL..."
apt_install postgresql
echo "Creating the 'cctv' DB..."
echo "CREATE ROLE cctv LOGIN ENCRYPTED PASSWORD 'cctv';" | sudo -u postgres psql
su postgres -c "createdb cctv --owner cctv"

# Open ports.
ufw_allow http
ufw_allow https