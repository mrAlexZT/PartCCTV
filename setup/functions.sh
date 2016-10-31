function hide_output {
	# This function hides the output of a command unless the command fails
	# and returns a non-zero exit code.

	# Get a temporary file.
	OUTPUT=$(tempfile)

	# Execute command, redirecting stderr/stdout to the temporary file.
	$@ &> $OUTPUT

	# If the command failed, show the output that was captured in the temporary file.
	E=$?
	if [ $E != 0 ]; then
		# Something failed.
		echo
		echo FAILED: $@
		echo -----------------------------------------
		cat $OUTPUT
		echo -----------------------------------------
		exit $E
	fi

	# Remove temporary file.
	rm -f $OUTPUT
}

function apt_get_quiet {
	# Run apt-get in a totally non-interactive mode.
	#
	# Somehow all of these options are needed to get it to not ask the user
	# questions about a) whether to proceed (-y), b) package options (noninteractive),
	# and c) what to do about files changed locally (we don't cause that to happen but
	# some VM providers muck with their images; -o).
	#
	# Although we could pass -qq to apt-get to make output quieter, many packages write to stdout
	# and stderr things that aren't really important. Use our hide_output function to capture
	# all of that and only show it if there is a problem (i.e. if apt_get returns a failure exit status).
	DEBIAN_FRONTEND=noninteractive hide_output apt-get -y -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confnew" "$@"
}

function apt_install {
	# Install a bunch of packages. We used to report which packages were already
	# installed and which needed installing, before just running an 'apt-get
	# install' for all of the packages.  Calling `dpkg` on each package is slow,
	# and doesn't affect what we actually do, except in the messages, so let's
	# not do that anymore.
	PACKAGES=$@
	apt_get_quiet install $PACKAGES
}

function ufw_allow {
	if [ -z "$DISABLE_FIREWALL" ]; then
		# ufw has completely unhelpful output
		ufw allow $1 > /dev/null;
	fi
}

function restart_service {
	hide_output service $1 restart
}

## Dialog Functions ##
function message_box {
	dialog --title "$1" --msgbox "$2" 0 0
}

function input_box {
	# input_box "title" "prompt" "defaultvalue" VARIABLE
	# The user's input will be stored in the variable VARIABLE.
	# The exit code from dialog will be stored in VARIABLE_EXITCODE.
	declare -n result=$4
	declare -n result_code=$4_EXITCODE
	result=$(dialog --stdout --title "$1" --inputbox "$2" 0 0 "$3")
	result_code=$?
}

function input_menu {
	# input_menu "title" "prompt" "tag item tag item" VARIABLE
	# The user's input will be stored in the variable VARIABLE.
	# The exit code from dialog will be stored in VARIABLE_EXITCODE.
	declare -n result=$4
	declare -n result_code=$4_EXITCODE
	local IFS=^$'\n'
	result=$(dialog --stdout --title "$1" --menu "$2" 0 0 0 $3)
	result_code=$?
}

function wget_verify {
	# Downloads a file from the web and checks that it matches
	# a provided hash. If the comparison fails, exit immediately.
	URL=$1
	HASH=$2
	DEST=$3
	CHECKSUM="$HASH  $DEST"
	rm -f $DEST
	wget -q -O $DEST $URL || exit 1
	if ! echo "$CHECKSUM" | sha1sum --check --strict > /dev/null; then
		echo "------------------------------------------------------------"
		echo "Download of $URL did not match expected checksum."
		echo "Found:"
		sha1sum $DEST
		echo
		echo "Expected:"
		echo "$CHECKSUM"
		rm -f $DEST
		exit 1
	fi
}

function git_clone {
	# Clones a git repository, checks out a particular commit or tag,
	# and moves the repository (or a subdirectory in it) to some path.
	# We use separate clone and checkout because -b only supports tags
	# and branches, but we sometimes want to reference a commit hash
	# directly when the repo doesn't provide a tag.
	REPO=$1
	TREEISH=$2
	SUBDIR=$3
	TARGETPATH=$4
	TMPPATH=/tmp/git-clone-$$
	rm -rf $TMPPATH $TARGETPATH
	git clone -q $REPO $TMPPATH || exit 1
	(cd $TMPPATH; git checkout -q $TREEISH;) || exit 1
	mv $TMPPATH/$SUBDIR $TARGETPATH
	rm -rf $TMPPATH
}
