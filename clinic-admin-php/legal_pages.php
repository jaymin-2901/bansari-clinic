<?php
/**
 * Bansari Homeopathy – Legal Pages Management
 * Admin page to edit Privacy Policy & Terms and Conditions
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Legal Pages';

// Ensure legal_pages table exists
try {
    $db = getClinicDB();
    $db->exec("
        CREATE TABLE IF NOT EXISTS legal_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            content LONGTEXT NOT NULL COMMENT 'HTML content allowed',
            created_by INT NULL,
            updated_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug),
            CONSTRAINT fk_legal_created FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
            CONSTRAINT fk_legal_updated FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    // Seed default pages if table is empty
    $count = $db->query("SELECT COUNT(*) FROM legal_pages")->fetchColumn();
    if ((int)$count === 0) {
        $db->exec("
            INSERT INTO legal_pages (title, slug, content) VALUES
            ('Privacy Policy', 'privacy-policy', '<h2>Privacy Policy</h2>\n<p>Your privacy is important to us. This policy explains how we collect, use, and protect your personal information.</p>'),
            ('Terms & Conditions', 'terms-conditions', '<h2>Terms &amp; Conditions</h2>\n<p>By using our services, you agree to these terms and conditions.</p>')
        ");
    }
} catch (PDOException $e) {
    // Table might already exist with different constraint names — that's fine
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_page') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $slug = $_POST['slug'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';

        // Validate slug
        if (!in_array($slug, ['privacy-policy', 'terms-conditions'])) {
            setFlash('error', 'Invalid page.');
        } elseif (empty($title) || empty($content)) {
            setFlash('error', 'Title and content are required.');
        } else {
            // Sanitize HTML – allow safe formatting tags
            $allowedTags = '<p><div><span><br><strong><em><u><li><ul><ol><h1><h2><h3><h4><h5><h6><a><blockquote><table><thead><tbody><tr><th><td>';
            $content = strip_tags($content, $allowedTags);

            try {
                $db = getClinicDB();
                $stmt = $db->prepare("SELECT id FROM legal_pages WHERE slug = ?");
                $stmt->execute([$slug]);

                if ($stmt->fetch()) {
                    $db->prepare("UPDATE legal_pages SET title = ?, content = ?, updated_by = ?, updated_at = NOW() WHERE slug = ?")
                       ->execute([$title, $content, getAdminId(), $slug]);
                } else {
                    $db->prepare("INSERT INTO legal_pages (title, slug, content, created_by, updated_by) VALUES (?, ?, ?, ?, ?)")
                       ->execute([$title, $slug, $content, getAdminId(), getAdminId()]);
                }
                setFlash('success', 'Page updated successfully.');
            } catch (PDOException $e) {
                error_log('Legal page update error: ' . $e->getMessage());
                setFlash('error', 'Failed to update page.');
            }
        }
    }
    header('Location: legal_pages.php?page=' . urlencode($slug ?? 'privacy-policy'));
    exit;
}

// Load pages
$pages = [];
try {
    $db = getClinicDB();
    $result = $db->query("SELECT id, title, slug, content, updated_at FROM legal_pages ORDER BY id");
    $pages = $result->fetchAll();
} catch (PDOException $e) {
    // Table may not exist yet
}

// Default pages if none exist
if (empty($pages)) {
    $pages = [
        ['id' => 0, 'title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'content' => '<h2>Privacy Policy</h2><p>Your privacy policy content here...</p>', 'updated_at' => null],
        ['id' => 0, 'title' => 'Terms & Conditions', 'slug' => 'terms-conditions', 'content' => '<h2>Terms & Conditions</h2><p>Your terms and conditions content here...</p>', 'updated_at' => null],
    ];
}

$activePage = $_GET['page'] ?? $pages[0]['slug'];
$currentPageData = null;
foreach ($pages as $p) {
    if ($p['slug'] === $activePage) {
        $currentPageData = $p;
        break;
    }
}
if (!$currentPageData) $currentPageData = $pages[0];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Page Tabs -->
<ul class="nav nav-tabs mb-4">
    <?php foreach ($pages as $p): ?>
    <li class="nav-item">
        <a class="nav-link <?= $p['slug'] === $currentPageData['slug'] ? 'active' : '' ?>" 
           href="?page=<?= urlencode($p['slug']) ?>">
            <i class="bi bi-<?= $p['slug'] === 'privacy-policy' ? 'shield-lock' : 'file-text' ?> me-1"></i>
            <?= clean($p['title']) ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Editor Card -->
<div class="table-container">
    <div class="table-header">
        <h6 class="mb-0 fw-bold">
            <i class="bi bi-pencil-square me-2"></i>
            Edit: <?= clean($currentPageData['title']) ?>
        </h6>
        <div class="d-flex align-items-center gap-3">
            <?php if ($currentPageData['updated_at']): ?>
            <small class="text-muted">Last updated: <?= formatDate($currentPageData['updated_at'], 'd M Y, h:i A') ?></small>
            <?php endif; ?>
            <a href="<?= getenv('NEXT_PUBLIC_APP_URL') ?: 'http://localhost:3000' ?>/<?= urlencode($currentPageData['slug']) ?>" 
               target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-up-right me-1"></i> View on Website
            </a>
        </div>
    </div>

    <form method="POST" id="legalForm" class="p-3">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="action" value="update_page">
        <input type="hidden" name="slug" value="<?= clean($currentPageData['slug']) ?>">

        <div class="mb-3">
            <label class="form-label fw-semibold">Page Title</label>
            <input type="text" name="title" class="form-control" 
                   value="<?= clean($currentPageData['title']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Content (HTML)</label>
            <!-- Formatting Toolbar -->
            <div class="mb-2 d-flex flex-wrap gap-1">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('h1')" title="Heading 1">H1</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('h2')" title="Heading 2">H2</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('h3')" title="Heading 3">H3</button>
                </div>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('p')" title="Paragraph"><i class="bi bi-text-paragraph"></i></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('strong')" title="Bold"><i class="bi bi-type-bold"></i></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('em')" title="Italic"><i class="bi bi-type-italic"></i></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('u')" title="Underline"><i class="bi bi-type-underline"></i></button>
                </div>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('ul')" title="Bullet List"><i class="bi bi-list-ul"></i></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('ol')" title="Numbered List"><i class="bi bi-list-ol"></i></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('li')" title="List Item"><i class="bi bi-list"></i> Item</button>
                </div>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('a')" title="Link"><i class="bi bi-link-45deg"></i></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('blockquote')" title="Quote"><i class="bi bi-quote"></i></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="insertBr()" title="Line break">&lt;br&gt;</button>
                </div>
                <div class="btn-group btn-group-sm ms-auto" role="group">
                    <button type="button" class="btn btn-outline-info" id="toggleViewBtn" onclick="toggleView()" title="Toggle Editor/Preview">
                        <i class="bi bi-eye me-1"></i> Preview
                    </button>
                </div>
            </div>

            <!-- Editor / Preview Split -->
            <div id="editorView">
                <textarea name="content" id="pageContent" class="form-control font-monospace" rows="22" required
                          style="font-size: 0.85rem; line-height: 1.6; tab-size: 2;"><?= htmlspecialchars($currentPageData['content']) ?></textarea>
            </div>
            <div id="previewView" class="d-none border rounded p-4 bg-white" style="min-height: 300px; max-height: 600px; overflow-y: auto;">
                <div id="pagePreview"></div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <button type="submit" class="btn btn-success btn-lg">
                <i class="bi bi-check-circle me-1"></i> Save Changes
            </button>
            <small class="text-muted" id="charCount"></small>
        </div>
    </form>
</div>

<script>
// Tag inserter
function insertTag(tag) {
    const ta = document.getElementById('pageContent');
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const selected = ta.value.substring(start, end);
    const before = ta.value.substring(0, start);
    const after = ta.value.substring(end);

    let insertion;
    if (tag === 'ul' || tag === 'ol') {
        const items = selected ? selected.split('\n').map(s => '  <li>' + s.trim() + '</li>').join('\n') : '  <li>Item</li>';
        insertion = '<' + tag + '>\n' + items + '\n</' + tag + '>';
    } else if (tag === 'a') {
        const text = selected || 'Link text';
        insertion = '<a href="#">' + text + '</a>';
    } else {
        insertion = '<' + tag + '>' + (selected || '') + '</' + tag + '>';
    }

    ta.value = before + insertion + after;
    ta.focus();
    ta.selectionStart = before.length;
    ta.selectionEnd = before.length + insertion.length;
    updateCharCount();
}

function insertBr() {
    const ta = document.getElementById('pageContent');
    const pos = ta.selectionStart;
    ta.value = ta.value.substring(0, pos) + '<br>\n' + ta.value.substring(pos);
    ta.focus();
    ta.selectionStart = ta.selectionEnd = pos + 5;
    updateCharCount();
}

// Toggle editor/preview
let showingPreview = false;
function toggleView() {
    showingPreview = !showingPreview;
    const btn = document.getElementById('toggleViewBtn');
    if (showingPreview) {
        document.getElementById('editorView').classList.add('d-none');
        document.getElementById('previewView').classList.remove('d-none');
        document.getElementById('pagePreview').innerHTML = document.getElementById('pageContent').value;
        btn.innerHTML = '<i class="bi bi-code-slash me-1"></i> Editor';
    } else {
        document.getElementById('editorView').classList.remove('d-none');
        document.getElementById('previewView').classList.add('d-none');
        btn.innerHTML = '<i class="bi bi-eye me-1"></i> Preview';
    }
}

// Character count
function updateCharCount() {
    const len = document.getElementById('pageContent').value.length;
    document.getElementById('charCount').textContent = len.toLocaleString() + ' characters';
}
document.getElementById('pageContent').addEventListener('input', updateCharCount);
updateCharCount();

// Tab key in textarea
document.getElementById('pageContent').addEventListener('keydown', function(e) {
    if (e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        this.value = this.value.substring(0, start) + '  ' + this.value.substring(this.selectionEnd);
        this.selectionStart = this.selectionEnd = start + 2;
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
