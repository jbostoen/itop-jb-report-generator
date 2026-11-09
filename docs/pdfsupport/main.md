
# Configuring PDF support

Note: Whether or not you'll be able to set this up, depends a lot on how much control you have over the webserver.

Currently, there are two built-in scenarios:

* [Using BrowserShot](browsershot.md): If you have full control over the webserver, you can set up BrowserShot. PDF generation happens on the web server.
* [Using an external PDF renderer](external.md): If you don't have full control over the web server, you can point to an external service to convert HTML to PDF.

# Attaching a PDF to an object

The "Attach PDF" action (`BaseAttachPDF`) stores the generated PDF as an `Attachment` of the object. Since this changes data:

* The menu item or button contains a single-use token (`transaction_id`). The action is refused without a valid token, so an old or copied link does not work anymore, and a link on another website can not trigger it.
* The user needs permission to create attachments **and** to modify the object the PDF is attached to.

Custom UI elements which trigger the attach action through `BaseAttachPDF` get this automatically. A custom UI element which changes data in another way can return `true` from `RequiresTransaction()` to get a token in its URL, and should verify it with `utils::IsTransactionValid()`.
