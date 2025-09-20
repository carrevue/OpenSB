#!/bin/bash

cecho(){
    Error='\033[0;31m'
    Success='\033[0;32m'
    Warning='\033[0;33m'
    Info='\033[0;34m'

    NC="\033[0m" # No Color

    printf "${!1}${2} ${NC}\n"
}

echo "OpenSB Upload Processor Test Script"


if ! [ -f dynamic/videos/text_videos.txt ]; then
  cp -R tools/test_videos/. dynamic/videos/
else
  cecho "Warning" "Testing videos appear to have been copied already. If running on production, please remove them."
fi

cecho "Info" "192x144 6fps H264"
php private/scripts/upload_processor.php "192x144" "dynamic/videos/192x144.mp4" "video" "0"
cecho "Info" "720x1280 29.97fps H264"
php private/scripts/upload_processor.php "720x1280" "dynamic/videos/720x1280.mp4" "video" "0"
cecho "Info" "1920x1080 60fps H264"
php private/scripts/upload_processor.php "1920x1080" "dynamic/videos/1920x1080.mp4" "video" "0"
cecho "Info" "1920x1080 30fps WMV"
php private/scripts/upload_processor.php "1920x1080windows" "dynamic/videos/1920x1080windows.wmv" "video" "0"
cecho "Info" "3200x1080 29.97fps H264"
php private/scripts/upload_processor.php "verywide1080" "dynamic/videos/verywide1080.mp4" "video" "0"