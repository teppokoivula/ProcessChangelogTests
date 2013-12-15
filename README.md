PHPUnit tests for Process Changelog ProcessWire module
======================================================

Intended to be run against a clean installation of ProcessWire with Process
Changelog installed. Most of the tests included depend on each other, which
is why they're grouped together in one file and use depends annotation.

DO NOT run these tests against production site, as they will add, edit and
remove pages when necessary, thus potentially seriously damaging your site!

## See also

* ProcessWire CMS/CMF: https://github.com/ryancramerdesign/ProcessWire
* Process Changelog module: https://github.com/teppokoivula/ProcessChangelog
