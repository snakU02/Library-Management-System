<?php
// auth/login.php
session_start();
require_once '../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../student/dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['student_id'] = $user['student_id'];

            if ($user['role'] === 'admin') {
                header('Location: ../admin/dashboard.php');
            } else {
                header('Location: ../student/dashboard.php');
            }
            exit;
        } else {
            // Check if account exists but is pending
            $stmtCheck = $pdo->prepare("SELECT status FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            $check = $stmtCheck->fetch();
            if ($check && $check['status'] === 'pending') {
                $error = 'Your account is pending admin approval.';
            } elseif ($check && $check['status'] === 'inactive') {
                $error = 'Your account has been deactivated. Contact the library.';
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

$template = '../../startbootstrap-sb-admin-2-gh-pages/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login | School Library Management System</title>
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
                        <!-- Left panel -->
                        <div class="col-lg-6 d-none d-lg-flex flex-column align-items-center justify-content-center
                                    bg-gradient-primary text-white p-5">
                            <div class="text-center">
                                <i class="fas fa-book-open fa-5x mb-4" style="opacity:0.9;"></i>
                                <h2 class="font-weight-bold">School Library</h2>
                                <p class="mb-0 text-white-50">Management System</p>
                                <hr class="bg-white-50 my-4">
                                <p class="small text-white-50">
                                    A complete solution for managing books, borrows, returns, and student records.
                                </p>
                            </div>
                        </div>

                        <!-- Right: Login form -->
                        <div class="col-lg-6">
                            <div class="p-5">
                                <div class="text-center">
                                    <h1 class="h4 text-gray-900 mb-2">Welcome Back!</h1>
                                    <p class="text-muted small mb-4">Sign in to the Library System</p>
                                </div>

                                <?php if ($error): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-circle mr-2"></i>
                                        <?= htmlspecialchars($error) ?>
                                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label class="small font-weight-bold text-muted">Email Address</label>
                                        <input type="email" name="email" id="loginEmail"
                                               class="form-control form-control-user"
                                               placeholder="Enter email address..."
                                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                               required autofocus>
                                    </div>
                                    <div class="form-group">
                                        <label class="small font-weight-bold text-muted">Password</label>
                                        <div class="input-group">
                                            <input type="password" name="password" id="loginPassword"
                                                   class="form-control form-control-user"
                                                   placeholder="Password" required>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary"
                                                        id="togglePassword" title="Show/Hide">
                                                    <i class="fas fa-eye" id="eyeIcon"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" id="loginBtn" class="btn btn-primary btn-user btn-block">
                                        <i class="fas fa-sign-in-alt mr-2"></i>Login
                                    </button>
                                    <hr>
                                    <div class="text-center">
                                        <a class="small" href="register.php">
                                            <i class="fas fa-user-plus mr-1"></i>Register as Student
                                        </a>
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
<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    var pwd = document.getElementById('loginPassword');
    var icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});
</script>
</body>
</html>
