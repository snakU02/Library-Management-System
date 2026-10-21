<?php
// admin/dashboard.php
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_admin();
update_overdue_borrows($pdo);

$page_title  = 'Admin Dashboard';
$active_page = 'dashboard';

// --- Stats ---
$total_books     = $pdo->query("SELECT COUNT(*) FROM books WHERE status='active'")->fetchColumn();
$total_students  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND status='active'")->fetchColumn();
$active_borrows  = $pdo->query("SELECT COUNT(*) FROM borrows WHERE status='active'")->fetchColumn();
$overdue_borrows = $pdo->query("SELECT COUNT(*) FROM borrows WHERE status='overdue'")->fetchColumn();
$pending_returns = (int)$active_borrows + (int)$overdue_borrows;
$total_penalties = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM penalties WHERE status='unpaid'")->fetchColumn();
$pending_accounts= $pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND status='pending'")->fetchColumn();
$pending_reservations = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='pending'")->fetchColumn();

// --- Recent borrows ---
$recent_borrows = $pdo->query("
    SELECT b.*, u.first_name, u.last_name, u.student_id, bk.title
    FROM borrows b
    JOIN users u  ON b.user_id = u.id
    JOIN books bk ON b.book_id = bk.id
    ORDER BY b.created_at DESC LIMIT 10
")->fetchAll();

// --- Overdue list ---
$overdue_list = $pdo->query("
    SELECT b.*, u.first_name, u.last_name, u.student_id, bk.title,
           DATEDIFF(CURDATE(), b.due_date) AS days_overdue
    FROM borrows b
    JOIN users u  ON b.user_id = u.id
    JOIN books bk ON b.book_id = bk.id
    WHERE b.status = 'overdue'
    ORDER BY days_overdue DESC LIMIT 5
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

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-tachometer-alt mr-2 text-primary"></i>Dashboard
        </h1>
        <span class="text-muted small"><i class="fas fa-clock mr-1"></i><?= date('l, F d, Y') ?></span>
    </div>

    <!-- Stats Row -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Books</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_books) ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-book fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Students</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_students) ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Active Borrows</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($active_borrows) ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-sign-out-alt fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Overdue Books</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($overdue_borrows) ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Second row of stats -->
    <div class="row">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Unpaid Penalties (Total)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">₱<?= number_format($total_penalties, 2) ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-peso-sign fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <a href="students.php?filter=pending" class="text-decoration-none">
                <div class="card border-left-secondary shadow h-100 py-2 <?= $pending_accounts > 0 ? 'lms-pulse-border' : '' ?>">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Pending Registrations</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($pending_accounts) ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-user-clock fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <a href="reservations.php" class="text-decoration-none">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Pending Reservations</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($pending_reservations) ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-calendar-check fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Recent Borrows -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-2"></i>Recent Borrows</h6>
                    <a href="reports.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Book</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_borrows as $b): ?>
                                <tr>
                                    <td>
                                        <span class="font-weight-bold"><?= htmlspecialchars($b['last_name'] . ', ' . $b['first_name']) ?></span>
                                        <br><small class="text-muted"><?= htmlspecialchars($b['student_id']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars(mb_strimwidth($b['title'], 0, 35, '...')) ?></td>
                                    <td><?= date('M d, Y', strtotime($b['borrow_date'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($b['due_date'])) ?></td>
                                    <td>
                                        <?php
                                        $badge = match($b['status']) {
                                            'active'   => 'success',
                                            'overdue'  => 'danger',
                                            'returned' => 'secondary',
                                            'lost'     => 'dark',
                                            default    => 'light'
                                        };
                                        ?>
                                        <span class="badge badge-<?= $badge ?>"><?= ucfirst($b['status']) ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_borrows)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No borrows yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overdue Alert Panel -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow border-left-danger">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Overdue Books</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($overdue_list)): ?>
                        <div class="text-center text-muted py-4"><i class="fas fa-check-circle fa-2x mb-2 text-success"></i><br>No overdue books!</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($overdue_list as $o): ?>
                            <li class="list-group-item px-3 py-2">
                                <div class="font-weight-bold small"><?= htmlspecialchars($o['last_name'] . ', ' . $o['first_name']) ?></div>
                                <div class="text-muted x-small"><?= htmlspecialchars(mb_strimwidth($o['title'], 0, 30, '...')) ?></div>
                                <span class="badge badge-danger"><?= $o['days_overdue'] ?> days overdue</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <?php if (!empty($overdue_list)): ?>
                <div class="card-footer text-center">
                    <a href="return.php?filter=overdue" class="btn btn-sm btn-danger">Process Returns</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /.container-fluid -->
<?php
$page_scripts = '<script>
$(function() {
    // Auto-refresh dashboard every 2 minutes
    setTimeout(function() { location.reload(); }, 120000);
});
</script>';
include '../includes/footer.php';
?>
