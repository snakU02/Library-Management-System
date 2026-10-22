<?php
// admin/borrow.php — Issue books to students
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_admin();
update_overdue_borrows($pdo);

$page_title  = 'Issue / Borrow Books';
$active_page = 'borrow';

$borrow_days = (int)get_setting($pdo, 'borrow_duration_days', BORROW_DAYS);
$max_borrows = (int)get_setting($pdo, 'max_borrows_per_student', MAX_BORROWS);

// Handle POST: Issue a book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'issue') {
    $user_id  = (int)$_POST['user_id'];
    $book_id  = (int)$_POST['book_id'];
    $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime("+$borrow_days days"));
    $notes    = trim($_POST['notes'] ?? '');
    $res_id   = (int)($_POST['reservation_id'] ?? 0) ?: null;

    $errors = [];

    // Check student active borrow count
    $count = $pdo->prepare("SELECT COUNT(*) FROM borrows WHERE user_id=? AND status IN ('active','overdue')");
    $count->execute([$user_id]);
    if ($count->fetchColumn() >= $max_borrows) {
        $errors[] = "Student has reached the maximum borrow limit ($max_borrows books).";
    }

    // Check book availability
    $book = $pdo->prepare("SELECT available_copies, title FROM books WHERE id=? AND status='active'");
    $book->execute([$book_id]);
    $book = $book->fetch();
    if (!$book) { $errors[] = "Book not found."; }
    elseif ($book['available_copies'] < 1) { $errors[] = "No available copies of this book."; }

    // Check if student already has this book borrowed
    $dup = $pdo->prepare("SELECT id FROM borrows WHERE user_id=? AND book_id=? AND status IN ('active','overdue')");
    $dup->execute([$user_id, $book_id]);
    if ($dup->fetch()) { $errors[] = "Student already has an active borrow of this book."; }

    if (empty($errors)) {
        // Insert borrow
        $stmt = $pdo->prepare("INSERT INTO borrows (user_id, book_id, reservation_id, borrow_date, due_date, issued_by, notes)
                               VALUES (?, ?, ?, CURDATE(), ?, ?, ?)");
        $stmt->execute([$user_id, $book_id, $res_id, $due_date, $_SESSION['user_id'], $notes]);

        // Decrease available copies
        $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id=?")->execute([$book_id]);

        // Mark reservation as converted if linked
        if ($res_id) {
            $pdo->prepare("UPDATE reservations SET status='converted' WHERE id=?")->execute([$res_id]);
        }

        set_flash('success', "Book \"{$book['title']}\" successfully issued.");
    } else {
        set_flash('error', implode(' ', $errors));
    }

    header('Location: borrow.php');
    exit;
}

// Active borrows list
$active_borrows = $pdo->query("
    SELECT b.*, u.first_name, u.last_name, u.student_id,
           bk.title, bk.author,
           DATEDIFF(b.due_date, CURDATE()) AS days_left
    FROM borrows b
    JOIN users u  ON b.user_id = u.id
    JOIN books bk ON b.book_id = bk.id
    WHERE b.status IN ('active','overdue')
    ORDER BY b.due_date ASC
")->fetchAll();

// Students list (for select)
$students = $pdo->query("SELECT id, student_id, first_name, last_name, course FROM users WHERE role='student' AND status='active' ORDER BY last_name")->fetchAll();

// Available books (for select)
$avail_books = $pdo->query("SELECT id, title, author, available_copies FROM books WHERE status='active' AND available_copies > 0 ORDER BY title")->fetchAll();

// Pending reservations (approved, ready to issue)
$pending_res = $pdo->query("
    SELECT r.*, u.first_name, u.last_name, u.student_id, bk.title AS book_title, bk.available_copies
    FROM reservations r
    JOIN users u  ON r.user_id = u.id
    JOIN books bk ON r.book_id = bk.id
    WHERE r.status = 'approved'
    ORDER BY r.approved_at
")->fetchAll();

$flash = get_flash();
include '../includes/header.php';
include '../includes/sidebar_admin.php';
?>
<div class="container-fluid">
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
        <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?> mr-2"></i>
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php endif; ?>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-sign-out-alt mr-2 text-primary"></i>Issue / Borrow</h1>
        <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#issueModal">
            <i class="fas fa-plus mr-2"></i>Issue a Book
        </button>
    </div>

    <!-- Pending Reservations Ready to Issue -->
    <?php if (!empty($pending_res)): ?>
    <div class="card shadow mb-4 border-left-warning">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-calendar-check mr-2"></i>Approved Reservations — Ready to Issue (<?= count($pending_res) ?>)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light"><tr>
                        <th>Student</th><th>Book</th><th>Reserved</th><th>Expiry</th><th>Action</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($pending_res as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['last_name'] . ', ' . $r['first_name']) ?><br><small class="text-muted"><?= htmlspecialchars($r['student_id']) ?></small></td>
                        <td><?= htmlspecialchars($r['book_title']) ?>
                            <?php if ($r['available_copies'] < 1): ?>
                                <span class="badge badge-danger ml-1">No copies</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M d, Y', strtotime($r['reserved_at'])) ?></td>
                        <td><?= date('M d, Y', strtotime($r['expiry_date'])) ?></td>
                        <td>
                            <button class="btn btn-sm btn-success"
                                    onclick="quickIssue(<?= $r['user_id'] ?>, <?= $r['book_id'] ?>, <?= $r['id'] ?>)"
                                    <?= $r['available_copies'] < 1 ? 'disabled' : '' ?>>
                                <i class="fas fa-check mr-1"></i>Issue
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Active Borrows Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-2"></i>Currently Borrowed Books (<?= count($active_borrows) ?>)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="borrowTable">
                    <thead class="thead-dark"><tr>
                        <th>Student</th><th>Book</th><th>Issued</th><th>Due Date</th><th>Status</th><th>Notes</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($active_borrows as $b): ?>
                    <tr class="<?= $b['status'] === 'overdue' ? 'table-danger' : '' ?>">
                        <td>
                            <strong><?= htmlspecialchars($b['last_name'] . ', ' . $b['first_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($b['student_id']) ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($b['title']) ?>
                            <br><small class="text-muted"><?= htmlspecialchars($b['author']) ?></small>
                        </td>
                        <td><?= date('M d, Y', strtotime($b['borrow_date'])) ?></td>
                        <td>
                            <?= date('M d, Y', strtotime($b['due_date'])) ?>
                            <?php if ($b['days_left'] < 0): ?>
                                <br><span class="text-danger small font-weight-bold"><?= abs($b['days_left']) ?> days overdue</span>
                            <?php elseif ($b['days_left'] <= 2): ?>
                                <br><span class="text-warning small"><?= $b['days_left'] ?> days left</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $b['status'] === 'overdue' ? 'danger' : 'success' ?>">
                                <?= ucfirst($b['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty(trim((string)$b['notes']))): ?>
                                <div class="text-muted small"><?= nl2br(htmlspecialchars($b['notes'])) ?></div>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($active_borrows)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No active borrows.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Issue Book Modal -->
<div class="modal fade" id="issueModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="issueForm">
                <input type="hidden" name="action" value="issue">
                <input type="hidden" name="reservation_id" id="resId" value="">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-sign-out-alt mr-2"></i>Issue Book to Student</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="small font-weight-bold">Student *</label>
                        <select name="user_id" id="issueStudent" class="form-control" required>
                            <option value="">— Select Student —</option>
                            <?php foreach ($students as $s): ?>
                                <option value="<?= $s['id'] ?>">
                                    <?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name'] . ' — ' . $s['student_id']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Book *</label>
                        <select name="book_id" id="issueBook" class="form-control" required>
                            <option value="">— Select Available Book —</option>
                            <?php foreach ($avail_books as $bk): ?>
                                <option value="<?= $bk['id'] ?>">
                                    <?= htmlspecialchars($bk['title']) ?> — <?= htmlspecialchars($bk['author']) ?>
                                    (<?= $bk['available_copies'] ?> avail.)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Due Date *</label>
                        <input type="date" name="due_date" id="issueDue" class="form-control" required
                               value="<?= date('Y-m-d', strtotime("+$borrow_days days")) ?>"
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        <small class="text-muted">Default: <?= $borrow_days ?> days from today</small>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check mr-1"></i>Issue Book</button>
                </div>
            </form>
        </div>
    </div>
</div>


</div><!-- /.container-fluid -->
<?php
$page_scripts = '<script>
$(function() {
    $("#borrowTable").DataTable({ order: [[3, "asc"]] });
});
function quickIssue(userId, bookId, resId) {
    document.getElementById("issueStudent").value = userId;
    document.getElementById("issueBook").value = bookId;
    document.getElementById("resId").value = resId;
    $("#issueModal").modal("show");
}
</script>';
include '../includes/footer.php';
?>
