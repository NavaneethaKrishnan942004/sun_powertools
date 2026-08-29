<?php

$pageTitle = 'View Product Type';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/header.php';

$id = (int) ($_GET['id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Get Product Type Details
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT 
        ptm.*,
        creator.user_name AS created_by_name,
        updater.user_name AS updated_by_name
    FROM product_type_master ptm
    LEFT JOIN user_master creator ON creator.id = ptm.created_by
    LEFT JOIN user_master updater ON updater.id = ptm.updated_by
    WHERE ptm.id = :id
";

$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $id]);
$productType = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Product Type Not Found
|--------------------------------------------------------------------------
*/

if (!$productType) {
    echo '
        <main class="main-content">
            <div class="container-fluid px-3 px-lg-4 py-4">
                <div class="alert alert-danger">
                    Product Type record not found.
                </div>
                <a href="manage_producttype.php" class="btn btn-secondary">
                    Back to Product Type Master
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
            ['title' => 'Product Type Master', 'link' => 'manage_producttype.php'],
            ['title' => 'View']
        ]);
        ?>

        <!-- Page Heading -->
        <div class="page-heading d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="page-title mb-1">View Product Type</h1>
                <p class="text-muted mb-0">View product type classification details.</p>
            </div>
        </div>

        <!-- Product Type Details Card -->
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="card-title mb-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-diagram-3 me-2 text-primary"></i>Product Type Details</h5>
                </div>
                <div class="card-header-actions">
                    <div class="d-flex gap-2">
                        <a href="manage_producttype.php" class="btn btn-outline-secondary">
                            Back
                        </a>
                        <a href="manage_producttype.php?edit=<?= (int) $productType['id'] ?>" class="btn btn-primary">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-4">

                    <!-- Product Type Code -->
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Product Type ID</label>
                        <div class="fw-semibold fs-6 text-primary"><?= htmlspecialchars($productType['product_type_code']) ?></div>
                    </div>

                    <!-- Status -->
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Status</label>
                        <div>
                            <?php if ((int)$productType['status'] === 1): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Product Type Name -->
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted">Product Type Name</label>
                        <div class="fw-semibold fs-6"><?= htmlspecialchars($productType['product_type_name']) ?></div>
                    </div>

                    <!-- Description -->
                    <div class="col-12">
                        <label class="form-label text-muted">Description</label>
                        <div class="p-3 bg-body-tertiary rounded-3 border">
                            <?php if (!empty($productType['description'])): ?>
                                <?= nl2br(htmlspecialchars($productType['description'])) ?>
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
                        <div><?= htmlspecialchars($productType['created_by_name'] ?? $productType['created_by'] ?? '-') ?></div>
                    </div>

                    <!-- Created At -->
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Created At</label>
                        <div><?= htmlspecialchars($productType['created_at'] ?? '-') ?></div>
                    </div>

                    <!-- Updated By -->
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Updated By</label>
                        <div><?= !empty($productType['updated_by_name']) ? htmlspecialchars($productType['updated_by_name']) : (!empty($productType['updated_by']) ? htmlspecialchars($productType['updated_by']) : '-') ?></div>
                    </div>

                    <!-- Updated At -->
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Updated At</label>
                        <div><?= !empty($productType['updated_at']) ? htmlspecialchars($productType['updated_at']) : '-' ?></div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
