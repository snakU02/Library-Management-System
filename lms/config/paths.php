<?php
// config/paths.php — Auto-calculates relative paths based on calling file location
// Include this AFTER require_once '../config/db.php'
// Usage:  require_once __DIR__ . '/../config/paths.php';
// Or for auth pages: require_once __DIR__ . '/../../lms/config/paths.php';

// Determine depth of current file relative to lms/ root
// lms/admin/      → depth 1 → '../'
// lms/student/    → depth 1 → '../'
// lms/auth/       → depth 1 → '../'
// lms/            → depth 0 → './'

$_lms_root = dirname(__DIR__);   // parent of config/ = lms/
$_calling   = dirname(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file']);

// Compute relative path from calling dir back to lms/ root
$_rel = '';
$_cur = $_calling;
while (realpath($_cur) !== realpath($_lms_root) && strlen($_cur) > 3) {
    $_rel .= '../';
    $_cur  = dirname($_cur);
    if ($_cur === dirname($_cur)) break;  // filesystem root guard
}
if ($_rel === '') $_rel = './';

// Public paths (relative URLs for HTML)
$template_path = $_rel . '../startbootstrap-sb-admin-2-gh-pages/';
$assets_path   = $_rel . 'assets/';
$uploads_url   = $_rel . 'uploads/';

// Admin/student nav paths (relative from caller)
// If calling file is in admin/ → admin files are in same dir
$_folder = basename($_calling);
$admin_path   = ($_folder === 'admin')   ? '' : $_rel . 'admin/';
$student_path = ($_folder === 'student') ? '' : $_rel . 'student/';
$auth_path    = ($_folder === 'auth')    ? '' : $_rel . 'auth/';
