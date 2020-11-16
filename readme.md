# jb-reportgen
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
* iTop-dir/web/libext/vendor/autoload.php should be present. Use 'libext' from this repository and run composer update to install all required packages.
* This is mainly for:
  * chillerlan\QRCode
  * mikeheartl\phpwkhtmltopdf (support for generating PDF -> needs wkhtmltopdf and some configuration)
  * Twig 3.x



## How-to

### Adding reports 
* Add a folder under the templates-folder within this extension with the class name (don't use abstract classes!)
* In this folder, create subfolders: *details* and/or *list*
* Create a Twig template (basically a HTML file) and save it in the appropriate folder
Some vary basic reports are included as an example.

### Making reports available in iTop
Open templates/reports.php.
Typically it looks like this: 'iTop-class-name' => 'details-or-list' => 'setting'

Settings are:
* title: required. String. (how this will be presented within iTop)
* button: optional. Boolean (true/false, defaults to false). Show as separate button in toolbar right above details/list view. If false: listed under 'other actions'
* file: required. String. name of the template (should be a similar structure in templates: templates/iTop-class-name/which-view/filename.ext)
* target: optional. String. Defaults to '_blank'
* parameters: optional. Array (hash table).
  * action: allows for custom actions (create a class implementing iReportGeneratorExtension. Examples: generate XML, PDF, store files, ...)


```
	$aReports = [

		// UserRequest
		'UserRequest' => [
			// Allowed keys: details, list
			'details' => [
				[
					// Allowed keys:
					// 'title' (String), 
					// 'button' (true/false), 
					// 'file' (string), 
					// 'parameters' (hash table; passed in URL). 'action' is reserved.
					// 'target' (String, optional)
					'title' => 'Sample report',
					'button' => true,
					'file' => 'basic_details.html'
				],
				[
					'title' => 'Sample report (PDF)',
					'button' => true,
					'file' => 'basic_details.html',
					'parameters' => [
						'action' => 'show_pdf'
					]
				],
				[
					'title' => 'Sample work order',
					'button' => true,
					'file' => 'werkbon.twig'
				]
			],
			'list' => [
				// Other reports 
			]
		]
		
	];
```


### Variables in reports

#### Single item (details view)

For details (single object), use the variable **item**. It exposes **key** and **fields** (see iTop REST Documentation). 
Example: **item.fields.description**
 
As a bonus: it's possible to use *item.attachments*. 
**{% for attachment in item.attachments %} ... {% endfor %}** exposes the attachment's field properties:
```
attachment.fields.contents.mimetype
attachment.fields.contents.data
attachment.fields.contents.filename
```

#### Multiple items (list view)
For lists (single or multiple objects), you can use **item** and create things like **{% for item in items % } ... {% endfor %}**

Attachments are also available for each item.


### Using iTop language strings
There's a Twig Filter named **dict_s** in templates.
Where in iTop code this would be ```Dict::S('languagestring')```, 
but it's the same as in iTop Portal templates, for example: {{ 'UI:Menu:ReportGenerator:ShowReport'|dict_s }} or {{ 'Class:Ticket/Attribute:ref'|dict_s }}


### Using QR codes
A Twig filter is available to convert text/URLs to QR-code. {{ 'this string will be converted'|qr }}

### Cookbook

PHP
* how to add an item to iTop's "Other actions" menu in both list view and detail view
* how to obtain iTop from data and render it using a Twig template
* how to add custom filters to Twig

