# Upgrading

While backward compatibility is important, it's unfortunately not always feasible to improve this extension and not break things.  
Here, you'll find useful info when coming from an older version.

### Upgrade to 2025-04-28

While not deprecated yet; it's no longer recommended to use the previous `lib.x` variables to include CSS or JavaScript.

Two new Twig filers have been added, to generate `link` tags for stylesheets and `script` tags for JavaScript files (including an integrity attribute).

Instead, you can now use this generic approach:

```
"frontend-lib-name"|html_css
"frontend-lib-name"|html_script
```

Available pre-bundled libraries:

```
Bootstrap
FontAwesome
Jquery
```

As of now, the values above are case-sensitive and may differ from how they're usually stylized.

So for example:
```
"Bootstrap"|html_css
"Bootstrap"|html_script
```



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

