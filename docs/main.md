# Implementing reports

* [Creating reports](reports/creatingreports.md)
* [Enabling PDF support](reports/pdfsupport/main.md)
* [Requirements](requirements.md)
* [Upgrade notes](upgrade_notes.md)



## PHP classes: Report vs. report tool

In this implementation, a **report processor** is something which either **enriches the data** (this could be transforming, linking different data, ...) and/or **provides new actions**.  
An example of a report tool is the PDF export option, which is included in this extension.

A **report UI element** is used to add a menu action or button in the front end.  
It is based on a certain condition (e.g. "if a list view of user requests is displayed, show this report option").  
It defines the action (just showing the report, showing a PDF version, attaching the PDF to the object, ...) that will be performed by one or more processors.  

The report processor and UI element are two separate things.


