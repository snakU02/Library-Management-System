<?php
// admin/settings.php — System-wide settings management
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_admin();

$page_title  = 'Settings';
$active_page = 'settings';

// Handle POST: Update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = [
        'library_name', 'school_name', 'library_contact',
        'borrow_duration_days', 'reservation_expiry_days',
        'daily_penalty_rate', 'max_borrows_per_student'
    ];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            $val = trim($_POST[$key]);
            $pdo->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?")
                ->execute([$val, $key]);
        }
    }

    // Handle admin password change
    if (!empty($_POST['new_password'])) {
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            $hash = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $_SESSION['user_id']]);
            set_flash('success', 'Settings and password updated successfully.');
        } else {
            set_flash('error', 'New passwords do not match. Other settings were saved.');
        }
    } else {
        set_flash('success', 'Settings updated successfully.');
    }

    header('Location: settings.php'); exit;
}

// Load all settings
$settings = [];
$rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
foreach ($rows as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

// Load current admin info
$admin = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id=?");
$admin->execute([$_SESSION['user_id']]);
$admin = $admin->fetch();

// DB stats
$total_books    = $pdo->query("SELECT COUNT(*) FROM books WHERE status='active'")->fetchColumn();
$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$total_borrows  = $pdo->query("SELECT COUNT(*) FROM borrows")->fetchColumn();
$total_penalties= $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM penalties")->fetchColumn();

$flash = get_flash();
include '../includes/header.php';
include '../includes/sidebar_admin.php';
?>
<div class="container-fluid">

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
        <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?> mr-2"></i>
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php endif; ?>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-cog mr-2 text-primary"></i>System Settings</h1>
    </div>

    <!-- System Stats -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-left-primary shadow py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Books</div>
                    <div class="h5 font-weight-bold"><?= number_format($total_books) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-left-success shadow py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Students</div>
                    <div class="h5 font-weight-bold"><?= number_format($total_students) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-left-info shadow py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Borrows</div>
                    <div class="h5 font-weight-bold"><?= number_format($total_borrows) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-left-warning shadow py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Penalties</div>
                    <div class="h5 font-weight-bold">₱<?= number_format($total_penalties, 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST">
        <div class="row">
            <!-- Library Info -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-university mr-2"></i>Library Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="small font-weight-bold">Library / School Name</label>
                            <input type="text" name="library_name" class="form-control"
                                   value="<?= htmlspecialchars($settings['library_name'] ?? 'School Library') ?>">
                        </div>
                        <div class="form-group">
                            <label class="small font-weight-bold">School Name</label>
                            <input type="text" name="school_name" class="form-control"
                                   value="<?= htmlspecialchars($settings['school_name'] ?? '') ?>">
                        </div>
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Contact Number</label>
                            <input type="text" name="library_contact" class="form-control"
                                   value="<?= htmlspecialchars($settings['library_contact'] ?? '') ?>"
                                   placeholder="e.g. (02) 8xxx-xxxx">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Circulation Rules -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-sliders-h mr-2"></i>Circulation Rules</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="small font-weight-bold">Default Borrow Duration (days)</label>
                            <input type="number" name="borrow_duration_days" class="form-control"
                                   min="1" max="90"
                                   value="<?= htmlspecialchars($settings['borrow_duration_days'] ?? 7) ?>">
                            <small class="text-muted">Number of days a student may borrow a book.</small>
                        </div>
                        <div class="form-group">
                            <label class="small font-weight-bold">Reservation Expiry (days)</label>
                            <input type="number" name="reservation_expiry_days" class="form-control"
                                   min="1" max="30"
                                   value="<?= htmlspecialchars($settings['reservation_expiry_days'] ?? 3) ?>">
                            <small class="text-muted">Days before an unapproved reservation expires.</small>
                        </div>
                        <div class="form-group">
                            <label class="small font-weight-bold">Daily Penalty Rate (₱ per day)</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">₱</span>
                                </div>
                                <input type="number" name="daily_penalty_rate" class="form-control"
                                       min="0" step="0.50"
                                       value="<?= htmlspecialchars($settings['daily_penalty_rate'] ?? 5) ?>">
                            </div>
                            <small class="text-muted">Charged per overdue day.</small>
                        </div>
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Max Books per Student</label>
                            <input type="number" name="max_borrows_per_student" class="form-control"
                                   min="1" max="20"
                                   value="<?= htmlspecialchars($settings['max_borrows_per_student'] ?? 3) ?>">
                            <small class="text-muted">Maximum simultaneous borrows allowed.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Account -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-user-shield mr-2"></i>Admin Account</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center mb-3">
                            <i class="fas fa-user-circle fa-5x text-primary mb-3"></i>
                            <h6 class="font-weight-bold"><?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?></h6>
                            <span class="badge badge-primary">Administrator</span><br>
                            <small class="text-muted"><?= htmlspecialchars($admin['email']) ?></small>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <h6 class="font-weight-bold text-muted mb-3">Change Password (leave blank to keep current)</h6>
                        <div class="form-group">
                            <label class="small font-weight-bold">New Password</label>
                            <input type="password" name="new_password" class="form-control"
                                   placeholder="Leave blank to keep current password" minlength="8">
                        </div>
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control"
                                   placeholder="Confirm new password">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-right mb-4">
            <button type="submit" class="btn btn-primary btn-lg px-5">
                <i class="fas fa-save mr-2"></i>Save All Settings
            </button>
        </div>
    </form>

    <!-- DB Info Card -->
    <div class="card shadow mb-4 border-left-secondary">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-secondary"><i class="fas fa-database mr-2"></i>Database Information</h6>
        </div>
        <div class="card-body">
            <table class="table table-sm table-borderless mb-0">
                <tr><th width="30%">Database Name</th><td><code><?= DB_NAME ?></code></td></tr>
                <tr><th>Host</th><td><code><?= DB_HOST ?></code></td></tr>
                <tr><th>PHP Version</th><td><code><?= phpversion() ?></code></td></tr>
                <tr><th>Server Time</th><td><?= date('Y-m-d H:i:s') ?></td></tr>
                <tr><th>Upload Directory</th><td><code><?= realpath(__DIR__ . '/../uploads/') ?: __DIR__ . '/../uploads/ (not created yet)' ?></code></td></tr>
            </table>
            <div class="mt-3">
                <a href="../scripts/hash_password.php" class="btn btn-sm btn-outline-secondary" target="_blank">
                    <i class="fas fa-key mr-1"></i>Password Hash Generator
                </a>
            </div>
        </div>
    </div>

</div>
<!-- End container-fluid -->


</div><!-- /.container-fluid -->
<?php include '../includes/footer.php'; ?>
