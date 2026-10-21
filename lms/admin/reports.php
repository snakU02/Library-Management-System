<?php
// admin/reports.php — Borrow / Return / Penalty records & reports
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_admin();

$page_title  = 'Records & Reports';
$active_page = 'reports';

$tab        = $_GET['tab']   ?? 'borrows';
$date_from  = $_GET['from']  ?? date('Y-m-01');
$date_to    = $_GET['to']    ?? date('Y-m-d');
$search     = trim($_GET['q'] ?? '');

// --- Borrows ---
$borrows = [];
if ($tab === 'borrows' || $tab === 'all') {
    $sql = "SELECT b.*, u.first_name, u.last_name, u.student_id,
                   bk.title, bk.author
            FROM borrows b
            JOIN users u  ON b.user_id = u.id
            JOIN books bk ON b.book_id = bk.id
            WHERE b.borrow_date BETWEEN ? AND ?";
    $params = [$date_from, $date_to];
    if ($search) { $sql .= " AND (u.last_name LIKE ? OR bk.title LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%"]); }
    $sql .= " ORDER BY b.borrow_date DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $borrows = $stmt->fetchAll();
}

// --- Returns ---
$returns = [];
if ($tab === 'returns' || $tab === 'all') {
    $sql = "SELECT rt.*, b.borrow_date, b.due_date, u.first_name, u.last_name, u.student_id, bk.title
            FROM returns rt
            JOIN borrows b ON rt.borrow_id = b.id
            JOIN users u   ON b.user_id = u.id
            JOIN books bk  ON b.book_id = bk.id
            WHERE rt.return_date BETWEEN ? AND ?";
    $params = [$date_from, $date_to];
    if ($search) { $sql .= " AND (u.last_name LIKE ? OR bk.title LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%"]); }
    $sql .= " ORDER BY rt.return_date DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $returns = $stmt->fetchAll();
}

// --- Penalties ---
$penalties = [];
if ($tab === 'penalties' || $tab === 'all') {
    $sql = "SELECT p.*, u.first_name, u.last_name, u.student_id, bk.title
            FROM penalties p
            JOIN users u  ON p.user_id = u.id
            JOIN borrows b ON p.borrow_id = b.id
            JOIN books bk ON b.book_id = bk.id
            WHERE DATE(p.created_at) BETWEEN ? AND ?";
    $params = [$date_from, $date_to];
    if ($search) { $sql .= " AND (u.last_name LIKE ? OR bk.title LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%"]); }
    $sql .= " ORDER BY p.created_at DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $penalties = $stmt->fetchAll();
}

include '../includes/header.php';
include '../includes/sidebar_admin.php';
?>
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-alt mr-2 text-primary"></i>Records & Reports</h1>
        <button onclick="window.print()" class="btn btn-outline-secondary shadow-sm d-print-none">
            <i class="fas fa-print mr-2"></i>Print Report
        </button>
    </div>

    <!-- Filter -->
    <div class="card shadow mb-4 d-print-none">
        <div class="card-body py-3">
            <form method="GET" class="form-inline flex-wrap">
                <select name="tab" class="form-control mr-2 mb-2">
                    <option value="borrows"  <?= $tab==='borrows' ?'selected':'' ?>>Borrow Records</option>
                    <option value="returns"  <?= $tab==='returns' ?'selected':'' ?>>Return Records</option>
                    <option value="penalties"<?= $tab==='penalties'?'selected':'' ?>>Penalty Records</option>
                </select>
                <label class="mr-1 mb-2 small">From:</label>
                <input type="date" name="from" class="form-control mr-2 mb-2" value="<?= $date_from ?>">
                <label class="mr-1 mb-2 small">To:</label>
                <input type="date" name="to"   class="form-control mr-2 mb-2" value="<?= $date_to ?>">
                <input type="text" name="q"    class="form-control mr-2 mb-2"
                       placeholder="Student or Book..." value="<?= htmlspecialchars($search) ?>" style="min-width:180px;">
                <button type="submit" class="btn btn-primary mr-2 mb-2"><i class="fas fa-filter mr-1"></i>Filter</button>
                <a href="reports.php" class="btn btn-secondary mb-2"><i class="fas fa-times mr-1"></i>Clear</a>
            </form>
        </div>
    </div>

    <!-- BORROW RECORDS -->
    <?php if ($tab === 'borrows'): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-sign-out-alt mr-2"></i>Borrow Records (<?= count($borrows) ?>)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="borrowReport">
                    <thead class="thead-dark">
                        <tr><th>#</th><th>Student</th><th>Book</th><th>Borrowed</th><th>Due Date</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($borrows as $i => $b): ?>
                    <tr class="<?= in_array($b['status'],['overdue','lost'])?'table-danger':'' ?>">
                        <td><?= $i+1 ?></td>
                        <td><?= htmlspecialchars($b['last_name'].', '.$b['first_name']) ?><br><small><?= htmlspecialchars($b['student_id']) ?></small></td>
                        <td><?= htmlspecialchars($b['title']) ?><br><small class="text-muted"><?= htmlspecialchars($b['author']) ?></small></td>
                        <td><?= date('M d, Y', strtotime($b['borrow_date'])) ?></td>
                        <td><?= date('M d, Y', strtotime($b['due_date'])) ?></td>
                        <td><?php $sc=['active'=>'success','returned'=>'secondary','overdue'=>'danger','lost'=>'dark'];?>
                            <span class="badge badge-<?= $sc[$b['status']]??"light" ?>"><?= ucfirst($b['status']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($borrows)): ?><tr><td colspan="6" class="text-center text-muted py-3">No records.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RETURN RECORDS -->
    <?php elseif ($tab === 'returns'): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-sign-in-alt mr-2"></i>Return Records (<?= count($returns) ?>)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="returnReport">
                    <thead class="thead-dark">
                        <tr><th>#</th><th>Student</th><th>Book</th><th>Borrowed</th><th>Due Date</th><th>Returned</th><th>Condition</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($returns as $i => $r): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= htmlspecialchars($r['last_name'].', '.$r['first_name']) ?><br><small><?= htmlspecialchars($r['student_id']) ?></small></td>
                        <td><?= htmlspecialchars($r['title']) ?></td>
                        <td><?= date('M d, Y', strtotime($r['borrow_date'])) ?></td>
                        <td><?= date('M d, Y', strtotime($r['due_date'])) ?></td>
                        <td><?= date('M d, Y', strtotime($r['return_date'])) ?></td>
                        <td><?php $cc=['good'=>'success','damaged'=>'warning','lost'=>'danger'];?>
                            <span class="badge badge-<?= $cc[$r['condition_note']]??"light"?>"><?= ucfirst($r['condition_note']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($returns)): ?><tr><td colspan="7" class="text-center text-muted py-3">No records.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PENALTY RECORDS -->
    <?php elseif ($tab === 'penalties'): ?>
    <?php
    $total_pen = array_sum(array_column($penalties, 'total_amount'));
    $paid_pen  = array_sum(array_map(fn($p) => $p['status']==='paid' ? $p['total_amount'] : 0, $penalties));
    ?>
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card border-left-danger py-2 shadow">
                <div class="card-body"><div class="text-xs text-uppercase text-danger font-weight-bold mb-1">Total Penalties</div>
                <div class="h5 font-weight-bold">₱<?= number_format($total_pen,2) ?></div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-success py-2 shadow">
                <div class="card-body"><div class="text-xs text-uppercase text-success font-weight-bold mb-1">Collected</div>
                <div class="h5 font-weight-bold">₱<?= number_format($paid_pen,2) ?></div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-warning py-2 shadow">
                <div class="card-body"><div class="text-xs text-uppercase text-warning font-weight-bold mb-1">Remaining</div>
                <div class="h5 font-weight-bold">₱<?= number_format($total_pen - $paid_pen,2) ?></div></div>
            </div>
        </div>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Penalty Records (<?= count($penalties) ?>)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="penaltyReport">
                    <thead class="thead-dark">
                        <tr><th>#</th><th>Student</th><th>Book</th><th>Overdue Days</th><th>Total Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($penalties as $i => $p): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= htmlspecialchars($p['last_name'].', '.$p['first_name']) ?><br><small><?= htmlspecialchars($p['student_id']) ?></small></td>
                        <td><?= htmlspecialchars($p['title']) ?></td>
                        <td><?= $p['overdue_days'] ?></td>
                        <td class="font-weight-bold">₱<?= number_format($p['total_amount'],2) ?></td>
                        <td><?php $ps=['unpaid'=>'danger','paid'=>'success','waived'=>'secondary'];?>
                            <span class="badge badge-<?= $ps[$p['status']]?>"><?= ucfirst($p['status']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($penalties)): ?><tr><td colspan="6" class="text-center text-muted py-3">No records.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>


</div><!-- /.container-fluid -->
<?php
$page_scripts = '<script>
$(function() {
    var tableId = "#borrowReport, #returnReport, #penaltyReport";
    $("table[id]").each(function() { $(this).DataTable({paging:true, pageLength:25}); });
});
</script>
<style>
@media print {
    .d-print-none { display:none !important; }
    .sidebar, .topbar, .sticky-footer, .scroll-to-top { display:none !important; }
    #content-wrapper { margin-left:0 !important; }
}
</style>';
include '../includes/footer.php';
?>
