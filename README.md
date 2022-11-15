# DocBookTool Assets

This GH Pages branch stores static assets that are needed by DocBookTool users. Please see the documentation below on what assets are available and how to integrate them.

### Navigation JS

**!! This is a critical component of the DocBookTool and should be included or single-page sites will not work !!**

The navigation JS ensures that side navigation, and links within the documentation pointing to doc pages, make the correct page jumps for the structure of the site. Using this JS is intentionally setup to be a file inclusion and function invocation. Somewhere in your online Twig template:

```html
<script src="https://roave.com/DocBookTool/js/docbook-tool.js"></script>
<script>loadDocBookNavigation('My Page Title');</script>
```