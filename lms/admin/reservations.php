<?php
// admin/reservations.php
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_admin();

$page_title  = 'Reservations';
$active_page = 'reservations';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)$_POST['id'];

    if ($action === 'approve') {
        // Fetch reservation details
        $res = $pdo->prepare("SELECT r.*, bk.available_copies, bk.title AS book_title
                               FROM reservations r
                               JOIN books bk ON r.book_id = bk.id
                               WHERE r.id = ? AND r.status = 'pending'");
        $res->execute([$id]);
        $res = $res->fetch();

        if (!$res) {
            set_flash('error', 'Reservation not found or already processed.');
        } elseif ($res['available_copies'] < 1) {
            set_flash('error', 'Cannot approve: no available copies of "' . htmlspecialchars($res['book_title']) . '".');
        } else {
            $borrow_days = (int)get_setting($pdo, 'borrow_duration_days', BORROW_DAYS);
            $due_date    = date('Y-m-d', strtotime("+$borrow_days days"));

            // 1. Insert borrow record
            $pdo->prepare("INSERT INTO borrows (user_id, book_id, reservation_id, borrow_date, due_date, issued_by, status)
                           VALUES (?, ?, ?, CURDATE(), ?, ?, 'active')")
                ->execute([$res['user_id'], $res['book_id'], $id, $due_date, $_SESSION['user_id']]);

            // 2. Decrease available copies
            $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?")
                ->execute([$res['book_id']]);

            // 3. Mark reservation as converted (not just approved)
            $pdo->prepare("UPDATE reservations SET status='converted', approved_by=?, approved_at=NOW() WHERE id=?")
                ->execute([$_SESSION['user_id'], $id]);

            set_flash('success', 'Reservation approved and borrow record created for "' . htmlspecialchars($res['book_title']) . '".');
        }

    } elseif ($action === 'cancel') {
        $pdo->prepare("UPDATE reservations SET status='cancelled' WHERE id=?")->execute([$id]);
        set_flash('success', 'Reservation cancelled.');
    }

    header('Location: reservations.php');
    exit;
}

// Auto-expire old reservations
$pdo->exec("UPDATE reservations SET status='expired' WHERE status='pending' AND expiry_date < CURDATE()");

$filter = $_GET['status'] ?? 'pending';
$sql = "SELECT r.*, u.first_name, u.last_name, u.student_id,
               bk.title AS book_title, bk.available_copies
        FROM reservations r
        JOIN users u  ON r.user_id = u.id
        JOIN books bk ON r.book_id = bk.id";
$params = [];
if (in_array($filter, ['pending','approved','cancelled','expired','converted'])) {
    $sql .= " WHERE r.status=?"; $params[] = $filter;
}
$sql .= " ORDER BY r.reserved_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$flash = get_flash();
include '../includes/header.php';
include '../includes/sidebar_admin.php';
?>
<div class="container-fluid">
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
        <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php endif; ?>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-calendar-check mr-2 text-primary"></i>Reservations</h1>
    </div>

    <!-- Status Tabs -->
    <ul class="nav nav-tabs mb-4">
        <?php foreach (['pending'=>'warning','approved'=>'success','cancelled'=>'secondary','expired'=>'danger','converted'=>'info'] as $s=>$c): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filter===$s?'active':'' ?>" href="?status=<?= $s ?>">
                <span class="badge badge-<?= $c ?> mr-1"><?= ucfirst($s) ?></span>
            </a>
        </li>
        <?php endforeach; ?>
        <li class="nav-item">
            <a class="nav-link <?= !in_array($filter,['pending','approved','cancelled','expired','converted'])?'active':'' ?>" href="reservations.php">All</a>
        </li>
    </ul>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="resTable">
                    <thead class="thead-dark">
                        <tr><th>Student</th><th>Book</th><th>Reserved On</th><th>Expiry</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reservations as $r): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($r['last_name'] . ', ' . $r['first_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($r['student_id']) ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($r['book_title']) ?>
                            <br><small class="badge badge-<?= $r['available_copies'] > 0 ? 'success' : 'danger' ?>">
                                <?= $r['available_copies'] ?> available
                            </small>
                        </td>
                        <td><?= date('M d, Y', strtotime($r['reserved_at'])) ?></td>
                        <td><?= date('M d, Y', strtotime($r['expiry_date'])) ?></td>
                        <td>
                            <?php $sc = ['pending'=>'warning','approved'=>'success','cancelled'=>'secondary','expired'=>'danger','converted'=>'info']; ?>
                            <span class="badge badge-<?= $sc[$r['status']] ?>"><?= ucfirst($r['status']) ?></span>
                        </td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button class="btn btn-sm btn-success mr-1">
                                        <i class="fas fa-check mr-1"></i>Approve & Issue
                                    </button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button class="btn btn-sm btn-danger"
                                            onclick="return confirm('Cancel this reservation?')">
                                        <i class="fas fa-times mr-1"></i>Cancel
                                    </button>
                                </form>
                            <?php elseif ($r['status'] === 'converted'): ?>
                                <span class="badge badge-info"><i class="fas fa-check-double mr-1"></i>Issued</span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reservations)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No reservations.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


</div><!-- /.container-fluid -->
<?php
$page_scripts = '<script>$(function(){ $("#resTable").DataTable({order:[[2,"desc"]]}); });</script>';
include '../includes/footer.php';
?>
