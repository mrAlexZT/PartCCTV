#!/bin/bash
source setup/functions.sh # load our functions

hide_output add-apt-repository -y ppa:djcj/hybrid
apt_install ffmpeg