<?php
// student/search.php — Search books by author, title, publisher
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_student();

$page_title  = 'Search Books';
$active_page = 'search';
$uid         = $_SESSION['user_id'];

$q    = trim($_GET['q']    ?? '');
$type = $_GET['type']      ?? 'all';
$books = [];

if ($q) {
    $sql = "SELECT b.*, c.name AS category_name FROM books b
            LEFT JOIN categories c ON b.category_id=c.id
            WHERE b.status='active'";
    $params = [];

    if ($type === 'title') {
        $sql .= " AND b.title LIKE ?"; $params[] = "%$q%";
    } elseif ($type === 'author') {
        $sql .= " AND b.author LIKE ?"; $params[] = "%$q%";
    } elseif ($type === 'publisher') {
        $sql .= " AND b.publisher LIKE ?"; $params[] = "%$q%";
    } else {
        $sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.publisher LIKE ? OR b.isbn LIKE ?)";
        $params = array_fill(0, 4, "%$q%");
    }
    $sql .= " ORDER BY b.title LIMIT 60";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll();
}

// Student's borrowed and reserved books
$my_borrows = $pdo->prepare("SELECT book_id FROM borrows WHERE user_id=? AND status IN ('active','overdue')");
$my_borrows->execute([$uid]);
$borrowed_books = array_column($my_borrows->fetchAll(), 'book_id');

$my_res = $pdo->prepare("SELECT book_id FROM reservations WHERE user_id=? AND status IN ('pending','approved')");
$my_res->execute([$uid]);
$reserved_books = array_column($my_res->fetchAll(), 'book_id');

include '../includes/header.php';
include '../includes/sidebar_student.php';
?>
<div class="container-fluid">
    <div class="mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-search mr-2 text-success"></i>Search Books</h1>
    </div>

    <!-- Search Form -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET">
                <div class="form-row align-items-end">
                    <div class="col-md-5 form-group mb-0">
                        <label class="small font-weight-bold">Search</label>
                        <input type="text" name="q" class="form-control form-control-lg"
                               placeholder="Enter keyword..." value="<?= htmlspecialchars($q) ?>" autofocus>
                    </div>
                    <div class="col-md-4 form-group mb-0">
                        <label class="small font-weight-bold">Search By</label>
                        <select name="type" class="form-control form-control-lg">
                            <option value="all"       <?= $type==='all'      ?'selected':'' ?>>All Fields</option>
                            <option value="title"     <?= $type==='title'    ?'selected':'' ?>>Title</option>
                            <option value="author"    <?= $type==='author'   ?'selected':'' ?>>Author</option>
                            <option value="publisher" <?= $type==='publisher'?'selected':'' ?>>Publisher</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group mb-0">
                        <button type="submit" class="btn btn-success btn-lg btn-block">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($q): ?>
    <div class="mb-3">
        <strong><?= count($books) ?></strong> result(s) for
        "<em><?= htmlspecialchars($q) ?></em>"
        <?= $type !== 'all' ? "in <strong>$type</strong>" : '' ?>
    </div>

    <?php if (empty($books)): ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-search fa-3x mb-3"></i><br>
            No books found. Try different keywords.
        </div>
    <?php else: ?>
    <div class="row">
        <?php foreach ($books as $book): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-body d-flex">
                    <div class="mr-3" style="width:60px;flex-shrink:0;">
                        <?php if ($book['cover_image']): ?>
                            <img src="../uploads/covers/<?= htmlspecialchars($book['cover_image']) ?>"
                                 style="width:60px;height:80px;object-fit:cover;border-radius:4px;">
                        <?php else: ?>
                            <div class="bg-success d-flex align-items-center justify-content-center"
                                 style="width:60px;height:80px;border-radius:4px;">
                                <i class="fas fa-book text-white"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="font-weight-bold mb-1"><?= htmlspecialchars($book['title']) ?>
                            <?php if ($book['edition']): ?><small class="text-muted">, <?= htmlspecialchars($book['edition']) ?> ed.</small><?php endif; ?>
                        </h6>
                        <div class="text-muted small mb-1">
                            <i class="fas fa-user mr-1"></i><?= htmlspecialchars($book['author']) ?>
                        </div>
                        <?php if ($book['publisher']): ?>
                        <div class="text-muted small mb-1">
                            <i class="fas fa-building mr-1"></i><?= htmlspecialchars($book['publisher']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($book['category_name']): ?>
                            <span class="badge badge-light border small"><?= htmlspecialchars($book['category_name']) ?></span>
                        <?php endif; ?>
                        <div class="mt-2">
                            <span class="badge badge-<?= $book['available_copies'] > 0 ? 'success' : 'danger' ?>">
                                <?= $book['available_copies'] > 0 ? $book['available_copies'].' copies available' : 'Not available' ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-footer py-2">
                    <?php
                    $is_borrowed = in_array($book['id'], $borrowed_books);
                    $is_reserved = in_array($book['id'], $reserved_books);
                    ?>
                    <?php if ($is_borrowed): ?>
                        <span class="badge badge-info"><i class="fas fa-check mr-1"></i>Already borrowed</span>
                    <?php elseif ($is_reserved): ?>
                        <span class="badge badge-warning"><i class="fas fa-clock mr-1"></i>Reserved</span>
                    <?php else: ?>
                        <form method="POST" action="reserve.php" style="display:inline">
                            <input type="hidden" name="action" value="reserve">
                            <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                            <button class="btn btn-sm btn-success">
                                <i class="fas fa-calendar-plus mr-1"></i>Reserve
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($book['location']): ?>
                        <small class="text-muted ml-2"><i class="fas fa-map-marker-alt mr-1"></i><?= htmlspecialchars($book['location']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <!-- Search tips -->
    <div class="text-center py-5 text-muted">
        <i class="fas fa-search fa-4x mb-3 opacity-50"></i>
        <h5>Search the Library Catalogue</h5>
        <p>Find books by Title, Author, or Publisher</p>
        <div class="row justify-content-center mt-3">
            <div class="col-md-3">
                <div class="card border-0 bg-light p-3 text-center">
                    <i class="fas fa-book fa-2x text-success mb-2"></i>
                    <div class="small font-weight-bold">By Title</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-light p-3 text-center">
                    <i class="fas fa-user fa-2x text-primary mb-2"></i>
                    <div class="small font-weight-bold">By Author</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-light p-3 text-center">
                    <i class="fas fa-building fa-2x text-info mb-2"></i>
                    <div class="small font-weight-bold">By Publisher</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

</div><!-- /.container-fluid -->
<?php include '../includes/footer.php'; ?>
