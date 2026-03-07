<?php
/**
 * Bansari Homeopathy – View Contact Messages
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Messages';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    try {
        $db = getClinicDB();
        $id = (int)($_POST['message_id'] ?? 0);

        if (($_POST['action'] ?? '') === 'mark_read') {
            $db->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([$id]);
        } elseif (($_POST['action'] ?? '') === 'delete') {
            $db->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
            setFlash('success', 'Message deleted.');
        }
    } catch (PDOException $e) {
        setFlash('error', 'Error processing action.');
    }
    header('Location: messages.php');
    exit;
}

// Fetch messages
try {
    $db = getClinicDB();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $total = $db->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
    $pagination = getPagination($total, $perPage, $page);

    $messages = $db->query("
        SELECT * FROM contact_messages 
        ORDER BY is_read ASC, created_at DESC 
        LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
    ")->fetchAll();
} catch (PDOException $e) {
    $messages = [];
    $pagination = getPagination(0);
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="table-container">
    <div class="table-header">
        <h6 class="mb-0 fw-bold">Contact Messages (<?= $pagination['total'] ?>)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th></th>
                    <th>Name</th>
                    <th>Email / Phone</th>
                    <th>Subject</th>
                    <th>Message</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No messages yet</td></tr>
                <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                <tr class="<?= !$msg['is_read'] ? 'fw-semibold' : '' ?>">
                    <td>
                        <?php if (!$msg['is_read']): ?>
                        <span class="badge bg-primary" style="width:8px;height:8px;padding:0;border-radius:50;display:inline-block"></span>
                        <?php endif; ?>
                    </td>
                    <td><?= clean($msg['name']) ?></td>
                    <td>
                        <small>
                            <?= $msg['email'] ? clean($msg['email']) : '' ?>
                            <?= $msg['phone'] ? '<br>' . clean($msg['phone']) : '' ?>
                        </small>
                    </td>
                    <td><?= clean($msg['subject'] ?? '-') ?></td>
                    <td>
                        <span title="<?= clean($msg['message']) ?>">
                            <?= clean(mb_substr($msg['message'], 0, 60)) ?><?= mb_strlen($msg['message']) > 60 ? '...' : '' ?>
                        </span>
                    </td>
                    <td><small><?= formatDate($msg['created_at'], 'd M Y, h:i A') ?></small></td>
                    <td class="d-flex gap-1">
                        <?php if (!$msg['is_read']): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                            <input type="hidden" name="action" value="mark_read">
                            <button class="btn btn-sm btn-action btn-outline-success" title="Mark Read">
                                <i class="bi bi-check"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn btn-sm btn-action btn-outline-danger" data-confirm="Delete this message?" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination-wrapper">
        <small class="text-muted">Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?></small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <li class="page-item <?= $i === $pagination['current_page'] ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
