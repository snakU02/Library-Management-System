<?php
// student/my_penalties.php
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_student();

$page_title  = 'My Penalties';
$active_page = 'penalties';
$uid         = $_SESSION['user_id'];

$penalties = $pdo->prepare("
    SELECT p.*, bk.title, b.due_date, b.borrow_date
    FROM penalties p
    JOIN borrows b ON p.borrow_id = b.id
    JOIN books bk  ON b.book_id = bk.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
");
$penalties->execute([$uid]);
$penalties = $penalties->fetchAll();

$total_unpaid = array_sum(array_map(fn($p) => $p['status'] === 'unpaid' ? $p['total_amount'] : 0, $penalties));

include '../includes/header.php';
include '../includes/sidebar_student.php';
?>
<div class="container-fluid">
    <div class="mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-exclamation-circle mr-2 text-danger"></i>My Penalties</h1>
    </div>

    <?php if ($total_unpaid > 0): ?>
    <div class="alert alert-danger d-flex align-items-center mb-4">
        <i class="fas fa-exclamation-triangle fa-2x mr-3"></i>
        <div>
            <strong>You have an unpaid penalty of ₱<?= number_format($total_unpaid, 2) ?></strong><br>
            <span class="small">Please settle your penalty at the library desk to continue borrowing books.</span>
        </div>
    </div>
    <?php elseif (!empty($penalties)): ?>
    <div class="alert alert-success mb-4">
        <i class="fas fa-check-circle mr-2"></i>All penalties paid. Thank you!
    </div>
    <?php endif; ?>

    <?php if (empty($penalties)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-smile fa-3x mb-3 text-success"></i><br>
        <h5>No penalty records</h5>
        <p class="small">Great! You have no penalties. Keep returning books on time!</p>
    </div>
    <?php else: ?>
    <div class="row">
        <?php foreach ($penalties as $p): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card shadow <?= $p['status']==='unpaid'?'border-danger':'' ?>">
                <div class="card-header py-2 d-flex justify-content-between align-items-center
                            <?= $p['status']==='unpaid'?'bg-danger text-white':($p['status']==='paid'?'bg-success text-white':'') ?>">
                    <strong>₱<?= number_format($p['total_amount'], 2) ?></strong>
                    <?php $sc=['unpaid'=>'light','paid'=>'light','waived'=>'secondary']; ?>
                    <span class="badge badge-<?= $sc[$p['status']] ?>"><?= ucfirst($p['status']) ?></span>
                </div>
                <div class="card-body">
                    <h6 class="font-weight-bold"><?= htmlspecialchars($p['title']) ?></h6>
                    <table class="table table-sm table-borderless small mb-0">
                        <tr><th width="50%">Overdue Days</th><td><?= $p['overdue_days'] ?></td></tr>
                        <tr><th>Daily Rate</th><td>₱<?= number_format($p['daily_rate'], 2) ?>/day</td></tr>
                        <tr><th>Overdue Fee</th><td>₱<?= number_format($p['overdue_days'] * $p['daily_rate'], 2) ?></td></tr>
                        <tr><th>Damage Fee</th><td>₱<?= number_format($p['damage_fee'], 2) ?></td></tr>
                        <tr class="font-weight-bold"><th>Total</th><td class="text-danger">₱<?= number_format($p['total_amount'], 2) ?></td></tr>
                    </table>
                    <?php if ($p['status'] === 'paid' && $p['paid_at']): ?>
                        <small class="text-muted"><i class="fas fa-check mr-1"></i>Paid on <?= date('M d, Y', strtotime($p['paid_at'])) ?></small>
                    <?php endif; ?>
                </div>
                <?php if ($p['status'] === 'unpaid'): ?>
                <div class="card-footer small bg-light">
                    <i class="fas fa-info-circle mr-1 text-info"></i>
                    Please pay at the library desk. Show this screen to the librarian.
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
