

# Adding reports

* Create a separate extension.

* For basic reports (HTML or PDF), add a file structure: templates/ClassName/details-or-list/filename.ext
  * Example: templates/UserRequest/details/ticket.html
  
* Create a PHP file which is loaded through the extension: extend the abstract class ```AbstractReportUIElement``` which implements the interface ```iReportUIElement```.
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
 * Class ReportUIElement_UserRequest_Details. Enables a "Show PDF" button in iTop's GUI.
 */
abstract class ReportUIElement_UserRequest_Details extends AbstractReportUIElement {
	
	/**
	 * @var \String $sModuleDir Name of current module dir. If this report is introduced with a new extension named "jb-report-generator-example", then adjust it like that.
	 */
	public const sModuleDir = 'jb-report-generator-example';
	
	/**
	 * Title of the menu item or button
	 *
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 * @param \String $sView View: 'details' or 'list'
	 *
	 * @return \String
	 *
	 * @details Hint: you can use Dict::S('...')
	 *
	 */
	public static function GetTitle(DBObjectSet $oSet_Objects, $sView) {
	
		return Dict::S('UI:Report:ShowPDF');
		
	}
	
	/**
	 * URL Parameters. Often 'template' or additional parameters for extended iReportProcessor implementations.
	 *
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 * @param \String $sView View: 'details' or 'list'
	 *
	 * @return \Array
	 */
	public static function GetURLParameters(DBObjectSet $oSet_Objects, $sView) {
	
		return [
			'type' => $sView,
			'template' => 'ticket.html'
			// Some actions which are supported by default (if no 'action' key is specified, it will just render a HTML template).
			// They include show_pdf (renders in browser unless browser is configured to download the file), download_pdf, attach_pdf (adds as attachment to the iTop object)
			'action' => 'show_pdf',
		];
		
	}
	
	
	/**
	 * Whether or not this UI element is applicable
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

## PHP classes: Report vs report tool

In this implementation, a **report processor** is something which either **enriches the data** (this could be transforming, linking different data, ...) and/or **provides new actions**.  
An example of a report tool is the PDF export option included in this extension.

A **report UI element** is used to add a menu action or button in the front end, typically based on a certain condition (e.g. "if a list view of user requests is displayed, show this report option").  
It typically defines the action (just showing the report, showing a PDF version, attaching the PDF to the object, ...) that will be performed by one or more processors.  

The report processor and UI element do not require each other.



# Variables in reports

## Single item (details view)

For details (single object), use the variable ```item```. It exposes ```key``` and ```fields``` (see iTop REST Documentation, it's similar). 
Example: 
```
item.fields.description
```
 
As a bonus: it's possible to use ```item.attachments```. 
```{% for attachment in item.attachments %} ... {% endfor %}``` exposes the attachment's field properties:

```
attachment.fields.contents.mimetype
attachment.fields.contents.data
attachment.fields.contents.filename
```

## Multiple items (list view)
For lists (single or multiple objects), you can use ```item``` and create things like ```{% for item in items % } ... {% endfor %}```

Attachments are also available for each item.


## Miscellaneous variables

⚠️ iTop 3.0 has possibly introduced alternative variables and function calls for their Twig system. This has not been tested yet, but this report generator extension is also compatible with 2.7.

Available in templates using the built-in Twig reporting:
* ```itop.root_url```: iTop root URL
* ```lib.bootstrap.css```: URL to CSS for Twitter BootStrap
* ```lib.bootstrap.js```: URL to JavaScript for Twitter BootStrap
* ```lib.jquery.js```: URL to JavaScript for jQuery


# Using iTop language strings

⚠️ iTop 3.0 has possibly introduced alternative variables and function calls for their Twig system. This has not been tested yet, but this report generator extension is also compatible with 2.7.

There's a Twig Filter named ```dict_s``` in templates.
Where in iTop code this would be ```Dict::S('languagestring')```, 
but it's the same as in iTop Portal templates.

Examples:
```
{{ 'UI:Menu:ReportGenerator:ShowReport'|dict_s }}
{{ 'Class:Ticket/Attribute:ref'|dict_s }}
```


# Using QR codes
See requirements!

A Twig filter is available to convert text/URLs to QR-code.
```
{{ 'this string will be converted'|qr }}
```


# Setting up PDF settings for BrowserShot

This extension can use a wrapper to generate PDF documents.
There's several PDF libraries available, each with their own benefits and limitations.
Natively, this extension uses **Spatie/BrowserShot** since it seemed to be stable and can handle modern web standards. At the moment, it's well maintained.  

Default example settings are included for Windows systems.

```
'jb-report-generator' => array(
	
	// Module specific settings go here, if any
	// This is a demo configuration for a Windows system
	'browsershot' => array(
		'node_binary' => 'node.exe', // Directory with node binary is in an environmental variable
		'npm_binary' => 'npm.cmd', // Directory with NPM cmd file is in an environmental variable
		'chrome_path' => 'C:/progra~1/Google/Chrome/Application/chrome.exe', // Directory with a Chrome browser executable
		
	),
	
);
```

In the URL, you can specify some additional optional parameters:

* page_format: A4
* timeout: defaults to 60


# Setting up external PDF renderer

The latest releases contain an experimental implementation  so you can use an external PDF renderer.  
Use case: basic webhosts often don't allow the necessary steps to set everything up correctly for BrowserShot/Puppeteer.

```
'jb-report-generator' => array(
	
	'mode' => 'external', // Defaults to 'browsershot'
	'pdf_external_renderer' => array(
		'url' => 'https://some-host.org/pdfproxy.php',
		'skip_certificate_check' => false, // Set to true to allow invalid or self-signed certificates
	),
	
);
```

As for the external PDF renderer:
A JSON payload is posted:  
```
{
 'data': 'Some HTML'
}
```

A JSON response is expected:  
```
{
 'error' => 0, // Could be a higher number + 'message' if there is an error
 'pdf' => '...', // Base64 encoded PDF
}
```


## Install

Install [Node.Js](https://nodejs.org/en/download/) on the web server.

NPM (Node Package Manager) needed:
* in directory of this extension: ```npm install puppeteer```

Use **composer** to install **Spatie/BrowserShot**

Don't forget to install Google Chrome.

Check if the "PATH" variable n (Windows) System Environment Variables includes the paths where nodejs.exe and npm.cmd are located.

### How to check node and npm installations

Check the versions by running this on the command line:
```
node -v
npm -v
```

Both commands should return version numbers when ran from a PHP script (mind the service/user account under which the web server is running).  

Quick troubleshoot script:
```
<?php 

	header('Content-Type: text/plain');
	
	echo 
		'Whoami: '.exec('whoami').PHP_EOL.
		'NPM: '.exec('npm -v').PHP_EOL.
		'Node:'.exec('node -v').PHP_EOL.
		'Chrome: '.exec('chrome -v');
		
```

## Hints

* Using HTML headers, you can build navigation in the PDF document
* Install the fonts on your local system rather than relying on web fonts
* It may be worth checking out Combodo's "iFrame dashlet" to embed reports

## Issues


### No PDF, Errors during rendering

These are personal notes I made while testing/debugging on Windows.

Check if the "PATH" variable n (Windows) System Environment Variables includes the paths where nodejs.exe and npm.cmd are located.

Sandbox mode is necessary for XAMPP x64 on Windows. 

Still having issues?
See if there's info in the error file (**%temp%\sf_proc_00.err**) (Symfony framework)


Various errors may occur, including E_CONNRESET.
It might also be wise to ignore https errors (self signed certificates) if CSS/JavaScript isn't loaded correctly.
```
[Error: ENOENT: no such file or directory, mkdtemp '\xampp\tmp\puppeteer_dev_chrome_profile-XXXXXX'] {
  errno: -4058,
  code: 'ENOENT',
  syscall: 'mkdtemp',
  path: '\\xampp\\tmp\\puppeteer_dev_chrome_profile-XXXXXX'
}
```

For the above error: check whether the path is correct.  
For instance, after moving an instance, the path may be different for the puppeteer_dev_chrome_profile-XXXXXX.  
Check whether the path to Chrome is properly configured, for example `C:/progra~1/Google/Chrome/Application/chrome.exe`  

Other recommended steps include removing **package-lock.json** and **node_modules** and running
```
npm install
npm cache clean --force
npm install -g npm
npm audit fix
npm install
```


```
Error: Failed to launch the browser process! spawn C:/progra~1/Google/Chrome/Application/ ENOENT
```
Full Chrome path must be provided.

### Images not loading

* By default, HTTPS errors are ignored.
* Images must be publicly accessible, as - for now - no authorization is included.
* The URL must be correct. Special attention should go to a work around that is already included for iTop environments (switch_env parameter).

## Incorrect rendering in PDF

For instance when displaying lots of data in a report and then rendering charts (Chart.js), the resulting PDF might have incorrect or even missing graphs.  
Adapt the default PDF implementation to allow for some time to process everything.

