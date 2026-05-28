# MarkNav

MarkNav is a lightweight PHP markdown browser for reading `.md` files from the local `data/` folder. It provides a browsable home page, clean document routes, and GitHub-like markdown styling without needing a database or build step.

## Features

- Automatically lists markdown files from `data/`.
- Opens each file with an extensionless URL, for example `/01_the_timeline_and_the_origin`.
- Supports nested folders inside `data/`.
- Renders common Markdown syntax, including headings, paragraphs, links, images, lists, blockquotes, horizontal rules, tables, inline code, and fenced code blocks.
- Uses `style.css` for the home page and GitHub-like document styling.
- Shows available markdown paths on 404 pages to make missing routes easy to debug.

## Run Locally

From the MarkNav folder:

```bash
cd marknav
php -S localhost:8000
```

Then open:

```text
http://localhost:8000
```

## Content

Add markdown files to:

```text
marknav/data/
```

A file named:

```text
marknav/data/example_document.md
```

is available at:

```text
http://localhost:8000/example_document
```

Nested files also work. For example:

```text
marknav/data/notes/example.md
```

is available at:

```text
http://localhost:8000/notes/example
```

## Project Structure

```text
marknav/
├── data/       # Markdown content
├── index.php   # Router, home page, and Markdown renderer
├── style.css   # Home page and GitHub-like document styles
└── README.md   # Project documentation
```

## Notes

MarkNav is intentionally small and self-contained. It does not aim to be a full GitHub Markdown implementation, but it renders the most common Markdown patterns in a similar visual style for local browsing.
