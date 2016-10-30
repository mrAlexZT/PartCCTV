source setup/functions.sh # load our functions

if [ ! -f /usr/bin/add-apt-repository ]; then
	echo "Installing add-apt-repository..."
	hide_output apt-get update
	apt_install software-properties-common
fi

# ### Update Packages

# Update system packages to make sure we have the latest upstream versions of things from Ubuntu.

echo Updating system packages...
hide_output apt-get update
apt_get_quiet upgrade

# ### Install System Packages

# Install basic utilities.
#
# * haveged: Provides extra entropy to /dev/random so it doesn't stall
#	         when generating random numbers for private keys (e.g. during
#	         ldns-keygen).
# * unattended-upgrades: Apt tool to install security updates automatically.
# * cron: Runs background processes periodically.
# * git: we install some things directly from github
# * sudo: allows privileged users to execute commands as root without being root
# * coreutils: includes `nproc` tool to report number of processors, mktemp

echo Installing system packages...
apt_install wget curl git sudo coreutils \
	haveged pollinate \
	unattended-upgrades cron
	
# ### Seed /dev/urandom
#
# /dev/urandom is used by various components for generating random bytes for
# encryption keys and passwords:
#
# * TLS private key (see `ssl.sh`, which calls `openssl genrsa`)
# * DNSSEC signing keys (see `dns.sh`)
# * our management server's API key (via Python's os.urandom method)
# * Roundcube's SECRET_KEY (`webmail.sh`)
# * ownCloud's administrator account password (`owncloud.sh`)
#
# Why /dev/urandom? It's the same as /dev/random, except that it doesn't wait
# for a constant new stream of entropy. In practice, we only need a little
# entropy at the start to get going. After that, we can safely pull a random
# stream from /dev/urandom and not worry about how much entropy has been
# added to the stream. (http://www.2uo.de/myths-about-urandom/) So we need
# to worry about /dev/urandom being seeded properly (which is also an issue
# for /dev/random), but after that /dev/urandom is superior to /dev/random
# because it's faster and doesn't block indefinitely to wait for hardware
# entropy. Note that `openssl genrsa` even uses `/dev/urandom`, and if it's
# good enough for generating an RSA private key, it's good enough for anything
# else we may need.
#
# Now about that seeding issue....
#
# /dev/urandom is seeded from "the uninitialized contents of the pool buffers when
# the kernel starts, the startup clock time in nanosecond resolution,...and
# entropy saved across boots to a local file" as well as the order of
# execution of concurrent accesses to /dev/urandom. (Heninger et al 2012,
# https://factorable.net/weakkeys12.conference.pdf) But when memory is zeroed,
# the system clock is reset on boot, /etc/init.d/urandom has not yet run, or
# the machine is single CPU or has no concurrent accesses to /dev/urandom prior
# to this point, /dev/urandom may not be seeded well. After this, /dev/urandom
# draws from the same entropy sources as /dev/random, but it doesn't block or
# issue any warnings if no entropy is actually available. (http://www.2uo.de/myths-about-urandom/)
# Entropy might not be readily available because this machine has no user input
# devices (common on servers!) and either no hard disk or not enough IO has
# ocurred yet --- although haveged tries to mitigate this. So there's a good chance
# that accessing /dev/urandom will not be drawing from any hardware entropy and under
# a perfect-storm circumstance where the other seeds are meaningless, /dev/urandom
# may not be seeded at all.
#
# The first thing we'll do is block until we can seed /dev/urandom with enough
# hardware entropy to get going, by drawing from /dev/random. haveged makes this
# less likely to stall for very long.

echo Initializing system random number generator...
dd if=/dev/random of=/dev/urandom bs=1 count=32 2> /dev/null

# This is supposedly sufficient. But because we're not sure if hardware entropy
# is really any good on virtualized systems, we'll also seed from Ubuntu's
# pollinate servers:

pollinate  -q -r

# Between these two, we really ought to be all set.

# ### Package maintenance
#
# Allow apt to install system updates automatically every day.

cat > /etc/apt/apt.conf.d/02periodic <<EOF;
APT::Periodic::MaxAge "7";
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::Verbose "1";
EOF
	