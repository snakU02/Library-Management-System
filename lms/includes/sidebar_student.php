<?php
// includes/sidebar_student.php
// Requires: $active_page, $student_path, $auth_path

function sb_student_active($page, $active) {
    return ($page === $active) ? 'active' : '';
}
$sp = $student_path ?? '';
// Penalty badge
$_pen_total = 0;
try {
    $_uid = $_SESSION['user_id'] ?? 0;
    $_pen = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM penalties WHERE user_id=? AND status='unpaid'");
    $_pen->execute([$_uid]);
    $_pen_total = (float)$_pen->fetchColumn();
} catch(Exception $e) {}
?>
<!-- ===================== STUDENT SIDEBAR ===================== -->
<ul class="navbar-nav bg-gradient-success sidebar sidebar-dark accordion" id="accordionSidebar">

    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= $sp ?>dashboard.php">
        <div class="sidebar-brand-icon"><i class="fas fa-book-reader"></i></div>
        <div class="sidebar-brand-text mx-3">School Library</div>
    </a>

    <hr class="sidebar-divider my-0">

    <li class="nav-item <?= sb_student_active('dashboard', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $sp ?>dashboard.php">
            <i class="fas fa-fw fa-home"></i><span>My Dashboard</span>
        </a>
    </li>

    <hr class="sidebar-divider">
    <div class="sidebar-heading">Books</div>

    <li class="nav-item <?= sb_student_active('catalogue', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $sp ?>catalogue.php">
            <i class="fas fa-fw fa-book-open"></i><span>View Books</span>
        </a>
    </li>
    <li class="nav-item <?= sb_student_active('search', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $sp ?>search.php">
            <i class="fas fa-fw fa-search"></i><span>Search Books</span>
        </a>
    </li>

    <hr class="sidebar-divider">
    <div class="sidebar-heading">My Activity</div>

    <li class="nav-item <?= sb_student_active('reserve', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $sp ?>reserve.php">
            <i class="fas fa-fw fa-calendar-alt"></i><span>Reservations</span>
        </a>
    </li>
    <li class="nav-item <?= sb_student_active('borrows', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $sp ?>my_borrows.php">
            <i class="fas fa-fw fa-book"></i><span>My Borrows</span>
        </a>
    </li>
    <li class="nav-item <?= sb_student_active('returns', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $sp ?>my_returns.php">
            <i class="fas fa-fw fa-undo-alt"></i><span>My Returns</span>
        </a>
    </li>
    <li class="nav-item <?= sb_student_active('penalties', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $sp ?>my_penalties.php">
            <i class="fas fa-fw fa-exclamation-circle"></i>
            <span>My Penalties
                <?php if ($_pen_total > 0): ?>
                    <span class="badge badge-danger badge-pill ml-1" style="font-size:0.65rem;">
                        ₱<?= number_format($_pen_total, 0) ?>
                    </span>
                <?php endif; ?>
            </span>
        </a>
    </li>

    <hr class="sidebar-divider d-none d-md-block">
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>
</ul>
<!-- ===================== END STUDENT SIDEBAR ===================== -->

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">
<div id="content">

<!-- Topbar -->
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
        <i class="fa fa-bars"></i>
    </button>
    <form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search"
          action="<?= $sp ?>search.php" method="GET">
        <div class="input-group">
            <input type="text" name="q" class="form-control bg-light border-0 small"
                   placeholder="Search title, author, publisher..."
                   value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            <div class="input-group-append">
                <button class="btn btn-success" type="submit"><i class="fas fa-search fa-sm"></i></button>
            </div>
        </div>
    </form>
    <ul class="navbar-nav ml-auto">
        <div class="topbar-divider d-none d-sm-block"></div>
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="studentUserDrop" role="button" data-toggle="dropdown">
                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                    <?= htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?>
                </span>
                <i class="fas fa-user-graduate fa-2x text-success"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in">
                <span class="dropdown-item-text small text-muted px-4">
                    <i class="fas fa-id-card mr-1 text-success"></i>
                    ID: <?= htmlspecialchars($_SESSION['student_id'] ?? 'N/A') ?>
                </span>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="<?= $auth_path ?? '../auth/' ?>logout.php">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>Logout
                </a>
            </div>
        </li>
    </ul>
</nav>
<!-- End Topbar -->
