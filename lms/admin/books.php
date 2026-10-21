<?php
// admin/books.php — Add / Edit / Delete Books
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_admin();

$page_title  = 'Manage Books';
$active_page = 'books';

// Load categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id             = (int)($_POST['id'] ?? 0);
        $isbn           = trim($_POST['isbn']           ?? '');
        $title          = trim($_POST['title']          ?? '');
        $author         = trim($_POST['author']         ?? '');
        $publisher      = trim($_POST['publisher']      ?? '');
        $pub_year       = (int)($_POST['publication_year'] ?? 0);
        $edition        = trim($_POST['edition']        ?? '');
        $category_id    = (int)($_POST['category_id']   ?? 0) ?: null;
        $description    = trim($_POST['description']    ?? '');
        $total_copies   = max(1, (int)($_POST['total_copies'] ?? 1));
        $location       = trim($_POST['location']       ?? '');

        if (!$title || !$author) {
            set_flash('error', 'Title and Author are required.');
        } else {
            // Handle cover image upload
            $cover_image = $_POST['existing_cover'] ?? null;
            if (!empty($_FILES['cover_image']['name'])) {
                $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                    $upload_dir = __DIR__ . '/../uploads/covers/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $filename = uniqid('book_') . '.' . $ext;
                    move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_dir . $filename);
                    $cover_image = $filename;
                }
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO books
                    (isbn, title, author, publisher, publication_year, edition, category_id, description,
                     cover_image, total_copies, available_copies, location)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$isbn,$title,$author,$publisher,$pub_year,$edition,$category_id,
                                $description,$cover_image,$total_copies,$total_copies,$location]);
                set_flash('success', "Book \"$title\" added successfully.");
            } else {
                // On edit: adjust available_copies by delta
                $old = $pdo->prepare("SELECT total_copies, available_copies FROM books WHERE id=?");
                $old->execute([$id]);
                $old = $old->fetch();
                $delta = $total_copies - ($old['total_copies'] ?? 0);
                $new_avail = max(0, ($old['available_copies'] ?? 0) + $delta);

                $stmt = $pdo->prepare("UPDATE books SET
                    isbn=?, title=?, author=?, publisher=?, publication_year=?, edition=?,
                    category_id=?, description=?, total_copies=?, available_copies=?, location=?,
                    cover_image=COALESCE(?,cover_image)
                    WHERE id=?");
                $stmt->execute([$isbn,$title,$author,$publisher,$pub_year,$edition,$category_id,
                                $description,$total_copies,$new_avail,$location,$cover_image,$id]);
                set_flash('success', "Book \"$title\" updated successfully.");
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $book = $pdo->prepare("SELECT title FROM books WHERE id=?");
        $book->execute([$id]);
        $book = $book->fetch();
        $pdo->prepare("UPDATE books SET status='inactive' WHERE id=?")->execute([$id]);
        set_flash('success', "Book \"{$book['title']}\" removed from catalogue.");
    }

    header('Location: books.php');
    exit;
}

// Load books
$search = trim($_GET['q'] ?? '');
$cat    = (int)($_GET['cat'] ?? 0);
$sql    = "SELECT b.*, c.name AS category_name FROM books b LEFT JOIN categories c ON b.category_id=c.id
           WHERE b.status='active'";
$params = [];
if ($search) {
    $sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.publisher LIKE ?)";
    $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
}
if ($cat) {
    $sql .= " AND b.category_id=?";
    $params[] = $cat;
}
$sql .= " ORDER BY b.title";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$flash = get_flash();

include '../includes/header.php';
include '../includes/sidebar_admin.php';
?>
<div class="container-fluid">
    <!-- Flash -->
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
        <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?> mr-2"></i>
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php endif; ?>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-2 text-primary"></i>Manage Books</h1>
        <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#bookModal"
                onclick="openAddBook()">
            <i class="fas fa-plus fa-sm mr-2"></i>Add New Book
        </button>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body py-3">
            <form method="GET" class="form-inline flex-wrap">
                <input type="text" name="q" class="form-control mr-2 mb-2" placeholder="Search title, author, ISBN..." value="<?= htmlspecialchars($search) ?>" style="min-width:250px;">
                <select name="cat" class="form-control mr-2 mb-2">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($cat == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary mr-2 mb-2"><i class="fas fa-search mr-1"></i>Filter</button>
                <a href="books.php" class="btn btn-secondary mb-2"><i class="fas fa-times mr-1"></i>Clear</a>
            </form>
        </div>
    </div>

    <!-- Books Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Book Catalogue (<?= count($books) ?> book<?= count($books) != 1 ? 's' : '' ?>)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="booksTable">
                    <thead class="thead-dark">
                        <tr>
                            <th>Cover</th>
                            <th>Title / Author</th>
                            <th>ISBN</th>
                            <th>Publisher</th>
                            <th>Year</th>
                            <th>Category</th>
                            <th>Copies</th>
                            <th>Available</th>
                            <th>Location</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td class="text-center" style="width:60px;">
                                <?php if ($book['cover_image']): ?>
                                    <img src="../uploads/covers/<?= htmlspecialchars($book['cover_image']) ?>"
                                         alt="Cover" class="img-thumbnail" style="width:45px;height:60px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center"
                                         style="width:45px;height:60px;border:1px solid #ddd;">
                                        <i class="fas fa-book text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($book['title']) ?></strong>
                                <?php if ($book['edition']): ?><small class="text-muted">, <?= htmlspecialchars($book['edition']) ?> ed.</small><?php endif; ?>
                                <br><span class="text-muted small"><?= htmlspecialchars($book['author']) ?></span>
                            </td>
                            <td><small><?= htmlspecialchars($book['isbn'] ?? 'N/A') ?></small></td>
                            <td><small><?= htmlspecialchars($book['publisher'] ?? '') ?></small></td>
                            <td><?= $book['publication_year'] ?: '' ?></td>
                            <td><?= htmlspecialchars($book['category_name'] ?? '—') ?></td>
                            <td class="text-center"><?= $book['total_copies'] ?></td>
                            <td class="text-center">
                                <span class="badge badge-<?= $book['available_copies'] > 0 ? 'success' : 'danger' ?>">
                                    <?= $book['available_copies'] ?>
                                </span>
                            </td>
                            <td><small><?= htmlspecialchars($book['location'] ?? '') ?></small></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-info mr-1"
                                        onclick='editBook(<?= json_encode($book) ?>)'
                                        title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline"
                                      onsubmit="return confirm('Remove this book from catalogue?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $book['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Remove">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($books)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No books found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Book Modal -->
<div class="modal fade" id="bookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="bookForm">
                <input type="hidden" name="action" id="bookAction" value="add">
                <input type="hidden" name="id" id="bookId" value="">
                <input type="hidden" name="existing_cover" id="existingCover" value="">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="bookModalTitle">
                        <i class="fas fa-book mr-2"></i>Add New Book
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="col-md-8">
                            <div class="form-row">
                                <div class="col-md-8 form-group">
                                    <label class="small font-weight-bold">Title *</label>
                                    <input type="text" name="title" id="bookTitle" class="form-control" required>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="small font-weight-bold">Edition</label>
                                    <input type="text" name="edition" id="bookEdition" class="form-control" placeholder="e.g. 3rd">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="small font-weight-bold">Author(s) *</label>
                                <input type="text" name="author" id="bookAuthor" class="form-control" required>
                            </div>
                            <div class="form-row">
                                <div class="col-md-6 form-group">
                                    <label class="small font-weight-bold">Publisher</label>
                                    <input type="text" name="publisher" id="bookPublisher" class="form-control">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="small font-weight-bold">Year</label>
                                    <input type="number" name="publication_year" id="bookYear" class="form-control"
                                           min="1800" max="<?= date('Y') ?>">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="small font-weight-bold">Copies</label>
                                    <input type="number" name="total_copies" id="bookCopies" class="form-control" min="1" value="1">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-md-6 form-group">
                                    <label class="small font-weight-bold">ISBN</label>
                                    <input type="text" name="isbn" id="bookIsbn" class="form-control">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="small font-weight-bold">Category</label>
                                    <select name="category_id" id="bookCategory" class="form-control">
                                        <option value="">-- Select --</option>
                                        <?php foreach ($categories as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="small font-weight-bold">Location (Shelf/Section)</label>
                                <input type="text" name="location" id="bookLocation" class="form-control"
                                       placeholder="e.g. Section A - Shelf 1">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="small font-weight-bold">Cover Image</label>
                                <input type="file" name="cover_image" id="bookCover" class="form-control-file"
                                       accept="image/*" onchange="previewCover(this)">
                                <div class="mt-2 text-center">
                                    <img id="coverPreview" src="" alt="Cover Preview"
                                         class="img-thumbnail d-none" style="max-height:180px;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Description</label>
                        <textarea name="description" id="bookDescription" class="form-control" rows="3"
                                  placeholder="Brief description of the book..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="bookSubmitBtn" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i>Save Book
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


</div><!-- /.container-fluid -->
<?php
$page_scripts = '<script>
$(function() {
    $("#booksTable").DataTable({
        order: [[1, "asc"]],
        columnDefs: [{orderable: false, targets: [0, 9]}]
    });
});

function openAddBook() {
    document.getElementById("bookAction").value = "add";
    document.getElementById("bookId").value = "";
    document.getElementById("bookModalTitle").innerHTML = \'<i class="fas fa-plus mr-2"></i>Add New Book\';
    document.getElementById("bookForm").reset();
    document.getElementById("coverPreview").classList.add("d-none");
}

function editBook(book) {
    document.getElementById("bookAction").value    = "edit";
    document.getElementById("bookId").value        = book.id;
    document.getElementById("bookModalTitle").innerHTML = \'<i class="fas fa-edit mr-2"></i>Edit Book\';
    document.getElementById("bookTitle").value     = book.title || "";
    document.getElementById("bookEdition").value   = book.edition || "";
    document.getElementById("bookAuthor").value    = book.author || "";
    document.getElementById("bookPublisher").value = book.publisher || "";
    document.getElementById("bookYear").value      = book.publication_year || "";
    document.getElementById("bookCopies").value    = book.total_copies || 1;
    document.getElementById("bookIsbn").value      = book.isbn || "";
    document.getElementById("bookLocation").value  = book.location || "";
    document.getElementById("bookDescription").value = book.description || "";
    document.getElementById("existingCover").value = book.cover_image || "";

    var sel = document.getElementById("bookCategory");
    for (var i = 0; i < sel.options.length; i++) {
        sel.options[i].selected = (sel.options[i].value == book.category_id);
    }

    if (book.cover_image) {
        var img = document.getElementById("coverPreview");
        img.src = "../uploads/covers/" + book.cover_image;
        img.classList.remove("d-none");
    } else {
        document.getElementById("coverPreview").classList.add("d-none");
    }
    $("#bookModal").modal("show");
}

function previewCover(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById("coverPreview");
            img.src = e.target.result;
            img.classList.remove("d-none");
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>';
include '../includes/footer.php';
?>
