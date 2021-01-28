# Roave Docbook Tool

Static HTML and PDF generator tool for generating documentation from Markdown files.

 * Generates a deployable HTML file from Markdown documentation
 * Generates PDF files of the same documentation that can be deployed alongside
 * Link pages to a Confluence instance so the content can be mirrored there

## Usage

```bash
bin/docbook-tool [--html] [--pdf] [--confluence]
```

For example, this command would generate only the HTML documentation:

```bash
$ DOCBOOK_TOOL_CONTENT_PATH=/path/to/myproject/docs/book \
> DOCBOOK_TOOL_TEMPLATE_PATH=/path/to/myproject/docs/template \
> DOCBOOK_TOOL_OUTPUT_HTML_FILE=/path/to/myproject/build/docs.html \
> bin/docbook-tool --html
[2021-01-28T12:28:41.000628+00:00] cli.INFO: Writing HTML output to /path/to/myproject/build/docs.html [] []
$
```

## Environment variables

 * `DOCBOOK_TOOL_CONTENT_PATH` - the path where your Markdown documentation is kept (Required)
   * Example: `/path/to/myproject/docs/book`
 * `DOCBOOK_TOOL_TEMPLATE_PATH` - the path to your Twig templates called `online.twig` and `pdf.twig` (Required)
   * Example: `/path/to/myproject/docs/template`
 * `DOCBOOK_TOOL_FEATURES_PATH` - the base path from where features are stored (Optional)
   * Example: `/path/to/myproject/features`
 * `DOCBOOK_TOOL_OUTPUT_HTML_FILE` - where to generate the HTML documentation (Required, if using `--html`)
   * Example: `/path/to/myproject/build/docs/index.html`
 * `DOCBOOK_TOOL_OUTPUT_PDF_PATH` - where to generate the PDF files, if used (Required, if using `--pdf`)
   * Example: `/path/to/myproject/build/docs/pdf`
 * `DOCBOOK_TOOL_CONFLUENCE_URL` - the base URL of confluence (`/rest/api/content` is appended to this, so don't include that) (Required, if using `--confluence`)
   * Example: `https://confluence.mycompany.com`
 * `DOCBOOK_TOOL_CONFLUENCE_AUTH_TOKEN` - the `Authorization` header value to use (Required, if using `--confluence` in a non-interactive terminal)
   * Example: `Basic bXktdXNlcm5hbWU6bXktcGFzc3dvcmQ=`
