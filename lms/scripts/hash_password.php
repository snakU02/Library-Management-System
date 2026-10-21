<?php
// scripts/hash_password.php — Utility to generate bcrypt password hashes
// Usage: Open in browser: http://localhost/lms/scripts/hash_password.php
// Or CLI: php hash_password.php

$passwords_to_hash = [
    'Admin@1234',
    'Student@1234',
    'password',   // default demo
];

echo "<pre>";
echo "=== Password Hash Generator ===\n\n";
foreach ($passwords_to_hash as $pw) {
    $hash = password_hash($pw, PASSWORD_BCRYPT);
    echo "Password : $pw\n";
    echo "Hash     : $hash\n";
    echo "Verify   : " . (password_verify($pw, $hash) ? "OK ✓" : "FAIL ✗") . "\n\n";
}

// Custom password
if (!empty($_GET['pw'])) {
    $custom = $_GET['pw'];
    $hash = password_hash($custom, PASSWORD_BCRYPT);
    echo "Custom: '$custom'\nHash: $hash\n";
}
echo "</pre>";
echo "<form>Custom password: <input name='pw' type='text'> <button>Hash</button></form>";
