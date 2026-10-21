<?php
// student/reserve.php — Student reservation management
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_student();

$page_title  = 'My Reservations';
$active_page = 'reserve';
$uid         = $_SESSION['user_id'];
$exp_days    = (int)get_setting($pdo, 'reservation_expiry_days', RESERVATION_DAYS);

// Handle POST: Submit reservation or cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $book_id = (int)($_POST['book_id'] ?? 0);
    $res_id  = (int)($_POST['res_id']  ?? 0);
    $notes   = trim($_POST['notes'] ?? '');

    if ($action === 'reserve' && $book_id) {
        // Check if already reserved
        $exists = $pdo->prepare("SELECT id FROM reservations WHERE user_id=? AND book_id=? AND status IN ('pending','approved')");
        $exists->execute([$uid, $book_id]);
        if ($exists->fetch()) {
            set_flash('error', 'You already have an active reservation for this book.');
        } else {
            // Check if already borrowed
            $borrowed = $pdo->prepare("SELECT id FROM borrows WHERE user_id=? AND book_id=? AND status IN ('active','overdue')");
            $borrowed->execute([$uid, $book_id]);
            if ($borrowed->fetch()) {
                set_flash('error', 'You already have this book borrowed.');
            } else {
                $expiry = date('Y-m-d', strtotime("+$exp_days days"));
                $pdo->prepare("INSERT INTO reservations (user_id, book_id, expiry_date, notes) VALUES (?, ?, ?, ?)")
                    ->execute([$uid, $book_id, $expiry, $notes]);
                set_flash('success', 'Reservation submitted! The librarian will review and approve it.');
            }
        }
    } elseif ($action === 'cancel' && $res_id) {
        $pdo->prepare("UPDATE reservations SET status='cancelled' WHERE id=? AND user_id=?")->execute([$res_id, $uid]);
        set_flash('success', 'Reservation cancelled.');
    }
    header('Location: reserve.php'); exit;
}

// Auto-expire
$pdo->exec("UPDATE reservations SET status='expired' WHERE status='pending' AND expiry_date < CURDATE()");

// Load my reservations
$my_res = $pdo->prepare("
    SELECT r.*, bk.title, bk.author, bk.available_copies, bk.cover_image
    FROM reservations r
    JOIN books bk ON r.book_id = bk.id
    WHERE r.user_id = ?
    ORDER BY r.reserved_at DESC
");
$my_res->execute([$uid]);
$reservations = $my_res->fetchAll();

$flash = get_flash();
include '../includes/header.php';
include '../includes/sidebar_student.php';
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
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-calendar-alt mr-2 text-success"></i>My Reservations</h1>
        <a href="catalogue.php" class="btn btn-success shadow-sm">
            <i class="fas fa-plus mr-2"></i>Reserve a Book
        </a>
    </div>

    <?php if (empty($reservations)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-calendar-plus fa-3x mb-3"></i><br>
        <h5>No reservations yet</h5>
        <a href="catalogue.php" class="btn btn-success mt-2">Browse Books to Reserve</a>
    </div>
    <?php else: ?>
    <div class="row">
        <?php foreach ($reservations as $r): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <?php $sc=['pending'=>'warning','approved'=>'success','cancelled'=>'secondary','expired'=>'danger','converted'=>'info']; ?>
                    <span class="badge badge-<?= $sc[$r['status']] ?>"><?= ucfirst($r['status']) ?></span>
                    <small class="text-muted"><?= date('M d, Y', strtotime($r['reserved_at'])) ?></small>
                </div>
                <div class="card-body d-flex">
                    <div class="mr-3" style="width:60px;flex-shrink:0;">
                        <?php if ($r['cover_image']): ?>
                            <img src="../uploads/covers/<?= htmlspecialchars($r['cover_image']) ?>"
                                 style="width:60px;height:80px;object-fit:cover;border-radius:4px;">
                        <?php else: ?>
                            <div class="bg-success d-flex align-items-center justify-content-center"
                                 style="width:60px;height:80px;border-radius:4px;">
                                <i class="fas fa-book text-white"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="font-weight-bold"><?= htmlspecialchars($r['title']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($r['author']) ?></div>
                        <div class="mt-2 small">
                            <?php if ($r['status'] === 'pending'): ?>
                                <span class="text-warning"><i class="fas fa-clock mr-1"></i>Awaiting librarian approval</span>
                            <?php elseif ($r['status'] === 'approved'): ?>
                                <span class="text-success"><i class="fas fa-check mr-1"></i>
                                    Approved! Pick up before <?= date('M d', strtotime($r['expiry_date'])) ?>
                                </span>
                            <?php elseif ($r['status'] === 'expired'): ?>
                                <span class="text-danger"><i class="fas fa-times mr-1"></i>Expired</span>
                            <?php elseif ($r['status'] === 'cancelled'): ?>
                                <span class="text-muted"><i class="fas fa-ban mr-1"></i>Cancelled</span>
                            <?php elseif ($r['status'] === 'converted'): ?>
                                <span class="text-info"><i class="fas fa-book mr-1"></i>Book issued</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($r['notes']): ?>
                            <div class="text-muted small mt-1"><em>"<?= htmlspecialchars($r['notes']) ?>"</em></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($r['status'] === 'pending'): ?>
                <div class="card-footer py-2">
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="res_id" value="<?= $r['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Cancel this reservation?')">
                            <i class="fas fa-times mr-1"></i>Cancel
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</div><!-- /.container-fluid -->
<?php include '../includes/footer.php'; ?>
