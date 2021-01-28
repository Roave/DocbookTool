# Roave Docbook Tool

Static HTML and PDF generator tool for generating documentation from Markdown files.

 * Generates a deployable HTML file from Markdown documentation
 * Generates PDF files of the same documentation that can be deployed alongside
 * Link pages to a Confluence instance so the content can be mirrored there

## Usage

```bash
bin/docbook-tool [--html] [--pdf] [--confluence]
```

## Environment variables

 * `DOCBOOK_TOOL_CONTENT_PATH` - the path where your Markdown documentation is kept
   * Example: `/path/to/myproject/docs/book`
 * `DOCBOOK_TOOL_TEMPLATE_PATH` - the path to your Twig templates called `online.twig` and `pdf.twig`
   * Example: `/path/to/myproject/docs/template`
 * `DOCBOOK_TOOL_FEATURES_PATH` - the base path from where features are stored
   * Example: `/path/to/myproject/features`
 * `DOCBOOK_TOOL_OUTPUT_HTML_FILE` - where to generate the HTML documentation
   * Example: `/path/to/myproject/build/docs/index.html`
 * `DOCBOOK_TOOL_OUTPUT_PDF_PATH` - where to generate the PDF files, if used
   * Example: `/path/to/myproject/build/docs/pdf`
 * `DOCBOOK_TOOL_CONFLUENCE_URL` - the base URL of confluence (`/rest/api/content` is appended to this, so don't include that)
   * Example: `https://confluence.mycompany.com`
 * `DOCBOOK_TOOL_CONFLUENCE_AUTH_TOKEN` - the `Authorization` header value to use
   * Example: `Basic bXktdXNlcm5hbWU6bXktcGFzc3dvcmQ=`
