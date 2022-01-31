# jb-report-generator

Copyright (c) 2019-2022 Jeffrey Bostoen

[![License](https://img.shields.io/github/license/jbostoen/iTop-custom-extensions)](https://github.com/jbostoen/iTop-custom-extensions/blob/master/license.md)
[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.me/jbostoen)
🍻 ☕

Need assistance with iTop or one of its extensions?  
Need custom development?  
Please get in touch to discuss the terms: **info@jeffreybostoen.be** / https://jeffreybostoen.be

## What?

Feature: report generator. Quickly add detailed reports to different classes (detail view and list view).  

⚠ Work in progress. Needs some tweaking to make it work in other environments!  


## Requirements

This extension uses some PHP packages that are installed with composer.  
Use composer to install and update all required packages (composer.json is included)

Needs at least PHP 7.4


**Required packages**:

| package 	                 | version | comment                                                         |
|--------------------------- |-------- | --------------------------------------------------------------  |
| twig/twig                  | 3.3     | Templates                                                       |


**Optional packages**:

| package 	                 | version | comment                                                         |
|--------------------------- |-------- | --------------------------------------------------------------  |
| chillerlan/php-qrcode      | 4.3.2   | Optional. QR-code generation.                                   |
| spatie/browsershot         | 3.52.3  | Optional. For PDF reporting.                                    |
| nesk/puphpeteer            | 2.0.0   | Optional. For PDF reporting;                                    |
| components/jquery          | 3.6.0   | Optional. For use of jQuery in templates.                       |
| twbs/bootstrap             | 5.1.3   | Optional. For use of Twitter BootStrap in templates.            |



### Cookbook

PHP
* how to add an item to iTop's "Other actions" menu in both list view and detail view
* how to obtain iTop from data and render it using a Twig template
* how to add custom filters to Twig


## Documentation
See [Documentation](documentation.md)


