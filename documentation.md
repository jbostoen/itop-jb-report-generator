# Documentation

## How-to

### ⚠ Setting up PDF generation

This extension can use a wrapper to generate PDF documents.
There's several PDF libraries available, each with their own benefits and limitations.
Natively, this extension uses **mikehaertl/phpwkhtmltopdf** since it seemed to be most stable and easiest to use.
TCPPDF was expected to change in iTop 2.7 and wkhtml offers more options.
It's worth noting that it may not support more modern HTML/JS/CSS standards (such as flex).
This is due to the fact that wkhtmltopdf (<= 0.12.5) uses an older webkit version.

Default settings (for Windows) can be seen in **reporthelper.class.inc.php** -> RTPDF::GetPDFObject().
They can be overruled in the module settings for this extension, found in iTop configuration file.
Edit the default settings found under **extras_wkhtml**.

This extension has been tested with wkhtmltopdf 0.12.6.

https://wkhtmltopdf.org/status.html

This might change to Puppeteer at a more suitable point, but Puppeteer also had implementation issues.
A candidate is https://github.com/rialto-php/puphpeteer ; however it requires PHP 7.3.


### Adding reports
* Create a separate extension.

* For basic reports (HTML or PDF), add a file structure: templates/ClassName/details-or-list/filename.ext
  * Example: templates/UserRequest/details/ticket.html
  
* Create a PHP file which is loaded through the extension: extend the abstract class ```DefaultReport``` and implement the interface ```iReport```.
  * Set a condition
  
  
Example:
 
```
<?php

// Use the same namespace as the report generator
namespace jb_itop_extensions\report_generator;

// iTop internals
use \DBObjectSet;
use \Dict;

/**
 * Class DefaultReport just represents a basic report to extend.
 */
abstract class ReporUserRequest_Details extends DefaultReport implements iReport {
		
	public const sModuleDir = 'jb-report-generator-example';
	
	/**
	 * Title of the menu item or button
	 *
	 * @return \String
	 *
	 * @details Hint: you can use Dict::S('...')
	 *
	 */
	public static function GetTitle() {
		return Dict::S('UI:Report:SomeDescription');
	}
	
	/**
	 * URL Parameters. Often 'template' or additional parameters for extended iReportTool implementations.
	 *
	 * @return \Array
	 */
	public static function GetURLParameters() {
		return [
			'type' => 'details',
			'template' => 'ticket.html'
			// Also natively supported is one of these:
			// 'action' => 'show_pdf',
			// 'action' => 'download_pdf',
		];
	}
	
	
	/**
	 * Whether or not this extension is applicable
	 *
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 * @param \String $sView View: 'details' or 'list'
	 *
	 * @return \Boolean
	 *
	 */
	public static function IsApplicable(DBObjectSet $oSet_Objects, $sView) {
		return ($sView == 'details' && $oSet_Objects->GetClass() == 'UserRequest');
	}
	
}
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


#### Miscellaneous variables

Available in templates using the built-in Twig reporting:
* **itop.root_url**: iTop root url


### Using iTop language strings
There's a Twig Filter named **dict_s** in templates.
Where in iTop code this would be ```Dict::S('languagestring')```, 
but it's the same as in iTop Portal templates.

Examples:
```
{{ 'UI:Menu:ReportGenerator:ShowReport'|dict_s }}
{{ 'Class:Ticket/Attribute:ref'|dict_s }}
```


### Using QR codes
See requirements!

A Twig filter is available to convert text/URLs to QR-code.
```
{{ 'this string will be converted'|qr }}
```

