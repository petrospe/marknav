/*
 * MarkNav - client-side markdown browser.
 *
 * Loads a manifest of .md files from data/files.json, then either:
 *   - Shows a homepage grid with searchable cards, OR
 *   - Fetches a specific .md file and renders it as GitHub-flavored HTML.
 *
 * Routing uses the History API (clean URLs, no '#'):
 *   <base>/                       -> homepage
 *   <base>/<filename>             -> render data/<filename>.md
 *   <base>/<folder>/<filename>    -> render data/<folder>/<filename>.md
 *
 * This requires the server to fall back to index.html for unknown paths.
 * For Apache see the bundled .htaccess; for Nginx use `try_files`.
 */

(function () {
  'use strict';

  // BASE_PATH is the URL prefix MarkNav is mounted at, e.g. "/marknav/" or "/".
  // We derive it from the location of this <script> so the app works from any subfolder.
  const BASE_PATH = (function () {
    const script = Array.from(document.scripts).find(
      (s) => s.src && /(?:^|\/)app\.js(?:\?|$)/.test(s.src)
    );
    if (script) {
      const url = new URL('./', script.src);
      return url.pathname;
    }
    return '/';
  })();

  const DATA_DIR = BASE_PATH + 'data/';
  const MANIFEST_URL = DATA_DIR + 'files.json';

  const FOLDER_ICONS = {
    courses: '📚',
    docs: '📖',
    tutorials: '🎓',
    notes: '📝',
    projects: '💼',
    archive: '🗄️',
    blog: '✍️',
    recipes: '🍳',
    guides: '🧭',
  };

  // In-memory cache for the file index and document contents.
  const state = {
    manifest: null,
    documents: [],
    rootDocs: [],
    foldered: {},
    contentCache: new Map(),
    contentLoading: new Map(),
    searchIndexLoaded: false,
  };

  document.addEventListener('DOMContentLoaded', init);

  async function init() {
    if (!window.marked || !window.DOMPurify || !window.katex) {
      await waitForGlobals();
    }

    configureMarked();

    try {
      await loadManifest();
    } catch (err) {
      showFatalError(err);
      return;
    }

    setupSearch();
    setupNavigation();
    setupHomeLinks();

    // If we landed here via the legacy "#/" hash, upgrade it to a clean URL.
    if (window.location.hash.startsWith('#/')) {
      const target = window.location.hash.replace(/^#\/?/, '');
      history.replaceState({}, '', BASE_PATH + target);
    }

    route();
  }

  function waitForGlobals() {
    return new Promise((resolve) => {
      const check = () => {
        if (window.marked && window.DOMPurify && window.katex) resolve();
        else setTimeout(check, 25);
      };
      check();
    });
  }

  function configureMarked() {
    window.marked.setOptions({
      gfm: true,
      breaks: false,
      headerIds: true,
      mangle: false,
    });
  }

  async function loadManifest() {
    const res = await fetch(MANIFEST_URL, { cache: 'no-cache' });
    if (!res.ok) {
      throw new Error(
        `Could not load ${MANIFEST_URL} (HTTP ${res.status}). ` +
          'Run ./generate-manifest.sh to create it.'
      );
    }
    const data = await res.json();
    const files = Array.isArray(data.files) ? data.files : [];

    state.manifest = data;
    state.documents = files
      .filter((f) => typeof f === 'string' && f.endsWith('.md'))
      .map(normalizeFileEntry)
      .sort((a, b) => a.urlPath.localeCompare(b.urlPath));

    state.rootDocs = state.documents.filter((d) => !d.folder);
    state.foldered = state.documents.reduce((acc, doc) => {
      if (!doc.folder) return acc;
      (acc[doc.folder] = acc[doc.folder] || []).push(doc);
      return acc;
    }, {});

    const total = state.documents.length;
    const pluralized = total === 1 ? 'document' : 'documents';
    document.getElementById('stats-pill').textContent =
      `📄 ${total} ${pluralized} available`;
    document.getElementById('document-count').textContent =
      `${total} ${pluralized}`;
  }

  function normalizeFileEntry(filePath) {
    const cleaned = filePath.replace(/^\.\//, '');
    const parts = cleaned.split('/');
    const fileName = parts.pop();
    const baseName = fileName.replace(/\.md$/i, '');
    const folder = parts.length ? parts.join('/') : null;
    const urlPath = folder ? `${folder}/${baseName}` : baseName;
    const display = baseName.replace(/_/g, ' ');

    return {
      relativePath: cleaned,
      folder,
      fileName,
      baseName,
      urlPath,
      display,
    };
  }

  function buildHref(urlPath) {
    if (!urlPath) return BASE_PATH;
    return BASE_PATH + encodePath(urlPath);
  }

  function getRoute() {
    let path = window.location.pathname;
    if (path.startsWith(BASE_PATH)) {
      path = path.slice(BASE_PATH.length);
    }
    path = path.replace(/^\/+/, '').replace(/\/+$/, '');
    try {
      path = decodeURIComponent(path);
    } catch (_) {
      /* keep raw */
    }
    return path;
  }

  function setupHomeLinks() {
    document.querySelectorAll('[data-home-link]').forEach((el) => {
      el.setAttribute('href', BASE_PATH);
    });
  }

  function setupNavigation() {
    // Intercept clicks on internal links so we can use pushState.
    document.addEventListener('click', (event) => {
      if (event.defaultPrevented) return;
      if (event.button !== 0) return;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

      const anchor = event.target.closest('a[href]');
      if (!anchor) return;
      if (anchor.target && anchor.target !== '_self') return;
      if (anchor.hasAttribute('download')) return;

      const url = new URL(anchor.href, document.baseURI);
      if (url.origin !== window.location.origin) return;
      if (!url.pathname.startsWith(BASE_PATH)) return;

      const rest = url.pathname.slice(BASE_PATH.length);
      // Don't intercept direct links to real files (markdown, assets, etc.).
      if (rest.startsWith('data/')) return;
      if (/\.(css|js|json|md|png|jpe?g|gif|svg|webp|pdf|ico|map)$/i.test(rest)) return;

      event.preventDefault();
      const target = url.pathname + url.search + url.hash;
      if (target !== window.location.pathname + window.location.search + window.location.hash) {
        history.pushState({}, '', target);
      }
      route();
    });

    window.addEventListener('popstate', route);
  }

  function route() {
    const path = getRoute();
    if (!path) {
      showHome();
      return;
    }
    const doc = state.documents.find((d) => d.urlPath === path);
    if (doc) {
      showDocument(doc);
    } else {
      showNotFound(path);
    }
  }

  function setView(viewId) {
    ['view-home', 'view-document', 'view-404'].forEach((id) => {
      document.getElementById(id).hidden = id !== viewId;
    });
    document.body.classList.toggle('body-home', viewId === 'view-home');
    window.scrollTo({ top: 0 });
  }

  function showHome() {
    setView('view-home');
    document.title = 'MarkNav · Document Browser';
    renderHomeGrid();
    if (!state.searchIndexLoaded) {
      preloadAllContents();
    }
  }

  function renderHomeGrid() {
    const container = document.getElementById('home-content');
    container.innerHTML = '';

    if (state.documents.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <div class="emoji">📭</div>
          <h3>No markdown files found</h3>
          <p>Add some <code>.md</code> files to the <code>data/</code> folder and run <code>./generate-manifest.sh</code>.</p>
        </div>`;
      return;
    }

    if (state.rootDocs.length) {
      const section = document.createElement('section');
      section.innerHTML = `<h2 class="section-title">📄 Documents</h2>`;
      const grid = document.createElement('div');
      grid.className = 'files-grid';
      state.rootDocs.forEach((doc) => grid.appendChild(buildCard(doc, '📄')));
      section.appendChild(grid);
      container.appendChild(section);
    }

    Object.keys(state.foldered)
      .sort()
      .forEach((folder) => {
        const icon = FOLDER_ICONS[folder.toLowerCase()] || '📁';
        const section = document.createElement('div');
        section.className = 'folder-section';
        section.dataset.folder = folder;
        section.innerHTML = `
          <div class="folder-title">
            <span>${icon}</span>
            <h2>${escapeHtml(capitalize(folder.replace(/_/g, ' ')))}</h2>
          </div>`;
        const grid = document.createElement('div');
        grid.className = 'files-grid';
        grid.dataset.folder = folder;
        state.foldered[folder].forEach((doc) =>
          grid.appendChild(buildCard(doc, '📝'))
        );
        section.appendChild(grid);
        container.appendChild(section);
      });
  }

  function buildCard(doc, icon) {
    const card = document.createElement('div');
    card.className = 'file-card';
    card.dataset.name = doc.baseName;
    card.dataset.searchBase = normalizeForSearch(
      `${doc.baseName} ${doc.relativePath} ${doc.display}`
    );
    card.innerHTML = `
      <div class="file-icon">${icon}</div>
      <div class="file-name">${escapeHtml(doc.display)}</div>
      <div class="file-path">${escapeHtml(doc.relativePath)}</div>
      <a href="${escapeHtml(buildHref(doc.urlPath))}" class="file-link">Read more →</a>`;
    return card;
  }

  // Lowercase + strip combining diacritics + unify Greek sigma so searching
  // "ελληνικα" matches "Ελληνικά", "cafe" matches "café", and "ελληνας" matches
  // "Έλληνας" (final-sigma 'ς' vs medial-sigma 'σ').
  function normalizeForSearch(value) {
    return String(value)
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/ς/g, 'σ');
  }

  function setupSearch() {
    const input = document.getElementById('searchInput');
    if (!input) return;
    input.addEventListener('input', filterDocs);
  }

  function filterDocs() {
    const input = document.getElementById('searchInput');
    const rawTerm = (input.value || '').trim();
    const term = normalizeForSearch(rawTerm);
    const shouldFilter = term.length >= 3;
    const cards = document.querySelectorAll('#view-home .file-card');

    cards.forEach((card) => {
      const baseText = card.dataset.searchBase || '';
      const contentText = card.dataset.searchContent || '';
      const matches =
        !shouldFilter || baseText.includes(term) || contentText.includes(term);
      card.style.display = matches ? '' : 'none';
    });

    document.querySelectorAll('#view-home .folder-section').forEach((section) => {
      const visible = section.querySelectorAll('.file-card:not([style*="display: none"])');
      section.style.display = visible.length ? '' : 'none';
    });
  }

  async function preloadAllContents() {
    state.searchIndexLoaded = true;
    await Promise.all(
      state.documents.map((doc) =>
        loadDocumentContent(doc)
          .then((md) => {
            const card = document.querySelector(
              `#view-home .file-card[data-name="${cssEscape(doc.baseName)}"]`
            );
            if (card) {
              card.dataset.searchContent = normalizeForSearch(md);
            }
          })
          .catch(() => {/* ignore individual fetch failures */})
      )
    );
  }

  async function showDocument(doc) {
    setView('view-document');
    populateDocumentNav(doc);
    document.getElementById('document-active-title').textContent = doc.display;
    document.title = `${doc.display} · MarkNav`;

    const content = document.getElementById('markdown-content');
    content.innerHTML = '<p class="doc-loading">Loading document…</p>';

    try {
      const md = await loadDocumentContent(doc);
      const { stripped, blocks } = extractMath(md);
      const parsed = window.marked.parse(stripped);
      const withMath = restoreMath(parsed, blocks);
      const html = window.DOMPurify.sanitize(withMath, {
        ADD_TAGS: ['math', 'mrow', 'mi', 'mo', 'mn', 'msup', 'msub', 'msubsup', 'mfrac', 'msqrt', 'mtext', 'mspace', 'semantics', 'annotation'],
        ADD_ATTR: ['xmlns', 'encoding'],
      });
      content.innerHTML = html;
      rewriteInternalLinks(content);
    } catch (err) {
      content.innerHTML = `
        <h1>Could not load document</h1>
        <p>${escapeHtml(err.message || String(err))}</p>
        <p><a href="${escapeHtml(BASE_PATH)}">← Back to home</a></p>`;
    }
  }

  function populateDocumentNav(activeDoc) {
    const dropdown = document.getElementById('document-pages-dropdown');
    dropdown.innerHTML = '';
    state.documents.forEach((doc) => {
      const isActive = doc.urlPath === activeDoc.urlPath;
      const el = document.createElement(isActive ? 'span' : 'a');
      el.className = 'document-pages-item' + (isActive ? ' is-active' : '');
      if (el.tagName === 'A') {
        el.href = buildHref(doc.urlPath);
      }
      el.textContent = doc.display;
      dropdown.appendChild(el);
    });
  }

  // Math handling: pull $$...$$ and $...$ out of the markdown BEFORE marked.js
  // sees them, so syntax like \mathcal{M}_{11} doesn't get mangled by markdown's
  // emphasis/underscore rules. We replace each block with an opaque placeholder,
  // then swap KaTeX-rendered HTML back in after marked is done.
  const MATH_PLACEHOLDER_PREFIX = '\u0000MARKNAV_MATH_';
  const MATH_PLACEHOLDER_SUFFIX = '\u0000';

  function extractMath(markdown) {
    const blocks = [];
    const codeSpans = [];
    const CODE_PREFIX = '\u0000MARKNAV_CODE_';
    const CODE_SUFFIX = '\u0000';

    // Protect fenced code blocks and inline code from math extraction so things
    // like `console.log("$" + x)` aren't misread as math.
    let stripped = markdown.replace(/```[\s\S]*?```/g, (match) => {
      const idx = codeSpans.length;
      codeSpans.push(match);
      return `${CODE_PREFIX}${idx}${CODE_SUFFIX}`;
    });
    stripped = stripped.replace(/`[^`\n]+`/g, (match) => {
      const idx = codeSpans.length;
      codeSpans.push(match);
      return `${CODE_PREFIX}${idx}${CODE_SUFFIX}`;
    });

    // Block math: $$ ... $$
    stripped = stripped.replace(/\$\$([\s\S]+?)\$\$/g, (_, expr) => {
      const idx = blocks.length;
      blocks.push({ expr: expr.trim(), display: true });
      return `\n\n${MATH_PLACEHOLDER_PREFIX}${idx}${MATH_PLACEHOLDER_SUFFIX}\n\n`;
    });

    // Inline math: $ ... $   (requires non-whitespace next to delimiters to
    // avoid matching prices like "$5 and $10")
    stripped = stripped.replace(
      /(^|[^\\$])\$(?!\s)([^\n$]+?)(?<!\s)\$(?!\$)/g,
      (_, prefix, expr) => {
        const idx = blocks.length;
        blocks.push({ expr: expr.trim(), display: false });
        return `${prefix}${MATH_PLACEHOLDER_PREFIX}${idx}${MATH_PLACEHOLDER_SUFFIX}`;
      }
    );

    // Put protected code spans back so marked can render them normally.
    const codeRe = new RegExp(CODE_PREFIX + '(\\d+)' + CODE_SUFFIX, 'g');
    stripped = stripped.replace(codeRe, (_, idx) => codeSpans[Number(idx)] || '');

    return { stripped, blocks };
  }

  function restoreMath(html, blocks) {
    if (!blocks.length) return html;
    const re = new RegExp(
      MATH_PLACEHOLDER_PREFIX + '(\\d+)' + MATH_PLACEHOLDER_SUFFIX,
      'g'
    );
    return html.replace(re, (_, idx) => {
      const block = blocks[Number(idx)];
      if (!block) return '';
      try {
        return window.katex.renderToString(block.expr, {
          displayMode: block.display,
          throwOnError: false,
          strict: 'ignore',
        });
      } catch (e) {
        return `<code class="math-error">${escapeHtml(block.expr)}</code>`;
      }
    });
  }

  function rewriteInternalLinks(root) {
    // Convert intra-doc links like "01_the_timeline_and_the_origin.md" or "./foo.md"
    // into clean SPA URLs.
    root.querySelectorAll('a[href]').forEach((a) => {
      const href = a.getAttribute('href');
      if (!href) return;
      if (/^[a-z]+:\/\//i.test(href) || href.startsWith('#') || href.startsWith('mailto:')) {
        return;
      }
      const cleaned = href.replace(/^\.\//, '');
      const match = cleaned.match(/^(.+?)\.md(#.*)?$/i);
      if (match) {
        const targetPath = match[1];
        const fragment = match[2] || '';
        a.setAttribute('href', buildHref(targetPath) + fragment);
      }
    });
  }

  async function loadDocumentContent(doc) {
    if (state.contentCache.has(doc.urlPath)) {
      return state.contentCache.get(doc.urlPath);
    }
    if (state.contentLoading.has(doc.urlPath)) {
      return state.contentLoading.get(doc.urlPath);
    }
    const url = DATA_DIR + doc.relativePath;
    const promise = fetch(url, { cache: 'no-cache' })
      .then((res) => {
        if (!res.ok) {
          throw new Error(`Failed to fetch ${url} (HTTP ${res.status})`);
        }
        return res.text();
      })
      .then((text) => {
        state.contentCache.set(doc.urlPath, text);
        state.contentLoading.delete(doc.urlPath);
        return text;
      })
      .catch((err) => {
        state.contentLoading.delete(doc.urlPath);
        throw err;
      });
    state.contentLoading.set(doc.urlPath, promise);
    return promise;
  }

  function showNotFound(path) {
    setView('view-404');
    document.title = '404 · MarkNav';
    document.getElementById('not-found-message').textContent =
      `Markdown file not found for path: ${path}`;
    const list = document.getElementById('not-found-list');
    list.innerHTML = '';
    state.documents.forEach((doc) => {
      const li = document.createElement('li');
      const href = escapeHtml(buildHref(doc.urlPath));
      li.innerHTML = `<a href="${href}"><code>${escapeHtml(doc.urlPath)}</code></a> → <code>data/${escapeHtml(doc.relativePath)}</code>`;
      list.appendChild(li);
    });
  }

  function showFatalError(err) {
    const banner = document.createElement('div');
    banner.className = 'app-fatal-error';
    banner.innerHTML = `
      <h1>MarkNav couldn't start</h1>
      <p>${escapeHtml(err.message || String(err))}</p>
      <p>Make sure you are serving the project over HTTP (e.g. <code>python3 -m http.server 8000</code>) and that <code>data/files.json</code> exists.</p>`;
    document.body.prepend(banner);
  }

  function encodePath(path) {
    return path.split('/').map(encodeURIComponent).join('/');
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    })[c]);
  }

  function capitalize(value) {
    if (!value) return value;
    return value.charAt(0).toUpperCase() + value.slice(1);
  }

  function cssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(value);
    }
    return String(value).replace(/([^\w-])/g, '\\$1');
  }
})();
