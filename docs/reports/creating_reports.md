# Adding reports

* Create a separate iTop extension.

* For basic reports (HTML or PDF), add a file structure: templates/ClassName/details-or-list/filename.ext
  * Example: templates/UserRequest/details/ticket.html
  
* Create a PHP file which is loaded through the extension: extend the abstract class ```Base``` or one of its child classes.
  * Set a condition which determines when the report button should be shown (for which classes, for which view, ...).
  
  
Below you'll find a basic example to add a "Show PDF" button.

For a "Show report" button that shows a HTML report, use `extends Base` instead of `extends BasePDF`.
 
```
<?php

namespace JeffreyBostoenExtensions\Reporting\UI;

// iTop internals
use DBObjectSet;
use Dict;

/**
 * Class UserRequestDetails. Enables a "Show PDF" button in iTop's GUI.
 */
abstract class UserRequestDetails extends BaseShowPDF {
	
	/**
	 *@inheritDoc
	 */
	public static function GetURLParameters(DBObjectSet $oSet_Objects, $sView) : array {

		return [
			// The name of the template.
			// The modern implementation expects a full relative path (compared to your new iTop extension's directory).
			'template' => 'reports/UserRequest/details_ticket.html',
		];
		
	}
	
	
	/**
	 * @inheritDoc
	 */
	public static function IsApplicable() : bool {
	
		return ($sView == 'details' && $oSet_Objects->GetClass() == 'UserRequest');
		
	}
	
}
```


# Variables in reports

The reports are rendered using [Twig](https://github.com/twigphp/Twig) .  


## Single item (details view)

For details (single object), use the variable ```item```. It exposes the ```key``` and ```fields``` (See [Combodo's iTop REST Documentation](https://www.itophub.io/wiki/page?id=latest:advancedtopics:rest_json), it's similar). 


Example: 
```
item.fields.description
```
 
As a bonus: It's possible to use ```item.attachments```. 
```
{% for attachment in item.attachments %} ... {% endfor %}
``` 

The following attachment properties are exposed: 

```
attachment.fields.contents.mimetype
attachment.fields.contents.data
attachment.fields.contents.filename
```

For example, this makes it possible to include attached images and show them in the PDF.


## Multiple items (list view)

For lists (single or multiple objects), you can use ```item``` and create things like ```{% for item in items % } ... {% endfor %}```

Attachments are also available for each item.


## Miscellaneous variables


The following variables are available to use in the reports:

**iTop**

* ```itop.env```: iTop environment.
* ```itop.reporting_url```: The URL pointing to the "reporting.php" file; containing the default parameters.
* ```itop.root_url```: iTop root URL.


# Twig filters


## Using iTop language strings


There's a Twig Filter named ```dict_s``` in templates.
Where in iTop code this would be ```Dict::S('languagestring')```, 
but it's the same as in iTop Portal templates.

Examples:
```
{{ 'UI:Menu:ReportGenerator:ShowReport'|dict_s }}
{{ 'Class:Ticket/Attribute:ref'|dict_s }}
```



Since this extension at some point used a more recent version of Twig than the native one in iTop, the available filters are a bit different.

## Object URL

It's possible to generate an object URL (to the iTop object's details page) with a simple filter.

```
{{ item.key|make_object_url(item.class) }}
```

## Popular frameworks


These come pre-bundled.


| Front end library          | CSS | JavaScript |
|--------------------------- | ---------------  |
| Bootstrap                  | Yes | Yes        |
| FontAwesome                | Yes | No         |
| Jquery                     | No  | Yes        |

Usage:

```
"Bootstrap"|html_css
"Bootstrap"|html_script
```



## Using QR codes

See requirements!

This filter will turn any string into a QR code.

```
{{ 'this string will be converted'|qr }}
```

Here's an example to generate a QR code for an object:

```
{{ item.key|make_object_url(item.class)|qr }}
```




# PHP classes: Report vs. report tool

In this implementation, a **report processor** either **enriches the data** (this could be transforming, linking different data, ...) and/or **provides new actions**.  
An example of a report tool is the PDF export option, included in this extension.

A **report UI element** is used to add a menu action or button in the user interface (object list or details).  
It is based on a certain condition (e.g. "if a list view of user requests is displayed, show this report option").  
It defines the action (just showing the report, showing a PDF version, attaching the PDF to the object, ...) that will be performed by one or more processors.  

The report processor and UI element are two separate things.


