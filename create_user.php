<?php
$pageTitle = 'Create User';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$errors = ['user_name' => '', 'user_email' => '', 'user_phone' => '', 'password' => '', 'confirm_password' => '', 'role' => ''];
$v = ['user_name' => '', 'user_email' => '', 'user_phone' => '', 'role' => 'user', 'status' => 1];
function validateCreate($conn, $d)
{
    $e = ['user_name' => '', 'user_email' => '', 'user_phone' => '', 'password' => '', 'confirm_password' => '', 'role' => ''];
    $n = trim($d['user_name'] ?? '');
    $m = trim($d['user_email'] ?? '');
    $p = trim($d['user_phone'] ?? '');
    $pw = $d['password'] ?? '';
    $cp = $d['confirm_password'] ?? '';
    $r = $d['role'] ?? '';
    if ($n === '')
        $e['user_name'] = 'User Name is required.';
    elseif (mb_strlen($n) < 2)
        $e['user_name'] = 'User Name must be at least 2 characters.';
    elseif (mb_strlen($n) > 100)
        $e['user_name'] = 'User Name cannot exceed 100 characters.';
    if ($m === '')
        $e['user_email'] = 'User Mail is required.';
    elseif (!filter_var($m, FILTER_VALIDATE_EMAIL))
        $e['user_email'] = 'Please enter a valid email address.';
    elseif (mb_strlen($m) > 150)
        $e['user_email'] = 'User Mail cannot exceed 150 characters.';
    if ($p === '')
        $e['user_phone'] = 'User Phone No is required.';
    elseif (!preg_match('/^[0-9+\-\s]{7,20}$/', $p))
        $e['user_phone'] = 'Please enter a valid phone number.';
    if ($pw === '')
        $e['password'] = 'Password is required.';
    elseif (strlen($pw) < 6)
        $e['password'] = 'Password must be at least 6 characters.';
    if ($cp === '')
        $e['confirm_password'] = 'Confirm Password is required.';
    elseif ($pw !== $cp)
        $e['confirm_password'] = 'Password and Confirm Password do not match.';
    if (!in_array($r, ['user', 'admin'], true))
        $e['role'] = 'Please select a valid role.';
    if (!$e['user_email']) {
        $q = $conn->prepare('SELECT COUNT(*) FROM user_master WHERE LOWER(user_email)=LOWER(:m)');
        $q->execute([':m' => $m]);
        if ((int) $q->fetchColumn())
            $e['user_email'] = 'User Mail already exists.';
    }
    if (!$e['user_phone']) {
        $q = $conn->prepare('SELECT COUNT(*) FROM user_master WHERE user_phone=:p');
        $q->execute([':p' => $p]);
        if ((int) $q->fetchColumn())
            $e['user_phone'] = 'User Phone No already exists.';
    }
    return $e;
}
function generateUserId($conn)
{
    $q = $conn->query("SELECT user_id FROM user_master ORDER BY id DESC LIMIT 1");
    $last = $q->fetchColumn();
    if (!$last)
        return 'US-001';
    return 'US-' . str_pad((int) str_replace('US-', '', $last) + 1, 3, '0', STR_PAD_LEFT);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['user_name'] = trim($_POST['user_name'] ?? '');
    $v['user_email'] = trim($_POST['user_email'] ?? '');
    $v['user_phone'] = trim($_POST['user_phone'] ?? '');
    $v['role'] = $_POST['role'] ?? 'user';
    $v['status'] = isset($_POST['status']) ? 1 : 0;
    $errors = validateCreate($conn, $_POST);
    if (!array_filter($errors)) {
        $uid = generateUserId($conn);
        $q = $conn->prepare('INSERT INTO user_master(user_id,user_name,user_email,user_phone,password,role,status,created_at) VALUES(:uid,:n,:m,:p,:pw,:r,:s,:c)');
        $q->execute([':uid' => $uid, ':n' => $v['user_name'], ':m' => $v['user_email'], ':p' => $v['user_phone'], ':pw' => password_hash($_POST['password'], PASSWORD_DEFAULT), ':r' => $v['role'], ':s' => $v['status'], ':c' => date('Y-m-d H:i:s')]);
        header('Location: manage_user.php?success=' . urlencode("User {$uid} created successfully."));
        exit;
    }
}
require_once __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Breadcrumb Navigation -->
        <?php
        renderBreadcrumbs([
            ['title' => '', 'icon' => 'bi bi-house-door', 'link' => 'index.php'],
            ['title' => 'Users Management', 'link' => 'manage_user.php'],
            ['title' => 'Add']
        ]);
        ?>

        <div class="page-heading mb-4">
            <div>
                <h1 class="page-title mb-1">Create User</h1>
                <p class="text-muted mb-0">Create a new application user.</p>
            </div>
        </div>

        <form method="POST" novalidate>
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="card-title mb-0">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-person-plus me-2 text-primary"></i>User Details</h5>
                    </div>
                    <div class="card-header-actions">
                        <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                            <input class="form-check-input" type="checkbox" name="status" id="createStatus" <?= $v['status'] ? 'checked' : '' ?>>
                            <label class="form-check-label mb-0 fw-semibold" id="createStatusLabel" for="createStatus"><?= $v['status'] ? 'Active' : 'Inactive' ?></label>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- <div class="col-12 col-md-6">
                            <label class="form-label">User ID</label>
                            <input class="form-control" value="Auto Generated (US-001)" readonly>
                        </div> -->
                        <?php foreach ([['user_name', 'User Name', 'text'], ['user_email', 'User Mail', 'email'], ['user_phone', 'User Phone No', 'text']] as $f):
                            [$k, $lab, $type] = $f; ?>
                            <div class="col-12 col-md-6">
                                <label class="form-label"><?= $lab ?> <span class="text-danger">*</span></label>
                                <input type="<?= $type ?>" name="<?= $k ?>" class="form-control <?= $errors[$k] ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($v[$k]) ?>" placeholder="Enter <?= strtolower($lab) ?>">
                                <?php if ($errors[$k]): ?>
                                    <div class="validation-message"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors[$k]) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control <?= $errors['password'] ? 'is-invalid' : '' ?>" placeholder="Enter password">
                            <?php if ($errors['password']): ?>
                                <div class="validation-message"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['password']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control <?= $errors['confirm_password'] ? 'is-invalid' : '' ?>" placeholder="Confirm password">
                            <?php if ($errors['confirm_password']): ?>
                                <div class="validation-message"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['confirm_password']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select <?= $errors['role'] ? 'is-invalid' : '' ?>">
                                <option value="user" <?= $v['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="admin" <?= $v['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <?php if ($errors['role']): ?>
                                <div class="validation-message"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['role']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="manage_user.php" class="btn btn-outline-secondary">Back</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const s = document.getElementById('createStatus'), l = document.getElementById('createStatusLabel');
    if (s && l) {
        s.addEventListener('change', function () {
            l.textContent = this.checked ? 'Active' : 'Inactive';
        });
    }
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>