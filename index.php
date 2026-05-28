<?php
$dataDir = __DIR__ . '/data/';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$request = trim($requestPath === false ? '' : $requestPath, '/');

// IMPORTANT: Check for home page FIRST before anything else
if ($request === '' || $request === 'index.php' || $request === 'index') {
    showHomePage($dataDir);
    exit;
}

// If request is a file that exists (including subfolders)
$possiblePath = $dataDir . $request . '.md';
if (file_exists($possiblePath)) {
    renderMarkdown($possiblePath, $request, $dataDir);
    exit;
}

// Check for nested paths (folder/filename)
$lastSlash = strrpos($request, '/');
if ($lastSlash !== false) {
    $folder = substr($request, 0, $lastSlash);
    $file = substr($request, $lastSlash + 1);
    $nestedPath = $dataDir . $folder . '/' . $file . '.md';
    if (file_exists($nestedPath)) {
        renderMarkdown($nestedPath, $request, $dataDir);
        exit;
    }
}

// If we get here, it's a 404
http_response_code(404);
echo "<!DOCTYPE html><html><head><title>404</title><meta charset='UTF-8'><style>
    body { font-family: sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
    ul { list-style: none; padding: 0; }
    li { margin: 10px 0; }
    code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
</style></head><body>";
echo "<h1>404 - File Not Found</h1>";
echo "<p>Markdown file not found: <code>" . htmlspecialchars($request) . "</code></p>";
echo "<p><strong>Available paths:</strong></p><ul>";

// Show available files to help debug
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dataDir));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'md') {
        $relativePath = str_replace($dataDir, '', $file->getPathname());
        $urlPath = pathinfo($relativePath, PATHINFO_DIRNAME);
        $filename = pathinfo($relativePath, PATHINFO_FILENAME);
        $fullUrl = $urlPath === '.' ? $filename : $urlPath . '/' . $filename;
        echo "<li><code>/$fullUrl</code> → /data/$relativePath</li>";
    }
}
echo "</ul><p><a href='/'>← Back to home</a></p>";
echo "</body></html>";

// Function to show beautiful home page
function showHomePage($dataDir) {
    // Recursively scan for .md files and organize by folder
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dataDir));
    $filesByFolder = [];
    $rootFiles = [];
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $relativePath = str_replace($dataDir, '', $file->getPathname());
            $pathParts = explode('/', trim($relativePath, '/'));
            $filename = pathinfo($relativePath, PATHINFO_FILENAME);
            $searchText = $filename . ' ' . str_replace(['_', '-'], ' ', $relativePath) . ' ' . file_get_contents($file->getPathname());
            
            if (count($pathParts) === 1) {
                // File in root
                $rootFiles[] = [
                    'name' => $filename,
                    'fullPath' => $filename,
                    'searchText' => $searchText
                ];
            } else {
                // File in subfolder
                $folder = $pathParts[0];
                if (!isset($filesByFolder[$folder])) {
                    $filesByFolder[$folder] = [];
                }
                $filesByFolder[$folder][] = [
                    'name' => $filename,
                    'fullPath' => $folder . '/' . $filename,
                    'searchText' => $searchText
                ];
            }
        }
    }
    
    usort($rootFiles, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    ksort($filesByFolder);
    
    // Get total count
    $totalFiles = count($rootFiles);
    foreach ($filesByFolder as $files) {
        $totalFiles += count($files);
    }
    
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MarkNav - Document Browser</title>
        <link rel="stylesheet" href="/style.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 40px 20px;
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
            }
            
            /* Header Section */
            .header {
                text-align: center;
                margin-bottom: 50px;
                color: white;
            }
            
            .header h1 {
                font-size: 3rem;
                margin-bottom: 10px;
                animation: fadeInDown 0.6s ease;
            }
            
            .header .subtitle {
                font-size: 1.2rem;
                opacity: 0.95;
                margin-bottom: 20px;
                animation: fadeInUp 0.6s ease;
            }
            
            .stats {
                display: inline-block;
                background: rgba(255,255,255,0.2);
                backdrop-filter: blur(10px);
                padding: 10px 20px;
                border-radius: 50px;
                font-size: 0.9rem;
                animation: fadeIn 0.8s ease;
            }
            
            /* Search Box */
            .search-container {
                margin-bottom: 40px;
                animation: fadeIn 0.4s ease;
            }
            
            .search-box {
                width: 100%;
                max-width: 500px;
                margin: 0 auto;
                position: relative;
            }
            
            .search-box input {
                width: 100%;
                padding: 15px 20px;
                font-size: 1rem;
                border: none;
                border-radius: 50px;
                background: white;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                transition: all 0.3s ease;
                outline: none;
            }
            
            .search-box input:focus {
                transform: translateY(-2px);
                box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            }
            
            /* Section Titles */
            .section-title {
                color: white;
                font-size: 1.8rem;
                margin: 30px 0 20px 0;
                padding-bottom: 10px;
                border-bottom: 2px solid rgba(255,255,255,0.3);
                display: inline-block;
            }
            
            /* Cards Grid */
            .files-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 25px;
                margin-bottom: 40px;
            }
            
            .file-card {
                background: white;
                border-radius: 15px;
                padding: 25px;
                transition: all 0.3s ease;
                cursor: pointer;
                animation: fadeInUp 0.6s ease;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }
            
            .file-card:hover {
                transform: translateY(-8px);
                box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            }
            
            .file-icon {
                font-size: 2.5rem;
                margin-bottom: 15px;
            }
            
            .file-name {
                font-size: 1.2rem;
                font-weight: 600;
                color: #333;
                margin-bottom: 10px;
                word-break: break-word;
            }
            
            .file-path {
                font-size: 0.8rem;
                color: #888;
                margin-bottom: 15px;
                font-family: monospace;
            }
            
            .file-link {
                display: inline-block;
                color: #667eea;
                text-decoration: none;
                font-weight: 500;
                transition: all 0.3s ease;
            }
            
            .file-link:hover {
                color: #764ba2;
                transform: translateX(5px);
            }
            
            /* Folder Section */
            .folder-section {
                margin-bottom: 40px;
                background: rgba(255,255,255,0.1);
                backdrop-filter: blur(5px);
                border-radius: 20px;
                padding: 20px;
                animation: fadeInUp 0.6s ease;
            }
            
            .folder-title {
                color: white;
                font-size: 1.5rem;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .folder-title span {
                font-size: 2rem;
            }
            
            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 60px 20px;
                background: white;
                border-radius: 20px;
                color: #999;
            }
            
            .empty-state .emoji {
                font-size: 4rem;
                margin-bottom: 20px;
            }
            
            /* Footer */
            .footer {
                text-align: center;
                color: rgba(255,255,255,0.7);
                margin-top: 50px;
                padding-top: 20px;
                border-top: 1px solid rgba(255,255,255,0.2);
            }
            
            /* Animations */
            @keyframes fadeInDown {
                from {
                    opacity: 0;
                    transform: translateY(-30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @keyframes fadeIn {
                from {
                    opacity: 0;
                }
                to {
                    opacity: 1;
                }
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                .header h1 {
                    font-size: 2rem;
                }
                
                .files-grid {
                    grid-template-columns: 1fr;
                }
                
                .container {
                    padding: 0 15px;
                }
            }
        </style>
    </head>
    <body class="marknav-home">
        <div class="container">
            <div class="header">
                <h1>📚 MarkNav</h1>
                <div class="subtitle">Your Markdown Document Browser</div>
                <div class="stats">
                    📄 ' . $totalFiles . ' document' . ($totalFiles !== 1 ? 's' : '') . ' available
                </div>
            </div>
            
            <div class="search-container">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="🔍 Search inside documents (3+ chars)..." onkeyup="filterDocs()">
                </div>
            </div>';
    
    // Root files section
    if (!empty($rootFiles)) {
        echo '<h2 class="section-title">📄 Documents</h2>
              <div class="files-grid" id="filesGrid">';
        
        foreach ($rootFiles as $file) {
            // Format filename for display (replace underscores with spaces)
            $displayName = str_replace('_', ' ', $file['name']);
            echo '<div class="file-card" data-name="' . htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') . '" data-search="' . htmlspecialchars($file['searchText'], ENT_QUOTES, 'UTF-8') . '">
                    <div class="file-icon">📄</div>
                    <div class="file-name">' . htmlspecialchars($displayName) . '</div>
                    <div class="file-path">' . htmlspecialchars($file['name']) . '.md</div>
                    <a href="/' . htmlspecialchars($file['name']) . '" class="file-link">Read more →</a>
                  </div>';
        }
        
        echo '</div>';
    }
    
    // Folder sections
    foreach ($filesByFolder as $folderName => $files) {
        $folderIcon = getFolderIcon($folderName);
        $displayFolderName = str_replace('_', ' ', $folderName);
        echo '<div class="folder-section">
                <div class="folder-title">
                    <span>' . $folderIcon . '</span>
                    <h2>' . ucfirst(htmlspecialchars($displayFolderName)) . '</h2>
                </div>
                <div class="files-grid" data-folder="' . $folderName . '">';
        
        foreach ($files as $file) {
            $displayName = str_replace('_', ' ', $file['name']);
            echo '<div class="file-card" data-name="' . htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') . '" data-search="' . htmlspecialchars($file['searchText'], ENT_QUOTES, 'UTF-8') . '">
                    <div class="file-icon">📝</div>
                    <div class="file-name">' . htmlspecialchars($displayName) . '</div>
                    <div class="file-path">' . htmlspecialchars($file['fullPath']) . '.md</div>
                    <a href="/' . htmlspecialchars($file['fullPath']) . '" class="file-link">Read more →</a>
                  </div>';
        }
        
        echo '</div></div>';
    }
    
    // Empty state
    if (empty($rootFiles) && empty($filesByFolder)) {
        echo '<div class="empty-state">
                <div class="emoji">📭</div>
                <h3>No markdown files found</h3>
                <p>Add some .md files to the /data folder to get started</p>
              </div>';
    }
    
    echo '<div class="footer">
                <p>✨ MarkNav • Browse your markdown files with style</p>
              </div>
        </div>
        
        <script>
            function filterDocs() {
                const searchTerm = document.getElementById("searchInput").value.trim().toLowerCase();
                const allCards = document.querySelectorAll(".file-card");
                const shouldSearch = searchTerm.length >= 3;
                
                allCards.forEach(card => {
                    const searchText = (card.getAttribute("data-search") || card.getAttribute("data-name") || "").toLowerCase();
                    if (!shouldSearch || searchText.includes(searchTerm)) {
                        card.style.display = "block";
                        card.style.animation = "fadeInUp 0.4s ease";
                    } else {
                        card.style.display = "none";
                    }
                });
                
                // Hide empty sections
                const sections = document.querySelectorAll(".files-grid");
                sections.forEach(section => {
                    let visibleCount = 0;
                    const cards = section.querySelectorAll(".file-card");
                    cards.forEach(card => {
                        if (card.style.display !== "none") visibleCount++;
                    });
                    
                    const parentSection = section.closest(".folder-section");
                    if (parentSection && visibleCount === 0) {
                        parentSection.style.display = "none";
                    } else if (parentSection && visibleCount > 0) {
                        parentSection.style.display = "block";
                    }
                });
            }
        </script>
    </body>
    </html>';
}

// Function to get folder icons
function getFolderIcon($folderName) {
    $icons = [
        'courses' => '📚',
        'docs' => '📖',
        'tutorials' => '🎓',
        'notes' => '📝',
        'projects' => '💼',
        'archive' => '🗄️',
        'blog' => '✍️',
        'recipes' => '🍳',
        'guides' => '🧭'
    ];
    
    return $icons[strtolower($folderName)] ?? '📁';
}

function escapeHtml($text) {
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function renderInlineMarkdown($text) {
    $html = escapeHtml($text);

    $html = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/', function($matches) {
        $alt = escapeHtml($matches[1]);
        $src = escapeHtml($matches[2]);
        $title = isset($matches[3]) ? ' title="' . escapeHtml($matches[3]) . '"' : '';
        return '<img src="' . $src . '" alt="' . $alt . '"' . $title . '>';
    }, $html);

    $html = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/', function($matches) {
        $label = $matches[1];
        $href = escapeHtml($matches[2]);
        $title = isset($matches[3]) ? ' title="' . escapeHtml($matches[3]) . '"' : '';
        return '<a href="' . $href . '"' . $title . '>' . $label . '</a>';
    }, $html);

    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
    $html = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $html);
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $html);
    $html = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $html);

    return $html;
}

function isTableSeparator($line) {
    $cells = array_filter(array_map('trim', explode('|', trim($line, '| '))), 'strlen');

    if (empty($cells)) {
        return false;
    }

    foreach ($cells as $cell) {
        if (!preg_match('/^:?-{3,}:?$/', $cell)) {
            return false;
        }
    }

    return true;
}

function parseTableCells($line) {
    return array_map('trim', explode('|', trim($line, '| ')));
}

function renderMarkdownTable($lines, $startIndex) {
    $header = parseTableCells($lines[$startIndex]);
    $html = "<table>\n<thead>\n<tr>";

    foreach ($header as $cell) {
        $html .= '<th>' . renderInlineMarkdown($cell) . '</th>';
    }

    $html .= "</tr>\n</thead>\n<tbody>\n";
    $index = $startIndex + 2;
    $lineCount = count($lines);

    while ($index < $lineCount && strpos(trim($lines[$index]), '|') !== false && trim($lines[$index]) !== '') {
        $html .= '<tr>';
        foreach (parseTableCells($lines[$index]) as $cell) {
            $html .= '<td>' . renderInlineMarkdown($cell) . '</td>';
        }
        $html .= "</tr>\n";
        $index++;
    }

    $html .= "</tbody>\n</table>\n";

    return [$html, $index];
}

// GitHub-like Markdown renderer for the small MarkNav browser.
function parseMarkdown($text) {
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $html = '';
    $paragraph = [];
    $listType = null;
    $lineCount = count($lines);

    $flushParagraph = function() use (&$html, &$paragraph) {
        if (!empty($paragraph)) {
            $html .= '<p>' . renderInlineMarkdown(implode(' ', $paragraph)) . "</p>\n";
            $paragraph = [];
        }
    };

    $closeList = function() use (&$html, &$listType) {
        if ($listType !== null) {
            $html .= '</' . $listType . ">\n";
            $listType = null;
        }
    };

    for ($index = 0; $index < $lineCount; $index++) {
        $line = $lines[$index];
        $trimmed = trim($line);

        if (preg_match('/^```([A-Za-z0-9_-]*)\s*$/', $trimmed, $matches)) {
            $flushParagraph();
            $closeList();

            $language = $matches[1] !== '' ? ' class="language-' . escapeHtml($matches[1]) . '"' : '';
            $codeLines = [];
            $index++;

            while ($index < $lineCount && trim($lines[$index]) !== '```') {
                $codeLines[] = $lines[$index];
                $index++;
            }

            $html .= '<pre><code' . $language . '>' . escapeHtml(implode("\n", $codeLines)) . "</code></pre>\n";
            continue;
        }

        if ($trimmed === '') {
            $flushParagraph();
            $closeList();
            continue;
        }

        if ($index + 1 < $lineCount && strpos($trimmed, '|') !== false && isTableSeparator(trim($lines[$index + 1]))) {
            $flushParagraph();
            $closeList();
            list($tableHtml, $nextIndex) = renderMarkdownTable($lines, $index);
            $html .= $tableHtml;
            $index = $nextIndex - 1;
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/', $trimmed, $matches)) {
            $flushParagraph();
            $closeList();
            $level = strlen($matches[1]);
            $html .= '<h' . $level . '>' . renderInlineMarkdown($matches[2]) . '</h' . $level . ">\n";
            continue;
        }

        if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $trimmed)) {
            $flushParagraph();
            $closeList();
            $html .= "<hr>\n";
            continue;
        }

        if (preg_match('/^>\s?(.*)$/', $trimmed, $matches)) {
            $flushParagraph();
            $closeList();
            $html .= '<blockquote><p>' . renderInlineMarkdown($matches[1]) . "</p></blockquote>\n";
            continue;
        }

        if (preg_match('/^[-*+]\s+(.+)$/', $trimmed, $matches)) {
            $flushParagraph();
            if ($listType !== 'ul') {
                $closeList();
                $html .= "<ul>\n";
                $listType = 'ul';
            }
            $html .= '<li>' . renderInlineMarkdown($matches[1]) . "</li>\n";
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $matches)) {
            $flushParagraph();
            if ($listType !== 'ol') {
                $closeList();
                $html .= "<ol>\n";
                $listType = 'ol';
            }
            $html .= '<li>' . renderInlineMarkdown($matches[1]) . "</li>\n";
            continue;
        }

        $closeList();
        $paragraph[] = $trimmed;
    }

    $flushParagraph();
    $closeList();

    return $html;
}

function renderMarkdown($filePath, $requestUri, $dataDir) {
    $mdContent = file_get_contents($filePath);
    
    // Parse markdown with table support
    $html = parseMarkdown($mdContent);
    
    // Build navigation menu from all .md files
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dataDir));
    $allFiles = [];
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $relativePath = str_replace($dataDir, '', $file->getPathname());
            $allFiles[] = $relativePath;
        }
    }
    sort($allFiles);
    
    $currentPage = trim($requestUri, '/');
    $menu = "<header class='document-header'>";
    $menu .= "<div class='document-header-main'>";
    $menu .= "<a href='/' class='document-brand'><span class='document-brand-icon'>📚</span><span><strong>MarkNav</strong><small>Markdown Browser</small></span></a>";
    $menu .= "<span class='document-count'>" . count($allFiles) . " document" . (count($allFiles) !== 1 ? "s" : "") . "</span>";
    $menu .= "</div>";
    $activeDisplay = str_replace('_', ' ', basename($currentPage));
    $activeDisplay = $activeDisplay !== '' ? $activeDisplay : 'Document';

    $menu .= "<nav class='document-nav' aria-label='Documents'>";
    $menu .= "<a href='/' class='document-nav-link document-nav-home'>Home</a>";
    $menu .= "<span class='document-nav-link is-active'>" . escapeHtml($activeDisplay) . "</span>";
    $menu .= "<details class='document-pages-menu'>";
    $menu .= "<summary>Pages</summary>";
    $menu .= "<div class='document-pages-dropdown'>";

    foreach ($allFiles as $file) {
        $urlPath = pathinfo($file, PATHINFO_DIRNAME);
        $filename = pathinfo($file, PATHINFO_FILENAME);
        $fullUrl = $urlPath === '.' ? $filename : $urlPath . '/' . $filename;
        $display = str_replace('_', ' ', $filename);
        $escapedDisplay = escapeHtml($display);
        $escapedUrl = escapeHtml($fullUrl);
        
        // Highlight current page
        if ($fullUrl === $currentPage) {
            $menu .= "<span class='document-pages-item is-active'>" . $escapedDisplay . "</span>";
        } else {
            $menu .= "<a href='/" . $escapedUrl . "' class='document-pages-item'>" . $escapedDisplay . "</a>";
        }
    }

    $menu .= "</div>";
    $menu .= "</details>";
    $menu .= "</nav>";
    $menu .= "</header>";
    
    $title = basename($requestUri);
    $displayTitle = str_replace('_', ' ', $title);
    echo "<!DOCTYPE html><html><head><title>" . htmlspecialchars($displayTitle) . "</title><meta charset='UTF-8'><link rel='stylesheet' href='/style.css'></head><body class='marknav-document'>";
    echo $menu;
    echo "<div class='markdown-content'>$html</div>";
    echo "<footer class='document-footer'><p>✨ MarkNav • Browse your markdown files with style</p><a href='/'>Back to library</a></footer>";
    echo "</body></html>";
}
?>