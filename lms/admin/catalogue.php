<?php
// admin/catalogue.php — Full book database / catalogue view
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_admin();

$page_title  = 'Book Catalogue';
$active_page = 'catalogue';

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$search = trim($_GET['q']    ?? '');
$cat    = (int)($_GET['cat'] ?? 0);
$avail  = $_GET['avail']     ?? '';

$sql  = "SELECT b.*, c.name AS category_name FROM books b LEFT JOIN categories c ON b.category_id=c.id WHERE b.status='active'";
$params = [];

if ($search) {
    $sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.publisher LIKE ? OR b.isbn LIKE ?)";
    $params = array_fill(0, 4, "%$search%");
}
if ($cat) { $sql .= " AND b.category_id=?"; $params[] = $cat; }
if ($avail === 'yes') { $sql .= " AND b.available_copies > 0"; }
if ($avail === 'no')  { $sql .= " AND b.available_copies = 0"; }

$sql .= " ORDER BY b.title";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

// ── PRINT / EXPORT ──────────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $lib_name = get_setting($pdo, 'library_name', 'Library');
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Book Catalogue — <?= htmlspecialchars($lib_name) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #000; padding: 20px; }
        h1  { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #555; margin-bottom: 14px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 5px 8px; text-align: left; vertical-align: top; }
        th { background: #1c3a5e; color: #fff; font-size: 11px; }
        tr:nth-child(even) td { background: #f4f4f4; }
        .avail-yes { color: #1a7a1a; font-weight: bold; }
        .avail-no  { color: #c00; font-weight: bold; }
        .footer { margin-top: 14px; font-size: 10px; color: #777; }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:16px;">
        <button onclick="window.print()" style="padding:8px 20px;background:#1c3a5e;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;">
            🖨️ Print / Save as PDF
        </button>
        <a href="catalogue.php" style="margin-left:12px;font-size:12px;">← Back to Catalogue</a>
    </div>

    <h1>📚 Book Catalogue — <?= htmlspecialchars($lib_name) ?></h1>
    <p class="meta">
        Generated: <?= date('F d, Y h:i A') ?>
        <?php if ($search):  ?> &nbsp;|&nbsp; Search: <strong><?= htmlspecialchars($search) ?></strong><?php endif; ?>
        <?php if ($avail === 'yes'): ?> &nbsp;|&nbsp; Filter: <strong>Available only</strong><?php endif; ?>
        <?php if ($avail === 'no'):  ?> &nbsp;|&nbsp; Filter: <strong>Not available</strong><?php endif; ?>
        &nbsp;|&nbsp; Total: <strong><?= count($books) ?></strong> title(s)
    </p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Title</th>
                <th>Author</th>
                <th>Publisher</th>
                <th>Year</th>
                <th>ISBN</th>
                <th>Category</th>
                <th>Location</th>
                <th style="text-align:center;">Total</th>
                <th style="text-align:center;">Available</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($books)): ?>
            <tr><td colspan="10" style="text-align:center;color:#888;padding:16px;">No books found.</td></tr>
        <?php else: ?>
            <?php foreach ($books as $i => $b): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($b['title']) ?></td>
                <td><?= htmlspecialchars($b['author']) ?></td>
                <td><?= htmlspecialchars($b['publisher'] ?? '—') ?></td>
                <td><?= htmlspecialchars($b['publication_year'] ?? '—') ?></td>
                <td><?= htmlspecialchars($b['isbn'] ?? '—') ?></td>
                <td><?= htmlspecialchars($b['category_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($b['location'] ?? '—') ?></td>
                <td style="text-align:center;"><?= (int)$b['total_copies'] ?></td>
                <td style="text-align:center;" class="<?= $b['available_copies'] > 0 ? 'avail-yes' : 'avail-no' ?>">
                    <?= (int)$b['available_copies'] ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <p class="footer">— End of Report —</p>
    <script>window.onload = function(){ window.print(); }</script>
</body>
</html>
<?php
    exit;
}
// ── END PRINT ────────────────────────────────────────────────────────────────

include '../includes/header.php';
include '../includes/sidebar_admin.php';
?>
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-th-list mr-2 text-primary"></i>Book Catalogue</h1>
        <div>
            <a href="books.php" class="btn btn-primary shadow-sm mr-2">
                <i class="fas fa-plus mr-1"></i>Add Book
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>" class="btn btn-success shadow-sm">
                <i class="fas fa-print mr-1"></i>Print List
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow mb-4">
        <div class="card-body py-3">
            <form method="GET" class="form-inline flex-wrap">
                <input type="text" name="q" class="form-control mr-2 mb-2" style="min-width:220px;"
                       placeholder="Title, Author, Publisher, ISBN..."
                       value="<?= htmlspecialchars($search) ?>">
                <select name="cat" class="form-control mr-2 mb-2">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($cat==$c['id'])?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="avail" class="form-control mr-2 mb-2">
                    <option value="">Any Availability</option>
                    <option value="yes" <?= $avail==='yes'?'selected':'' ?>>Available Only</option>
                    <option value="no"  <?= $avail==='no' ?'selected':'' ?>>Not Available</option>
                </select>
                <button type="submit" class="btn btn-primary mr-2 mb-2"><i class="fas fa-filter mr-1"></i>Filter</button>
                <a href="catalogue.php" class="btn btn-secondary mb-2"><i class="fas fa-times mr-1"></i>Clear</a>
            </form>
        </div>
    </div>

    <!-- Books Grid -->
    <div class="row" id="catalogueGrid">
        <?php foreach ($books as $book): ?>
        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4">
            <div class="card shadow h-100 book-card">
                <div class="book-cover-container bg-gradient-primary"
                     style="height:160px;display:flex;align-items:center;justify-content:center;overflow:hidden;">
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
                    <?php if ($book['category_name']): ?>
                        <span class="badge badge-light border small mt-1"><?= htmlspecialchars($book['category_name']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-footer p-2 d-flex justify-content-between align-items-center">
                    <span class="badge badge-<?= $book['available_copies'] > 0 ? 'success' : 'danger' ?> small">
                        <?= $book['available_copies'] ?>/<?= $book['total_copies'] ?> avail.
                    </span>
                    <button class="btn btn-xs btn-outline-info" data-toggle="modal" data-target="#detailModal"
                            onclick='showDetail(<?= json_encode($book) ?>)'>
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($books)): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-search fa-3x mb-3"></i><br>No books found matching your criteria.
            </div>
        <?php endif; ?>
    </div>

    <p class="text-muted small"><strong><?= count($books) ?></strong> book title(s) found.</p>
</div>

<!-- Book Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-book mr-2"></i><span id="dTitle"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3 text-center mb-3" id="dCoverWrap"></div>
                    <div class="col-md-9">
                        <table class="table table-sm table-borderless">
                            <tr><th width="30%">Author</th><td id="dAuthor"></td></tr>
                            <tr><th>Publisher</th><td id="dPublisher"></td></tr>
                            <tr><th>Year / Edition</th><td id="dYearEd"></td></tr>
                            <tr><th>ISBN</th><td id="dIsbn"></td></tr>
                            <tr><th>Category</th><td id="dCat"></td></tr>
                            <tr><th>Location</th><td id="dLocation"></td></tr>
                            <tr><th>Total Copies</th><td id="dTotal"></td></tr>
                            <tr><th>Available</th><td id="dAvail"></td></tr>
                        </table>
                        <p id="dDesc" class="text-muted small mt-2"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a id="dEditLink" href="#" class="btn btn-info"><i class="fas fa-edit mr-1"></i>Edit</a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


</div><!-- /.container-fluid -->
<?php
$page_scripts = '<script>
function showDetail(b) {
    document.getElementById("dTitle").textContent      = b.title;
    document.getElementById("dAuthor").textContent     = b.author;
    document.getElementById("dPublisher").textContent  = b.publisher || "—";
    document.getElementById("dYearEd").textContent     = (b.publication_year || "") + (b.edition ? ", " + b.edition + " ed." : "");
    document.getElementById("dIsbn").textContent       = b.isbn || "—";
    document.getElementById("dCat").textContent        = b.category_name || "—";
    document.getElementById("dLocation").textContent   = b.location || "—";
    document.getElementById("dTotal").textContent      = b.total_copies;
    document.getElementById("dDesc").textContent       = b.description || "";
    var avail = document.getElementById("dAvail");
    avail.innerHTML = "<span class=\'badge badge-" + (b.available_copies > 0 ? "success" : "danger") + "\'>" + b.available_copies + " available</span>";
    var cw = document.getElementById("dCoverWrap");
    cw.innerHTML = b.cover_image
        ? "<img src=\'../uploads/covers/" + b.cover_image + "\' class=\'img-fluid rounded shadow\'>"
        : "<div class=\'bg-primary p-4 rounded text-center\'><i class=\'fas fa-book fa-3x text-white\'></i></div>";
    document.getElementById("dEditLink").href = "books.php";
}
</script>';
include '../includes/footer.php';
?>
