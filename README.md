# MarkNav

MarkNav is a lightweight, **fully static** markdown browser for reading `.md` files from the local `data/` folder. It serves your markdown files as GitHub-styled HTML pages — no PHP, no database, no build step beyond regenerating a small file manifest.

## Features

- 100% static: just HTML, CSS, JavaScript, and your `.md` files.
- Renders Markdown to GitHub-flavored HTML in the browser using [marked](https://marked.js.org/) and [DOMPurify](https://github.com/cure53/DOMPurify).
- Automatic homepage listing of every markdown file in `data/`.
- Clean URLs such as `/01_the_timeline_and_the_origin` for each document (no `#`).
- Supports nested folders inside `data/`.
- Searches both filenames and full document contents (3+ characters), with Unicode-aware accent folding (Greek/Latin diacritics ignored).
- Rewrites in-document `*.md` links so cross-document navigation keeps working.
- LaTeX math rendering via [KaTeX](https://katex.org/) for both inline (`$…$`) and block (`$$…$$`) expressions.
- Drop-in **image gallery**: any image placed in `images/` shows up as a card on the homepage and opens in a built-in lightbox (keyboard arrows + Escape supported).
- GitHub-style document layout shared with the original CSS.

## Run Locally

MarkNav is a pure static site, so any HTTP server works. From the project folder:

```bash
cd marknav
python3 -m http.server 8000
```

or

```bash
npx serve -l 8000 .
```

Then open:

```text
http://localhost:8000
```

> **Note:** opening `index.html` directly via `file://` will not work because the browser blocks `fetch()` calls for local files. Use any small HTTP server.

## Content

Add markdown files to:

```text
marknav/data/
```

After adding, removing, or renaming files, regenerate the manifests:

```bash
./generate-manifest.sh
```

This rewrites `data/files.json` and `images/files.json`, which are the indexes used by the browser to discover documents and gallery images (static sites can't list directories).

### Images / gallery

Drop image files into:

```text
marknav/images/
```

Supported formats: `.png`, `.jpg` / `.jpeg`, `.gif`, `.webp`, `.svg`, `.avif`.

After adding files, run `./generate-manifest.sh` to refresh the gallery manifest. The new images will appear as a gallery section at the bottom of the homepage. Clicking a thumbnail opens a lightbox with:

- Keyboard arrows (`←` / `→`) to flip between images
- `Esc` or click on the backdrop to close
- Counter showing position (e.g. `2 / 7`)

### URLs

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

### Server requirement for clean URLs

Because URLs like `/example_document` don't correspond to real files on disk, the
server must fall back to `index.html` for unknown paths so `app.js` can take
over routing. A ready-made `.htaccess` is included for Apache. Other servers
need similar config:

**Apache** — already handled by the bundled `.htaccess` (needs `mod_rewrite`
and `AllowOverride All` on the directory).

**Nginx**:

```nginx
location /marknav/ {
  try_files $uri $uri/ /marknav/index.html;
}
```

**Python http.server / `npx serve`** — these don't have rewrite support, so
deep links won't survive a refresh. Navigating from the homepage works, but
typing `http://localhost:8000/example_document` directly will 404. Fine for
development; use a real server (Apache/Nginx/Caddy) in production.

**GitHub Pages** — copy `index.html` to `404.html` so unknown paths fall back
to the SPA.

## Project Structure

```text
marknav/
├── data/                    # Markdown content
│   └── files.json           # Auto-generated manifest of .md files
├── images/                  # Gallery images
│   └── files.json           # Auto-generated manifest of image files
├── index.html               # Single-page entry (home + document viewer)
├── app.js                   # Router, manifest loader, markdown rendering, gallery
├── style.css                # Home page + GitHub-like document + gallery styles
├── .htaccess                # Apache SPA fallback (clean URLs)
├── generate-manifest.sh     # Helper: rebuild both files.json manifests
└── README.md                # Project documentation
```

## How it Works

1. The browser loads `index.html`, which pulls in `marked.js` and `DOMPurify` from a CDN.
2. `app.js` fetches `data/files.json` to learn what documents exist.
3. The URL path decides what to render (via the History API):
   - `/` → homepage grid with search.
   - `/<path>` → fetches `data/<path>.md`, converts to HTML, sanitizes, and displays it with GitHub-style theming.
4. Cross-document links written as `[other doc](other_doc.md)` are rewritten on the fly so they navigate within the SPA.
5. Clicks on internal links are intercepted and pushed to the History API, so the URL stays clean and refreshes still land on the right document (with the SPA fallback configured server-side).

## Notes

MarkNav is intentionally small and self-contained. It does not aim to be a full GitHub Markdown implementation, but `marked` covers the vast majority of common Markdown syntax including tables, fenced code blocks, task lists, and inline HTML (sanitized).
