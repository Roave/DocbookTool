# Roave Docbook Tool

Static HTML and PDF generator tool for generating documentation from Markdown files.

 * Generates a deployable HTML file from Markdown documentation
 * Generates PDF files of the same documentation that can be deployed alongside
 * Link pages to a Confluence instance so the content can be mirrored there

## Running with Docker

A Docker image is provided with all the pre-built tools. You will need to map several volumes into the container:

 - `/docs-package` - this will be where the tool writes the output
 - `/docs-src/book` - the path containing the Markdown files to be rendered
 - `/docs-src/templates` - the `online.twig` and `pdf.twig` templates to use for rendering HTML/PDF respectively
 - `/docs-src/features` - if you have features, this should contain your features

Additionally, you can provide environment variables to override the default paths used, or to enable the Confluence
functionality.

```bash
# Will build the test fixtures and put in a folder called "build"
docker run \
  -v $(pwd)/test/fixture/docbook:/docs-src/book \
  -v $(pwd)/test/fixture/templates:/docs-src/templates \
  -v $(pwd)/test/fixture/feature:/docs-src/features \
  -v $(pwd)/build:/docs-package \
  --rm ghcr.io/roave/docbooktool:latest

# Will build your stuff - replace host paths as appropriate
docker run \
  -v $(pwd)/docs/book:/docs-src/book \
  -v $(pwd)/docs/templates:/docs-src/templates \
  -v $(pwd)/features:/docs-src/features \
  -v $(pwd)/build:/docs-package \
  --rm ghcr.io/roave/docbooktool:latest

# Will generate HTML, PDF, and update any configured Confluence pages
docker run \
  -v $(pwd)/docs/book:/docs-src/book \
  -v $(pwd)/docs/templates:/docs-src/templates \
  -v $(pwd)/features:/docs-src/features \
  -v $(pwd)/build:/docs-package \
  -e DOCBOOK_TOOL_CONFLUENCE_URL=https://confluence.mycompany.com \
  -e DOCBOOK_TOOL_CONFLUENCE_AUTH_TOKEN="<auth token>" \
  --rm ghcr.io/roave/docbooktool:latest --html --pdf --confluence
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

## Formatting

We have limited support for YAML front matter:

 * `title: Your title here` - when specified, this will be used as the page title (`{{ title }}` in template)
 * `pdf: true` - when specified, a PDF will be generated for this
 * `confluencePageId: 1234` - when specified, Confluence page `1234` will be updated (numeric ID only)
 * `order: 100` - when specified, pages are ordered by this. Defaults to 100. Matching values are sorted alphabetically.

Additionally, we have a special Markdown syntax:

 * `{{feature:test.feature}}` will render `$(DOCBOOK_TOOL_FEATURES_PATH)/test.feature` as a code block
 * Code blocks (triple-backtick) with the `puml` syntax will be converted into a PlantUML diagram. Note your diagram
   must start and end with `@startuml` and `@enduml` respectively.
 * `{{src-json:test.json}}` will render `$(DOCBOOK_TOOL_FEATURES_PATH)/test.json` as a code block. Only `json` is
   supported at the moment.

Example showing all syntax can be seen in `test/fixture/docbook/test.md`.

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
 * `DOCBOOK_TOOL_CONFLUENCE_URL` - the base URL of confluence (`/rest/api/content` is appended to this, so don't
   include that) (Required, if using `--confluence`)
   * Example: `https://confluence.mycompany.com`
 * `DOCBOOK_TOOL_CONFLUENCE_AUTH_TOKEN` - the `Authorization` header value to use (Required, if using `--confluence` in
   a non-interactive terminal).
   * Example using a [personal access token (PAT)](https://confluence.atlassian.com/enterprise/using-personal-access-tokens-1026032365.html): `Bearer MTA4NjU3Njg2OTEyOt53L29niGxOkuIZJpKcgjItNMoI`
   * Example using [basic auth](https://developer.atlassian.com/cloud/confluence/basic-auth-for-rest-apis/): `Basic bXktdXNlcm5hbWU6bXktcGFzc3dvcmQ=`
 * `DOCBOOK_TOOL_CONFLUENCE_SKIP_CONTENT_HASH_CHECKS` - Should the content hash check be skipped. Set to `yes` to skip
   the hash check. Note that this means every time the tool runs, the content will create a version in Confluence, even
   if nothing has changed. This is a workaround for some API issues with certain Confluence setups, so we don't
   recommend enabling this unless you have a specific need to.
   * Example: `no`
