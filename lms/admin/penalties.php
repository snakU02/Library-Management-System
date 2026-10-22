<?php
// admin/penalties.php
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_admin();

$page_title  = 'Penalties';
$active_page = 'penalties';

// Handle: Mark penalty paid / waived
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)$_POST['id'];

    if ($action === 'paid') {
        $pdo->prepare("UPDATE penalties SET status='paid', paid_at=NOW(), received_by=? WHERE id=?")
            ->execute([$_SESSION['user_id'], $id]);
        set_flash('success', 'Penalty marked as paid.');
    } elseif ($action === 'waive') {
        $pdo->prepare("UPDATE penalties SET status='waived', received_by=? WHERE id=?")->execute([$_SESSION['user_id'], $id]);
        set_flash('success', 'Penalty waived.');
    }
    header('Location: penalties.php');
    exit;
}

$filter = $_GET['status'] ?? 'unpaid';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT p.*, u.first_name, u.last_name, u.student_id, bk.title AS book_title,
               b.borrow_date, b.due_date
        FROM penalties p
        JOIN users u  ON p.user_id = u.id
        JOIN borrows b ON p.borrow_id = b.id
        JOIN books bk ON b.book_id = bk.id
        WHERE 1=1";
$params = [];

if (in_array($filter, ['unpaid','paid','waived'])) {
    $sql .= " AND p.status=?"; $params[] = $filter;
}
if ($search) {
    $sql .= " AND (u.last_name LIKE ? OR u.student_id LIKE ? OR bk.title LIKE ?)";
    $params = array_merge($params, array_fill(0, 3, "%$search%"));
}
$sql .= " ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$penalties = $stmt->fetchAll();

// Summary stats
$stats = $pdo->query("SELECT
    SUM(CASE WHEN status='unpaid' THEN total_amount ELSE 0 END) AS total_unpaid,
    SUM(CASE WHEN status='paid'   THEN total_amount ELSE 0 END) AS total_paid,
    COUNT(CASE WHEN status='unpaid' THEN 1 END) AS count_unpaid
    FROM penalties")->fetch();

if (isset($_GET['export'])) {
    $lib_name = get_setting($pdo, 'library_name', 'Library');
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Penalty Records — <?= htmlspecialchars($lib_name) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #000; padding: 20px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #555; margin-bottom: 14px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 5px 8px; text-align: left; vertical-align: top; }
        th { background: #1c3a5e; color: #fff; font-size: 11px; }
        tr:nth-child(even) td { background: #f4f4f4; }
        .status { font-weight: bold; }
        .footer { margin-top: 14px; font-size: 10px; color: #777; }
        @media print { body { padding: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:16px;">
        <button onclick="window.print()" style="padding:8px 20px;background:#1c3a5e;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;">
            🖨️ Print / Save as PDF
        </button>
        <a href="penalties.php" style="margin-left:12px;font-size:12px;">← Back to Penalties</a>
    </div>

    <h1>⚠️ Penalty Records — <?= htmlspecialchars($lib_name) ?></h1>
    <p class="meta">
        Generated: <?= date('F d, Y h:i A') ?>
        <?php if ($search): ?> &nbsp;|&nbsp; Search: <strong><?= htmlspecialchars($search) ?></strong><?php endif; ?>
        <?php if ($filter): ?> &nbsp;|&nbsp; Status: <strong><?= htmlspecialchars(ucfirst($filter)) ?></strong><?php endif; ?>
        &nbsp;|&nbsp; Total: <strong><?= count($penalties) ?></strong> record(s)
    </p>

    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Book</th>
                <th>Overdue Days</th>
                <th>Overdue Fee</th>
                <th>Damage Fee</th>
                <th>Total</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($penalties)): ?>
            <tr><td colspan="7" style="text-align:center;color:#888;padding:16px;">No penalty records found.</td></tr>
        <?php else: ?>
            <?php foreach ($penalties as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name']) ?><br><small><?= htmlspecialchars($p['student_id']) ?></small></td>
                <td><?= htmlspecialchars($p['book_title']) ?><br><small>Due: <?= date('M d, Y', strtotime($p['due_date'])) ?></small></td>
                <td><?= $p['overdue_days'] ?> days</td>
                <td>₱<?= number_format($p['overdue_days'] * $p['daily_rate'], 2) ?></td>
                <td>₱<?= number_format($p['damage_fee'], 2) ?></td>
                <td><strong>₱<?= number_format($p['total_amount'], 2) ?></strong></td>
                <td class="status"><?= ucfirst($p['status']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <p class="footer">— End of Penalty Records —</p>
    <script>window.onload = function(){ window.print(); }</script>
</body>
</html>
<?php
    exit;
}

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
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-exclamation-triangle mr-2 text-warning"></i>Penalties</h1>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>" class="btn btn-success shadow-sm">
            <i class="fas fa-print mr-1"></i>Print / PDF
        </a>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Unpaid Penalties</div>
                            <div class="h5 mb-0 font-weight-bold">₱<?= number_format($stats['total_unpaid'] ?? 0, 2) ?></div>
                            <small class="text-muted"><?= $stats['count_unpaid'] ?? 0 ?> student(s)</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-exclamation-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Collected</div>
                            <div class="h5 mb-0 font-weight-bold">₱<?= number_format($stats['total_paid'] ?? 0, 2) ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card shadow mb-4">
        <div class="card-body py-3">
            <form method="GET" class="form-inline flex-wrap">
                <input type="text" name="q" class="form-control mr-2 mb-2"
                       placeholder="Student name, ID, or book..."
                       value="<?= htmlspecialchars($search) ?>" style="min-width:220px;">
                <select name="status" class="form-control mr-2 mb-2">
                    <option value="" <?= !$filter ? 'selected' : '' ?>>All Statuses</option>
                    <option value="unpaid" <?= $filter==='unpaid'?'selected':'' ?>>Unpaid</option>
                    <option value="paid"   <?= $filter==='paid'  ?'selected':'' ?>>Paid</option>
                    <option value="waived" <?= $filter==='waived'?'selected':'' ?>>Waived</option>
                </select>
                <button type="submit" class="btn btn-primary mr-2 mb-2"><i class="fas fa-filter mr-1"></i>Filter</button>
                <a href="penalties.php" class="btn btn-secondary mb-2"><i class="fas fa-times mr-1"></i>Clear</a>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-2"></i>Penalty Records (<?= count($penalties) ?>)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="penaltyTable">
                    <thead class="thead-dark">
                        <tr><th>Student</th><th>Book</th><th>Overdue Days</th><th>Overdue Fee</th><th>Damage Fee</th><th>Total</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($penalties as $p): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($p['student_id']) ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($p['book_title']) ?>
                            <br><small class="text-muted">Due: <?= date('M d, Y', strtotime($p['due_date'])) ?></small>
                        </td>
                        <td><?= $p['overdue_days'] ?> days</td>
                        <td>₱<?= number_format($p['overdue_days'] * $p['daily_rate'], 2) ?></td>
                        <td>₱<?= number_format($p['damage_fee'], 2) ?></td>
                        <td class="font-weight-bold text-danger">₱<?= number_format($p['total_amount'], 2) ?></td>
                        <td>
                            <?php $badges = ['unpaid'=>'danger','paid'=>'success','waived'=>'secondary']; ?>
                            <span class="badge badge-<?= $badges[$p['status']] ?? 'light' ?>"><?= ucfirst($p['status']) ?></span>
                            <?php if ($p['status'] === 'paid'): ?>
                                <br><small class="text-muted"><?= date('M d, Y', strtotime($p['paid_at'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['status'] === 'unpaid'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="paid">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-sm btn-success mb-1" title="Mark Paid"
                                            onclick="return confirm('Mark this penalty as PAID?')">
                                        <i class="fas fa-check mr-1"></i>Paid
                                    </button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="waive">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-sm btn-secondary" title="Waive penalty"
                                            onclick="return confirm('Waive this penalty?')">
                                        <i class="fas fa-times mr-1"></i>Waive
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($penalties)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-3">No penalty records found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


</div><!-- /.container-fluid -->
<?php
$page_scripts = '<script>$(function(){ $("#penaltyTable").DataTable({order:[[6,"asc"]]}); });</script>';
include '../includes/footer.php';
?>
