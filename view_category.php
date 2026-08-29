<?php

$pageTitle = 'View Category';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/header.php';


$id = (int) ($_GET['id'] ?? 0);


/*
|--------------------------------------------------------------------------
| Get Category
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT 
        cm.*,
        creator.user_name AS created_by_name,
        updater.user_name AS updated_by_name
    FROM category_master cm
    LEFT JOIN user_master creator ON creator.id = cm.created_by
    LEFT JOIN user_master updater ON updater.id = cm.updated_by
    WHERE cm.id = :id
";

$stmt = $conn->prepare($sql);

$stmt->execute([
    ':id' => $id
]);

$category = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| Category Not Found
|--------------------------------------------------------------------------
*/

if (!$category) {

    echo '
        <main class="main-content">
            <div class="container-fluid px-3 px-lg-4 py-4">
                <div class="alert alert-danger">
                    Category not found.
                </div>

                <a href="manage_category.php"
                   class="btn btn-secondary">
                    Back
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
            ['title' => 'Master', 'link' => 'masters.php'],
            ['title' => 'Category Master', 'link' => 'manage_category.php'],
            ['title' => 'View']
        ]);
        ?>

        <!-- Page Heading -->
        <div class="page-heading d-flex justify-content-between align-items-center mb-4">

            <div>

                <h1 class="page-title mb-1">
                    View Category
                </h1>

                <p class="text-muted mb-0">
                    View category details.
                </p>

            </div>


        </div>


        <!-- Category Details -->
        <div class="card shadow-sm">

            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="card-title mb-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-tags me-2 text-primary"></i>Category Details</h5>
                </div>
                <div class="card-header-actions">
                    <div class="d-flex gap-2">
                        <a href="manage_category.php" class="btn btn-outline-secondary">
                            Back
                        </a>
                        <a href="manage_category.php?edit=<?= (int) $category['id'] ?>" class="btn btn-primary">
                            <i class="bi bi-pencil me-1"></i>
                            Edit
                        </a>

                    </div>
                </div>
            </div>

            <div class="card-body">

                <div class="row g-4">


                    <!-- Category Code -->
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">
                            Category ID : <?= htmlspecialchars($category['category_code']) ?>
                        </label>
                    </div>

                    <!-- Status -->
                    <div class="col-6 col-md-3">

                        <label class="form-label text-muted">
                            Status : <?php if ($category['status'] == 1): ?>

                                <span class="badge bg-success">
                                    Active
                                </span>

                            <?php else: ?>

                                <span class="badge bg-secondary">
                                    Inactive
                                </span>

                            <?php endif; ?>
                        </label>


                    </div>


                    <!-- Category Name -->
                    <div class="col-6">
                        <label class="form-label text-muted">
                            Category Name : <?= htmlspecialchars($category['category_name']) ?>
                        </label>
                    </div>


                    <!-- Description -->
                    <div class="col-12">

                        <label class="form-label text-muted">
                            Description : <?php if (!empty($category['description'])): ?>

                                <?= nl2br(
                                    htmlspecialchars($category['description'])
                                ) ?>

                            <?php else: ?>

                                <span class="text-muted">
                                    -
                                </span>

                            <?php endif; ?>

                        </label>

                    </div>


                    <!-- Created By -->
                    <div class="col-6 col-md-3">

                        <label class="form-label text-muted">
                            Created By : <?= htmlspecialchars($category['created_by_name'] ?? $category['created_by'] ?? '-') ?>
                        </label>
                    </div>

                    <!-- Created At -->
                    <div class="col-6 col-md-3">

                        <label class="form-label text-muted">
                            Created At : <?= htmlspecialchars($category['created_at']) ?>
                        </label>
                    </div>


                    <!-- Updated By -->
                    <div class="col-6 col-md-3">

                        <label class="form-label text-muted">
                            Updated By :
                            <?=
                                !empty($category['updated_by_name'])
                                ? htmlspecialchars($category['updated_by_name'])
                                : (!empty($category['updated_by']) ? htmlspecialchars($category['updated_by']) : '-')
                                ?>
                        </label>
                    </div>

                    <!-- Updated At -->
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">
                            Updated At : <?= htmlspecialchars($category['updated_at']) ?>
                        </label>

                    </div>

                </div>

            </div>

        </div>

    </div>

</main>


<?php

require_once __DIR__ . '/includes/footer.php';

?>