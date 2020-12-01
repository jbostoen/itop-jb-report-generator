# jb-report-generator
Copyright (C) 2019-2020 Jeffrey Bostoen

[![License](https://img.shields.io/github/license/jbostoen/iTop-custom-extensions)](https://github.com/jbostoen/iTop-custom-extensions/blob/master/license.md)
[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.me/jbostoen)
🍻 ☕

Need assistance with iTop or one of its extensions?  
Need custom development?  
Please get in touch to discuss the terms: **jbostoen.itop@outlook.com**

## What?
Feature: report generator. Quickly add reports to different classes (detail view and list view).

Work in progress. Needs some tweaking to make it work in other environments!
It's heavily focused on one project for now.

## Requirements

This extension uses some PHP packages.
* iTop-dir/web/libext/vendor/autoload.php should be present. Use composer to install and update all required packages.
* Required packages:
  * Twig 3.x
* Optional packages:
  * chillerlan\QRCode (optional. Support to generate QR-codes using a Twig-filter)
  * mikeheartl\phpwkhtmltopdf (optional. support for generating PDF -> needs wkhtmltopdf and some configuration)


### Cookbook

PHP
* how to add an item to iTop's "Other actions" menu in both list view and detail view
* how to obtain iTop from data and render it using a Twig template
* how to add custom filters to Twig


## Documentation
See [Documentation](documentation.md)



```

