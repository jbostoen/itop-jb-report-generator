# Best practices

## Escape output in your templates

Automatic escaping (autoescape) is **disabled** in the Twig environment of the report generator.  
This is deliberate: some values have to be rendered as HTML (for example the content of an `AttributeHTML` field, such as a ticket description).

The consequence is that **the person who writes the report is responsible for escaping values**. Twig prints exactly what it receives.

Why this matters:

* **HTML reports** are served from the same address as iTop. Unescaped values can run script in the context of the logged-in user (cross-site scripting).
* **PDF reports** are rendered by a headless browser on the server (or by an external service). Injected markup such as `<iframe>`, `<img>` or `<script>` can make that browser request internal addresses or local files, and the result ends up in the PDF.


### Which values can be trusted?

* `request.*` (URL parameters): **not trusted.** Fully controlled by whoever builds the link. Always escape.
* Text fields, titles, names, ... (`item.fields.*`): **not trusted.** Editable by users, also by users with limited rights (such as portal users). Escape them.
* HTML fields (`AttributeHTML`, for example `description`): **partly trusted.** iTop sanitizes HTML when it is saved (see `html_sanitizer` in the iTop configuration), so these can be output as is. Verify this for your setup.
* Numbers, dates and enum codes: **mostly trusted.** Still escape them when they are placed in a JavaScript or attribute context.
* Values built by your own extension code: **depends.** Escape or sanitize them when they contain anything that originates from users.


### How to escape

Use the Twig `escape` filter (`e`) with the strategy that matches the place where the value ends up:

* HTML text: `<p>{{ item.fields.title|e }}</p>`
* HTML attribute: `<a title="{{ item.fields.title|e('html_attr') }}">`
* JavaScript string: `console.log('{{ request.timeout|e('js') }}');`
* JavaScript data: `const oData = {{ item.fields|json_encode }};`
* URL parameter: `?name={{ item.fields.name|e('url') }}`
* CSS: `content: '{{ item.fields.name|e('css') }}';`

Notes:

* The strategy depends on the surrounding context, not on the value. A value inside `<script>` needs `js` (or `json_encode`), not `html`.
* `json_encode` escapes quotes, but not a closing script tag. Data that is embedded in a `<script>` block and may contain user text should be encoded with `json_encode(constant('JSON_HEX_TAG'))` or similar flags.
* Only skip escaping for values you know are safe HTML (see above).


### Typical mistakes

* Printing `request.*` in a title, heading, attribute or inside a JavaScript string.
* Printing a free-text field (title, name, comment) without `|e`, especially in reports for lists of tickets created by external users.
* Building JavaScript or JSON by concatenating strings. Use `|json_encode`.


### Extra care for PDF reports

* Do not include user-supplied HTML or URLs in PDF reports unless you trust them.
* Remote images and stylesheets are fetched by the server that renders the PDF. Prefer content from iTop itself (or `data:` URIs).
* If the PDF is rendered by [an external service](../pdfsupport/external.md), the full HTML of the report is sent to that service. Treat the report content accordingly.


## Other recommendations

* Restrict what a report can show: only output the fields that are needed, and keep reports free of secrets.
* Provide the report parameters you expect (for example `year`), validate them in your template or processor, and ignore everything else.
* When enriching data in PHP, see the documentation about enriching report data (PHP) for guidance on permissions.
