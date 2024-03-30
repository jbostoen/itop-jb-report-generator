
# PDF support - using BrowserShot

This extension can use a wrapper to generate PDF documents.
There are several PDF libraries available, each with their own benefits and limitations.
Natively, this extension now uses **Spatie/BrowserShot** since it seemed to be stable and can handle modern web standards. At the moment, it's well maintained.  

With the previous implementation (wkhtmltopdf), it was possible to use HTML headings to build a Table of Content.  
Unfortunately, with Spatie's BrowserShot, it's not possible at the moment.

Default example settings are included for the Microsoft Windows operating system.

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

* `page_format`: A4
* `timeout`: defaults to 60


# Installation of BrowserShot

Install [Node.Js](https://nodejs.org/en/download/) on the web server.

NPM (Node Package Manager) needed:
* In directory of this extension: ```npm install puppeteer```

Use **composer** to install **Spatie/BrowserShot**

Don't forget to install Google Chrome.


## How to check node and npm installations


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

* Install the fonts on the system which renders the PDF. Don't rely on web fonts.
* It may be worth checking out Combodo's "iFrame dashlet" to embed reports.


# Issues


## No PDF, Errors during rendering

These are personal notes I made while testing/debugging on a Microsoft Windows operating system.


1) Check if the "PATH" variable in System Environment Variables includes the paths where nodejs.exe and npm.cmd are located.

2) See if there's info in the error file (**%temp%\sf_proc_00.err**) (Symfony framework)


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

The full Chrome path must be specified.

## Images not loading

* By default, HTTPS errors are ignored.
* Images must be publicly accessible, as - for now - no authorization is included.
* The URL must be correct. Special attention should go to a work around that is already included for iTop environments (switch_env parameter).

## Incorrect rendering in PDF

When displaying lots of data in a report and then rendering charts (Chart.js), the resulting PDF might have incorrect or even missing graphs.  
Adapt the default PDF implementation to allow for some time to process everything.

