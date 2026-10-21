<?php
// ================================================
// config/db.php  — Database Connection
// ================================================
// Edit the values below to match your server setup.
// ================================================

define('DB_HOST',     'localhost');
define('DB_NAME',     'library_lms');
define('DB_USER',     'root');      // <-- your MySQL username
define('DB_PASS',     '');          // <-- your MySQL password
define('DB_CHARSET',  'utf8mb4');

// -- App paths --
define('BASE_URL',    'http://localhost/Library%20Management%20System/lms');   // adjust to your server URL
define('TEMPLATE_PATH', '../startbootstrap-sb-admin-2-gh-pages/');
define('UPLOAD_DIR',  __DIR__ . '/../uploads/');
define('UPLOAD_URL',  BASE_URL . '/uploads/');

// -- Library defaults (can also come from `settings` table) --
define('BORROW_DAYS',       7);
define('RESERVATION_DAYS',  3);
define('DAILY_PENALTY',     5.00);   // PHP per day
define('MAX_BORROWS',       3);

// ================================================
// PDO connection (used throughout the app)
// ================================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    $msg = htmlspecialchars($e->getMessage());
    die("<!DOCTYPE html><html><head><meta charset='utf-8'>
    <link href='https://fonts.googleapis.com/css2?family=Nunito:wght@600;700&display=swap' rel='stylesheet'>
    <style>body{font-family:Nunito,sans-serif;background:#4e73df;display:flex;align-items:center;
    justify-content:center;min-height:100vh;margin:0;}
    .box{background:#fff;border-radius:12px;padding:2.5rem;max-width:520px;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,.2);}
    h2{color:#e74a3b;} code{background:#fce8e6;padding:2px 6px;border-radius:4px;font-size:.85rem;}
    a{color:#4e73df;}</style>
    </head><body><div class='box'>
    <h2>&#x26A0; Database Connection Failed</h2>
    <p>The system could not connect to the database.</p>
    <p><code>$msg</code></p>
    <hr>
    <p><strong>Steps to fix:</strong></p>
    <ol style='text-align:left'>
      <li>Open <code>lms/config/db.php</code></li>
      <li>Check your <strong>DB_HOST</strong>, <strong>DB_USER</strong>, and <strong>DB_PASS</strong></li>
      <li>Make sure MySQL is running</li>
      <li>Import <code>lms.sql</code> into your database</li>
    </ol>
    </div></body></html>");
}

// ================================================
// Helper: Load settings from DB (overrides constants)
// ================================================
function get_setting($pdo, $key, $default = '') {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

// ================================================
// Helper: Session-based flash messages
// ================================================
function set_flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ================================================
// Helper: Auth guards
// ================================================
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ' . BASE_URL . '/student/dashboard.php');
        exit;
    }
}

function require_student() {
    require_login();
    if ($_SESSION['role'] !== 'student') {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    }
}

// ================================================
// Helper: Update overdue borrows automatically
// ================================================
function update_overdue_borrows($pdo) {
    $pdo->exec("UPDATE borrows SET status='overdue'
                WHERE status='active' AND due_date < CURDATE()");
}
