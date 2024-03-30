# Setting up external PDF renderer

If you are unable to set up BrowserShot on the webserver, you can use an external HTML to PDF service.

```
'jb-report-generator' => array(
	
	'mode' => 'external', // Defaults to 'browsershot'
	'pdf_external_renderer' => array(
		'url' => 'https://some-host.org/pdfproxy.php',
		'skip_certificate_check' => false, // Set to true to allow invalid or self-signed certificates
	),
	
);
```

As for the external PDF renderer: You'll likely need an intermediate script.
By default, a JSON payload is posted:  
```
{
 'data': 'Some HTML'
}
```

In return, a JSON response is expected:  
```
{
 'error' => 0, // Could be a higher number + 'message' if there is an error
 'pdf' => '...', // Base64 encoded PDF
}
```


