<?php

$pageTitle = 'Brand Master';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$conn = $pdo;

$errors = [];

$brandNameError = '';
$descriptionError = '';
$duplicateNameError = '';

$validationAction = '';
$validationId = 0;

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';


/*
|--------------------------------------------------------------------------
| Generate Brand ID
|--------------------------------------------------------------------------
*/

function generateBrandCode($conn)
{
    $stmt = $conn->prepare("
        SELECT brand_code
        FROM brand_master
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute();

    $lastCode = $stmt->fetchColumn();

    if (!$lastCode) {
        return 'BRA-001';
    }

    $number = (int) str_replace('BRA-', '', $lastCode) + 1;

    return 'BRA-' . str_pad($number, 3, '0', STR_PAD_LEFT);
}


/*
|--------------------------------------------------------------------------
| POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['form_action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | CREATE BRAND
    |--------------------------------------------------------------------------
    */

    if ($action === 'create') {

        $brandName = trim($_POST['brand_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // Create should default to Active
        $status = isset($_POST['status']) ? 1 : 0;

        $validationAction = 'create';


        /*
        |--------------------------------------------------------------------------
        | Brand Name Validation
        |--------------------------------------------------------------------------
        */

        if ($brandName === '') {

            $brandNameError = 'Brand Name is required.';

        } elseif (mb_strlen($brandName) < 2) {

            $brandNameError = 'Brand Name must be at least 2 characters.';

        } elseif (mb_strlen($brandName) > 100) {

            $brandNameError = 'Brand Name cannot exceed 100 characters.';

        }


        /*
        |--------------------------------------------------------------------------
        | Description Validation
        |--------------------------------------------------------------------------
        */

        if (mb_strlen($description) > 200) {

            $descriptionError = 'Description cannot exceed 200 characters.';

        }


        /*
        |--------------------------------------------------------------------------
        | Duplicate Brand Name
        |--------------------------------------------------------------------------
        */

        if ($brandNameError === '') {

            $stmt = $conn->prepare("
                SELECT COUNT(*)
                FROM brand_master
                WHERE LOWER(brand_name) = LOWER(:brand_name)
            ");

            $stmt->execute([
                ':brand_name' => $brandName
            ]);

            if ((int)$stmt->fetchColumn() > 0) {

                $duplicateNameError = 'Brand Name already exists.';

            }
        }


        /*
        |--------------------------------------------------------------------------
        | Collect Errors
        |--------------------------------------------------------------------------
        */

        if ($brandNameError !== '') {
            $errors[] = $brandNameError;
        }

        if ($descriptionError !== '') {
            $errors[] = $descriptionError;
        }

        if ($duplicateNameError !== '') {
            $errors[] = $duplicateNameError;
        }


        /*
        |--------------------------------------------------------------------------
        | Insert Brand
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            $brandCode = generateBrandCode($conn);

            $createdBy = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
            $createdAt = date('Y-m-d H:i:s');

            $stmt = $conn->prepare("
                INSERT INTO brand_master
                (
                    brand_code,
                    brand_name,
                    description,
                    status,
                    created_by,
                    created_at
                )
                VALUES
                (
                    :brand_code,
                    :brand_name,
                    :description,
                    :status,
                    :created_by,
                    :created_at
                )
            ");

            $stmt->execute([
                ':brand_code' => $brandCode,
                ':brand_name' => $brandName,
                ':description' => $description,
                ':status' => $status,
                ':created_by' => $createdBy,
                ':created_at' => $createdAt
            ]);

            header(
                'Location: manage_brand.php?success=' .
                urlencode("Brand {$brandCode} created successfully.")
            );

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT BRAND
    |--------------------------------------------------------------------------
    */

    if ($action === 'edit') {

        $id = (int)($_POST['id'] ?? 0);

        $brandName = trim($_POST['brand_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $status = isset($_POST['status']) ? 1 : 0;

        $validationAction = 'edit';
        $validationId = $id;


        /*
        |--------------------------------------------------------------------------
        | Validate ID
        |--------------------------------------------------------------------------
        */

        if ($id <= 0) {

            $errors[] = 'Invalid brand record.';

        } else {


            /*
            |--------------------------------------------------------------------------
            | Brand Name Validation
            |--------------------------------------------------------------------------
            */

            if ($brandName === '') {

                $brandNameError = 'Brand Name is required.';

            } elseif (mb_strlen($brandName) < 2) {

                $brandNameError = 'Brand Name must be at least 2 characters.';

            } elseif (mb_strlen($brandName) > 100) {

                $brandNameError = 'Brand Name cannot exceed 100 characters.';

            }


            /*
            |--------------------------------------------------------------------------
            | Description Validation
            |--------------------------------------------------------------------------
            */

            if (mb_strlen($description) > 200) {

                $descriptionError = 'Description cannot exceed 200 characters.';

            }


            /*
            |--------------------------------------------------------------------------
            | Duplicate Brand Name
            |--------------------------------------------------------------------------
            */

            if ($brandNameError === '') {

                $stmt = $conn->prepare("
                    SELECT COUNT(*)
                    FROM brand_master
                    WHERE LOWER(brand_name) = LOWER(:brand_name)
                    AND id != :id
                ");

                $stmt->execute([
                    ':brand_name' => $brandName,
                    ':id' => $id
                ]);

                if ((int)$stmt->fetchColumn() > 0) {

                    $duplicateNameError = 'Brand Name already exists.';

                }
            }


            /*
            |--------------------------------------------------------------------------
            | Collect Errors
            |--------------------------------------------------------------------------
            */

            if ($brandNameError !== '') {
                $errors[] = $brandNameError;
            }

            if ($descriptionError !== '') {
                $errors[] = $descriptionError;
            }

            if ($duplicateNameError !== '') {
                $errors[] = $duplicateNameError;
            }


            /*
            |--------------------------------------------------------------------------
            | Update Brand
            |--------------------------------------------------------------------------
            */

            if (empty($errors)) {

                $updatedBy = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
                $updatedAt = date('Y-m-d H:i:s');

                $stmt = $conn->prepare("
                    UPDATE brand_master
                    SET
                        brand_name = :brand_name,
                        description = :description,
                        status = :status,
                        updated_by = :updated_by,
                        updated_at = :updated_at
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':brand_name' => $brandName,
                    ':description' => $description,
                    ':status' => $status,
                    ':updated_by' => $updatedBy,
                    ':updated_at' => $updatedAt,
                    ':id' => $id
                ]);

                header(
                    'Location: manage_brand.php?success=' .
                    urlencode('Brand updated successfully.')
                );

                exit;
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Get Brands
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
    WHERE 1=1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            bm.brand_code LIKE :search
            OR bm.brand_name LIKE :search
        )
    ";

    $params[':search'] = "%{$search}%";
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($statusFilter !== '' && in_array($statusFilter, ['0', '1'], true)) {

    $sql .= " AND bm.status = :status";

    $params[':status'] = (int)$statusFilter;
}


$sql .= " ORDER BY bm.id DESC";

$stmt = $conn->prepare($sql);

$stmt->execute($params);

$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__ . '/includes/header.php';

?>

<main class="main-content">

    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Breadcrumb Navigation -->
        <?php
        renderBreadcrumbs([
            ['title' => '', 'icon' => 'bi bi-house-door', 'link' => 'index.php'],
            ['title' => 'Masters', 'link' => 'masters.php'],
            ['title' => 'Brand Master']
        ]);
        ?>

        <!-- PAGE HEADING -->

        <div class="page-heading d-flex justify-content-between align-items-center mb-4">

            <div>

                <h1 class="page-title mb-1">
                    Brand Master
                </h1>
            </div>


            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#createBrandModal"
            >

                <i class="bi bi-plus-lg me-1"></i>

                Create Brand

            </button>

        </div>


        <!-- SUCCESS MESSAGE -->

        <?php if (!empty($_GET['success'])): ?>

            <div class="alert alert-success alert-dismissible fade show auto-hide-alert">

                <span>

                    <i class="bi bi-check-circle me-2"></i>

                    <?= htmlspecialchars($_GET['success']) ?>

                </span>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

                <div class="alert-time-line"></div>

            </div>

        <?php endif; ?>


        <!-- SEARCH / FILTER -->

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <form method="GET" id="filterForm">

                    <div class="row g-2 align-items-end">


                        <div class="col-12 col-md-8">

                            <label class="form-label">
                                Search
                            </label>

                            <input
                                type="text"
                                name="search"
                                id="brandSearch"
                                class="form-control"
                                placeholder="Search ID or brand name"
                                value="<?= htmlspecialchars($search) ?>"
                                autocomplete="off"
                            >

                        </div>


                        <?php if ($search !== '' || $statusFilter !== ''): ?>

                            <div class="col-6 col-md-2">

                                <a
                                    href="manage_brand.php"
                                    class="btn btn-outline-secondary w-100"
                                >

                                    <i class="bi bi-x-circle me-1"></i>

                                    Clear

                                </a>

                            </div>

                        <?php endif; ?>


                        <div class="<?= ($search !== '' || $statusFilter !== '') ? 'col-6' : 'col-12' ?> col-md-2">

                            <button
                                type="button"
                                class="btn btn-outline-primary w-100"
                                data-bs-toggle="collapse"
                                data-bs-target="#filterSection"
                                aria-expanded="false"
                            >

                                <i class="bi bi-funnel me-1"></i>

                                Filter

                                <i
                                    class="bi bi-chevron-down ms-1"
                                    id="filterIcon"
                                ></i>

                            </button>

                        </div>

                    </div>


                    <!-- FILTER SECTION -->

                    <div
                        class="collapse mt-3"
                        id="filterSection"
                    >

                        <div class="filter-section-box">

                            <div class="row g-3">

                                <div class="col-12 col-md-4">

                                    <label class="form-label">
                                        Status
                                    </label>

                                    <select
                                        name="status"
                                        id="statusFilter"
                                        class="form-select"
                                    >

                                        <option value="">
                                            All Status
                                        </option>

                                        <option
                                            value="1"
                                            <?= $statusFilter === '1' ? 'selected' : '' ?>
                                        >
                                            Active
                                        </option>

                                        <option
                                            value="0"
                                            <?= $statusFilter === '0' ? 'selected' : '' ?>
                                        >
                                            Inactive
                                        </option>

                                    </select>

                                </div>

                            </div>

                        </div>

                    </div>

                </form>

            </div>

        </div>


        <!-- BRAND TABLE -->

        <div class="card shadow-sm">

            <div class="card-body">

                <div class="category-table-wrapper">

                    <div class="category-table-scroll">

                        <table class="table category-table align-middle mb-0">

                            <thead>

                                <tr>

                                    <th>Brand ID</th>

                                    <th>Brand Name</th>

                                    <th>Description</th>

                                    <th>Created By</th>

                                    <th>Created At</th>

                                    <th>Updated By</th>

                                    <th>Updated At</th>

                                    <th class="action-column text-end">
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php if (empty($brands)): ?>

                                    <tr>

                                        <td
                                            colspan="8"
                                            class="text-center py-5 text-muted"
                                        >

                                            <i
                                                class="bi bi-tags fs-2 d-block mb-2"
                                            ></i>

                                            No brands found.

                                        </td>

                                    </tr>

                                <?php else: ?>


                                    <?php foreach ($brands as $row): ?>


                                        <tr>


                                            <!-- BRAND ID -->

                                            <td>

                                                <span
                                                    class="status-dot <?= ((int)$row['status'] === 1) ? 'active' : 'inactive' ?>"
                                                    title="<?= ((int)$row['status'] === 1) ? 'Active' : 'Inactive' ?>"
                                                ></span>

                                                <strong>
                                                    <?= htmlspecialchars($row['brand_code']) ?>
                                                </strong>

                                            </td>


                                            <!-- BRAND NAME -->

                                            <td>

                                                <?= htmlspecialchars($row['brand_name']) ?>

                                            </td>


                                            <!-- DESCRIPTION -->

                                            <td class="text-muted">

                                                <?= !empty($row['description'])
                                                    ? htmlspecialchars($row['description'])
                                                    : '-'
                                                ?>

                                            </td>


                                            <!-- CREATED BY -->

                                            <td>

                                                <?= htmlspecialchars($row['created_by_name'] ?? $row['created_by'] ?? '-') ?>

                                            </td>


                                            <!-- CREATED AT -->

                                            <td>

                                                <?= htmlspecialchars($row['created_at']) ?>

                                            </td>


                                            <!-- UPDATED BY -->

                                            <td>

                                                <?= !empty($row['updated_by_name'])
                                                    ? htmlspecialchars($row['updated_by_name'])
                                                    : (!empty($row['updated_by']) ? htmlspecialchars($row['updated_by']) : '-')
                                                ?>

                                            </td>


                                            <!-- UPDATED AT -->

                                            <td>

                                                <?= !empty($row['updated_at'])
                                                    ? htmlspecialchars($row['updated_at'])
                                                    : '-'
                                                ?>

                                            </td>


                                            <!-- ACTION -->

                                            <td class="action-column text-end text-nowrap">


                                                <a
                                                    href="view_brand.php?id=<?= (int)$row['id'] ?>"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    title="View"
                                                >

                                                    <i class="bi bi-eye"></i>

                                                </a>


                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Edit"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editBrandModal<?= (int)$row['id'] ?>"
                                                >

                                                    <i class="bi bi-pencil"></i>

                                                </button>


                                            </td>

                                        </tr>


                                        <?php

                                        $isEditValidation =
                                            (
                                                $validationAction === 'edit'
                                                &&
                                                $validationId === (int)$row['id']
                                            );

                                        $editBrandName =
                                            $isEditValidation
                                                ? ($_POST['brand_name'] ?? $row['brand_name'])
                                                : $row['brand_name'];

                                        $editDescription =
                                            $isEditValidation
                                                ? ($_POST['description'] ?? $row['description'])
                                                : $row['description'];

                                        $editStatus =
                                            $isEditValidation
                                                ? isset($_POST['status'])
                                                : ((int)$row['status'] === 1);

                                        ?>


                                        <!-- EDIT MODAL -->

                                        <div
                                            class="modal fade"
                                            id="editBrandModal<?= (int)$row['id'] ?>"
                                            tabindex="-1"
                                            aria-hidden="true"
                                        >

                                            <div class="modal-dialog modal-lg modal-dialog-centered">

                                                <div class="modal-content">


                                                    <div class="modal-header">

                                                        <div>

                                                            <h5 class="modal-title">
                                                                Edit Brand
                                                            </h5>

                                                            <small class="text-muted">

                                                                <?= htmlspecialchars($row['brand_code']) ?>

                                                            </small>

                                                        </div>

                                                        <button
                                                            type="button"
                                                            class="btn-close"
                                                            data-bs-dismiss="modal"
                                                        ></button>

                                                    </div>


                                                    <form method="POST">

                                                        <div class="modal-body">

                                                            <input
                                                                type="hidden"
                                                                name="form_action"
                                                                value="edit"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="id"
                                                                value="<?= (int)$row['id'] ?>"
                                                            >


                                                            <div class="row g-3">


                                                                <!-- BRAND ID -->

                                                                <div class="col-12 col-md-6">

                                                                    <label class="form-label">
                                                                        Brand ID
                                                                    </label>

                                                                    <input
                                                                        type="text"
                                                                        class="form-control"
                                                                        value="<?= htmlspecialchars($row['brand_code']) ?>"
                                                                        readonly
                                                                    >

                                                                </div>


                                                                <!-- STATUS -->

                                                                <div class="col-12 col-md-6">

                                                                    <label class="form-label">
                                                                        Status
                                                                    </label>

                                                                    <div class="form-check form-switch mt-2">

                                                                        <input
                                                                            class="form-check-input"
                                                                            type="checkbox"
                                                                            name="status"
                                                                            id="editStatus<?= (int)$row['id'] ?>"
                                                                            <?= $editStatus ? 'checked' : '' ?>
                                                                        >

                                                                        <label
                                                                            class="form-check-label"
                                                                            for="editStatus<?= (int)$row['id'] ?>"
                                                                            id="editStatusLabel<?= (int)$row['id'] ?>"
                                                                        >

                                                                            <?= $editStatus ? 'Active' : 'Inactive' ?>

                                                                        </label>

                                                                    </div>

                                                                </div>


                                                                <!-- BRAND NAME -->

                                                                <div class="col-12">

                                                                    <label class="form-label">

                                                                        Brand Name

                                                                        <span class="text-danger">
                                                                            *
                                                                        </span>

                                                                    </label>


                                                                    <input
                                                                        type="text"
                                                                        name="brand_name"
                                                                        class="form-control <?= ($isEditValidation && ($brandNameError !== '' || $duplicateNameError !== '')) ? 'is-invalid' : '' ?>"
                                                                        value="<?= htmlspecialchars($editBrandName) ?>"
                                                                        placeholder="Enter brand name"
                                                                    >


                                                                    <?php if ($isEditValidation && $brandNameError !== ''): ?>

                                                                        <div class="validation-message">

                                                                            <i class="bi bi-exclamation-circle me-1"></i>

                                                                            <?= htmlspecialchars($brandNameError) ?>

                                                                        </div>

                                                                    <?php elseif ($isEditValidation && $duplicateNameError !== ''): ?>

                                                                        <div class="validation-message">

                                                                            <i class="bi bi-exclamation-circle me-1"></i>

                                                                            <?= htmlspecialchars($duplicateNameError) ?>

                                                                        </div>

                                                                    <?php endif; ?>

                                                                </div>


                                                                <!-- DESCRIPTION -->

                                                                <div class="col-12">

                                                                    <div class="d-flex justify-content-between">

                                                                        <label class="form-label">
                                                                            Description
                                                                        </label>

                                                                        <small
                                                                            class="text-muted"
                                                                            id="editDescriptionCount<?= (int)$row['id'] ?>"
                                                                        >

                                                                            <?= strlen($editDescription ?? '') ?> / 200

                                                                        </small>

                                                                    </div>


                                                                    <textarea
                                                                        name="description"
                                                                        id="editDescription<?= (int)$row['id'] ?>"
                                                                        class="form-control <?= ($isEditValidation && $descriptionError !== '') ? 'is-invalid' : '' ?>"
                                                                        rows="4"
                                                                        maxlength="200"
                                                                        placeholder="Enter brand description"
                                                                    ><?= htmlspecialchars($editDescription ?? '') ?></textarea>


                                                                    <?php if ($isEditValidation && $descriptionError !== ''): ?>

                                                                        <div class="validation-message">

                                                                            <i class="bi bi-exclamation-circle me-1"></i>

                                                                            <?= htmlspecialchars($descriptionError) ?>

                                                                        </div>

                                                                    <?php endif; ?>

                                                                </div>


                                                                <!-- AUDIT -->

                                                                <div class="col-12">

                                                                    <hr>

                                                                    <div class="row g-3">


                                                                        <div class="col-12 col-md-6">

                                                                            <label class="form-label text-muted">
                                                                                Created By
                                                                            </label>

                                                                            <input
                                                                                type="text"
                                                                                class="form-control"
                                                                                value="<?= htmlspecialchars($row['created_by_name'] ?? $row['created_by'] ?? '') ?>"
                                                                                readonly
                                                                            >

                                                                        </div>


                                                                        <div class="col-12 col-md-6">

                                                                            <label class="form-label text-muted">
                                                                                Created At
                                                                            </label>

                                                                            <input
                                                                                type="text"
                                                                                class="form-control"
                                                                                value="<?= htmlspecialchars($row['created_at']) ?>"
                                                                                readonly
                                                                            >

                                                                        </div>


                                                                    </div>

                                                                </div>


                                                            </div>

                                                        </div>


                                                        <div class="modal-footer">

                                                            <button
                                                                type="button"
                                                                class="btn btn-outline-secondary"
                                                                data-bs-dismiss="modal"
                                                            >
                                                                Back
                                                            </button>

                                                            <button
                                                                type="submit"
                                                                class="btn btn-primary"
                                                            >

                                                                <i class="bi bi-check-lg me-1"></i>

                                                                Update

                                                            </button>

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

    </div>

</main>


<!-- ========================================================= -->
<!-- CREATE BRAND MODAL -->
<!-- ========================================================= -->

<div
    class="modal fade"
    id="createBrandModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <form method="POST">

                <div class="modal-header d-flex justify-content-between align-items-center flex-wrap gap-2">

                    <div>

                        <h5 class="modal-title mb-0 fw-bold">
                            Create Brand
                        </h5>

                        <small class="text-muted">
                            Add a new product brand
                        </small>

                    </div>

                    <div class="d-flex align-items-center gap-3 ms-auto">
                        <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="status"
                                id="createStatus"
                                checked
                            >
                            <label
                                class="form-check-label mb-0 fw-semibold"
                                for="createStatus"
                                id="createStatusLabel"
                            >
                                Active
                            </label>
                        </div>
                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                        ></button>
                    </div>

                </div>

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="form_action"
                        value="create"
                    >


                    <div class="row g-3">


                        <!-- BRAND NAME -->

                        <div class="col-12">

                            <label class="form-label">

                                Brand Name

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                name="brand_name"
                                class="form-control <?= ($validationAction === 'create' && ($brandNameError !== '' || $duplicateNameError !== '')) ? 'is-invalid' : '' ?>"
                                value="<?= $validationAction === 'create'
                                    ? htmlspecialchars($_POST['brand_name'] ?? '')
                                    : '' ?>"
                                placeholder="Enter brand name"
                            >


                            <?php if ($validationAction === 'create' && $brandNameError !== ''): ?>

                                <div class="validation-message">

                                    <i class="bi bi-exclamation-circle me-1"></i>

                                    <?= htmlspecialchars($brandNameError) ?>

                                </div>

                            <?php elseif ($validationAction === 'create' && $duplicateNameError !== ''): ?>

                                <div class="validation-message">

                                    <i class="bi bi-exclamation-circle me-1"></i>

                                    <?= htmlspecialchars($duplicateNameError) ?>

                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- DESCRIPTION -->

                        <div class="col-12">

                            <div class="d-flex justify-content-between">

                                <label class="form-label">
                                    Description
                                </label>

                                <small
                                    class="text-muted"
                                    id="createDescriptionCount"
                                >
                                    <?= $validationAction === 'create'
                                        ? strlen($_POST['description'] ?? '')
                                        : 0
                                    ?>
                                    / 200
                                </small>

                            </div>


                            <textarea
                                name="description"
                                id="createDescription"
                                class="form-control <?= ($validationAction === 'create' && $descriptionError !== '') ? 'is-invalid' : '' ?>"
                                rows="4"
                                maxlength="200"
                                placeholder="Enter brand description"
                            ><?= $validationAction === 'create'
                                ? htmlspecialchars($_POST['description'] ?? '')
                                : ''
                            ?></textarea>


                            <?php if ($validationAction === 'create' && $descriptionError !== ''): ?>

                                <div class="validation-message">

                                    <i class="bi bi-exclamation-circle me-1"></i>

                                    <?= htmlspecialchars($descriptionError) ?>

                                </div>

                            <?php endif; ?>

                        </div>


                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"
                    >
                        Back
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-check-lg me-1"></i>

                        Save

                    </button>

                </div>


            </form>

        </div>

    </div>

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {


    /*
    |--------------------------------------------------------------------------
    | Auto Hide Success Message
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll('.auto-hide-alert').forEach(function (alert) {

        setTimeout(function () {

            alert.classList.remove('show');

            setTimeout(function () {

                alert.remove();

            }, 300);

        }, 2000);

    });


    /*
    |--------------------------------------------------------------------------
    | Automatic Search
    |--------------------------------------------------------------------------
    */

    const searchInput = document.getElementById('brandSearch');

    const filterForm = document.getElementById('filterForm');

    let searchTimer;


    if (searchInput && filterForm) {

        searchInput.addEventListener('input', function () {

            clearTimeout(searchTimer);

            searchTimer = setTimeout(function () {

                filterForm.submit();

            }, 500);

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Status Filter
    |--------------------------------------------------------------------------
    */

    const statusFilter =
        document.getElementById('statusFilter');


    if (statusFilter && filterForm) {

        statusFilter.addEventListener('change', function () {

            filterForm.submit();

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Filter Icon
    |--------------------------------------------------------------------------
    */

    const filterSection =
        document.getElementById('filterSection');

    const filterIcon =
        document.getElementById('filterIcon');


    if (filterSection && filterIcon) {

        filterSection.addEventListener(
            'show.bs.collapse',
            function () {

                filterIcon.classList.remove(
                    'bi-chevron-down'
                );

                filterIcon.classList.add(
                    'bi-chevron-up'
                );

            }
        );


        filterSection.addEventListener(
            'hide.bs.collapse',
            function () {

                filterIcon.classList.remove(
                    'bi-chevron-up'
                );

                filterIcon.classList.add(
                    'bi-chevron-down'
                );

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Create Status
    |--------------------------------------------------------------------------
    */

    const createStatus =
        document.getElementById('createStatus');

    const createStatusLabel =
        document.getElementById('createStatusLabel');


    if (createStatus && createStatusLabel) {

        createStatus.addEventListener('change', function () {

            createStatusLabel.textContent =
                this.checked
                    ? 'Active'
                    : 'Inactive';

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Edit Status
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            'input[type="checkbox"][id^="editStatus"]'
        )
        .forEach(function (checkbox) {

            const id =
                checkbox.id.replace(
                    'editStatus',
                    ''
                );

            const label =
                document.getElementById(
                    'editStatusLabel' + id
                );


            if (label) {

                checkbox.addEventListener(
                    'change',
                    function () {

                        label.textContent =
                            this.checked
                                ? 'Active'
                                : 'Inactive';

                    }
                );

            }

        });


    /*
    |--------------------------------------------------------------------------
    | Create Description Counter
    |--------------------------------------------------------------------------
    */

    const createDescription =
        document.getElementById(
            'createDescription'
        );

    const createDescriptionCount =
        document.getElementById(
            'createDescriptionCount'
        );


    if (
        createDescription &&
        createDescriptionCount
    ) {

        createDescription.addEventListener(
            'input',
            function () {

                createDescriptionCount.textContent =
                    this.value.length + ' / 200';

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Edit Description Counters
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            'textarea[id^="editDescription"]'
        )
        .forEach(function (textarea) {

            const id =
                textarea.id.replace(
                    'editDescription',
                    ''
                );

            const counter =
                document.getElementById(
                    'editDescriptionCount' + id
                );


            if (counter) {

                textarea.addEventListener(
                    'input',
                    function () {

                        counter.textContent =
                            this.value.length +
                            ' / 200';

                    }
                );

            }

        });


    /*
    |--------------------------------------------------------------------------
    | Reopen Modal After Validation
    |--------------------------------------------------------------------------
    */

    const validationAction =
        <?= json_encode($validationAction) ?>;

    const validationId =
        <?= (int)$validationId ?>;


    if (validationAction === 'create') {

        const modal =
            document.getElementById(
                'createBrandModal'
            );


        if (modal) {

            bootstrap.Modal
                .getOrCreateInstance(modal)
                .show();

        }

    }


    if (
        validationAction === 'edit' &&
        validationId > 0
    ) {

        const modal =
            document.getElementById(
                'editBrandModal' + validationId
            );


        if (modal) {

            bootstrap.Modal
                .getOrCreateInstance(modal)
                .show();

        }

    }

});

</script>


<?php

require_once __DIR__ . '/includes/footer.php';

?>