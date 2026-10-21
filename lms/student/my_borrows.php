<?php
// student/my_borrows.php
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_student();
update_overdue_borrows($pdo);

$page_title  = 'My Borrows';
$active_page = 'borrows';
$uid         = $_SESSION['user_id'];

$borrows = $pdo->prepare("
    SELECT b.*, bk.title, bk.author, bk.cover_image,
           DATEDIFF(b.due_date, CURDATE()) AS days_left
    FROM borrows b
    JOIN books bk ON b.book_id = bk.id
    WHERE b.user_id = ?
    ORDER BY FIELD(b.status,'overdue','active','returned','lost'), b.due_date
");
$borrows->execute([$uid]);
$borrows = $borrows->fetchAll();

include '../includes/header.php';
include '../includes/sidebar_student.php';
?>
<div class="container-fluid">
    <div class="mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-hand-holding-medical mr-2 text-success"></i>My Borrowed Books</h1>
    </div>

    <?php
    $active = array_filter($borrows, fn($b) => in_array($b['status'],['active','overdue']));
    $history= array_filter($borrows, fn($b) => !in_array($b['status'],['active','overdue']));
    ?>

    <!-- Active Borrows -->
    <h5 class="font-weight-bold text-primary mb-3"><i class="fas fa-book mr-2"></i>Currently Borrowed (<?= count($active) ?>)</h5>
    <?php if (empty($active)): ?>
        <div class="alert alert-info"><i class="fas fa-info-circle mr-2"></i>You have no books currently borrowed.</div>
    <?php else: ?>
    <div class="row mb-4">
        <?php foreach ($active as $b): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card shadow h-100 <?= $b['status']==='overdue'?'border-danger':'' ?>">
                <?php if ($b['status'] === 'overdue'): ?>
                    <div class="card-header py-2 bg-danger text-white small">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        OVERDUE — <?= abs($b['days_left']) ?> day(s) late
                    </div>
                <?php elseif ($b['days_left'] <= 2): ?>
                    <div class="card-header py-2 bg-warning small">
                        <i class="fas fa-clock mr-1"></i>Due soon: <?= $b['days_left'] ?> day(s) left
                    </div>
                <?php endif; ?>
                <div class="card-body d-flex">
                    <div class="mr-3" style="width:60px;flex-shrink:0;">
                        <?php if ($b['cover_image']): ?>
                            <img src="../uploads/covers/<?= htmlspecialchars($b['cover_image']) ?>"
                                 style="width:60px;height:80px;object-fit:cover;border-radius:4px;">
                        <?php else: ?>
                            <div class="bg-primary d-flex align-items-center justify-content-center"
                                 style="width:60px;height:80px;border-radius:4px;">
                                <i class="fas fa-book text-white"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="font-weight-bold"><?= htmlspecialchars($b['title']) ?></div>
                        <div class="text-muted small mb-1"><?= htmlspecialchars($b['author']) ?></div>
                        <div class="small">
                            <i class="fas fa-calendar mr-1"></i>Borrowed: <?= date('M d, Y', strtotime($b['borrow_date'])) ?>
                        </div>
                        <div class="small <?= $b['status']==='overdue'?'text-danger font-weight-bold':'' ?>">
                            <i class="fas fa-calendar-times mr-1"></i>Due: <?= date('M d, Y', strtotime($b['due_date'])) ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer py-2 small text-muted">
                    Please return to the library desk to return this book.
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Borrow History -->
    <?php if (!empty($history)): ?>
    <h5 class="font-weight-bold text-secondary mb-3"><i class="fas fa-history mr-2"></i>Borrow History (<?= count($history) ?>)</h5>
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="historyTable">
                    <thead class="thead-light">
                        <tr><th>Book</th><th>Author</th><th>Borrowed</th><th>Due</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $b): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($b['title']) ?></strong></td>
                        <td><?= htmlspecialchars($b['author']) ?></td>
                        <td><?= date('M d, Y', strtotime($b['borrow_date'])) ?></td>
                        <td><?= date('M d, Y', strtotime($b['due_date'])) ?></td>
                        <td>
                            <?php $sc=['returned'=>'secondary','lost'=>'dark']; ?>
                            <span class="badge badge-<?= $sc[$b['status']]??"secondary" ?>"><?= ucfirst($b['status']) ?></span>
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
$page_scripts = '<script>$(function(){ if($("#historyTable").length) $("#historyTable").DataTable({order:[[2,"desc"]]}); });</script>';
include '../includes/footer.php';
?>
