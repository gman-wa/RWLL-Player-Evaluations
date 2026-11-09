#!/bin/sh
# php-fpm in the background, Apache in the foreground
set -e
php-fpm -D
exec httpd -D FOREGROUND
