# Upgrading

While backward compatibility is important, it's unfortunately not always feasible to improve this extension and not break things.  
Here, you'll find useful info when coming from an older version.

### From before 2024-12-16

Change name space: `namespace jb_itop_extensions\report_generator;` is now `JeffreyBostoenExtensions\ReportGenerator;`

### From before 2022-06-14

Some renaming was done to make things more clear:

| Before                  | After                        |
| ----------------------- | ---------------------------- |
| iReport                 | iReportUIElement             |
| DefaultReport           | AbstractReportUIElement      |
| iReportTool             | iReportProcessor             |
| RTParent                | ReportProcessorParent        |
| RTTwig                  | ReportProcessorTwig          |
| RTTwigToPDF             | ReportProcessorTwigToPDF     |

