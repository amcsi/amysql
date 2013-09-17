<!--- vim: set tabstop=8 expandtab filetype=php : <?php -->
Installation
=

Composer
-----
Install composer in your project:

    curl -s https://getcomposer.org/installer | php

Create a composer.json file in your project root:

    {
        "require": {
            "amcsi/amysql": "1.*"
        }
    }

Install via composer:

    php composer.phar install

Add this line to your application’s index.php file (PHP 5.3+):

    <?php
    require 'vendor/autoload.php';

Or just include AMysql.php if your PHP version is 5.2.*:

    <?php
    require 'vendor/amcsi/amysql/AMysql.php';

Manual
-----

Copy files:

    AMysql.php
    AMysql/

To /path/to/libs/ (Zend Framework style) or /path/to/libs/AMysql/ (subprojects separated)

Then include AMysql.php or have AMysql.php be autoloadable.
