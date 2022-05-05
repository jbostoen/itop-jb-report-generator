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

⚠ Needs some tweaking to make it work in other environments! Unlikely to work on a typical web host unless you have more control over the server.


## Requirements

This extension uses some PHP packages that are installed with composer.  
Use composer to install and update all required packages (composer.json is included)

Needs at least PHP 7.4


**Required packages**:

For the version, see composer.json

| Package 	                 | Comment                                                         |
|--------------------------- | --------------------------------------------------------------  |
| twig/twig                  | Templates                                                       |


**Optional packages**:

| Package 	                 | Comment                                                         |
|--------------------------- | --------------------------------------------------------------  |
| chillerlan/php-qrcode      | Optional. QR-code generation.                                   |
| spatie/browsershot         | Optional. For PDF reporting.                                    |
| nesk/puphpeteer            | Optional. For PDF reporting.                                    |
| components/jquery          | Optional. For use of jQuery in templates.                       |
| twbs/bootstrap             | Optional. For use of Twitter BootStrap in templates.            |



### Cookbook

PHP
* how to add an item to iTop's "Other actions" menu in both list view and detail view
* how to obtain iTop from data and render it using a Twig template
* how to add custom filters to Twig


## Documentation
See [Documentation](documentation.md)

## Professional support

Get in touch to get this up and running and/or have custom made reports for your organization.


## Sponsor features

You can sponsor the development of these features:

- [ ] Periodic e-mail reports (daily, monthly, ...) based on a predefined OQL

Feel free to suggest other ideas!
