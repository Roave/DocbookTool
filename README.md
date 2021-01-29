# Roave Docbook Tool

Static HTML and PDF generator tool for generating documentation from Markdown files.

 * Generates a deployable HTML file from Markdown documentation
 * Generates PDF files of the same documentation that can be deployed alongside
 * Link pages to a Confluence instance so the content can be mirrored there

## Running with Docker

@todo - publish to a container registry, update this guide to remove the `docker build` (since it will be done by CI)

A Docker image is provided with all the pre-built tools. You will need to map several volumes into the container:

 - `/docs-package` - this will be where the tool writes the output
 - `/docs-src/book` - the path containing the Markdown files to be rendered
 - `/docs-src/templates` - the `online.twig` and `pdf.twig` templates to use for rendering HTML/PDF respectively
 - `/docs-src/features` - if you have features, this should contain your features

Additionally, you can provide environment variables to override the default paths used, or to enable the Confluence
functionality.

```bash
docker build -t roave-docbook-tool .

# Will build the test fixtures and put in a folder called "build"
docker run -v $(pwd)/build:/docs-package --rm roave-docbook-tool

# Will build your stuff - replace host paths as appropriate
docker run \
  -v $(pwd)/docs/book:/docs-src/book \
  -v $(pwd)/docs/templates:/docs-src/templates \
  -v $(pwd)/features:/docs-src/features \
  -v $(pwd)/build:/docs-package \
  --rm roave-docbook-tool

# Will generate HTML, PDF, and update any configured Confluence pages
docker run \
  -v $(pwd)/docs/book:/docs-src/book \
  -v $(pwd)/docs/templates:/docs-src/templates \
  -v $(pwd)/features:/docs-src/features \
  -v $(pwd)/build:/docs-package \
  -e DOCBOOK_TOOL_CONFLUENCE_URL=https://confluence.mycompany.com \
  -e DOCBOOK_TOOL_CONFLUENCE_AUTH_TOKEN="Basic bXktdXNlcm5hbWU6bXktcGFzc3dvcmQ=" \
  --rm roave-docbook-tool --html --pdf --confluence
```

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
