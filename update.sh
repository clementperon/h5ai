#!/bin/bash
npm install
npm run build
mv /var/www/_h5ai/cache/thumbs /var/www
rm -r /var/www/_h5ai/
mv build/_h5ai/ /var/www/
mv /var/www/thumbs /var/www/_h5ai/cache
chown www-data:www-data /var/www/_h5ai/cache/
