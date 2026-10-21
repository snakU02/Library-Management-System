<?php
// student/dashboard.php
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_student();
update_overdue_borrows($pdo);

$page_title  = 'My Dashboard';
$active_page = 'dashboard';
$uid = $_SESSION['user_id'];

// Student stats
$active_borrows  = $pdo->prepare("SELECT COUNT(*) FROM borrows WHERE user_id=? AND status IN ('active','overdue')");
$active_borrows->execute([$uid]);
$active_borrows = $active_borrows->fetchColumn();

$overdue_count = $pdo->prepare("SELECT COUNT(*) FROM borrows WHERE user_id=? AND status='overdue'");
$overdue_count->execute([$uid]);
$overdue_count = $overdue_count->fetchColumn();

$my_reservations = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE user_id=? AND status IN ('pending','approved')");
$my_reservations->execute([$uid]);
$my_reservations = $my_reservations->fetchColumn();

$unpaid_penalty = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM penalties WHERE user_id=? AND status='unpaid'");
$unpaid_penalty->execute([$uid]);
$unpaid_penalty = $unpaid_penalty->fetchColumn();

// My current borrows
$my_books = $pdo->prepare("
    SELECT b.*, bk.title, bk.author, DATEDIFF(b.due_date, CURDATE()) AS days_left
    FROM borrows b
    JOIN books bk ON b.book_id = bk.id
    WHERE b.user_id=? AND b.status IN ('active','overdue')
    ORDER BY b.due_date ASC
");
$my_books->execute([$uid]);
$my_books = $my_books->fetchAll();

// Recent activity
$activity = $pdo->prepare("
    SELECT 'borrow' AS type, b.borrow_date AS date_col, bk.title, b.status
    FROM borrows b JOIN books bk ON b.book_id=bk.id WHERE b.user_id=?
    UNION ALL
    SELECT 'return', rt.return_date, bk.title, rt.condition_note
    FROM returns rt JOIN borrows b ON rt.borrow_id=b.id JOIN books bk ON b.book_id=bk.id WHERE b.user_id=?
    ORDER BY date_col DESC LIMIT 8
");
$activity->execute([$uid, $uid]);
$activity = $activity->fetchAll();

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

    <!-- Welcome Banner -->
    <div class="lms-welcome-banner card shadow mb-4">
        <div class="card-body py-4 px-4 d-flex align-items-center justify-content-between">
            <div>
                <h4 class="font-weight-bold mb-1">
                    Welcome back, <?= htmlspecialchars($_SESSION['first_name']) ?>! 👋
                </h4>
                <p class="mb-0 text-white-50">
                    Student ID: <?= htmlspecialchars($_SESSION['student_id'] ?? 'N/A') ?> &nbsp;|&nbsp;
                    <?= date('l, F d, Y') ?>
                </p>
            </div>
            <i class="fas fa-book-reader fa-4x text-white-50 d-none d-md-block"></i>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Books Borrowed</div>
                            <div class="h5 mb-0 font-weight-bold"><?= $active_borrows ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-book fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-<?= $overdue_count > 0 ? 'danger' : 'success' ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?= $overdue_count > 0 ? 'danger' : 'success' ?> text-uppercase mb-1">Overdue</div>
                            <div class="h5 mb-0 font-weight-bold"><?= $overdue_count ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Reservations</div>
                            <div class="h5 mb-0 font-weight-bold"><?= $my_reservations ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-calendar fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-<?= $unpaid_penalty > 0 ? 'danger' : 'success' ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?= $unpaid_penalty > 0 ? 'danger' : 'success' ?> text-uppercase mb-1">Unpaid Penalty</div>
                            <div class="h5 mb-0 font-weight-bold">₱<?= number_format($unpaid_penalty, 2) ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-exclamation-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert for overdue / penalty -->
    <?php if ($overdue_count > 0): ?>
    <div class="alert alert-danger d-flex align-items-center mb-4">
        <i class="fas fa-exclamation-triangle fa-2x mr-3"></i>
        <div>
            <strong>You have <?= $overdue_count ?> overdue book(s)!</strong>
            Please return them immediately to avoid additional penalties.
            <a href="my_borrows.php" class="btn btn-danger btn-sm ml-2">View Overdue</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- My Borrowed Books -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-book mr-2"></i>My Borrowed Books</h6>
                    <a href="catalogue.php" class="btn btn-sm btn-success">Borrow More</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($my_books)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-book-open fa-2x mb-2"></i><br>
                            No books currently borrowed.<br>
                            <a href="catalogue.php" class="btn btn-success mt-2">Browse Books</a>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light"><tr><th>Book</th><th>Due Date</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($my_books as $b): ?>
                            <tr class="<?= $b['status'] === 'overdue' ? 'table-danger' : '' ?>">
                                <td>
                                    <strong><?= htmlspecialchars($b['title']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($b['author']) ?></small>
                                </td>
                                <td>
                                    <?= date('M d, Y', strtotime($b['due_date'])) ?>
                                    <?php if ($b['days_left'] < 0): ?>
                                        <br><small class="text-danger"><?= abs($b['days_left']) ?> days overdue</small>
                                    <?php elseif ($b['days_left'] <= 2): ?>
                                        <br><small class="text-warning"><?= $b['days_left'] ?> day(s) left</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $b['status']==='overdue'?'danger':'success' ?>">
                                        <?= ucfirst($b['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history mr-2"></i>Recent Activity</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($activity)): ?>
                        <div class="text-center text-muted py-4">No activity yet.</div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($activity as $a): ?>
                        <li class="list-group-item px-3 py-2">
                            <div class="d-flex align-items-center">
                                <span class="badge badge-<?= $a['type']==='borrow'?'primary':'success' ?> mr-2">
                                    <?= $a['type'] === 'borrow' ? '<i class="fas fa-book"></i>' : '<i class="fas fa-undo"></i>' ?>
                                </span>
                                <div>
                                    <div class="small font-weight-bold"><?= htmlspecialchars(mb_strimwidth($a['title'],0,30,'...')) ?></div>
                                    <div class="x-small text-muted"><?= ucfirst($a['type']) ?> &mdash; <?= date('M d', strtotime($a['date_col'])) ?></div>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- /.container-fluid -->
<?php include '../includes/footer.php'; ?>
