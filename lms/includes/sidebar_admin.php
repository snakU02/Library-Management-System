<?php
// includes/sidebar_admin.php
// Requires before include:
//   $active_page   — current page key string
//   $template_path — path to sb-admin-2 folder
//   $admin_path    — relative path to admin/ ('' if already in admin/, '../admin/' from other dirs)
//   $auth_path     — relative path to auth/ logout

function sb_active($page, $active) {
    return ($page === $active) ? 'active' : '';
}
$ap = $admin_path ?? '';
?>
<!-- ===================== SIDEBAR ===================== -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= $ap ?>dashboard.php">
        <div class="sidebar-brand-icon"><i class="fas fa-book-open"></i></div>
        <div class="sidebar-brand-text mx-3">School Library <sup>LMS</sup></div>
    </a>

    <hr class="sidebar-divider my-0">

    <li class="nav-item <?= sb_active('dashboard', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $ap ?>dashboard.php">
            <i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
    </li>

    <hr class="sidebar-divider">
    <div class="sidebar-heading">Books</div>

    <li class="nav-item <?= sb_active('books', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $ap ?>books.php">
            <i class="fas fa-fw fa-book"></i><span>Books</span>
        </a>
    </li>
    <li class="nav-item <?= sb_active('catalogue', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $ap ?>catalogue.php">
            <i class="fas fa-fw fa-th-list"></i><span>Catalogue</span>
        </a>
    </li>
    <li class="nav-item <?= sb_active('search', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $ap ?>search.php">
            <i class="fas fa-fw fa-search"></i><span>Search</span>
        </a>
    </li>

    <hr class="sidebar-divider">
    <div class="sidebar-heading">Circulation</div>

    <li class="nav-item <?= sb_active('reservations', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $ap ?>reservations.php">
            <i class="fas fa-fw fa-calendar-check"></i><span>Reservations</span>
        </a>
    </li>
    <li class="nav-item <?= sb_active('borrow', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $ap ?>borrow.php">
            <i class="fas fa-fw fa-sign-out-alt"></i><span>Issue / Borrow</span>
        </a>
    </li>
    <li class="nav-item <?= sb_active('return', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $ap ?>return.php">
            <i class="fas fa-fw fa-sign-in-alt"></i><span>Return</span>
        </a>
    </li>
    <li class="nav-item <?= sb_active('penalties', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $ap ?>penalties.php">
            <i class="fas fa-fw fa-exclamation-triangle"></i><span>Penalties</span>
        </a>
    </li>

    <hr class="sidebar-divider">
    <div class="sidebar-heading">People</div>

    <li class="nav-item <?= sb_active('students', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $ap ?>students.php">
            <i class="fas fa-fw fa-users"></i><span>Students</span>
        </a>
    </li>

    <hr class="sidebar-divider">
    <div class="sidebar-heading">Reports</div>

    <li class="nav-item <?= sb_active('reports', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $ap ?>reports.php">
            <i class="fas fa-fw fa-file-alt"></i><span>Records & Reports</span>
        </a>
    </li>
    <li class="nav-item <?= sb_active('settings', $active_page ?? '') ?>">
        <a class="nav-link" href="<?= $ap ?>settings.php">
            <i class="fas fa-fw fa-cog"></i><span>Settings</span>
        </a>
    </li>

    <hr class="sidebar-divider d-none d-md-block">
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>
</ul>
<!-- ===================== END SIDEBAR ===================== -->

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">
<div id="content">

<!-- Topbar -->
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
        <i class="fa fa-bars"></i>
    </button>
    <form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search"
          action="<?= $ap ?>search.php" method="GET">
        <div class="input-group">
            <input type="text" name="q" class="form-control bg-light border-0 small"
                   placeholder="Search books, students..."
                   value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            <div class="input-group-append">
                <button class="btn btn-primary" type="submit"><i class="fas fa-search fa-sm"></i></button>
            </div>
        </div>
    </form>
    <ul class="navbar-nav ml-auto">
        <?php
        try {
            $pending_notif = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND status='pending'")->fetchColumn();
        } catch(Exception $e) { $pending_notif = 0; }
        ?>
        <?php if ($pending_notif > 0): ?>
        <li class="nav-item mx-1">
            <a class="nav-link" href="<?= $ap ?>students.php?filter=pending" title="Pending registrations">
                <i class="fas fa-bell fa-fw"></i>
                <span class="badge badge-danger badge-counter"><?= $pending_notif ?></span>
            </a>
        </li>
        <div class="topbar-divider"></div>
        <?php endif; ?>
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="adminUserDrop" role="button" data-toggle="dropdown">
                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                    <?= htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?>
                </span>
                <i class="fas fa-user-circle fa-2x text-primary"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in">
                <span class="dropdown-item-text small text-muted px-4">
                    <i class="fas fa-shield-alt mr-1 text-warning"></i> Administrator
                </span>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="<?= $ap ?>settings.php">
                    <i class="fas fa-cog fa-sm fa-fw mr-2 text-gray-400"></i>Settings
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="<?= $auth_path ?? '../auth/' ?>logout.php">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>Logout
                </a>
            </div>
        </li>
    </ul>
</nav>
<!-- End Topbar -->
