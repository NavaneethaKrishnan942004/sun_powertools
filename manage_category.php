<?php
$pageTitle = 'Category Master';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$conn = $pdo;

$errors = [];
$categoryNameError = '';
$descriptionError = '';
$duplicateNameError = '';
$validationAction = '';
$validationId = 0;
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

function generateCategoryCode($conn)
{
    $stmt = $conn->prepare("SELECT category_code FROM category_master ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $lastCode = $stmt->fetchColumn();

    if (!$lastCode) {
        return 'CAT-001';
    }

    $number = (int) str_replace('CAT-', '', $lastCode) + 1;
    return 'CAT-' . str_pad($number, 3, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'create') {
        $categoryName = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        $validationAction = 'create';

        if ($categoryName === '') {
            $categoryNameError = 'Category Name is required.';
        } elseif (mb_strlen($categoryName) < 2) {
            $categoryNameError = 'Category Name must be at least 2 characters.';
        } elseif (mb_strlen($categoryName) > 100) {
            $categoryNameError = 'Category Name cannot exceed 100 characters.';
        }

        if (mb_strlen($description) > 200) {
            $descriptionError = 'Description cannot exceed 200 characters.';
        }

        if ($categoryNameError === '') {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM category_master WHERE LOWER(category_name) = LOWER(:category_name)");
            $stmt->execute([':category_name' => $categoryName]);
            if ((int) $stmt->fetchColumn() > 0) {
                $duplicateNameError = 'Category Name already exists.';
            }
        }

        if ($categoryNameError !== '')
            $errors[] = $categoryNameError;
        if ($descriptionError !== '')
            $errors[] = $descriptionError;
        if ($duplicateNameError !== '')
            $errors[] = $duplicateNameError;

        if (empty($errors)) {
            $categoryCode = generateCategoryCode($conn);
            $createdBy = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 1;
            $createdAt = date('Y-m-d H:i:s');

            $stmt = $conn->prepare("INSERT INTO category_master (category_code, category_name, description, status, created_by, created_at) VALUES (:category_code, :category_name, :description, :status, :created_by, :created_at)");
            $stmt->execute([
                ':category_code' => $categoryCode,
                ':category_name' => $categoryName,
                ':description' => $description,
                ':status' => $status,
                ':created_by' => $createdBy,
                ':created_at' => $createdAt
            ]);

            header('Location: manage_category.php?success=' . urlencode("Category {$categoryCode} created successfully."));
            exit;
        }
    }

    if ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $categoryName = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        $validationAction = 'edit';
        $validationId = $id;

        if ($id <= 0) {
            $errors[] = 'Invalid category record.';
        } else {
            if ($categoryName === '') {
                $categoryNameError = 'Category Name is required.';
            } elseif (mb_strlen($categoryName) < 2) {
                $categoryNameError = 'Category Name must be at least 2 characters.';
            } elseif (mb_strlen($categoryName) > 100) {
                $categoryNameError = 'Category Name cannot exceed 100 characters.';
            }

            if (mb_strlen($description) > 200) {
                $descriptionError = 'Description cannot exceed 200 characters.';
            }

            if ($categoryNameError === '') {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM category_master WHERE LOWER(category_name) = LOWER(:category_name) AND id != :id");
                $stmt->execute([
                    ':category_name' => $categoryName,
                    ':id' => $id
                ]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $duplicateNameError = 'Category Name already exists.';
                }
            }

            if ($categoryNameError !== '')
                $errors[] = $categoryNameError;
            if ($descriptionError !== '')
                $errors[] = $descriptionError;
            if ($duplicateNameError !== '')
                $errors[] = $duplicateNameError;

            if (empty($errors)) {
                $updatedBy = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 1;
                $updatedAt = date('Y-m-d H:i:s');

                $stmt = $conn->prepare("UPDATE category_master SET category_name = :category_name, description = :description, status = :status, updated_by = :updated_by, updated_at = :updated_at WHERE id = :id");
                $stmt->execute([
                    ':category_name' => $categoryName,
                    ':description' => $description,
                    ':status' => $status,
                    ':updated_by' => $updatedBy,
                    ':updated_at' => $updatedAt,
                    ':id' => $id
                ]);

                header('Location: manage_category.php?success=' . urlencode('Category updated successfully.'));
                exit;
            }
        }
    }
}

$sql = "SELECT cm.*, creator.user_name AS created_by_name, updater.user_name AS updated_by_name
        FROM category_master cm
        LEFT JOIN user_master creator ON creator.id = cm.created_by
        LEFT JOIN user_master updater ON updater.id = cm.updated_by
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (cm.category_code LIKE :search OR cm.category_name LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($statusFilter !== '' && in_array($statusFilter, ['0', '1'], true)) {
    $sql .= " AND cm.status = :status";
    $params[':status'] = (int) $statusFilter;
}

$sql .= " ORDER BY cm.id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<main class="main-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Breadcrumb Navigation -->
        <?php
        renderBreadcrumbs([
            ['title' => '', 'icon' => 'bi bi-house-door', 'link' => 'index.php'],
            ['title' => 'Masters', 'link' => 'masters.php'],
            ['title' => 'Category Master']
        ]);
        ?>

        <div class="page-heading d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="page-title mb-1">Category Master</h1>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                <i class="bi bi-plus-lg me-1"></i> Create Category
            </button>
        </div>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show auto-hide-alert">
                <span><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <div class="alert-time-line"></div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" id="filterForm">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-8">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" id="categorySearch" class="form-control"
                                placeholder="Search ID or category name" value="<?= htmlspecialchars($search) ?>"
                                autocomplete="off">
                        </div>

                        <?php if ($search !== '' || $statusFilter !== ''): ?>
                            <div class="col-6 col-md-2">
                                <a href="manage_category.php" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-x-circle me-1"></i> Clear
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="<?= ($search !== '' || $statusFilter !== '') ? 'col-6' : 'col-12' ?> col-md-2">
                            <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="collapse"
                                data-bs-target="#filterSection" aria-expanded="false" aria-controls="filterSection"
                                id="filterToggleBtn">
                                <i class="bi bi-funnel me-1"></i> Filter
                                <i class="bi bi-chevron-down ms-1" id="filterIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="collapse mt-3" id="filterSection">
                        <div class="filter-section-box">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="status" id="statusFilter" class="form-select">
                                        <option value="">All Status</option>
                                        <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Active</option>
                                        <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="category-table-wrapper">

                    <div class="category-table-scroll">

                        <table class="table category-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Category ID</th>
                                    <th>Category Name</th>
                                    <th>Description</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th>Updated By</th>
                                    <th>Updated At</th>
                                    <th class="action-column text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="bi bi-folder2-open fs-2 d-block mb-2"></i>
                                            No categories found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $row): ?>
                                        <tr>
                                            <td>
                                                <span
                                                    class="status-dot <?= ((int) $row['status'] === 1) ? 'active' : 'inactive' ?>"
                                                    title="<?= ((int) $row['status'] === 1) ? 'Active' : 'Inactive' ?>"></span>
                                                <strong><?= htmlspecialchars($row['category_code']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($row['category_name']) ?></td>
                                            <td class="text-muted">
                                                <?= !empty($row['description']) ? htmlspecialchars($row['description']) : '-' ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['created_by_name'] ?? $row['created_by'] ?? '-') ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                                            <td><?= !empty($row['updated_by_name']) ? htmlspecialchars($row['updated_by_name']) : (!empty($row['updated_by']) ? htmlspecialchars($row['updated_by']) : '-') ?>
                                            </td>
                                            <td><?= !empty($row['updated_at']) ? htmlspecialchars($row['updated_at']) : '-' ?>
                                            </td>
                                            <td class="action-column text-end text-nowrap">

                                                <a href="view_category.php?id=<?= (int) $row['id'] ?>"
                                                    class="btn btn-sm btn-outline-secondary" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>

                                                <button type="button" class="btn btn-sm btn-outline-primary" title="Edit"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editCategoryModal<?= (int) $row['id'] ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>

                                            </td>
                                        </tr>

                                        <?php
                                        $isEditValidation = ($validationAction === 'edit' && $validationId === (int) $row['id']);
                                        $editCategoryName = $isEditValidation ? ($_POST['category_name'] ?? $row['category_name']) : $row['category_name'];
                                        $editDescription = $isEditValidation ? ($_POST['description'] ?? $row['description']) : $row['description'];
                                        $editStatus = $isEditValidation ? isset($_POST['status']) : ((int) $row['status'] === 1);
                                        ?>

                                        <div class="modal fade" id="editCategoryModal<?= (int) $row['id'] ?>" tabindex="-1"
                                            aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <div>
                                                            <h5 class="modal-title">Edit Category</h5>
                                                            <small
                                                                class="text-muted"><?= htmlspecialchars($row['category_code']) ?></small>
                                                        </div>
                                                        <button type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>

                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="form_action" value="edit">
                                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

                                                            <div class="row g-3">
                                                                <div class="col-6 col-md-6">
                                                                    <label class="form-label">Category Code</label>
                                                                    <input type="text" class="form-control"
                                                                        value="<?= htmlspecialchars($row['category_code']) ?>"
                                                                        readonly>
                                                                </div>

                                                                <div class="col-6 col-md-6">
                                                                    <label class="form-label">Status</label>
                                                                    <div class="form-check form-switch mt-2">
                                                                        <input class="form-check-input" type="checkbox"
                                                                            name="status" id="editStatus<?= (int) $row['id'] ?>"
                                                                            <?= $editStatus ? 'checked' : '' ?>>
                                                                        <label class="form-check-label"
                                                                            for="editStatus<?= (int) $row['id'] ?>"
                                                                            id="editStatusLabel<?= (int) $row['id'] ?>">
                                                                            <?= $editStatus ? 'Active' : 'Inactive' ?>
                                                                        </label>
                                                                    </div>
                                                                </div>

                                                                <div class="col-12">
                                                                    <label class="form-label">Category Name <span
                                                                            class="text-danger">*</span></label>
                                                                    <input type="text" name="category_name"
                                                                        class="form-control <?= ($isEditValidation && ($categoryNameError !== '' || $duplicateNameError !== '')) ? 'is-invalid' : '' ?>"
                                                                        value="<?= htmlspecialchars($editCategoryName) ?>"
                                                                        placeholder="Enter category name">

                                                                    <?php if ($isEditValidation && $categoryNameError !== ''): ?>
                                                                        <div class="validation-message"><i
                                                                                class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($categoryNameError) ?>
                                                                        </div>
                                                                    <?php elseif ($isEditValidation && $duplicateNameError !== ''): ?>
                                                                        <div class="validation-message"><i
                                                                                class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($duplicateNameError) ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>

                                                            <div class="col-12">
                                                                <div class="d-flex justify-content-between">
                                                                    <label class="form-label">Description</label>
                                                                    <small class="text-muted"
                                                                        id="editDescriptionCount<?= (int) $row['id'] ?>"><?= strlen($editDescription ?? '') ?>
                                                                        / 200</small>
                                                                </div>

                                                                <textarea name="description"
                                                                    id="editDescription<?= (int) $row['id'] ?>"
                                                                    class="form-control <?= ($isEditValidation && $descriptionError !== '') ? 'is-invalid' : '' ?>"
                                                                    rows="4"
                                                                    placeholder="Enter category description"><?= htmlspecialchars($editDescription ?? '') ?></textarea>

                                                                <?php if ($isEditValidation && $descriptionError !== ''): ?>
                                                                    <div class="validation-message"><i
                                                                            class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($descriptionError) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>

                                                            <div class="col-12">
                                                                <hr>
                                                                <div class="row g-3">
                                                                    <div class="col-12 col-md-6">
                                                                        <label class="form-label text-muted">Created
                                                                            By</label>
                                                                        <input type="text" class="form-control"
                                                                            value="<?= htmlspecialchars($row['created_by_name'] ?? $row['created_by'] ?? '') ?>"
                                                                            readonly>
                                                                    </div>
                                                                    <div class="col-12 col-md-6">
                                                                        <label class="form-label text-muted">Created
                                                                            At</label>
                                                                        <input type="text" class="form-control"
                                                                            value="<?= htmlspecialchars($row['created_at']) ?>"
                                                                            readonly>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-outline-secondary"
                                                                data-bs-dismiss="modal">Back</button>
                                                            <button type="submit" class="btn btn-primary"><i
                                                                    class="bi bi-check-lg me-1"></i>Update</button>
                                                        </div>
                                                </div>


                                                </form>
                                            </div>
                                        </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- CREATE MODAL -->
<div class="modal fade" id="createCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="modal-title mb-0 fw-bold">Create Category</h5>
                        <small class="text-muted">Add a new product category</small>
                    </div>
                    <div class="d-flex align-items-center gap-3 ms-auto">
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="form_action" value="create">

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch m-0 d-flex align-items-center gap-2 justify-content-end">
                                <input class="form-check-input" type="checkbox" name="status" id="createStatus" checked>
                                <label class="form-check-label mb-0 fw-semibold" for="createStatus"
                                    id="createStatusLabel">Active</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" name="category_name"
                                class="form-control <?= ($validationAction === 'create' && ($categoryNameError !== '' || $duplicateNameError !== '')) ? 'is-invalid' : '' ?>"
                                value="<?= $validationAction === 'create' ? htmlspecialchars($_POST['category_name'] ?? '') : '' ?>"
                                placeholder="Enter category name">

                            <?php if ($validationAction === 'create' && $categoryNameError !== ''): ?>
                                <div class="validation-message"><i
                                        class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($categoryNameError) ?>
                                </div>
                            <?php elseif ($validationAction === 'create' && $duplicateNameError !== ''): ?>
                                <div class="validation-message"><i
                                        class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($duplicateNameError) ?>
                                </div>
                            <?php endif; ?>

                        </div>

                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <label class="form-label">Description</label>
                                <small class="text-muted"
                                    id="createDescriptionCount"><?= $validationAction === 'create' ? strlen($_POST['description'] ?? '') : 0 ?>
                                    / 200</small>
                            </div>

                            <textarea name="description" id="createDescription"
                                class="form-control <?= ($validationAction === 'create' && $descriptionError !== '') ? 'is-invalid' : '' ?>"
                                rows="4"
                                placeholder="Enter category description"><?= $validationAction === 'create' ? htmlspecialchars($_POST['description'] ?? '') : '' ?></textarea>

                            <?php if ($validationAction === 'create' && $descriptionError !== ''): ?>
                                <div class="validation-message"><i
                                        class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($descriptionError) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Back</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        /* Reopen the correct modal after PHP validation */
        const validationAction = <?= json_encode($validationAction) ?>;
        const validationId = <?= (int) $validationId ?>;

        if (validationAction === 'create') {
            const modal = document.getElementById('createCategoryModal');
            if (modal) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        }

        if (validationAction === 'edit' && validationId > 0) {
            const modal = document.getElementById('editCategoryModal' + validationId);
            if (modal) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>