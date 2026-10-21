<?php
/**
 * setup.php — One-time database setup & seed script
 * 
 * Run this ONCE in your browser after importing lms.sql:
 *   http://localhost/lms/scripts/setup.php
 *
 * This script will:
 * 1. Set correct bcrypt password hashes for demo accounts
 * 2. Verify the database tables exist
 * 3. Show system status
 */
require_once __DIR__ . '/../config/db.php';

$results = [];
$errors  = [];

// ── 1. Update admin password to Admin@1234 ──────────────────────
$adminHash = password_hash('Admin@1234', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("UPDATE users SET password=? WHERE email='admin@library.com'");
$stmt->execute([$adminHash]);
if ($stmt->rowCount() > 0) {
    $results[] = "✅ Admin password set to: <strong>Admin@1234</strong>";
} else {
    $errors[] = "⚠️ Admin account not found — make sure you've imported lms.sql first.";
}

// ── 2. Update student demo passwords to Student@1234 ────────────
$studHash = password_hash('Student@1234', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("UPDATE users SET password=? WHERE role='student'");
$stmt->execute([$studHash]);
$results[] = "✅ Student passwords set to: <strong>Student@1234</strong> ({$stmt->rowCount()} accounts)";

// ── 3. Verify tables ────────────────────────────────────────────
$requiredTables = ['users','books','categories','reservations','borrows','returns','penalties','settings'];
foreach ($requiredTables as $tbl) {
    try {
        $pdo->query("SELECT 1 FROM `$tbl` LIMIT 1");
        $results[] = "✅ Table <code>$tbl</code> — OK";
    } catch (PDOException $e) {
        $errors[] = "❌ Table <code>$tbl</code> — MISSING! Import lms.sql first.";
    }
}

// ── 4. Create uploads/covers directory ──────────────────────────
$uploadDir = __DIR__ . '/../uploads/covers/';
if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        $results[] = "✅ Created uploads/covers/ directory";
    } else {
        $errors[] = "⚠️ Could not create uploads/covers/ — create it manually.";
    }
} else {
    $results[] = "✅ uploads/covers/ directory exists";
}

// ── Display ─────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LMS Setup</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: Nunito, sans-serif; background: #f0f4f8; margin:0; padding:2rem; }
        .card { background:#fff; border-radius:12px; padding:2rem; max-width:700px; margin:0 auto;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h1 { color: #4e73df; }
        .result { padding: 0.5rem 0.75rem; border-radius:6px; margin-bottom:0.5rem; background:#e8f5e9; }
        .error  { padding: 0.5rem 0.75rem; border-radius:6px; margin-bottom:0.5rem; background:#ffebee; }
        .cred-box { background:#f8f9fc; border:1px solid #e3e6f0; border-radius:8px; padding:1rem; margin-top:1.5rem; }
        a.btn { display:inline-block; padding:0.6rem 1.5rem; background:#4e73df; color:#fff;
                border-radius:6px; text-decoration:none; margin-top:1rem; }
        a.btn-success { background:#1cc88a; }
    </style>
</head>
<body>
<div class="card">
    <h1>📚 Library LMS — Setup</h1>

    <?php if (!empty($errors)): ?>
        <h3 style="color:#e53935;">Errors:</h3>
        <?php foreach ($errors as $e): ?>
            <div class="error"><?= $e ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h3 style="color:#2e7d32; margin-top:1.5rem;">Results:</h3>
    <?php foreach ($results as $r): ?>
        <div class="result"><?= $r ?></div>
    <?php endforeach; ?>

    <?php if (empty($errors)): ?>
    <div class="cred-box">
        <h4 style="margin-top:0;">🔑 Demo Login Credentials</h4>
        <table style="width:100%;border-collapse:collapse;">
            <tr style="font-weight:bold;border-bottom:2px solid #e0e0e0;">
                <td style="padding:0.4rem;">Role</td>
                <td style="padding:0.4rem;">Email</td>
                <td style="padding:0.4rem;">Password</td>
            </tr>
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:0.4rem;">🛡️ Admin</td>
                <td style="padding:0.4rem;"><code>admin@library.com</code></td>
                <td style="padding:0.4rem;"><code>Admin@1234</code></td>
            </tr>
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:0.4rem;">🎓 Student 1</td>
                <td style="padding:0.4rem;"><code>juan.delacruz@student.com</code></td>
                <td style="padding:0.4rem;"><code>Student@1234</code></td>
            </tr>
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:0.4rem;">🎓 Student 2</td>
                <td style="padding:0.4rem;"><code>maria.santos@student.com</code></td>
                <td style="padding:0.4rem;"><code>Student@1234</code></td>
            </tr>
        </table>
        <a href="../auth/login.php" class="btn btn-success">🚀 Go to Login Page</a>
    </div>
    <?php endif; ?>

    <p style="color:#999; font-size:0.8rem; margin-top:1.5rem;">
        ⚠️ Delete or restrict access to this file after setup for security.
    </p>
</div>
</body>
</html>
