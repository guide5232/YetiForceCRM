rm -rf .user.ini
rm -rf public_html/.user.ini

sudo cp tests/setup/my.cnf /etc/mysql/conf.d/my.cnf
sudo service mysql restart
composer install
vendor/bin/phpunit --version
yarn install --force --modules-folder "./public_html/libraries"
mysql -e "create database IF NOT EXISTS yetiforce;" -uroot
cp tests/setup/Db.txt config/Db.php
cp tests/setup/Main.txt config/Main.php
cp tests/setup/Debug.txt config/Debug.php
cp tests/setup/Developer.txt config/Developer.php
cp tests/setup/Api.txt config/Api.php
cp tests/setup/.htaccess .htaccess
# cp vendor/phpunit/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon/phpunit_coverage.php phpunit_coverage.php

if [ ! -f $HOME/build/YetiForceCompany/cache/ocular.phar ]; then wget -O $HOME/build/YetiForceCompany/cache/ocular.phar https://scrutinizer-ci.com/ocular.phar; fi
