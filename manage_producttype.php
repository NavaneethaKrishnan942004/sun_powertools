<?php

$pageTitle = 'Product Type Master';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$conn = $pdo;


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$errors = [];

$productTypeNameError = '';
$descriptionError = '';
$duplicateNameError = '';

$validationAction = '';
$validationId = 0;

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';


/*
|--------------------------------------------------------------------------
| Generate Product Type Code
|--------------------------------------------------------------------------
| Format: PROTY-001
|--------------------------------------------------------------------------
*/

function generateProductTypeCode($conn)
{
    $stmt = $conn->prepare("
        SELECT product_type_code
        FROM product_type_master
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute();

    $lastCode = $stmt->fetchColumn();

    if (!$lastCode) {
        return 'PROTY-001';
    }

    $number = (int) str_replace('PROTY-', '', $lastCode) + 1;

    return 'PROTY-' . str_pad($number, 3, '0', STR_PAD_LEFT);
}


/*
|--------------------------------------------------------------------------
| POST PROCESSING
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['form_action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | CREATE PRODUCT TYPE
    |--------------------------------------------------------------------------
    */

    if ($action === 'create') {

        $productTypeName = trim($_POST['product_type_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // Checkbox checked = Active
        // Checkbox unchecked = Inactive
        $status = isset($_POST['status']) ? 1 : 0;

        $validationAction = 'create';


        /*
        |--------------------------------------------------------------------------
        | Product Type Name Validation
        |--------------------------------------------------------------------------
        */

        if ($productTypeName === '') {

            $productTypeNameError =
                'Product Type Name is required.';

        } elseif (mb_strlen($productTypeName) < 2) {

            $productTypeNameError =
                'Product Type Name must be at least 2 characters.';

        } elseif (mb_strlen($productTypeName) > 100) {

            $productTypeNameError =
                'Product Type Name cannot exceed 100 characters.';

        }


        /*
        |--------------------------------------------------------------------------
        | Description Validation
        |--------------------------------------------------------------------------
        */

        if (mb_strlen($description) > 200) {

            $descriptionError =
                'Description cannot exceed 200 characters.';

        }


        /*
        |--------------------------------------------------------------------------
        | Duplicate Product Type Name
        |--------------------------------------------------------------------------
        */

        if ($productTypeNameError === '') {

            $stmt = $conn->prepare("
                SELECT COUNT(*)
                FROM product_type_master
                WHERE LOWER(product_type_name) =
                      LOWER(:product_type_name)
            ");

            $stmt->execute([
                ':product_type_name' => $productTypeName
            ]);

            if ((int)$stmt->fetchColumn() > 0) {

                $duplicateNameError =
                    'Product Type Name already exists.';

            }
        }


        /*
        |--------------------------------------------------------------------------
        | Collect Errors
        |--------------------------------------------------------------------------
        */

        if ($productTypeNameError !== '') {
            $errors[] = $productTypeNameError;
        }

        if ($descriptionError !== '') {
            $errors[] = $descriptionError;
        }

        if ($duplicateNameError !== '') {
            $errors[] = $duplicateNameError;
        }


        /*
        |--------------------------------------------------------------------------
        | INSERT
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            $productTypeCode =
                generateProductTypeCode($conn);

            $createdBy = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

            $createdAt = date('Y-m-d H:i:s');


            $stmt = $conn->prepare("
                INSERT INTO product_type_master
                (
                    product_type_code,
                    product_type_name,
                    description,
                    status,
                    created_by,
                    created_at
                )
                VALUES
                (
                    :product_type_code,
                    :product_type_name,
                    :description,
                    :status,
                    :created_by,
                    :created_at
                )
            ");


            $stmt->execute([
                ':product_type_code' => $productTypeCode,
                ':product_type_name' => $productTypeName,
                ':description' => $description,
                ':status' => $status,
                ':created_by' => $createdBy,
                ':created_at' => $createdAt
            ]);


            header(
                'Location: manage_producttype.php?success=' .
                urlencode(
                    "Product Type {$productTypeCode} created successfully."
                )
            );

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT PRODUCT TYPE
    |--------------------------------------------------------------------------
    */

    if ($action === 'edit') {

        $id = (int)($_POST['id'] ?? 0);

        $productTypeName =
            trim($_POST['product_type_name'] ?? '');

        $description =
            trim($_POST['description'] ?? '');

        $status =
            isset($_POST['status']) ? 1 : 0;

        $validationAction = 'edit';
        $validationId = $id;


        /*
        |--------------------------------------------------------------------------
        | Validate ID
        |--------------------------------------------------------------------------
        */

        if ($id <= 0) {

            $errors[] =
                'Invalid product type record.';

        } else {


            /*
            |--------------------------------------------------------------------------
            | Product Type Name Validation
            |--------------------------------------------------------------------------
            */

            if ($productTypeName === '') {

                $productTypeNameError =
                    'Product Type Name is required.';

            } elseif (mb_strlen($productTypeName) < 2) {

                $productTypeNameError =
                    'Product Type Name must be at least 2 characters.';

            } elseif (mb_strlen($productTypeName) > 100) {

                $productTypeNameError =
                    'Product Type Name cannot exceed 100 characters.';

            }


            /*
            |--------------------------------------------------------------------------
            | Description Validation
            |--------------------------------------------------------------------------
            */

            if (mb_strlen($description) > 200) {

                $descriptionError =
                    'Description cannot exceed 200 characters.';

            }


            /*
            |--------------------------------------------------------------------------
            | Duplicate Product Type Name
            |--------------------------------------------------------------------------
            */

            if ($productTypeNameError === '') {

                $stmt = $conn->prepare("
                    SELECT COUNT(*)
                    FROM product_type_master
                    WHERE LOWER(product_type_name) =
                          LOWER(:product_type_name)
                    AND id != :id
                ");

                $stmt->execute([
                    ':product_type_name' => $productTypeName,
                    ':id' => $id
                ]);


                if ((int)$stmt->fetchColumn() > 0) {

                    $duplicateNameError =
                        'Product Type Name already exists.';

                }
            }


            /*
            |--------------------------------------------------------------------------
            | Collect Errors
            |--------------------------------------------------------------------------
            */

            if ($productTypeNameError !== '') {
                $errors[] = $productTypeNameError;
            }

            if ($descriptionError !== '') {
                $errors[] = $descriptionError;
            }

            if ($duplicateNameError !== '') {
                $errors[] = $duplicateNameError;
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            if (empty($errors)) {

                $updatedBy = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

                $updatedAt = date('Y-m-d H:i:s');


                $stmt = $conn->prepare("
                    UPDATE product_type_master
                    SET
                        product_type_name = :product_type_name,
                        description = :description,
                        status = :status,
                        updated_by = :updated_by,
                        updated_at = :updated_at
                    WHERE id = :id
                ");


                $stmt->execute([
                    ':product_type_name' => $productTypeName,
                    ':description' => $description,
                    ':status' => $status,
                    ':updated_by' => $updatedBy,
                    ':updated_at' => $updatedAt,
                    ':id' => $id
                ]);


                header(
                    'Location: manage_producttype.php?success=' .
                    urlencode(
                        'Product Type updated successfully.'
                    )
                );

                exit;
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET PRODUCT TYPES
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
        AND
        (
            ptm.product_type_code LIKE :search
            OR ptm.product_type_name LIKE :search
        )
    ";

    $params[':search'] =
        "%{$search}%";
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if (
    $statusFilter !== ''
    &&
    in_array($statusFilter, ['0', '1'], true)
) {

    $sql .= " AND ptm.status = :status";

    $params[':status'] = (int)$statusFilter;
}


/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY id DESC
";


$stmt = $conn->prepare($sql);

$stmt->execute($params);

$productTypes =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__ . '/includes/header.php';

?>

<main class="main-content">


    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Breadcrumb Navigation -->
        <?php
        renderBreadcrumbs([
            ['title' => '', 'icon' => 'bi bi-house-door', 'link' => 'index.php'],
            ['title' => 'Masters', 'link' => 'masters.php'],
            ['title' => 'Product Type Master']
        ]);
        ?>


        <!-- ===================================================== -->
        <!-- PAGE HEADING -->
        <!-- ===================================================== -->

        <div
            class="page-heading d-flex justify-content-between align-items-center mb-4"
        >


            <div>

                <h1 class="page-title mb-1">
                    Product Type Master
                </h1>
            </div>


            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#createProductTypeModal"
            >

                <i class="bi bi-plus-lg me-1"></i>

                Create Product Type

            </button>


        </div>


        <!-- ===================================================== -->
        <!-- SUCCESS MESSAGE -->
        <!-- ===================================================== -->

        <?php if (!empty($_GET['success'])): ?>

            <div
                class="alert alert-success alert-dismissible fade show auto-hide-alert"
            >

                <span>

                    <i class="bi bi-check-circle me-2"></i>

                    <?= htmlspecialchars(
                        $_GET['success']
                    ) ?>

                </span>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>


                <div class="alert-time-line"></div>

            </div>

        <?php endif; ?>


        <!-- ===================================================== -->
        <!-- SEARCH / FILTER -->
        <!-- ===================================================== -->

        <div class="card shadow-sm mb-4">

            <div class="card-body">


                <form
                    method="GET"
                    id="filterForm"
                >


                    <div
                        class="row g-2 align-items-end"
                    >


                        <!-- SEARCH -->

                        <div class="col-12 col-md-8">

                            <label class="form-label">
                                Search
                            </label>


                            <input
                                type="text"
                                name="search"
                                id="productTypeSearch"
                                class="form-control"
                                placeholder="Search ID or product type name"
                                value="<?= htmlspecialchars(
                                    $search
                                ) ?>"
                                autocomplete="off"
                            >

                        </div>


                        <!-- CLEAR -->

                        <?php if (
                            $search !== ''
                            ||
                            $statusFilter !== ''
                        ): ?>

                            <div class="col-6 col-md-2">

                                <a
                                    href="manage_producttype.php"
                                    class="btn btn-outline-secondary w-100"
                                >

                                    <i class="bi bi-x-circle me-1"></i>

                                    Clear

                                </a>

                            </div>

                        <?php endif; ?>


                        <!-- FILTER -->

                        <div
                            class="<?= (
                                $search !== ''
                                ||
                                $statusFilter !== ''
                            )
                                ? 'col-6'
                                : 'col-12'
                            ?> col-md-2"
                        >


                            <button
                                type="button"
                                class="btn btn-outline-primary w-100"
                                data-bs-toggle="collapse"
                                data-bs-target="#filterSection"
                                aria-expanded="false"
                                aria-controls="filterSection"
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


                                <div
                                    class="col-12 col-md-4"
                                >


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
                                            <?= $statusFilter === '1'
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >
                                            Active
                                        </option>


                                        <option
                                            value="0"
                                            <?= $statusFilter === '0'
                                                ? 'selected'
                                                : ''
                                            ?>
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


        <!-- ===================================================== -->
        <!-- PRODUCT TYPE TABLE -->
        <!-- ===================================================== -->

        <div class="card shadow-sm">


            <div class="card-body">


                <div class="category-table-wrapper">


                    <div class="category-table-scroll">


                        <table
                            class="table category-table align-middle mb-0"
                        >


                            <thead>

                                <tr>

                                    <th>
                                        Product Type ID
                                    </th>

                                    <th>
                                        Product Type Name
                                    </th>

                                    <th>
                                        Description
                                    </th>

                                    <th>
                                        Created By
                                    </th>

                                    <th>
                                        Created At
                                    </th>

                                    <th>
                                        Updated By
                                    </th>

                                    <th>
                                        Updated At
                                    </th>

                                    <th
                                        class="action-column text-end"
                                    >
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php if (
                                    empty($productTypes)
                                ): ?>


                                    <tr>

                                        <td
                                            colspan="8"
                                            class="text-center py-5 text-muted"
                                        >

                                            <i
                                                class="bi bi-box-seam fs-2 d-block mb-2"
                                            ></i>

                                            No product types found.

                                        </td>

                                    </tr>


                                <?php else: ?>


                                    <?php foreach (
                                        $productTypes
                                        as $row
                                    ): ?>


                                        <tr>


                                            <!-- PRODUCT TYPE ID -->

                                            <td>

                                                <span
                                                    class="status-dot <?= (
                                                        (int)$row['status'] === 1
                                                    )
                                                        ? 'active'
                                                        : 'inactive'
                                                    ?>"
                                                    title="<?= (
                                                        (int)$row['status'] === 1
                                                    )
                                                        ? 'Active'
                                                        : 'Inactive'
                                                    ?>"
                                                ></span>


                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $row['product_type_code']
                                                    ) ?>

                                                </strong>

                                            </td>


                                            <!-- NAME -->

                                            <td>

                                                <?= htmlspecialchars(
                                                    $row['product_type_name']
                                                ) ?>

                                            </td>


                                            <!-- DESCRIPTION -->

                                            <td class="text-muted">

                                                <?= !empty(
                                                    $row['description']
                                                )
                                                    ? htmlspecialchars(
                                                        $row['description']
                                                    )
                                                    : '-'
                                                ?>

                                            </td>


                                            <!-- CREATED BY -->

                                            <td>

                                                <?= htmlspecialchars(
                                                    $row['created_by_name'] ?? $row['created_by'] ?? '-'
                                                ) ?>

                                            </td>


                                            <!-- CREATED AT -->

                                            <td>

                                                <?= htmlspecialchars(
                                                    $row['created_at']
                                                ) ?>

                                            </td>


                                            <!-- UPDATED BY -->

                                            <td>

                                                <?= !empty(
                                                    $row['updated_by_name']
                                                )
                                                    ? htmlspecialchars(
                                                        $row['updated_by_name']
                                                    )
                                                    : (!empty($row['updated_by']) ? htmlspecialchars($row['updated_by']) : '-')
                                                ?>

                                            </td>


                                            <!-- UPDATED AT -->

                                            <td>

                                                <?= !empty(
                                                    $row['updated_at']
                                                )
                                                    ? htmlspecialchars(
                                                        $row['updated_at']
                                                    )
                                                    : '-'
                                                ?>

                                            </td>


                                            <!-- ACTION -->

                                            <td
                                                class="action-column text-end text-nowrap"
                                            >


                                                <!-- VIEW -->

                                                <a
                                                    href="view_product_type.php?id=<?= (int)$row['id'] ?>"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    title="View"
                                                >

                                                    <i class="bi bi-eye"></i>

                                                </a>


                                                <!-- EDIT -->

                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Edit"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editProductTypeModal<?= (int)$row['id'] ?>"
                                                >

                                                    <i class="bi bi-pencil"></i>

                                                </button>


                                            </td>


                                        </tr>


                                        <?php

                                        /*
                                        |--------------------------------------------------------------------------
                                        | Edit Validation Values
                                        |--------------------------------------------------------------------------
                                        */

                                        $isEditValidation =
                                            (
                                                $validationAction === 'edit'
                                                &&
                                                $validationId === (int)$row['id']
                                            );


                                        $editProductTypeName =
                                            $isEditValidation
                                                ? (
                                                    $_POST['product_type_name']
                                                    ??
                                                    $row['product_type_name']
                                                )
                                                : $row['product_type_name'];


                                        $editDescription =
                                            $isEditValidation
                                                ? (
                                                    $_POST['description']
                                                    ??
                                                    $row['description']
                                                )
                                                : $row['description'];


                                        $editStatus =
                                            $isEditValidation
                                                ? isset($_POST['status'])
                                                : (
                                                    (int)$row['status'] === 1
                                                );

                                        ?>


                                        <!-- ================================================= -->
                                        <!-- EDIT MODAL -->
                                        <!-- ================================================= -->

                                        <div
                                            class="modal fade"
                                            id="editProductTypeModal<?= (int)$row['id'] ?>"
                                            tabindex="-1"
                                            aria-hidden="true"
                                        >


                                            <div
                                                class="modal-dialog modal-lg modal-dialog-centered"
                                            >


                                                <div class="modal-content">


                                                    <!-- HEADER -->

                                                    <div class="modal-header">


                                                        <div>

                                                            <h5 class="modal-title">
                                                                Edit Product Type
                                                            </h5>


                                                            <small class="text-muted">

                                                                <?= htmlspecialchars(
                                                                    $row['product_type_code']
                                                                ) ?>

                                                            </small>

                                                        </div>


                                                        <button
                                                            type="button"
                                                            class="btn-close"
                                                            data-bs-dismiss="modal"
                                                        ></button>


                                                    </div>


                                                    <!-- FORM -->

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


                                                                <!-- ID -->

                                                                <div
                                                                    class="col-12 col-md-6"
                                                                >

                                                                    <label class="form-label">
                                                                        Product Type ID
                                                                    </label>


                                                                    <input
                                                                        type="text"
                                                                        class="form-control"
                                                                        value="<?= htmlspecialchars(
                                                                            $row['product_type_code']
                                                                        ) ?>"
                                                                        readonly
                                                                    >

                                                                </div>


                                                                <!-- STATUS -->

                                                                <div
                                                                    class="col-12 col-md-6"
                                                                >

                                                                    <label class="form-label">
                                                                        Status
                                                                    </label>


                                                                    <div
                                                                        class="form-check form-switch mt-2"
                                                                    >


                                                                        <input
                                                                            class="form-check-input"
                                                                            type="checkbox"
                                                                            name="status"
                                                                            id="editStatus<?= (int)$row['id'] ?>"
                                                                            <?= $editStatus
                                                                                ? 'checked'
                                                                                : ''
                                                                            ?>
                                                                        >


                                                                        <label
                                                                            class="form-check-label"
                                                                            for="editStatus<?= (int)$row['id'] ?>"
                                                                            id="editStatusLabel<?= (int)$row['id'] ?>"
                                                                        >

                                                                            <?= $editStatus
                                                                                ? 'Active'
                                                                                : 'Inactive'
                                                                            ?>

                                                                        </label>


                                                                    </div>


                                                                </div>


                                                                <!-- NAME -->

                                                                <div class="col-12">


                                                                    <label class="form-label">

                                                                        Product Type Name

                                                                        <span class="text-danger">
                                                                            *
                                                                        </span>

                                                                    </label>


                                                                    <input
                                                                        type="text"
                                                                        name="product_type_name"
                                                                        class="form-control <?= (
                                                                            $isEditValidation
                                                                            &&
                                                                            (
                                                                                $productTypeNameError !== ''
                                                                                ||
                                                                                $duplicateNameError !== ''
                                                                            )
                                                                        )
                                                                            ? 'is-invalid'
                                                                            : ''
                                                                        ?>"
                                                                        value="<?= htmlspecialchars(
                                                                            $editProductTypeName
                                                                        ) ?>"
                                                                        placeholder="Enter product type name"
                                                                        maxlength="100"
                                                                    >


                                                                    <?php if (
                                                                        $isEditValidation
                                                                        &&
                                                                        $productTypeNameError !== ''
                                                                    ): ?>


                                                                        <div
                                                                            class="validation-message"
                                                                        >

                                                                            <i
                                                                                class="bi bi-exclamation-circle me-1"
                                                                            ></i>

                                                                            <?= htmlspecialchars(
                                                                                $productTypeNameError
                                                                            ) ?>

                                                                        </div>


                                                                    <?php elseif (
                                                                        $isEditValidation
                                                                        &&
                                                                        $duplicateNameError !== ''
                                                                    ): ?>


                                                                        <div
                                                                            class="validation-message"
                                                                        >

                                                                            <i
                                                                                class="bi bi-exclamation-circle me-1"
                                                                            ></i>

                                                                            <?= htmlspecialchars(
                                                                                $duplicateNameError
                                                                            ) ?>

                                                                        </div>


                                                                    <?php endif; ?>


                                                                </div>


                                                                <!-- DESCRIPTION -->

                                                                <div class="col-12">


                                                                    <div
                                                                        class="d-flex justify-content-between"
                                                                    >


                                                                        <label class="form-label">
                                                                            Description
                                                                        </label>


                                                                        <small
                                                                            class="text-muted"
                                                                            id="editDescriptionCount<?= (int)$row['id'] ?>"
                                                                        >

                                                                            <?= strlen(
                                                                                $editDescription ?? ''
                                                                            ) ?>

                                                                            / 200

                                                                        </small>


                                                                    </div>


                                                                    <textarea
                                                                        name="description"
                                                                        id="editDescription<?= (int)$row['id'] ?>"
                                                                        class="form-control <?= (
                                                                            $isEditValidation
                                                                            &&
                                                                            $descriptionError !== ''
                                                                        )
                                                                            ? 'is-invalid'
                                                                            : ''
                                                                        ?>"
                                                                        rows="4"
                                                                        maxlength="200"
                                                                        placeholder="Enter product type description"
                                                                    ><?= htmlspecialchars(
                                                                        $editDescription ?? ''
                                                                    ) ?></textarea>


                                                                    <?php if (
                                                                        $isEditValidation
                                                                        &&
                                                                        $descriptionError !== ''
                                                                    ): ?>


                                                                        <div
                                                                            class="validation-message"
                                                                        >

                                                                            <i
                                                                                class="bi bi-exclamation-circle me-1"
                                                                            ></i>

                                                                            <?= htmlspecialchars(
                                                                                $descriptionError
                                                                            ) ?>

                                                                        </div>


                                                                    <?php endif; ?>


                                                                </div>


                                                                <!-- AUDIT -->

                                                                <div class="col-12">


                                                                    <hr>


                                                                    <div
                                                                        class="row g-3"
                                                                    >


                                                                        <div
                                                                            class="col-12 col-md-6"
                                                                        >

                                                                            <label
                                                                                class="form-label text-muted"
                                                                            >
                                                                                Created By
                                                                            </label>


                                                                            <input
                                                                                type="text"
                                                                                class="form-control"
                                                                                value="<?= htmlspecialchars(
                                                                                    $row['created_by_name'] ?? $row['created_by'] ?? ''
                                                                                ) ?>"
                                                                                readonly
                                                                            >

                                                                        </div>


                                                                        <div
                                                                            class="col-12 col-md-6"
                                                                        >

                                                                            <label
                                                                                class="form-label text-muted"
                                                                            >
                                                                                Created At
                                                                            </label>


                                                                            <input
                                                                                type="text"
                                                                                class="form-control"
                                                                                value="<?= htmlspecialchars(
                                                                                    $row['created_at']
                                                                                ) ?>"
                                                                                readonly
                                                                            >

                                                                        </div>


                                                                    </div>


                                                                </div>


                                                            </div>


                                                        </div>


                                                        <!-- FOOTER -->

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
<!-- CREATE PRODUCT TYPE MODAL -->
<!-- ========================================================= -->

<div
    class="modal fade"
    id="createProductTypeModal"
    tabindex="-1"
    aria-hidden="true"
>


    <div
        class="modal-dialog modal-lg modal-dialog-centered"
    >


        <div class="modal-content">

            <!-- FORM -->

            <form method="POST">

                <!-- HEADER -->

                <div class="modal-header d-flex justify-content-between align-items-center flex-wrap gap-2">

                    <div>

                        <h5 class="modal-title mb-0 fw-bold">
                            Create Product Type
                        </h5>

                        <small class="text-muted">
                            Add a new product type
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


                        <!-- PRODUCT TYPE NAME -->

                        <div class="col-12">


                            <label class="form-label">

                                Product Type Name

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                name="product_type_name"
                                class="form-control <?= (
                                    $validationAction === 'create'
                                    &&
                                    (
                                        $productTypeNameError !== ''
                                        ||
                                        $duplicateNameError !== ''
                                    )
                                )
                                    ? 'is-invalid'
                                    : ''
                                ?>"
                                value="<?= (
                                    $validationAction === 'create'
                                )
                                    ? htmlspecialchars(
                                        $_POST['product_type_name'] ?? ''
                                    )
                                    : ''
                                ?>"
                                placeholder="Enter product type name"
                                maxlength="100"
                            >


                            <?php if (
                                $validationAction === 'create'
                                &&
                                $productTypeNameError !== ''
                            ): ?>


                                <div
                                    class="validation-message"
                                >

                                    <i
                                        class="bi bi-exclamation-circle me-1"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $productTypeNameError
                                    ) ?>

                                </div>


                            <?php elseif (
                                $validationAction === 'create'
                                &&
                                $duplicateNameError !== ''
                            ): ?>


                                <div
                                    class="validation-message"
                                >

                                    <i
                                        class="bi bi-exclamation-circle me-1"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $duplicateNameError
                                    ) ?>

                                </div>


                            <?php endif; ?>


                        </div>


                        <!-- DESCRIPTION -->

                        <div class="col-12">


                            <div
                                class="d-flex justify-content-between"
                            >


                                <label class="form-label">
                                    Description
                                </label>


                                <small
                                    class="text-muted"
                                    id="createDescriptionCount"
                                >

                                    <?= $validationAction === 'create'
                                        ? strlen(
                                            $_POST['description'] ?? ''
                                        )
                                        : 0
                                    ?>

                                    / 200

                                </small>


                            </div>


                            <textarea
                                name="description"
                                id="createDescription"
                                class="form-control <?= (
                                    $validationAction === 'create'
                                    &&
                                    $descriptionError !== ''
                                )
                                    ? 'is-invalid'
                                    : ''
                                ?>"
                                rows="4"
                                maxlength="200"
                                placeholder="Enter product type description"
                            ><?= $validationAction === 'create'
                                ? htmlspecialchars(
                                    $_POST['description'] ?? ''
                                )
                                : ''
                            ?></textarea>


                            <?php if (
                                $validationAction === 'create'
                                &&
                                $descriptionError !== ''
                            ): ?>


                                <div
                                    class="validation-message"
                                >

                                    <i
                                        class="bi bi-exclamation-circle me-1"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $descriptionError
                                    ) ?>

                                </div>


                            <?php endif; ?>


                        </div>


                    </div>


                </div>


                <!-- FOOTER -->

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

document.addEventListener(
    'DOMContentLoaded',
    function () {


        /*
        |--------------------------------------------------------------------------
        | Auto Hide Success Message
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll('.auto-hide-alert')
            .forEach(function (alert) {


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

        const searchInput =
            document.getElementById(
                'productTypeSearch'
            );


        const filterForm =
            document.getElementById(
                'filterForm'
            );


        let searchTimer;


        if (
            searchInput &&
            filterForm
        ) {


            searchInput.addEventListener(
                'input',
                function () {


                    clearTimeout(
                        searchTimer
                    );


                    searchTimer =
                        setTimeout(
                            function () {

                                filterForm.submit();

                            },
                            500
                        );


                }
            );


        }


        /*
        |--------------------------------------------------------------------------
        | Automatic Status Filter
        |--------------------------------------------------------------------------
        */

        const statusFilter =
            document.getElementById(
                'statusFilter'
            );


        if (
            statusFilter &&
            filterForm
        ) {


            statusFilter.addEventListener(
                'change',
                function () {

                    filterForm.submit();

                }
            );


        }


        /*
        |--------------------------------------------------------------------------
        | Filter Arrow
        |--------------------------------------------------------------------------
        */

        const filterSection =
            document.getElementById(
                'filterSection'
            );


        const filterIcon =
            document.getElementById(
                'filterIcon'
            );


        if (
            filterSection &&
            filterIcon
        ) {


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
        | Create Status Label
        |--------------------------------------------------------------------------
        */

        const createStatus =
            document.getElementById(
                'createStatus'
            );


        const createStatusLabel =
            document.getElementById(
                'createStatusLabel'
            );


        if (
            createStatus &&
            createStatusLabel
        ) {


            createStatus.addEventListener(
                'change',
                function () {


                    createStatusLabel.textContent =
                        this.checked
                            ? 'Active'
                            : 'Inactive';


                }
            );


        }


        /*
        |--------------------------------------------------------------------------
        | Edit Status Labels
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                'input[type="checkbox"][id^="editStatus"]'
            )
            .forEach(
                function (checkbox) {


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


                }
            );


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
                        this.value.length +
                        ' / 200';


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
            .forEach(
                function (textarea) {


                    const id =
                        textarea.id.replace(
                            'editDescription',
                            ''
                        );


                    const counter =
                        document.getElementById(
                            'editDescriptionCount' +
                            id
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


                }
            );


        /*
        |--------------------------------------------------------------------------
        | Reopen Create Modal After Validation
        |--------------------------------------------------------------------------
        */

        const validationAction =
            <?= json_encode(
                $validationAction
            ) ?>;


        const validationId =
            <?= (int)$validationId ?>;


        if (
            validationAction === 'create'
        ) {


            const modal =
                document.getElementById(
                    'createProductTypeModal'
                );


            if (modal) {


                bootstrap.Modal
                    .getOrCreateInstance(
                        modal
                    )
                    .show();


            }


        }


        /*
        |--------------------------------------------------------------------------
        | Reopen Edit Modal After Validation
        |--------------------------------------------------------------------------
        */

        if (
            validationAction === 'edit'
            &&
            validationId > 0
        ) {


            const modal =
                document.getElementById(
                    'editProductTypeModal' +
                    validationId
                );


            if (modal) {


                bootstrap.Modal
                    .getOrCreateInstance(
                        modal
                    )
                    .show();


            }


        }


    }
);

</script>


<?php

require_once __DIR__ . '/includes/footer.php';

?>