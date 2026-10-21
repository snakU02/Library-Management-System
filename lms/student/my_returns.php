<?php
// student/my_returns.php — Student view of their return history
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_student();

$page_title  = 'My Returns';
$active_page = 'returns';
$uid         = $_SESSION['user_id'];

$returns = $pdo->prepare("
    SELECT rt.*, b.borrow_date, b.due_date, bk.title, bk.author, bk.cover_image,
           DATEDIFF(rt.return_date, b.due_date) AS days_late
    FROM returns rt
    JOIN borrows b ON rt.borrow_id = b.id
    JOIN books bk  ON b.book_id = bk.id
    WHERE b.user_id = ?
    ORDER BY rt.return_date DESC
");
$returns->execute([$uid]);
$returns = $returns->fetchAll();

include '../includes/header.php';
include '../includes/sidebar_student.php';
?>
<div class="container-fluid">
    <div class="mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-undo-alt mr-2 text-success"></i>My Return History</h1>
    </div>

    <?php if (empty($returns)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-undo fa-3x mb-3"></i><br>
        <h5>No return records yet</h5>
        <p class="small">Your returned books will appear here.</p>
    </div>
    <?php else: ?>
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="returnsTable">
                    <thead class="thead-dark">
                        <tr><th>Book</th><th>Borrowed</th><th>Due Date</th><th>Returned</th><th>Days Late</th><th>Condition</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($returns as $r): ?>
                    <tr class="<?= $r['days_late'] > 0 ? 'table-warning' : '' ?>">
                        <td>
                            <strong><?= htmlspecialchars($r['title']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($r['author']) ?></small>
                        </td>
                        <td><?= date('M d, Y', strtotime($r['borrow_date'])) ?></td>
                        <td><?= date('M d, Y', strtotime($r['due_date'])) ?></td>
                        <td><?= date('M d, Y', strtotime($r['return_date'])) ?></td>
                        <td>
                            <?php if ($r['days_late'] > 0): ?>
                                <span class="badge badge-danger"><?= $r['days_late'] ?> days late</span>
                            <?php else: ?>
                                <span class="badge badge-success">On time</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $cc=['good'=>'success','damaged'=>'warning','lost'=>'danger']; ?>
                            <span class="badge badge-<?= $cc[$r['condition_note']] ?>"><?= ucfirst($r['condition_note']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>


</div><!-- /.container-fluid -->
<?php
$page_scripts = '<script>$(function(){ if($("#returnsTable").length) $("#returnsTable").DataTable({order:[[3,"desc"]]}); });</script>';
include '../includes/footer.php';
?>
