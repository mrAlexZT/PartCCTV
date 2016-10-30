if [ -z "$NONINTERACTIVE" ]; then
	# Install 'dialog' so we can ask the user questions. The original motivation for
	# this was being able to ask the user for input even if stdin has been redirected,
	# e.g. if we piped a bootstrapping install script to bash to get started. In that
	# case, the nifty '[ -t 0 ]' test won't work. But with Vagrant we must suppress so we
	# use a shell flag instead. Really suppress any output from installing dialog.
	#
	# Also install dependencies needed to validate the email address.
	if [ ! -f /usr/bin/dialog ]; then
		echo Installing packages needed for setup...
		apt-get -q -q update
		apt_get_quiet install dialog || exit 1
	fi

	message_box "PartCCTV Installation" \
		"Hello and thanks for deploying a PartCCTV installation!
		\n\nI'm going to ask you a few questions.
		\n\nNOTE: You should only install this on a brand new Ubuntu installation 100% dedicated to PartCCTV. It will, for example, remove apache2."
fi

if [ -z "$STORAGE_ROOT" ]; then

	input_box "Hostname" \
	"PartCCTV needs some place, where it will store recordered media.
	\n\nIt can be changed in the WEBGUI.
	\n\Store Path:" \
		STORAGE_ROOT

	if [ -z "$STORAGE_ROOT" ]; then
		# user hit ESC/cancel
		exit
	fi

	# Show the configuration, since the user may have not entered it manually.
	echo
	echo "Store Path: $STORAGE_ROOT"
	if [ -f /usr/bin/git ] && [ -d .git ]; then
		echo "PartCCTV Version: " $(git describe)
	fi
	echo
fi