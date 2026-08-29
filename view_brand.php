<?php

$pageTitle = 'View Brand';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/header.php';

$id = (int) ($_GET['id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Get Brand Details
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT 
        bm.*,
        creator.user_name AS created_by_name,
        updater.user_name AS updated_by_name
    FROM brand_master bm
    LEFT JOIN user_master creator ON creator.id = bm.created_by
    LEFT JOIN user_master updater ON updater.id = bm.updated_by
    WHERE bm.id = :id
";

$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $id]);
$brand = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Brand Not Found
|--------------------------------------------------------------------------
*/

if (!$brand) {
    echo '
        <main class="main-content">
            <div class="container-fluid px-3 px-lg-4 py-4">
                <div class="alert alert-danger">
                    Brand record not found.
                </div>
                <a href="manage_brand.php" class="btn btn-secondary">
                    Back to Brand Master
                </a>
            </div>
        </main>
    ';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

?>

<main class="main-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Breadcrumb Navigation -->
        <?php
        renderBreadcrumbs([
            ['title' => '', 'icon' => 'bi bi-house-door', 'link' => 'index.php'],
            ['title' => 'Masters', 'link' => 'masters.php'],
            ['title' => 'Brand Master', 'link' => 'manage_brand.php'],
            ['title' => 'View']
        ]);
        ?>

        <!-- Page Heading -->
        <div class="page-heading d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="page-title mb-1">View Brand</h1>
                <p class="text-muted mb-0">View brand details and specifications.</p>
            </div>
        </div>

        <!-- Brand Details Card -->
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="card-title mb-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-bootstrap me-2 text-primary"></i>Brand Details</h5>
                </div>
                <div class="card-header-actions">
                    <div class="d-flex gap-2">
                        <a href="manage_brand.php" class="btn btn-outline-secondary">
                            Back
                        </a>
                        <a href="manage_brand.php?edit=<?= (int) $brand['id'] ?>" class="btn btn-primary">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-4">

                    <!-- Brand Code -->
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Brand ID</label>
                        <div class="fw-semibold fs-6 text-primary"><?= htmlspecialchars($brand['brand_code']) ?></div>
                    </div>

                    <!-- Status -->
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Status</label>
                        <div>
                            <?php if ((int)$brand['status'] === 1): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Brand Name -->
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted">Brand Name</label>
                        <div class="fw-semibold fs-6"><?= htmlspecialchars($brand['brand_name']) ?></div>
                    </div>

                    <!-- Description -->
                    <div class="col-12">
                        <label class="form-label text-muted">Description</label>
                        <div class="p-3 bg-body-tertiary rounded-3 border">
                            <?php if (!empty($brand['description'])): ?>
                                <?= nl2br(htmlspecialchars($brand['description'])) ?>
                            <?php else: ?>
                                <span class="text-muted">No description provided.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Audit Header -->
                    <div class="col-12">
                        <hr class="my-2">
                        <h6 class="fw-bold mb-0 text-muted"><i class="bi bi-clock-history me-1"></i>Audit Information</h6>
                    </div>

                    <!-- Created By -->
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Created By</label>
                        <div><?= htmlspecialchars($brand['created_by_name'] ?? $brand['created_by'] ?? '-') ?></div>
                    </div>

                    <!-- Created At -->
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Created At</label>
                        <div><?= htmlspecialchars($brand['created_at'] ?? '-') ?></div>
                    </div>

                    <!-- Updated By -->
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Updated By</label>
                        <div><?= !empty($brand['updated_by_name']) ? htmlspecialchars($brand['updated_by_name']) : (!empty($brand['updated_by']) ? htmlspecialchars($brand['updated_by']) : '-') ?></div>
                    </div>

                    <!-- Updated At -->
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Updated At</label>
                        <div><?= !empty($brand['updated_at']) ? htmlspecialchars($brand['updated_at']) : '-' ?></div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
