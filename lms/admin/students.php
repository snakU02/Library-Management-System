<?php
// admin/students.php — Manage student accounts
session_start();
require_once '../config/db.php';
require_once '../config/paths.php';
require_admin();

$page_title  = 'Manage Students';
$active_page = 'students';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'approve') {
        $pdo->prepare("UPDATE users SET status='active' WHERE id=? AND role='student'")->execute([$id]);
        set_flash('success', 'Student account approved.');
    } elseif ($action === 'deactivate') {
        $pdo->prepare("UPDATE users SET status='inactive' WHERE id=? AND role='student'")->execute([$id]);
        set_flash('success', 'Student account deactivated.');
    } elseif ($action === 'add') {
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name']  ?? '');
        $sid   = trim($_POST['student_id'] ?? '');
        $email = trim($_POST['email']      ?? '');
        $pw    = $_POST['password']        ?? 'Student@1234';
        $course= trim($_POST['course']     ?? '');
        $year  = (int)($_POST['year_level']?? 1);
        $contact=trim($_POST['contact']   ?? '');

        if (!$first || !$last || !$sid || !$email) {
            set_flash('error', 'Fill in all required fields.');
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email=? OR student_id=?");
            $check->execute([$email, $sid]);
            if ($check->fetch()) {
                set_flash('error', 'Email or Student ID already exists.');
            } else {
                $hash = password_hash($pw, PASSWORD_BCRYPT);
                $pdo->prepare("INSERT INTO users (student_id,first_name,last_name,email,password,role,course,year_level,contact,status)
                               VALUES (?,?,?,?,?,'student',?,?,?,'active')")
                    ->execute([$sid,$first,$last,$email,$hash,$course,$year,$contact]);
                set_flash('success', "Student $first $last added successfully.");
            }
        }
    } elseif ($action === 'edit') {
        $first   = trim($_POST['first_name'] ?? '');
        $last    = trim($_POST['last_name']  ?? '');
        $sid     = trim($_POST['student_id'] ?? '');
        $email   = trim($_POST['email']      ?? '');
        $course  = trim($_POST['course']     ?? '');
        $year    = (int)($_POST['year_level']?? 1);
        $contact = trim($_POST['contact']    ?? '');

        // Check for duplicate student_id or email on a DIFFERENT student
        $dup = $pdo->prepare("SELECT id FROM users WHERE (student_id=? OR email=?) AND id != ?");
        $dup->execute([$sid, $email, $id]);
        if ($dup->fetch()) {
            set_flash('error', 'Another student already has that Student ID or Email. Please use a different value.');
        } else {
            $pdo->prepare("UPDATE users SET first_name=?,last_name=?,student_id=?,email=?,course=?,year_level=?,contact=? WHERE id=?")
                ->execute([$first,$last,$sid,$email,$course,$year,$contact,$id]);
            set_flash('success', 'Student updated successfully.');
        }
    }
    header('Location: students.php');
    exit;
}

$filter = $_GET['filter'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM borrows b WHERE b.user_id=u.id AND b.status IN ('active','overdue')) AS active_borrows,
        (SELECT COALESCE(SUM(p.total_amount),0) FROM penalties p WHERE p.user_id=u.id AND p.status='unpaid') AS unpaid_penalties
        FROM users u WHERE u.role='student'";
$params = [];
if (in_array($filter, ['active','inactive','pending'])) {
    $sql .= " AND u.status=?"; $params[] = $filter;
}
if ($search) {
    $sql .= " AND (u.last_name LIKE ? OR u.first_name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?)";
    $params = array_merge($params, array_fill(0, 4, "%$search%"));
}
$sql .= " ORDER BY u.last_name, u.first_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

if (isset($_GET['export'])) {
    $lib_name = get_setting($pdo, 'library_name', 'Library');
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Student Records — <?= htmlspecialchars($lib_name) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #000; padding: 20px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #555; margin-bottom: 14px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 5px 8px; text-align: left; vertical-align: top; }
        th { background: #1c3a5e; color: #fff; font-size: 11px; }
        tr:nth-child(even) td { background: #f4f4f4; }
        .footer { margin-top: 14px; font-size: 10px; color: #777; }
        @media print { body { padding: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:16px;">
        <button onclick="window.print()" style="padding:8px 20px;background:#1c3a5e;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;">
            🖨️ Print / Save as PDF
        </button>
        <a href="students.php" style="margin-left:12px;font-size:12px;">← Back to Students</a>
    </div>

    <h1>👥 Student Records — <?= htmlspecialchars($lib_name) ?></h1>
    <p class="meta">
        Generated: <?= date('F d, Y h:i A') ?>
        <?php if ($search): ?> &nbsp;|&nbsp; Search: <strong><?= htmlspecialchars($search) ?></strong><?php endif; ?>
        <?php if ($filter): ?> &nbsp;|&nbsp; Filter: <strong><?= htmlspecialchars(ucfirst($filter)) ?></strong><?php endif; ?>
        &nbsp;|&nbsp; Total: <strong><?= count($students) ?></strong> student(s)
    </p>

    <table>
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Name</th>
                <th>Course/Year</th>
                <th>Email</th>
                <th>Contact</th>
                <th>Borrows</th>
                <th>Penalty</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($students)): ?>
            <tr><td colspan="8" style="text-align:center;color:#888;padding:16px;">No students found.</td></tr>
        <?php else: ?>
            <?php foreach ($students as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['student_id']) ?></td>
                <td><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name']) ?></td>
                <td><?= htmlspecialchars($s['course'] ?? '') ?> <?= $s['year_level'] ? '- Yr. '.$s['year_level'] : '' ?></td>
                <td><?= htmlspecialchars($s['email']) ?></td>
                <td><?= htmlspecialchars($s['contact'] ?? '') ?></td>
                <td><?= (int)$s['active_borrows'] ?></td>
                <td>₱<?= number_format((float)($s['unpaid_penalties'] ?? 0), 2) ?></td>
                <td><?= ucfirst($s['status']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <p class="footer">— End of Student Records —</p>
    <script>window.onload = function(){ window.print(); }</script>
</body>
</html>
<?php
    exit;
}

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
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users mr-2 text-primary"></i>Manage Students</h1>
        <div>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>" class="btn btn-success shadow-sm mr-2">
                <i class="fas fa-print mr-1"></i>Print / PDF
            </a>
            <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#addStudentModal">
                <i class="fas fa-user-plus mr-2"></i>Add Student
            </button>
        </div>
    </div>

    <!-- Filter -->
    <div class="card shadow mb-4">
        <div class="card-body py-3">
            <form method="GET" class="form-inline flex-wrap">
                <input type="text" name="q" class="form-control mr-2 mb-2"
                       placeholder="Name, Student ID, Email..."
                       value="<?= htmlspecialchars($search) ?>" style="min-width:220px;">
                <select name="filter" class="form-control mr-2 mb-2">
                    <option value="" <?= !$filter?'selected':'' ?>>All Students</option>
                    <option value="active"   <?= $filter==='active'  ?'selected':'' ?>>Active</option>
                    <option value="pending"  <?= $filter==='pending' ?'selected':'' ?>>Pending Approval</option>
                    <option value="inactive" <?= $filter==='inactive'?'selected':'' ?>>Inactive</option>
                </select>
                <button type="submit" class="btn btn-primary mr-2 mb-2"><i class="fas fa-filter mr-1"></i>Filter</button>
                <a href="students.php" class="btn btn-secondary mb-2"><i class="fas fa-times mr-1"></i>Clear</a>
            </form>
        </div>
    </div>

    <!-- Students Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-2"></i>Students (<?= count($students) ?>)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="studentsTable">
                    <thead class="thead-dark">
                        <tr><th>Student ID</th><th>Name</th><th>Course/Year</th><th>Email</th><th>Contact</th><th>Borrows</th><th>Penalty</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['student_id']) ?></td>
                        <td><strong><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name']) ?></strong></td>
                        <td><?= htmlspecialchars($s['course'] ?? '') ?> <?= $s['year_level'] ? '- Yr. '.$s['year_level'] : '' ?></td>
                        <td><small><?= htmlspecialchars($s['email']) ?></small></td>
                        <td><small><?= htmlspecialchars($s['contact'] ?? '') ?></small></td>
                        <td class="text-center">
                            <span class="badge badge-info"><?= $s['active_borrows'] ?></span>
                        </td>
                        <td>
                            <?php if ($s['unpaid_penalties'] > 0): ?>
                                <span class="badge badge-danger">₱<?= number_format($s['unpaid_penalties'], 2) ?></span>
                            <?php else: ?>
                                <span class="badge badge-success">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $badges = ['active'=>'success','pending'=>'warning','inactive'=>'secondary']; ?>
                            <span class="badge badge-<?= $badges[$s['status']] ?? 'light' ?>"><?= ucfirst($s['status']) ?></span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info mr-1" title="Edit"
                                    onclick='editStudent(<?= json_encode($s) ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($s['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button class="btn btn-sm btn-success mr-1" title="Approve"
                                            onclick="return confirm('Approve this student account?')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            <?php elseif ($s['status'] === 'active'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="deactivate">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button class="btn btn-sm btn-warning" title="Deactivate"
                                            onclick="return confirm('Deactivate this account?')">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                </form>
                            <?php elseif ($s['status'] === 'inactive'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button class="btn btn-sm btn-success" title="Reactivate"
                                            onclick="return confirm('Reactivate this student?')">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-3">No students found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" id="sAction" value="add">
                <input type="hidden" name="id" id="sId" value="">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="sModalTitle"><i class="fas fa-user-plus mr-2"></i>Add Student</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="col-md-3 form-group">
                            <label class="small font-weight-bold">Student ID *</label>
                            <input type="text" name="student_id" id="sStudentId" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="small font-weight-bold">First Name *</label>
                            <input type="text" name="first_name" id="sFirstName" class="form-control" required>
                        </div>
                        <div class="col-md-5 form-group">
                            <label class="small font-weight-bold">Last Name *</label>
                            <input type="text" name="last_name" id="sLastName" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Email *</label>
                            <input type="email" name="email" id="sEmail" class="form-control" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="small font-weight-bold">Course</label>
                            <input type="text" name="course" id="sCourse" class="form-control" placeholder="BSCS">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="small font-weight-bold">Year Level</label>
                            <select name="year_level" id="sYear" class="form-control">
                                <?php for ($y=1;$y<=5;$y++): ?>
                                    <option value="<?=$y?>"><?=$y?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Contact</label>
                            <input type="text" name="contact" id="sContact" class="form-control">
                        </div>
                        <div class="col-md-6 form-group" id="sPasswordGroup">
                            <label class="small font-weight-bold">Default Password</label>
                            <input type="text" name="password" class="form-control" value="Student@1234">
                            <small class="text-muted">Student should change this after login.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>


</div><!-- /.container-fluid -->
<?php
$page_scripts = '<script>
$(function() { $("#studentsTable").DataTable({order:[[1,"asc"]]}); });

function editStudent(s) {
    document.getElementById("sAction").value    = "edit";
    document.getElementById("sId").value        = s.id;
    document.getElementById("sModalTitle").innerHTML = \'<i class="fas fa-edit mr-2"></i>Edit Student\';
    document.getElementById("sStudentId").value = s.student_id || "";
    document.getElementById("sFirstName").value = s.first_name || "";
    document.getElementById("sLastName").value  = s.last_name  || "";
    document.getElementById("sEmail").value     = s.email      || "";
    document.getElementById("sCourse").value    = s.course     || "";
    document.getElementById("sContact").value   = s.contact    || "";
    document.getElementById("sYear").value      = s.year_level || 1;
    document.getElementById("sPasswordGroup").style.display = "none";
    $("#addStudentModal").modal("show");
}
</script>';
include '../includes/footer.php';
?>
