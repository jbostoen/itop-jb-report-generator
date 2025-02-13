# Upgrading

While backward compatibility is important, it's unfortunately not always feasible to improve this extension and not break things.  
Here, you'll find useful info when coming from an older version.

### Upgrade to 2025-02-10

`namespace JeffreyBostoenExtensions\ReportGenerator;`
is now
`namespace JeffreyBostoenExtensions\Reporting;`

Processors and UI elements have their own namespace now:
`namespace JeffreyBostoenExtensions\Reporting\Processor;`
`namespace JeffreyBostoenExtensions\Reporting\UI;`


Some renaming was done to simplify things:


| Before                  | After                        | Namespace                                     |
| ----------------------- | ---------------------------- | --------------------------------------------- |
| AbstractReportUIElement | Base                         | JeffreyBostoenExtensions\Reporting\Processor  |
| iReportUIElement        | iBase                        | JeffreyBostoenExtensions\Reporting\Processor  |
| ReportProcessorParent   | Base                         | JeffreyBostoenExtensions\Reporting\UI         |
| ReportProcessorParent   | iBase                        | JeffreyBostoenExtensions\Reporting\UI         |
| ReportGeneratorHelper   | Helper                       | JeffreyBostoenExtensions\Reporting            |


This should make this extension easier to maintain.


### Upgrade to 2024-12-16

Change name space: `namespace jb_itop_extensions\report_generator;` is now `namespace JeffreyBostoenExtensions\ReportGenerator;`

### Upgrade to 2022-06-14

Some renaming was done to make things more clear:

| Before                  | After                        |
| ----------------------- | ---------------------------- |
| iReport                 | iReportUIElement             |
| DefaultReport           | AbstractReportUIElement      |
| iReportTool             | iReportProcessor             |
| RTParent                | ReportProcessor        |
| RTTwig                  | ReportProcessorTwig          |
| RTTwigToPDF             | ReportProcessorTwigToPDF     |

