<?php
// student/catalogue.php — Browse all books
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_student();

$page_title  = 'View Books';
$active_page = 'catalogue';

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$search = trim($_GET['q']    ?? '');
$cat    = (int)($_GET['cat'] ?? 0);
$avail  = $_GET['avail']     ?? '';

$sql  = "SELECT b.*, c.name AS category_name FROM books b LEFT JOIN categories c ON b.category_id=c.id WHERE b.status='active'";
$params = [];
if ($search) { $sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.publisher LIKE ? OR b.isbn LIKE ?)"; $params = array_fill(0,4,"%$search%"); }
if ($cat)    { $sql .= " AND b.category_id=?"; $params[] = $cat; }
if ($avail === 'yes') { $sql .= " AND b.available_copies > 0"; }
$sql .= " ORDER BY b.title";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$books = $stmt->fetchAll();

// Student's current reservations (to check if already reserved)
$uid = $_SESSION['user_id'];
$my_res = $pdo->prepare("SELECT book_id FROM reservations WHERE user_id=? AND status IN ('pending','approved')");
$my_res->execute([$uid]);
$reserved_books = array_column($my_res->fetchAll(), 'book_id');

// Student's current borrows
$my_borrows = $pdo->prepare("SELECT book_id FROM borrows WHERE user_id=? AND status IN ('active','overdue')");
$my_borrows->execute([$uid]);
$borrowed_books = array_column($my_borrows->fetchAll(), 'book_id');

include '../includes/header.php';
include '../includes/sidebar_student.php';
?>
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book-open mr-2 text-success"></i>Library Catalogue</h1>
    </div>

    <!-- Filter -->
    <div class="card shadow mb-4">
        <div class="card-body py-3">
            <form method="GET" class="form-inline flex-wrap">
                <input type="text" name="q" class="form-control mr-2 mb-2" style="min-width:220px;"
                       placeholder="Search title, author, publisher..."
                       value="<?= htmlspecialchars($search) ?>">
                <select name="cat" class="form-control mr-2 mb-2">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $cat==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="avail" class="form-control mr-2 mb-2">
                    <option value="">Any</option>
                    <option value="yes" <?= $avail==='yes'?'selected':'' ?>>Available Now</option>
                </select>
                <button type="submit" class="btn btn-success mr-2 mb-2"><i class="fas fa-search mr-1"></i>Search</button>
                <a href="catalogue.php" class="btn btn-secondary mb-2"><i class="fas fa-times mr-1"></i>Clear</a>
            </form>
        </div>
    </div>

    <!-- Grid -->
    <div class="row">
        <?php foreach ($books as $book): ?>
        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4">
            <div class="card shadow h-100 book-card">
                <div style="height:160px;overflow:hidden;background:linear-gradient(135deg,#1cc88a,#17a673);
                            display:flex;align-items:center;justify-content:center;cursor:pointer;"
                     data-toggle="modal" data-target="#bookDetailModal"
                     onclick='showBookDetail(<?= json_encode($book) ?>)'>
                    <?php if ($book['cover_image']): ?>
                        <img src="../uploads/covers/<?= htmlspecialchars($book['cover_image']) ?>"
                             alt="Cover" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <i class="fas fa-book fa-3x text-white-50"></i>
                    <?php endif; ?>
                </div>
                <div class="card-body p-2">
                    <div class="small font-weight-bold text-truncate" title="<?= htmlspecialchars($book['title']) ?>">
                        <?= htmlspecialchars($book['title']) ?>
                    </div>
                    <div class="x-small text-muted text-truncate"><?= htmlspecialchars($book['author']) ?></div>
                </div>
                <div class="card-footer p-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge badge-<?= $book['available_copies'] > 0 ? 'success' : 'danger' ?> x-small">
                            <?= $book['available_copies'] > 0 ? $book['available_copies'].' avail.' : 'Not Available' ?>
                        </span>
                        <?php
                        $is_borrowed  = in_array($book['id'], $borrowed_books);
                        $is_reserved  = in_array($book['id'], $reserved_books);
                        ?>
                        <?php if ($is_borrowed): ?>
                            <span class="badge badge-info x-small">Borrowed</span>
                        <?php elseif ($is_reserved): ?>
                            <span class="badge badge-warning x-small">Reserved</span>
                        <?php elseif ($book['available_copies'] > 0): ?>
                            <button class="btn btn-xs btn-success"
                                    data-toggle="modal" data-target="#reserveModal"
                                    onclick="setReserve(<?= $book['id'] ?>, '<?= addslashes($book['title']) ?>')">
                                Reserve
                            </button>
                        <?php else: ?>
                            <button class="btn btn-xs btn-outline-warning"
                                    data-toggle="modal" data-target="#reserveModal"
                                    onclick="setReserve(<?= $book['id'] ?>, '<?= addslashes($book['title']) ?>')">
                                Queue
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($books)): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-search fa-3x mb-3"></i><br>No books found.
            </div>
        <?php endif; ?>
    </div>
    <p class="text-muted small"><?= count($books) ?> book title(s) found.</p>
</div>

<!-- Book Detail Modal -->
<div class="modal fade" id="bookDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="bdTitle"></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3 text-center mb-3" id="bdCoverWrap"></div>
                    <div class="col-md-9">
                        <table class="table table-sm table-borderless">
                            <tr><th width="35%">Author</th><td id="bdAuthor"></td></tr>
                            <tr><th>Publisher</th><td id="bdPublisher"></td></tr>
                            <tr><th>Year / Edition</th><td id="bdYearEd"></td></tr>
                            <tr><th>Category</th><td id="bdCat"></td></tr>
                            <tr><th>Location</th><td id="bdLocation"></td></tr>
                            <tr><th>Available Copies</th><td id="bdAvail"></td></tr>
                        </table>
                        <p id="bdDesc" class="text-muted small"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Reserve Modal -->
<div class="modal fade" id="reserveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="reserve.php">
                <input type="hidden" name="action" value="reserve">
                <input type="hidden" name="book_id" id="reserveBookId">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-calendar-alt mr-2"></i>Reserve Book</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>You are reserving: <strong id="reserveBookTitle"></strong></p>
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle mr-1"></i>
                        A reservation must be approved by the librarian. Once approved, you have
                        <?= get_setting($pdo, 'reservation_expiry_days', RESERVATION_DAYS) ?> days to pick up the book.
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Notes (optional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Any notes for the librarian..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-calendar-check mr-1"></i>Submit Reservation</button>
                </div>
            </form>
        </div>
    </div>
</div>


</div><!-- /.container-fluid -->
<?php
$page_scripts = '<script>
function setReserve(bookId, bookTitle) {
    document.getElementById("reserveBookId").value = bookId;
    document.getElementById("reserveBookTitle").textContent = bookTitle;
}
function showBookDetail(b) {
    document.getElementById("bdTitle").textContent    = b.title;
    document.getElementById("bdAuthor").textContent   = b.author;
    document.getElementById("bdPublisher").textContent= b.publisher || "—";
    document.getElementById("bdYearEd").textContent   = (b.publication_year||"") + (b.edition?", "+b.edition+" ed.":"");
    document.getElementById("bdCat").textContent      = b.category_name || "—";
    document.getElementById("bdLocation").textContent = b.location || "—";
    document.getElementById("bdDesc").textContent     = b.description || "";
    var av = document.getElementById("bdAvail");
    av.innerHTML = "<span class=\'badge badge-"+(b.available_copies>0?"success":"danger")+"\'>"+b.available_copies+" available</span>";
    var cw = document.getElementById("bdCoverWrap");
    cw.innerHTML = b.cover_image
        ? "<img src=\'../uploads/covers/"+b.cover_image+"\' class=\'img-fluid rounded shadow\'>"
        : "<div class=\'bg-success p-4 rounded text-center\'><i class=\'fas fa-book fa-3x text-white\'></i></div>";
}
</script>';
include '../includes/footer.php';
?>
