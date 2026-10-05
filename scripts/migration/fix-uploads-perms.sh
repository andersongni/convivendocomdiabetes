#!/bin/bash
set -euo pipefail
UP=/var/www/html/wp-content/uploads
for d in 2015 2018 2019 2020 2021 2022 revslider groovy elementor; do
  if [ -d "$UP/$d/$d" ]; then
    echo "flatten $d"
    cp -a "$UP/$d/$d/." "$UP/$d/"
    rm -rf "$UP/$d/$d"
  fi
done
chown -R www-data:www-data "$UP"
find "$UP" -type d -exec chmod 775 {} +
echo "OK"
df -h "$UP"
ls -la "$UP/2022/07/LOGO-CONVIVENDO-COM-DIABETES-5.png"
