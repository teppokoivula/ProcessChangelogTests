PHPUnit tests for Process Changelog ProcessWire module
======================================================

Intended to be run against a clean installation of ProcessWire with Process
Changelog included. Most of the tests included depend on each other, which
is why they're grouped together in one file and use depends annotation.

DO NOT run these tests against production site, as they will add, edit and
remove pages when necessary, thus potentially seriously damaging your site!

## Installing and running PHPUnit

You'll need to install PHPUnit in order to run these tests. There are couple
of ways to do that, including PHAR, Composer and PEAR; visit PHPUnit Manual 
for details: http://phpunit.de/manual/3.7/en/installation.html.

Once PHPUnit is installed, the rest is as simple as cloning this repository
into the module directory (usually /site/modules/name-of-module/) and while
there typing `phpunit name-of-tests-directory` (or simply `phpunit .` which
will run *all* PHPUnit tests found below current directory).

## See also

* ProcessWire CMS/CMF: https://github.com/ryancramerdesign/ProcessWire
* Process Changelog module: https://github.com/teppokoivula/ProcessChangelog
