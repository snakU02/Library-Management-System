<?php
// admin/search.php — Search books / authors / publishers / student last name
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_admin();

$page_title  = 'Search';
$active_page = 'search';

$q    = trim($_GET['q']    ?? '');
$type = $_GET['type']      ?? 'all';

$books   = [];
$students = [];

if ($q) {
    // Search books
    if ($type === 'all' || $type === 'books') {
        $stmt = $pdo->prepare("
            SELECT b.*, c.name AS category_name FROM books b
            LEFT JOIN categories c ON b.category_id=c.id
            WHERE b.status='active'
              AND (b.title LIKE ? OR b.author LIKE ? OR b.publisher LIKE ? OR b.isbn LIKE ?)
            ORDER BY b.title LIMIT 50
        ");
        $stmt->execute(array_fill(0, 4, "%$q%"));
        $books = $stmt->fetchAll();
    }

    // Search students by last name
    if ($type === 'all' || $type === 'students') {
        $stmt = $pdo->prepare("
            SELECT u.*,
                   (SELECT COUNT(*) FROM borrows b WHERE b.user_id=u.id AND b.status IN ('active','overdue')) AS active_borrows,
                   (SELECT COALESCE(SUM(p.total_amount),0) FROM penalties p WHERE p.user_id=u.id AND p.status='unpaid') AS unpaid
            FROM users u
            WHERE u.role='student' AND (u.last_name LIKE ? OR u.student_id LIKE ?)
            ORDER BY u.last_name LIMIT 30
        ");
        $stmt->execute(["%$q%", "%$q%"]);
        $students = $stmt->fetchAll();
    }
}

include '../includes/header.php';
include '../includes/sidebar_admin.php';
?>
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-search mr-2 text-primary"></i>Search</h1>
    </div>

    <!-- Search Form -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET">
                <div class="form-row align-items-end">
                    <div class="col-md-6 form-group mb-0">
                        <label class="small font-weight-bold">Search Query</label>
                        <input type="text" name="q" class="form-control form-control-lg"
                               placeholder="Enter title, author, publisher, ISBN, or student last name..."
                               value="<?= htmlspecialchars($q) ?>" autofocus>
                    </div>
                    <div class="col-md-3 form-group mb-0">
                        <label class="small font-weight-bold">Search In</label>
                        <select name="type" class="form-control form-control-lg">
                            <option value="all"      <?= $type==='all'     ?'selected':'' ?>>Books & Students</option>
                            <option value="books"    <?= $type==='books'   ?'selected':'' ?>>Books Only</option>
                            <option value="students" <?= $type==='students'?'selected':'' ?>>Students Only</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group mb-0">
                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($q): ?>

    <!-- Book Results -->
    <?php if ($type === 'all' || $type === 'books'): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-book mr-2"></i>Books
                <span class="badge badge-primary ml-2"><?= count($books) ?></span>
            </h6>
        </div>
        <div class="card-body">
            <?php if (empty($books)): ?>
                <p class="text-muted text-center py-3">No books found for "<?= htmlspecialchars($q) ?>".</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="bookSearchTable">
                    <thead class="thead-light">
                        <tr><th>Title</th><th>Author</th><th>Publisher</th><th>Category</th><th>Available</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($books as $b): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($b['title']) ?></strong>
                            <?php if ($b['edition']): ?><small class="text-muted">, <?= htmlspecialchars($b['edition']) ?> ed.</small><?php endif; ?>
                            <?php if ($b['isbn']): ?><br><small class="text-muted">ISBN: <?= htmlspecialchars($b['isbn']) ?></small><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($b['author']) ?></td>
                        <td><?= htmlspecialchars($b['publisher'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($b['category_name'] ?? '—') ?></td>
                        <td>
                            <span class="badge badge-<?= $b['available_copies'] > 0 ? 'success' : 'danger' ?>">
                                <?= $b['available_copies'] ?>/<?= $b['total_copies'] ?>
                            </span>
                        </td>
                        <td>
                            <a href="books.php?q=<?= urlencode($b['title']) ?>" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Student Results -->
    <?php if ($type === 'all' || $type === 'students'): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-success">
                <i class="fas fa-users mr-2"></i>Students
                <span class="badge badge-success ml-2"><?= count($students) ?></span>
            </h6>
        </div>
        <div class="card-body">
            <?php if (empty($students)): ?>
                <p class="text-muted text-center py-3">No students found for "<?= htmlspecialchars($q) ?>".</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="studentSearchTable">
                    <thead class="thead-light">
                        <tr><th>Student ID</th><th>Name</th><th>Course/Year</th><th>Active Borrows</th><th>Unpaid Penalty</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['student_id']) ?></td>
                        <td><strong><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name']) ?></strong></td>
                        <td><?= htmlspecialchars($s['course'] ?? '') ?> <?= $s['year_level'] ? 'Yr.'.$s['year_level'] : '' ?></td>
                        <td><span class="badge badge-info"><?= $s['active_borrows'] ?></span></td>
                        <td>
                            <?php if ($s['unpaid'] > 0): ?>
                                <span class="badge badge-danger">₱<?= number_format($s['unpaid'], 2) ?></span>
                            <?php else: ?>
                                <span class="badge badge-success">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $sc = ['active'=>'success','pending'=>'warning','inactive'=>'secondary']; ?>
                            <span class="badge badge-<?= $sc[$s['status']] ?? 'light' ?>"><?= ucfirst($s['status']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Empty State -->
    <div class="text-center py-5 text-muted">
        <i class="fas fa-search fa-4x mb-3 opacity-50"></i>
        <h5>Enter a search query to find books, authors, publishers, or students</h5>
        <p class="small">You can search by: Book Title, Author, Publisher, ISBN, or Student Last Name</p>
    </div>
    <?php endif; ?>
</div>


</div><!-- /.container-fluid -->
<?php
$page_scripts = '<script>
$(function() {
    if ($("#bookSearchTable").length)    $("#bookSearchTable").DataTable({paging:false});
    if ($("#studentSearchTable").length) $("#studentSearchTable").DataTable({paging:false});
});
</script>';
include '../includes/footer.php';
?>
