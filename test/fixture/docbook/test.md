---
# Title can be used as {{ page.title }} in your Twig template
title: Great title

# Controls whether a PDF version of this page is included
pdf: true

# If this is set to an integer, we will update the page content for the corresponding Confluence page
confluencePageId: 1234

# If not specified, defaults to 100
order: 105
---
# Hello there this is a great file

This is some markdown

```json
{
    "some": true,
    "json": 123
}
```

## Subtitle

Links [here](https://www.google.com). **Bold**, _italic_, ~~strikethrough~~, `inline code`.

{{feature:test.feature}}

## A diagram

```puml
@startuml
Bob->Alice : hello
@enduml
```
