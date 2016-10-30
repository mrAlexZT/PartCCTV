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
hide_output add-apt-repository -y ppa:nginx/$nginx
apt_install nginx php7.0-fpm php7.0-cli php7.0-json php7.0-pgsql php7.0-opcache php7.0-dev libzmq-dev pkg-config php-pear
hide_output pecl install zmq-beta

echo "Installing PostgreSQL..."
apt_install postgresql

# Open ports.
ufw_allow http
ufw_allow https