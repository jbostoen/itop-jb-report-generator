# jb-report-generator
Copyright (C) 2019-2021 Jeffrey Bostoen

[![License](https://img.shields.io/github/license/jbostoen/iTop-custom-extensions)](https://github.com/jbostoen/iTop-custom-extensions/blob/master/license.md)
[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.me/jbostoen)
🍻 ☕

Need assistance with iTop or one of its extensions?  
Need custom development?  
Please get in touch to discuss the terms: **jbostoen.itop@outlook.com**

## What?
Feature: report generator. Quickly add reports to different classes (detail view and list view).

⚠ Work in progress. Needs some tweaking to make it work in other environments!
It's heavily focused on one project for now.

## Requirements

This extension uses some PHP packages.  
Use composer to install and update all required packages (composer.json is included)


**Required packages**:

| package 	                 | version | comment                                                         |
|--------------------------- |-------- | --------------------------------------------------------------  |
| twig/twig                  | 3.3     | Templates                                                       |


**Optional packages**:

| package 	                 | version | comment                                                         |
|--------------------------- |-------- | --------------------------------------------------------------  |
| chillerlan/php-qrcode      | 3.4     | Optional. QR-code generation. Starting 4.0: requires PHP 7.4    |
| mikehaertl/phpwkhtmltopdf  | 2.5.0   | Optional. For PDF reporting.                                    |
| components/jquery          | 3.6.0   | Optional. For use of jQuery in templates.                       |
| twbs/bootstrap             | 5.5.1   | Optional. For use of Twitter BootStrap in templates.            |



### Cookbook

PHP
* how to add an item to iTop's "Other actions" menu in both list view and detail view
* how to obtain iTop from data and render it using a Twig template
* how to add custom filters to Twig


## Documentation
See [Documentation](documentation.md)


