<?php
// admin/return.php — Process book returns & calculate penalties
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_admin();
update_overdue_borrows($pdo);

$page_title  = 'Process Returns';
$active_page = 'return';

$daily_rate = (float)get_setting($pdo, 'daily_penalty_rate', DAILY_PENALTY);

// Handle POST: Process return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'return') {
    $borrow_id     = (int)$_POST['borrow_id'];
    $return_date   = $_POST['return_date'] ?? date('Y-m-d');
    $condition     = $_POST['condition_note'] ?? 'good';
    $damage_fee    = (float)($_POST['damage_fee'] ?? 0);
    $notes         = trim($_POST['notes'] ?? '');

    $borrow = $pdo->prepare("SELECT b.*, bk.title, bk.id AS book_id FROM borrows b JOIN books bk ON b.book_id=bk.id WHERE b.id=?");
    $borrow->execute([$borrow_id]);
    $borrow = $borrow->fetch();

    if ($borrow) {
        // Calculate overdue days
        $due    = new DateTime($borrow['due_date']);
        $ret    = new DateTime($return_date);
        $diff   = max(0, (int)$due->diff($ret)->days * ($ret > $due ? 1 : 0));
        $overdue_days = ($ret > $due) ? (int)$due->diff($ret)->days : 0;
        $penalty_amt  = ($overdue_days * $daily_rate) + $damage_fee;

        // Insert return record
        $stmt = $pdo->prepare("INSERT INTO returns (borrow_id, return_date, condition_note, received_by, notes)
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$borrow_id, $return_date, $condition, $_SESSION['user_id'], $notes]);

        // Update borrow status
        $pdo->prepare("UPDATE borrows SET status='returned' WHERE id=?")->execute([$borrow_id]);

        // Restore available copies
        $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id=?")->execute([$borrow['book_id']]);

        // Create penalty record if applicable
        if ($penalty_amt > 0) {
            $pstmt = $pdo->prepare("INSERT INTO penalties (borrow_id, user_id, overdue_days, daily_rate, damage_fee, total_amount)
                                    VALUES (?, ?, ?, ?, ?, ?)");
            $pstmt->execute([$borrow_id, $borrow['user_id'], $overdue_days, $daily_rate, $damage_fee, $penalty_amt]);
            set_flash('success', "Book returned. Penalty of ₱" . number_format($penalty_amt, 2) . " has been recorded.");
        } else {
            set_flash('success', "Book \"{$borrow['title']}\" returned successfully. No penalties.");
        }
    } else {
        set_flash('error', "Borrow record not found.");
    }

    header('Location: return.php');
    exit;
}

// Filter
$filter = $_GET['filter'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT b.*, u.first_name, u.last_name, u.student_id,
               bk.title, bk.author,
               DATEDIFF(CURDATE(), b.due_date) AS days_overdue
        FROM borrows b
        JOIN users u  ON b.user_id = u.id
        JOIN books bk ON b.book_id = bk.id
        WHERE b.status IN ('active','overdue')";
$params = [];
if ($filter === 'overdue') { $sql .= " AND b.status='overdue'"; }
if ($search) {
    $sql .= " AND (u.last_name LIKE ? OR u.student_id LIKE ? OR bk.title LIKE ?)";
    $params = array_fill(0, 3, "%$search%");
}
$sql .= " ORDER BY b.due_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$borrows = $stmt->fetchAll();

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
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-sign-in-alt mr-2 text-primary"></i>Process Returns</h1>
    </div>

    <!-- Filter Bar -->
    <div class="card shadow mb-4">
        <div class="card-body py-3">
            <form method="GET" class="form-inline flex-wrap">
                <input type="text" name="q" class="form-control mr-2 mb-2"
                       placeholder="Student name, ID, or book title..."
                       value="<?= htmlspecialchars($search) ?>" style="min-width:250px;">
                <select name="filter" class="form-control mr-2 mb-2">
                    <option value="" <?= !$filter ? 'selected' : '' ?>>All Active Borrows</option>
                    <option value="overdue" <?= $filter==='overdue' ? 'selected' : '' ?>>Overdue Only</option>
                </select>
                <button type="submit" class="btn btn-primary mr-2 mb-2"><i class="fas fa-filter mr-1"></i>Filter</button>
                <a href="return.php" class="btn btn-secondary mb-2"><i class="fas fa-times mr-1"></i>Clear</a>
            </form>
        </div>
    </div>

    <!-- Borrows Pending Return -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list mr-2"></i>Books Pending Return (<?= count($borrows) ?>)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="returnTable">
                    <thead class="thead-dark">
                        <tr><th>Student</th><th>Book</th><th>Borrowed</th><th>Due Date</th><th>Overdue</th><th>Est. Penalty</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($borrows as $b): ?>
                    <?php $est_penalty = max(0, $b['days_overdue']) * $daily_rate; ?>
                    <tr class="<?= $b['days_overdue'] > 0 ? 'table-warning' : '' ?>">
                        <td>
                            <strong><?= htmlspecialchars($b['last_name'] . ', ' . $b['first_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($b['student_id']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($b['title']) ?><br><small class="text-muted"><?= htmlspecialchars($b['author']) ?></small></td>
                        <td><?= date('M d, Y', strtotime($b['borrow_date'])) ?></td>
                        <td><?= date('M d, Y', strtotime($b['due_date'])) ?></td>
                        <td>
                            <?php if ($b['days_overdue'] > 0): ?>
                                <span class="badge badge-danger"><?= $b['days_overdue'] ?> days</span>
                            <?php else: ?>
                                <span class="badge badge-success">On time</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($est_penalty > 0): ?>
                                <span class="text-danger font-weight-bold">₱<?= number_format($est_penalty, 2) ?></span>
                            <?php else: ?>
                                <span class="text-success">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-success"
                                    onclick='openReturn(<?= json_encode($b) ?>, <?= $daily_rate ?>)'>
                                <i class="fas fa-sign-in-alt mr-1"></i>Return
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($borrows)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No pending returns.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Return Modal -->
<div class="modal fade" id="returnModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="returnForm">
                <input type="hidden" name="action" value="return">
                <input type="hidden" name="borrow_id" id="rBorrowId">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-sign-in-alt mr-2"></i>Process Return</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3" id="rInfo"></div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Return Date</label>
                        <input type="date" name="return_date" id="rReturnDate" class="form-control"
                               value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Book Condition</label>
                        <select name="condition_note" id="rCondition" class="form-control" onchange="calcPenalty()">
                            <option value="good">Good condition</option>
                            <option value="damaged">Damaged — add damage fee</option>
                            <option value="lost">Lost — full replacement fee</option>
                        </select>
                    </div>
                    <div class="form-group" id="damageFeeGroup" style="display:none;">
                        <label class="small font-weight-bold">Damage Fee (₱)</label>
                        <input type="number" name="damage_fee" id="rDamageFee" class="form-control"
                               min="0" step="0.01" value="0" onchange="calcPenalty()">
                    </div>
                    <div class="card bg-light mb-3">
                        <div class="card-body py-2">
                            <div class="row small">
                                <div class="col-6">Overdue Days: <strong id="rOverdueDays">0</strong></div>
                                <div class="col-6">Daily Rate: <strong>₱<?= number_format($daily_rate, 2) ?></strong></div>
                                <div class="col-6">Overdue Fee: <strong id="rOverdueFee">₱0.00</strong></div>
                                <div class="col-6">Damage Fee: <strong id="rDamageFeeDisplay">₱0.00</strong></div>
                            </div>
                            <hr class="my-1">
                            <div class="text-center font-weight-bold">
                                Total Penalty: <span id="rTotalPenalty" class="text-danger">₱0.00</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i>Confirm Return</button>
                </div>
            </form>
        </div>
    </div>
</div>


</div><!-- /.container-fluid -->
<?php
$page_scripts = '<script>
var dailyRate = ' . $daily_rate . ';
var dueDate = "";

$(function() {
    $("#returnTable").DataTable({ order: [[3, "asc"]] });
    document.getElementById("rReturnDate").addEventListener("change", calcPenalty);
    document.getElementById("rCondition").addEventListener("change", function() {
        var show = this.value !== "good";
        document.getElementById("damageFeeGroup").style.display = show ? "" : "none";
        if (!show) document.getElementById("rDamageFee").value = 0;
        calcPenalty();
    });
});

function openReturn(b, rate) {
    dueDate = b.due_date;
    dailyRate = rate;
    document.getElementById("rBorrowId").value = b.id;
    document.getElementById("rInfo").innerHTML =
        "<strong>" + b.last_name + ", " + b.first_name + "</strong> &mdash; " + b.title +
        "<br>Due: <strong>" + b.due_date + "</strong>";
    document.getElementById("rCondition").value = "good";
    document.getElementById("damageFeeGroup").style.display = "none";
    document.getElementById("rDamageFee").value = 0;
    calcPenalty();
    $("#returnModal").modal("show");
}

function calcPenalty() {
    var returnDate = document.getElementById("rReturnDate").value;
    var due = new Date(dueDate);
    var ret = new Date(returnDate);
    var diffDays = Math.max(0, Math.floor((ret - due) / 86400000));
    var damageFee = parseFloat(document.getElementById("rDamageFee").value) || 0;
    var overdueFee = diffDays * dailyRate;
    var total = overdueFee + damageFee;
    document.getElementById("rOverdueDays").textContent = diffDays;
    document.getElementById("rOverdueFee").textContent = "₱" + overdueFee.toFixed(2);
    document.getElementById("rDamageFeeDisplay").textContent = "₱" + damageFee.toFixed(2);
    document.getElementById("rTotalPenalty").textContent = "₱" + total.toFixed(2);
    document.getElementById("rTotalPenalty").style.color = total > 0 ? "red" : "green";
}
</script>';
include '../includes/footer.php';
?>
