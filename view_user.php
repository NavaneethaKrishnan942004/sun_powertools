<?php
$pageTitle='View User';
require_once __DIR__.'/config/config.php';
require_once __DIR__.'/includes/auth.php';
requireLogin();
$id=(int)($_GET['id']??0);$q=$conn->prepare('SELECT * FROM user_master WHERE id=:id LIMIT 1');$q->execute([':id'=>$id]);$user=$q->fetch(PDO::FETCH_ASSOC);if(!$user){header('Location: manage_user.php?error='.urlencode('User record not found.'));exit;}require_once __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Breadcrumb Navigation -->
        <?php
        renderBreadcrumbs([
            ['title' => '', 'icon' => 'bi bi-house-door', 'link' => 'index.php'],
            ['title' => 'Settings', 'link' => 'settings.php'],
            ['title' => 'DMS Masters', 'link' => 'masters.php'],
            ['title' => 'User Master', 'link' => 'manage_user.php'],
            ['title' => 'View']
        ]);
        ?>

        <div class="page-heading d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="page-title mb-1">View User</h1>
                <p class="text-muted mb-0"><?= htmlspecialchars($user['user_id']) ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="edit_user.php?id=<?= (int)$user['id'] ?>" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <a href="manage_user.php" class="btn btn-outline-secondary">Back</a>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="card-title mb-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2 text-primary"></i>User Details</h5>
                </div>
                <div class="card-header-actions">
                    <span class="badge <?= (int)$user['status'] === 1 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> px-3 py-1.5 rounded-pill border">
                        <span class="status-dot <?= (int)$user['status'] === 1 ? 'active' : 'inactive' ?>"></span>
                        <?= (int)$user['status'] === 1 ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <?php foreach ([['user_id', 'User ID'], ['user_name', 'User Name'], ['user_email', 'User Mail'], ['user_phone', 'User Phone No']] as $f): ?>
                        <div class="col-12 col-md-6">
                            <label class="form-label text-muted"><?= $f[1] ?></label>
                            <div class="form-control bg-body-tertiary"><?= htmlspecialchars($user[$f[0]]) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted">Role</label>
                        <div class="form-control bg-body-tertiary"><?= ucfirst(htmlspecialchars($user['role'])) ?></div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted">Status</label>
                        <div class="form-control bg-body-tertiary">
                            <span class="status-dot <?= $user['status'] ? 'active' : 'inactive' ?>"></span>
                            <?= $user['status'] ? 'Active' : 'Inactive' ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <hr>
                        <h6 class="mb-3 fw-bold">Audit Information</h6>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted">Created At</label>
                        <div class="form-control bg-body-tertiary"><?= htmlspecialchars($user['created_at']) ?></div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted">Updated At</label>
                        <div class="form-control bg-body-tertiary"><?= !empty($user['updated_at']) ? htmlspecialchars($user['updated_at']) : '-' ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
