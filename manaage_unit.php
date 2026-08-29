<?php

$pageTitle = 'Unit Master';

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

$unitNameError = '';
$descriptionError = '';
$duplicateNameError = '';

$validationAction = '';
$validationId = 0;

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$openEditId = (int) ($_GET['edit'] ?? 0);


/*
|--------------------------------------------------------------------------
| Generate Unit Code
|--------------------------------------------------------------------------
| Format: UNIT-001
|--------------------------------------------------------------------------
*/

function generateUnitCode($conn)
{
    $stmt = $conn->prepare("
        SELECT unit_code
        FROM unit_master
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute();

    $lastCode = $stmt->fetchColumn();

    if (!$lastCode) {
        return 'UNIT-001';
    }

    $number = (int) str_replace('UNIT-', '', $lastCode) + 1;

    return 'UNIT-' . str_pad($number, 3, '0', STR_PAD_LEFT);
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
    | CREATE UNIT
    |--------------------------------------------------------------------------
    */

    if ($action === 'create') {

        $unitName = trim($_POST['unit_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // Checked = Active
        // Unchecked = Inactive
        $status = isset($_POST['status']) ? 1 : 0;

        $validationAction = 'create';


        /*
        |--------------------------------------------------------------------------
        | Unit Name Validation
        |--------------------------------------------------------------------------
        */

        if ($unitName === '') {

            $unitNameError = 'Unit Name is required.';

        } elseif (mb_strlen($unitName) < 2) {

            $unitNameError = 'Unit Name must be at least 2 characters.';

        } elseif (mb_strlen($unitName) > 100) {

            $unitNameError = 'Unit Name cannot exceed 100 characters.';

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
        | Duplicate Unit Name
        |--------------------------------------------------------------------------
        */

        if ($unitNameError === '') {

            $stmt = $conn->prepare("
                SELECT COUNT(*)
                FROM unit_master
                WHERE LOWER(unit_name) = LOWER(:unit_name)
            ");

            $stmt->execute([
                ':unit_name' => $unitName
            ]);

            if ((int) $stmt->fetchColumn() > 0) {

                $duplicateNameError =
                    'Unit Name already exists.';

            }
        }


        /*
        |--------------------------------------------------------------------------
        | Collect Errors
        |--------------------------------------------------------------------------
        */

        if ($unitNameError !== '') {
            $errors[] = $unitNameError;
        }

        if ($descriptionError !== '') {
            $errors[] = $descriptionError;
        }

        if ($duplicateNameError !== '') {
            $errors[] = $duplicateNameError;
        }


        /*
        |--------------------------------------------------------------------------
        | Insert Unit
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            $unitCode = generateUnitCode($conn);

            $createdBy = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

            $createdAt = date('Y-m-d H:i:s');


            $stmt = $conn->prepare("
                INSERT INTO unit_master
                (
                    unit_code,
                    unit_name,
                    description,
                    status,
                    created_by,
                    created_at
                )
                VALUES
                (
                    :unit_code,
                    :unit_name,
                    :description,
                    :status,
                    :created_by,
                    :created_at
                )
            ");


            $stmt->execute([
                ':unit_code' => $unitCode,
                ':unit_name' => $unitName,
                ':description' => $description,
                ':status' => $status,
                ':created_by' => $createdBy,
                ':created_at' => $createdAt
            ]);


            header(
                'Location: manaage_unit.php?success=' .
                urlencode("Unit {$unitCode} created successfully.")
            );

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT UNIT
    |--------------------------------------------------------------------------
    */

    if ($action === 'edit') {

        $id = (int) ($_POST['id'] ?? 0);

        $unitName =
            trim($_POST['unit_name'] ?? '');

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
                'Invalid unit record.';

        } else {


            /*
            |--------------------------------------------------------------------------
            | Unit Name Validation
            |--------------------------------------------------------------------------
            */

            if ($unitName === '') {

                $unitNameError =
                    'Unit Name is required.';

            } elseif (mb_strlen($unitName) < 2) {

                $unitNameError =
                    'Unit Name must be at least 2 characters.';

            } elseif (mb_strlen($unitName) > 100) {

                $unitNameError =
                    'Unit Name cannot exceed 100 characters.';

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
            | Duplicate Unit Name
            |--------------------------------------------------------------------------
            */

            if ($unitNameError === '') {

                $stmt = $conn->prepare("
                    SELECT COUNT(*)
                    FROM unit_master
                    WHERE LOWER(unit_name) = LOWER(:unit_name)
                    AND id != :id
                ");

                $stmt->execute([
                    ':unit_name' => $unitName,
                    ':id' => $id
                ]);


                if ((int) $stmt->fetchColumn() > 0) {

                    $duplicateNameError =
                        'Unit Name already exists.';

                }
            }


            /*
            |--------------------------------------------------------------------------
            | Collect Errors
            |--------------------------------------------------------------------------
            */

            if ($unitNameError !== '') {
                $errors[] = $unitNameError;
            }

            if ($descriptionError !== '') {
                $errors[] = $descriptionError;
            }

            if ($duplicateNameError !== '') {
                $errors[] = $duplicateNameError;
            }


            /*
            |--------------------------------------------------------------------------
            | Update Unit
            |--------------------------------------------------------------------------
            */

            if (empty($errors)) {

                $updatedBy = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

                $updatedAt = date('Y-m-d H:i:s');


                $stmt = $conn->prepare("
                    UPDATE unit_master
                    SET
                        unit_name = :unit_name,
                        description = :description,
                        status = :status,
                        updated_by = :updated_by,
                        updated_at = :updated_at
                    WHERE id = :id
                ");


                $stmt->execute([
                    ':unit_name' => $unitName,
                    ':description' => $description,
                    ':status' => $status,
                    ':updated_by' => $updatedBy,
                    ':updated_at' => $updatedAt,
                    ':id' => $id
                ]);


                header(
                    'Location: manaage_unit.php?success=' .
                    urlencode(
                        'Unit updated successfully.'
                    )
                );

                exit;
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET UNITS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT 
        um.*,
        creator.user_name AS created_by_name,
        updater.user_name AS updated_by_name
    FROM unit_master um
    LEFT JOIN user_master creator
        ON creator.id = um.created_by
    LEFT JOIN user_master updater
        ON updater.id = um.updated_by
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
            unit_code LIKE :search
            OR unit_name LIKE :search
        )
    ";

    $params[':search'] = "%{$search}%";
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

    $sql .= "
        AND status = :status
    ";

    $params[':status'] =
        (int) $statusFilter;
}


$sql .= "
    ORDER BY id DESC
";


$stmt = $conn->prepare($sql);

$stmt->execute($params);

$units =
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
            ['title' => 'Unit Master']
        ]);
        ?>


        <!-- ===================================================== -->
        <!-- PAGE HEADING -->
        <!-- ===================================================== -->

        <div class="page-heading d-flex justify-content-between align-items-center mb-4">


            <div>

                <h1 class="page-title mb-1">
                    Unit Master
                </h1>


            </div>


            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUnitModal">

                <i class="bi bi-plus-lg me-1"></i>

                Create Unit

            </button>


        </div>


        <!-- ===================================================== -->
        <!-- SUCCESS MESSAGE -->
        <!-- ===================================================== -->

        <?php if (!empty($_GET['success'])): ?>

            <div class="alert alert-success alert-dismissible fade show auto-hide-alert">

                <span>

                    <i class="bi bi-check-circle me-2"></i>

                    <?= htmlspecialchars(
                        $_GET['success']
                    ) ?>

                </span>


                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>


                <div class="alert-time-line"></div>

            </div>

        <?php endif; ?>


        <!-- ===================================================== -->
        <!-- SEARCH / FILTER -->
        <!-- ===================================================== -->

        <div class="card shadow-sm mb-4">


            <div class="card-body">


                <form method="GET" id="filterForm">


                    <div class="row g-2 align-items-end">


                        <!-- SEARCH -->

                        <div class="col-12 col-md-8">


                            <label class="form-label">
                                Search
                            </label>


                            <input type="text" name="search" id="unitSearch" class="form-control"
                                placeholder="Search ID or unit name" value="<?= htmlspecialchars(
                                    $search
                                ) ?>" autocomplete="off">


                        </div>


                        <!-- CLEAR -->

                        <?php if (
                            $search !== ''
                            ||
                            $statusFilter !== ''
                        ): ?>


                            <div class="col-6 col-md-2">


                                <a href="manaage_unit.php" class="btn btn-outline-secondary w-100">

                                    <i class="bi bi-x-circle me-1"></i>

                                    Clear

                                </a>


                            </div>


                        <?php endif; ?>


                        <!-- FILTER -->

                        <div class="<?= (
                            $search !== ''
                            ||
                            $statusFilter !== ''
                        )
                            ? 'col-6'
                            : 'col-12'
                            ?> col-md-2">


                            <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="collapse"
                                data-bs-target="#filterSection" aria-expanded="false" aria-controls="filterSection">

                                <i class="bi bi-funnel me-1"></i>

                                Filter

                                <i class="bi bi-chevron-down ms-1" id="filterIcon"></i>

                            </button>


                        </div>


                    </div>


                    <!-- FILTER SECTION -->

                    <div class="collapse mt-3" id="filterSection">


                        <div class="filter-section-box">


                            <div class="row g-3">


                                <div class="col-12 col-md-4">


                                    <label class="form-label">
                                        Status
                                    </label>


                                    <select name="status" id="statusFilter" class="form-select">


                                        <option value="">
                                            All Status
                                        </option>


                                        <option value="1" <?= $statusFilter === '1'
                                            ? 'selected'
                                            : ''
                                            ?>>
                                            Active
                                        </option>


                                        <option value="0" <?= $statusFilter === '0'
                                            ? 'selected'
                                            : ''
                                            ?>>
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
        <!-- UNIT TABLE -->
        <!-- ===================================================== -->

        <div class="card shadow-sm">


            <div class="card-body">


                <div class="category-table-wrapper">


                    <div class="category-table-scroll">


                        <table class="table category-table align-middle mb-0">


                            <thead>

                                <tr>

                                    <th>
                                        Unit ID
                                    </th>

                                    <th>
                                        Unit Name
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

                                    <th class="action-column text-end">
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php if (empty($units)): ?>


                                    <tr>

                                        <td colspan="8" class="text-center py-5 text-muted">

                                            <i class="bi bi-rulers fs-2 d-block mb-2"></i>

                                            No units found.

                                        </td>

                                    </tr>


                                <?php else: ?>


                                    <?php foreach (
                                        $units
                                        as $row
                                    ): ?>


                                        <tr>


                                            <!-- UNIT ID -->

                                            <td>


                                                <span class="status-dot <?= (
                                                    (int) $row['status'] === 1
                                                )
                                                    ? 'active'
                                                    : 'inactive'
                                                    ?>" title="<?= (
                                                    (int) $row['status'] === 1
                                                )
                                                    ? 'Active'
                                                    : 'Inactive'
                                                    ?>"></span>


                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $row['unit_code']
                                                    ) ?>

                                                </strong>


                                            </td>


                                            <!-- UNIT NAME -->

                                            <td>

                                                <?= htmlspecialchars(
                                                    $row['unit_name']
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
                                                    $row['created_by_name'] ?? '-'
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

                                                <?= !empty($row['updated_by_name'])
                                                    ? htmlspecialchars($row['updated_by_name'])
                                                    : '-'
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

                                            <td class="action-column text-end text-nowrap">


                                                <!-- VIEW -->

                                                <a href="view_unit.php?id=<?= (int) $row['id'] ?>"
                                                    class="btn btn-sm btn-outline-secondary" title="View">

                                                    <i class="bi bi-eye"></i>

                                                </a>


                                                <!-- EDIT -->

                                                <button type="button" class="btn btn-sm btn-outline-primary" title="Edit"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editUnitModal<?= (int) $row['id'] ?>">

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
                                                $validationId === (int) $row['id']
                                            );


                                        $editUnitName =
                                            $isEditValidation
                                            ? (
                                                $_POST['unit_name']
                                                ??
                                                $row['unit_name']
                                            )
                                            : $row['unit_name'];


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
                                                (int) $row['status'] === 1
                                            );

                                        ?>


                                        <!-- ================================================= -->
                                        <!-- EDIT MODAL -->
                                        <!-- ================================================= -->

                                        <div class="modal fade" id="editUnitModal<?= (int) $row['id'] ?>" tabindex="-1"
                                            aria-hidden="true">


                                            <div class="modal-dialog modal-lg modal-dialog-centered">


                                                <div class="modal-content">


                                                    <!-- HEADER -->

                                                    <div class="modal-header">


                                                        <div>

                                                            <h5 class="modal-title">
                                                                Edit Unit
                                                            </h5>


                                                            <small class="text-muted">

                                                                <?= htmlspecialchars(
                                                                    $row['unit_code']
                                                                ) ?>

                                                            </small>

                                                        </div>


                                                        <button type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>


                                                    </div>


                                                    <!-- FORM -->

                                                    <form method="POST">


                                                        <div class="modal-body">


                                                            <input type="hidden" name="form_action" value="edit">


                                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">


                                                            <div class="row g-3">


                                                                <!-- UNIT ID -->

                                                                <div class="col-12 col-md-6">


                                                                    <label class="form-label">
                                                                        Unit ID
                                                                    </label>


                                                                    <input type="text" class="form-control" value="<?= htmlspecialchars(
                                                                        $row['unit_code']
                                                                    ) ?>" readonly>


                                                                </div>


                                                                <!-- STATUS -->

                                                                <div class="col-12 col-md-6">


                                                                    <label class="form-label">
                                                                        Status
                                                                    </label>


                                                                    <div class="form-check form-switch mt-2">


                                                                        <input class="form-check-input" type="checkbox"
                                                                            name="status" id="editStatus<?= (int) $row['id'] ?>"
                                                                            <?= $editStatus
                                                                                ? 'checked'
                                                                                : ''
                                                                                ?>>


                                                                        <label class="form-check-label"
                                                                            for="editStatus<?= (int) $row['id'] ?>"
                                                                            id="editStatusLabel<?= (int) $row['id'] ?>">

                                                                            <?= $editStatus
                                                                                ? 'Active'
                                                                                : 'Inactive'
                                                                                ?>

                                                                        </label>


                                                                    </div>


                                                                </div>


                                                                <!-- UNIT NAME -->

                                                                <div class="col-12">


                                                                    <label class="form-label">


                                                                        Unit Name


                                                                        <span class="text-danger">
                                                                            *
                                                                        </span>


                                                                    </label>


                                                                    <input type="text" name="unit_name" class="form-control <?= (
                                                                        $isEditValidation
                                                                        &&
                                                                        (
                                                                            $unitNameError !== ''
                                                                            ||
                                                                            $duplicateNameError !== ''
                                                                        )
                                                                    )
                                                                        ? 'is-invalid'
                                                                        : ''
                                                                        ?>" value="<?= htmlspecialchars(
                                                                        $editUnitName
                                                                    ) ?>" placeholder="Enter unit name"
                                                                        maxlength="100">


                                                                    <?php if (
                                                                        $isEditValidation
                                                                        &&
                                                                        $unitNameError !== ''
                                                                    ): ?>


                                                                        <div class="validation-message">

                                                                            <i class="bi bi-exclamation-circle me-1"></i>


                                                                            <?= htmlspecialchars(
                                                                                $unitNameError
                                                                            ) ?>


                                                                        </div>


                                                                    <?php elseif (
                                                                        $isEditValidation
                                                                        &&
                                                                        $duplicateNameError !== ''
                                                                    ): ?>


                                                                        <div class="validation-message">

                                                                            <i class="bi bi-exclamation-circle me-1"></i>


                                                                            <?= htmlspecialchars(
                                                                                $duplicateNameError
                                                                            ) ?>


                                                                        </div>


                                                                    <?php endif; ?>


                                                                </div>


                                                                <!-- DESCRIPTION -->

                                                                <div class="col-12">


                                                                    <div class="d-flex justify-content-between">


                                                                        <label class="form-label">
                                                                            Description
                                                                        </label>


                                                                        <small class="text-muted"
                                                                            id="editDescriptionCount<?= (int) $row['id'] ?>">

                                                                            <?= strlen(
                                                                                $editDescription ?? ''
                                                                            ) ?>

                                                                            / 200

                                                                        </small>


                                                                    </div>


                                                                    <textarea name="description"
                                                                        id="editDescription<?= (int) $row['id'] ?>" class="form-control <?= (
                                                                               $isEditValidation
                                                                               &&
                                                                               $descriptionError !== ''
                                                                           )
                                                                               ? 'is-invalid'
                                                                               : ''
                                                                               ?>" rows="4" maxlength="200"
                                                                        placeholder="Enter unit description"><?= htmlspecialchars(
                                                                            $editDescription ?? ''
                                                                        ) ?></textarea>


                                                                    <?php if (
                                                                        $isEditValidation
                                                                        &&
                                                                        $descriptionError !== ''
                                                                    ): ?>


                                                                        <div class="validation-message">

                                                                            <i class="bi bi-exclamation-circle me-1"></i>


                                                                            <?= htmlspecialchars(
                                                                                $descriptionError
                                                                            ) ?>


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


                                                                            <input type="text" class="form-control" value="<?= htmlspecialchars(
                                                                                $row['created_by_name'] ?? $row['created_by'] ?? ''
                                                                            ) ?>" readonly>


                                                                        </div>


                                                                        <div class="col-12 col-md-6">


                                                                            <label class="form-label text-muted">
                                                                                Created At
                                                                            </label>


                                                                            <input type="text" class="form-control" value="<?= htmlspecialchars(
                                                                                $row['created_at']
                                                                            ) ?>" readonly>


                                                                        </div>


                                                                    </div>


                                                                </div>


                                                            </div>


                                                        </div>


                                                        <!-- FOOTER -->

                                                        <div class="modal-footer">


                                                            <button type="button" class="btn btn-outline-secondary"
                                                                data-bs-dismiss="modal">

                                                                Back

                                                            </button>


                                                            <button type="submit" class="btn btn-primary">

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
<!-- CREATE UNIT MODAL -->
<!-- ========================================================= -->

<div class="modal fade" id="createUnitModal" tabindex="-1" aria-hidden="true">


    <div class="modal-dialog modal-lg modal-dialog-centered">


        <div class="modal-content">

            <!-- FORM -->

            <form method="POST">

                <!-- HEADER -->

                <div class="modal-header d-flex justify-content-between align-items-center flex-wrap gap-2">

                    <div>

                        <h5 class="modal-title mb-0 fw-bold">
                            Create Unit
                        </h5>

                        <small class="text-muted">
                            Add a new unit
                        </small>

                    </div>

                    <div class="d-flex align-items-center gap-3 ms-auto">
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                </div>

                <div class="modal-body">
                    <div class="form-check form-switch m-0 d-flex align-items-center gap-2 justify-content-end">
                        <input class="form-check-input" type="checkbox" name="status" id="createStatus" checked>
                        <label class="form-check-label mb-0 fw-semibold" for="createStatus" id="createStatusLabel">
                            Active
                        </label>
                    </div>
                    <input type="hidden" name="form_action" value="create">

                    <div class="row g-3">


                        <!-- UNIT NAME -->

                        <div class="col-12">


                            <label class="form-label">


                                Unit Name


                                <span class="text-danger">
                                    *
                                </span>


                            </label>


                            <input type="text" name="unit_name" class="form-control <?= (
                                $validationAction === 'create'
                                &&
                                (
                                    $unitNameError !== ''
                                    ||
                                    $duplicateNameError !== ''
                                )
                            )
                                ? 'is-invalid'
                                : ''
                                ?>" value="<?= (
                                $validationAction === 'create'
                            )
                                ? htmlspecialchars(
                                    $_POST['unit_name'] ?? ''
                                )
                                : ''
                                ?>" placeholder="Enter unit name" maxlength="100">


                            <?php if (
                                $validationAction === 'create'
                                &&
                                $unitNameError !== ''
                            ): ?>


                                <div class="validation-message">

                                    <i class="bi bi-exclamation-circle me-1"></i>


                                    <?= htmlspecialchars(
                                        $unitNameError
                                    ) ?>


                                </div>


                            <?php elseif (
                                $validationAction === 'create'
                                &&
                                $duplicateNameError !== ''
                            ): ?>


                                <div class="validation-message">

                                    <i class="bi bi-exclamation-circle me-1"></i>


                                    <?= htmlspecialchars(
                                        $duplicateNameError
                                    ) ?>


                                </div>


                            <?php endif; ?>


                        </div>


                        <!-- DESCRIPTION -->

                        <div class="col-12">


                            <div class="d-flex justify-content-between">


                                <label class="form-label">
                                    Description
                                </label>


                                <small class="text-muted" id="createDescriptionCount">

                                    <?= $validationAction === 'create'
                                        ? strlen(
                                            $_POST['description'] ?? ''
                                        )
                                        : 0
                                        ?>

                                    / 200

                                </small>


                            </div>


                            <textarea name="description" id="createDescription" class="form-control <?= (
                                $validationAction === 'create'
                                &&
                                $descriptionError !== ''
                            )
                                ? 'is-invalid'
                                : ''
                                ?>" rows="4" maxlength="200" placeholder="Enter unit description"><?= $validationAction === 'create'
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


                                <div class="validation-message">

                                    <i class="bi bi-exclamation-circle me-1"></i>


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


                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">

                        Back

                    </button>


                    <button type="submit" class="btn btn-primary">

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
                    'unitSearch'
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
            | Create Status
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
                <?= (int) $validationId ?>;


            if (
                validationAction === 'create'
            ) {


                const modal =
                    document.getElementById(
                        'createUnitModal'
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
                        'editUnitModal' +
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


            /*
            |--------------------------------------------------------------------------
            | Open Edit Modal From View Page
            |--------------------------------------------------------------------------
            */

            const openEditId =
                <?= (int) $openEditId ?>;


            if (openEditId > 0) {


                const modal =
                    document.getElementById(
                        'editUnitModal' +
                        openEditId
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