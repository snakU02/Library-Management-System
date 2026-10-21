<?php
// auth/register.php — Student self-registration
session_start();
require_once '../config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ../student/dashboard.php'); exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name']  ?? '');
    $student_id  = trim($_POST['student_id'] ?? '');
    $email       = trim($_POST['email']      ?? '');
    $password    = $_POST['password']        ?? '';
    $confirm     = $_POST['confirm_password']?? '';
    $course      = trim($_POST['course']     ?? '');
    $year_level  = (int)($_POST['year_level']?? 1);
    $contact     = trim($_POST['contact']    ?? '');

    // Validation
    if (!$first_name || !$last_name || !$student_id || !$email || !$password) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        // Check duplicates
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? OR student_id=?");
        $stmt->execute([$email, $student_id]);
        if ($stmt->fetch()) {
            $error = 'Email or Student ID already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users
                (student_id, first_name, last_name, email, password, role, course, year_level, contact, status)
                VALUES (?, ?, ?, ?, ?, 'student', ?, ?, ?, 'pending')");
            $stmt->execute([$student_id, $first_name, $last_name, $email, $hash, $course, $year_level, $contact]);
            $success = 'Registration submitted! Please wait for admin approval before logging in.';
        }
    }
}
$template = '../../startbootstrap-sb-admin-2-gh-pages/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Register | School Library</title>
    <link href="<?= $template ?>vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= $template ?>css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../assets/css/lms.css" rel="stylesheet">
</head>
<body class="bg-gradient-primary">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-10 col-lg-12 col-md-9">
            <div class="card o-hidden border-0 shadow-lg my-5">
                <div class="card-body p-0">
                    <div class="row">
                        <div class="col-lg-5 d-none d-lg-flex flex-column align-items-center justify-content-center
                                    bg-gradient-success text-white p-5">
                            <div class="text-center">
                                <i class="fas fa-user-graduate fa-5x mb-4" style="opacity:0.9;"></i>
                                <h2 class="font-weight-bold">Student Portal</h2>
                                <p class="text-white-50">Register to access the library system</p>
                                <hr class="bg-white-50 my-3">
                                <p class="small text-white-50">
                                    After registering, wait for the librarian to approve your account.
                                </p>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="p-5">
                                <div class="text-center mb-4">
                                    <h1 class="h4 text-gray-900">Create an Account</h1>
                                </div>
                                <?php if ($error): ?>
                                    <div class="alert alert-danger"><i class="fas fa-times-circle mr-2"></i><?= htmlspecialchars($error) ?></div>
                                <?php endif; ?>
                                <?php if ($success): ?>
                                    <div class="alert alert-success"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
                                        <a href="login.php" class="font-weight-bold">Login here</a></div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <div class="form-row">
                                        <div class="col-md-6 form-group">
                                            <label class="small font-weight-bold">First Name *</label>
                                            <input type="text" name="first_name" class="form-control"
                                                   value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label class="small font-weight-bold">Last Name *</label>
                                            <input type="text" name="last_name" class="form-control"
                                                   value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="col-md-6 form-group">
                                            <label class="small font-weight-bold">Student ID *</label>
                                            <input type="text" name="student_id" class="form-control"
                                                   placeholder="e.g. 2024-0001"
                                                   value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label class="small font-weight-bold">Email *</label>
                                            <input type="email" name="email" class="form-control"
                                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="col-md-6 form-group">
                                            <label class="small font-weight-bold">Course</label>
                                            <input type="text" name="course" class="form-control"
                                                   placeholder="e.g. BSCS, BSN, BSBA"
                                                   value="<?= htmlspecialchars($_POST['course'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-3 form-group">
                                            <label class="small font-weight-bold">Year Level</label>
                                            <select name="year_level" class="form-control">
                                                <?php for ($y = 1; $y <= 5; $y++): ?>
                                                    <option value="<?= $y ?>" <?= (($_POST['year_level'] ?? 1) == $y) ? 'selected' : '' ?>><?= $y ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 form-group">
                                            <label class="small font-weight-bold">Contact</label>
                                            <input type="text" name="contact" class="form-control"
                                                   value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="col-md-6 form-group">
                                            <label class="small font-weight-bold">Password *</label>
                                            <input type="password" name="password" class="form-control" required minlength="8">
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label class="small font-weight-bold">Confirm Password *</label>
                                            <input type="password" name="confirm_password" class="form-control" required>
                                        </div>
                                    </div>
                                    <button type="submit" id="registerBtn" class="btn btn-success btn-user btn-block">
                                        <i class="fas fa-user-plus mr-2"></i>Register
                                    </button>
                                    <hr>
                                    <div class="text-center">
                                        <a class="small" href="login.php">Already have an account? Login</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= $template ?>vendor/jquery/jquery.min.js"></script>
<script src="<?= $template ?>vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
