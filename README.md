# DocBookTool Assets

This GH Pages branch stores static assets that are needed by DocBookTool users. Please see the documentation below on what assets are available and how to integrate them.

### Navigation JS

The navigation JS ensures that side navigation, and links within the documentation pointing to doc pages, make the correct page jumps for single-page sites. Using this JS is intentionally setup to be a file inclusion and function invocation. Somewhere in your online Twig template:

```html
<script src="https://roave.com/DocbookTool/js/docbook-tool.js"></script>
<script>loadDocBookNavigation('My Page Title');</script>
```

The argument passed will be combined with the text of the active tab or page link to update the Document's title. For example, if you select a tab or side navigation link that has the content 'My Docs Page', with the argument passed in the example, the document's title would be updated to `My Docs Page :: My Page Title`.

This JS is necessary if you're making use of Roave's common Twig template. If you are not using this template it is your responsibility to ensure navigation works for the structure of your site.
