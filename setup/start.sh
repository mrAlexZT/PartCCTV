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
source setup/web_update.sh

# Done.
echo
echo "-----------------------------------------------"
echo
echo Your Mail-in-a-Box is running.
echo
echo Please log in to the control panel at:
echo
echo https://$PRIVATE_IP/
echo
echo You will be alerted that the website has an invalid certificate.
echo
