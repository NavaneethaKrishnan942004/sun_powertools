<?php

$pageTitle = 'Create Product';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$conn = $pdo;

$errors = [];

$productNameError = '';
$shortNameError = '';
$categoryError = '';
$brandError = '';
$imageError = '';
$saleError = '';
$sellingPriceError = '';
$rentalError = '';
$generalError = '';

$old = $_POST;


/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT id, category_code, category_name
    FROM category_master
    WHERE status = 1
    ORDER BY category_name ASC
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Brands
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT id, brand_code, brand_name
    FROM brand_master
    WHERE status = 1
    ORDER BY brand_name ASC
");

$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Units
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT id, unit_code, unit_name
    FROM unit_master
    WHERE status = 1
    ORDER BY unit_name ASC
");

$units = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Generate Product Code
|--------------------------------------------------------------------------
*/

function generateProductCode($conn)
{
    $stmt = $conn->prepare("
        SELECT product_code
        FROM product_master
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute();

    $lastCode = $stmt->fetchColumn();

    if (!$lastCode) {
        return 'PRO-001';
    }

    $number =
        (int)str_replace('PRO-', '', $lastCode) + 1;

    return 'PRO-' .
        str_pad($number, 3, '0', STR_PAD_LEFT);
}


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $status =
        isset($_POST['status']) ? 1 : 0;

    $productName =
        trim($_POST['product_name'] ?? '');

    $shortName =
        trim($_POST['short_name'] ?? '');

    $categoryId =
        (int)($_POST['category_id'] ?? 0);

    $brandId =
        (int)($_POST['brand_id'] ?? 0);

    $description =
        trim($_POST['description'] ?? '');

    $saleAvailable =
        ($_POST['sale_available'] ?? '') === 'yes'
            ? 1
            : 0;

    $purchasePrice =
        trim($_POST['purchase_price'] ?? '');

    $sellingPrice =
        trim($_POST['selling_price'] ?? '');

    $discountAllowed =
        ($_POST['discount_allowed'] ?? 'no') === 'yes'
            ? 1
            : 0;

    $discountPercent =
        trim($_POST['discount_percent'] ?? '');

    $saleUnit =
        trim($_POST['sale_unit'] ?? '');

    $rentalAvailable =
        ($_POST['rental_available'] ?? '') === 'yes'
            ? 1
            : 0;

    $powerRating =
        trim($_POST['power_rating'] ?? '');

    $voltage =
        trim($_POST['voltage'] ?? '');

    $rpm =
        trim($_POST['rpm'] ?? '');

    $chuckDiscSize =
        trim($_POST['chuck_disc_size'] ?? '');

    $weight =
        trim($_POST['weight'] ?? '');

    $batteryCapacity =
        trim($_POST['battery_capacity'] ?? '');

    $warrantyPeriod =
        trim($_POST['warranty_period'] ?? '');

    $warrantyApplicable =
        ($_POST['warranty_applicable'] ?? 'no') === 'yes'
            ? 1
            : 0;

    $warrantyMonths =
        trim($_POST['warranty_months'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($productName === '') {

        $productNameError =
            'Product Name is required.';

    } elseif (mb_strlen($productName) < 2) {

        $productNameError =
            'Product Name must be at least 2 characters.';

    } elseif (mb_strlen($productName) > 200) {

        $productNameError =
            'Product Name cannot exceed 200 characters.';

    }


    if ($shortName === '') {

        $shortNameError =
            'Short Name is required.';

    } elseif (mb_strlen($shortName) < 2) {

        $shortNameError =
            'Short Name must be at least 2 characters.';

    } elseif (mb_strlen($shortName) > 100) {

        $shortNameError =
            'Short Name cannot exceed 100 characters.';

    }


    if ($categoryId <= 0) {

        $categoryError =
            'Category is required.';

    }


    if ($brandId <= 0) {

        $brandError =
            'Brand is required.';

    }


    /*
    |--------------------------------------------------------------------------
    | Sale Validation
    |--------------------------------------------------------------------------
    */

    if (!isset($_POST['sale_available'])) {

        $saleError =
            'Sale Available is required.';

    }


    if ($saleAvailable === 1) {

        if ($sellingPrice === '') {

            $sellingPriceError =
                'Selling Price is required.';

        } elseif (!is_numeric($sellingPrice)) {

            $sellingPriceError =
                'Selling Price must be a valid number.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Rental Validation
    |--------------------------------------------------------------------------
    */

    if (!isset($_POST['rental_available'])) {

        $rentalError =
            'Rental Available is required.';

    }


    /*
    |--------------------------------------------------------------------------
    | Image Validation
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_FILES['product_images'])
        ||
        empty($_FILES['product_images']['name'][0])
    ) {

        $imageError =
            'At least one Product Image is required.';

    } else {

        $allowedExtensions = [
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp'
        ];

        foreach (
            $_FILES['product_images']['name']
            as $index => $name
        ) {

            if (
                $_FILES['product_images']['error'][$index]
                !== UPLOAD_ERR_OK
            ) {

                $imageError =
                    'One or more images could not be uploaded.';

                break;

            }

            $extension =
                strtolower(
                    pathinfo(
                        $name,
                        PATHINFO_EXTENSION
                    )
                );

            if (
                !in_array(
                    $extension,
                    $allowedExtensions,
                    true
                )
            ) {

                $imageError =
                    'Only JPG, JPEG, PNG, GIF and WEBP images are allowed.';

                break;

            }

        }

    }


    if ($productNameError !== '') {
        $errors[] = $productNameError;
    }

    if ($shortNameError !== '') {
        $errors[] = $shortNameError;
    }

    if ($categoryError !== '') {
        $errors[] = $categoryError;
    }

    if ($brandError !== '') {
        $errors[] = $brandError;
    }

    if ($imageError !== '') {
        $errors[] = $imageError;
    }

    if ($saleError !== '') {
        $errors[] = $saleError;
    }

    if ($sellingPriceError !== '') {
        $errors[] = $sellingPriceError;
    }

    if ($rentalError !== '') {
        $errors[] = $rentalError;
    }


    /*
    |--------------------------------------------------------------------------
    | Create Product
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $conn->beginTransaction();


            $productCode =
                generateProductCode($conn);


            $createdBy = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

            $createdAt =
                date('Y-m-d H:i:s');


            /*
            |--------------------------------------------------------------------------
            | Product
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                INSERT INTO product_master
                (
                    product_code,
                    product_name,
                    short_name,
                    category_id,
                    brand_id,
                    description,
                    sale_available,
                    purchase_price,
                    selling_price,
                    discount_allowed,
                    discount_percent,
                    sale_unit,
                    rental_available,
                    power_rating,
                    voltage,
                    rpm,
                    chuck_disc_size,
                    weight,
                    battery_capacity,
                    warranty_period,
                    warranty_applicable,
                    warranty_months,
                    status,
                    created_by,
                    created_at
                )
                VALUES
                (
                    :product_code,
                    :product_name,
                    :short_name,
                    :category_id,
                    :brand_id,
                    :description,
                    :sale_available,
                    :purchase_price,
                    :selling_price,
                    :discount_allowed,
                    :discount_percent,
                    :sale_unit,
                    :rental_available,
                    :power_rating,
                    :voltage,
                    :rpm,
                    :chuck_disc_size,
                    :weight,
                    :battery_capacity,
                    :warranty_period,
                    :warranty_applicable,
                    :warranty_months,
                    :status,
                    :created_by,
                    :created_at
                )
            ");


            $stmt->execute([

                ':product_code' => $productCode,

                ':product_name' => $productName,

                ':short_name' => $shortName,

                ':category_id' => $categoryId,

                ':brand_id' => $brandId,

                ':description' => $description,

                ':sale_available' => $saleAvailable,

                ':purchase_price' =>
                    $purchasePrice !== ''
                        ? $purchasePrice
                        : null,

                ':selling_price' =>
                    $sellingPrice !== ''
                        ? $sellingPrice
                        : null,

                ':discount_allowed' =>
                    $discountAllowed,

                ':discount_percent' =>
                    $discountPercent !== ''
                        ? $discountPercent
                        : null,

                ':sale_unit' =>
                    $saleUnit !== ''
                        ? $saleUnit
                        : null,

                ':rental_available' =>
                    $rentalAvailable,

                ':power_rating' =>
                    $powerRating ?: null,

                ':voltage' =>
                    $voltage ?: null,

                ':rpm' =>
                    $rpm ?: null,

                ':chuck_disc_size' =>
                    $chuckDiscSize ?: null,

                ':weight' =>
                    $weight ?: null,

                ':battery_capacity' =>
                    $batteryCapacity ?: null,

                ':warranty_period' =>
                    $warrantyPeriod ?: null,

                ':warranty_applicable' =>
                    $warrantyApplicable,

                ':warranty_months' =>
                    $warrantyMonths !== ''
                        ? (int)$warrantyMonths
                        : null,

                ':status' =>
                    $status,

                ':created_by' =>
                    $createdBy,

                ':created_at' =>
                    $createdAt
            ]);


            $productId =
                (int)$conn->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Rental Rates
            |--------------------------------------------------------------------------
            */

            if ($rentalAvailable === 1) {

                $periods = [
                    'hourly',
                    'daily',
                    'weekly',
                    'monthly'
                ];

                foreach ($periods as $period) {

                    $available =
                        isset(
                            $_POST['rental_available_period'][$period]
                        )
                            ? 1
                            : 0;

                    $rentalUnitId =
                        !empty(
                            $_POST['rental_unit_id'][$period]
                        )
                            ? (int)
                            $_POST['rental_unit_id'][$period]
                            : null;

                    $securityDeposit =
                        trim(
                            $_POST['security_deposit'][$period]
                            ?? ''
                        );

                    $rentalRate =
                        trim(
                            $_POST['rental_rate'][$period]
                            ?? ''
                        );


                    $stmt = $conn->prepare("
                        INSERT INTO product_rental_rates
                        (
                            product_id,
                            rental_period,
                            available,
                            rental_unit_id,
                            security_deposit,
                            rental_rate
                        )
                        VALUES
                        (
                            :product_id,
                            :rental_period,
                            :available,
                            :rental_unit_id,
                            :security_deposit,
                            :rental_rate
                        )
                    ");


                    $stmt->execute([

                        ':product_id' =>
                            $productId,

                        ':rental_period' =>
                            $period,

                        ':available' =>
                            $available,

                        ':rental_unit_id' =>
                            $rentalUnitId,

                        ':security_deposit' =>
                            $securityDeposit !== ''
                                ? $securityDeposit
                                : null,

                        ':rental_rate' =>
                            $rentalRate !== ''
                                ? $rentalRate
                                : null

                    ]);

                }

            }


            /*
            |--------------------------------------------------------------------------
            | Product Images
            |--------------------------------------------------------------------------
            */

            $uploadDir =
                __DIR__ . '/uploads/products/';


            if (!is_dir($uploadDir)) {

                mkdir(
                    $uploadDir,
                    0777,
                    true
                );

            }


            $files =
                $_FILES['product_images'];


            $imageCount =
                count($files['name']);


            for ($i = 0; $i < $imageCount; $i++) {

                if (
                    $files['error'][$i]
                    !== UPLOAD_ERR_OK
                ) {
                    continue;
                }


                $extension =
                    strtolower(
                        pathinfo(
                            $files['name'][$i],
                            PATHINFO_EXTENSION
                        )
                    );


                $fileName =
                    uniqid(
                        'product_',
                        true
                    )
                    . '.'
                    . $extension;


                $destination =
                    $uploadDir . $fileName;


                if (
                    !move_uploaded_file(
                        $files['tmp_name'][$i],
                        $destination
                    )
                ) {

                    throw new Exception(
                        'Failed to upload product image.'
                    );

                }


                $imagePath =
                    'uploads/products/' . $fileName;


                $isPrimary =
                    ($i === 0) ? 1 : 0;


                $stmt = $conn->prepare("
                    INSERT INTO product_images
                    (
                        product_id,
                        image_name,
                        image_path,
                        is_primary
                    )
                    VALUES
                    (
                        :product_id,
                        :image_name,
                        :image_path,
                        :is_primary
                    )
                ");


                $stmt->execute([

                    ':product_id' =>
                        $productId,

                    ':image_name' =>
                        $files['name'][$i],

                    ':image_path' =>
                        $imagePath,

                    ':is_primary' =>
                        $isPrimary

                ]);

            }


            $conn->commit();


            header(
                'Location: manage_product.php?success=' .
                urlencode(
                    "Product {$productCode} created successfully."
                )
            );

            exit;


        } catch (Throwable $e) {

            if ($conn->inTransaction()) {

                $conn->rollBack();

            }

            $generalError =
                'Unable to create product. ' .
                $e->getMessage();

        }

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
    ['title' => 'Settings', 'link' => 'settings.php'],
    ['title' => 'DMS Masters', 'link' => 'masters.php'],
    ['title' => 'Product Master', 'link' => 'manage_product.php'],
    ['title' => 'Add']
]);
?>

<!-- PAGE HEADING -->

<div class="page-heading d-flex justify-content-between align-items-center mb-4">

    <div>

        <h1 class="page-title mb-1">
            Create Product
        </h1>

        <p class="text-muted mb-0">
            Add a new product for rental and sales.
        </p>

    </div>

    <a
        href="manage_product.php"
        class="btn btn-outline-secondary"
    >
        Back
    </a>

</div>


<?php if ($generalError !== ''): ?>

<div class="alert alert-danger">

    <i class="bi bi-exclamation-circle me-2"></i>

    <?= htmlspecialchars($generalError) ?>

</div>

<?php endif; ?>


<form
    method="POST"
    enctype="multipart/form-data"
    id="productForm"
>


<!-- ========================================================= -->
<!-- SECTION 1 -->
<!-- ========================================================= -->

<div class="card shadow-sm mb-4">
<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="card-title mb-0">
        <h5 class="mb-0 fw-bold">
            <i class="bi bi-box-seam me-2 text-primary"></i>1. Basic Information
        </h5>
    </div>
    <div class="card-header-actions">
        <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
            <input
                class="form-check-input"
                type="checkbox"
                name="status"
                id="status"
                <?= !isset($old['status']) || $old['status'] ? 'checked' : '' ?>
            >
            <label
                class="form-check-label mb-0 fw-semibold"
                id="statusLabel"
                for="status"
            >
                <?= !isset($old['status']) || $old['status'] ? 'Active' : 'Inactive' ?>
            </label>
        </div>
    </div>
</div>


<div class="card-body">

<div class="row g-3">



<!-- PRODUCT NAME -->

<div class="col-12">

<label class="form-label">

    Product Name

    <span class="text-danger">*</span>

</label>

<input
    type="text"
    name="product_name"
    class="form-control <?= $productNameError ? 'is-invalid' : '' ?>"
    value="<?= htmlspecialchars($old['product_name'] ?? '') ?>"
    minlength="2"
    maxlength="200"
    placeholder="Enter product name"
    required
>

<?php if ($productNameError): ?>

<div class="validation-message">
    <?= htmlspecialchars($productNameError) ?>
</div>

<?php endif; ?>

</div>


<!-- SHORT NAME -->

<div class="col-12 col-md-6">

<label class="form-label">

    Short Name

    <span class="text-danger">*</span>

</label>

<input
    type="text"
    name="short_name"
    class="form-control <?= $shortNameError ? 'is-invalid' : '' ?>"
    value="<?= htmlspecialchars($old['short_name'] ?? '') ?>"
    minlength="2"
    maxlength="100"
    placeholder="Enter short name"
    required
>

<?php if ($shortNameError): ?>

<div class="validation-message">
    <?= htmlspecialchars($shortNameError) ?>
</div>

<?php endif; ?>

</div>


<!-- CATEGORY -->

<div class="col-12 col-md-6">

<label class="form-label">

    Category

    <span class="text-danger">*</span>

</label>

<select
    name="category_id"
    class="form-select <?= $categoryError ? 'is-invalid' : '' ?>"
    required
>

<option value="">
    Select Category
</option>

<?php foreach ($categories as $category): ?>

<option
    value="<?= (int)$category['id'] ?>"
    <?= (string)($old['category_id'] ?? '') ===
        (string)$category['id']
        ? 'selected'
        : '' ?>
>

<?= htmlspecialchars(
    $category['category_code']
) ?>

-

<?= htmlspecialchars(
    $category['category_name']
) ?>

</option>

<?php endforeach; ?>

</select>

<?php if ($categoryError): ?>

<div class="validation-message">
    <?= htmlspecialchars($categoryError) ?>
</div>

<?php endif; ?>

</div>


<!-- BRAND -->

<div class="col-12 col-md-6">

<label class="form-label">

    Brand

    <span class="text-danger">*</span>

</label>

<select
    name="brand_id"
    class="form-select <?= $brandError ? 'is-invalid' : '' ?>"
    required
>

<option value="">
    Select Brand
</option>

<?php foreach ($brands as $brand): ?>

<option
    value="<?= (int)$brand['id'] ?>"
    <?= (string)($old['brand_id'] ?? '') ===
        (string)$brand['id']
        ? 'selected'
        : '' ?>
>

<?= htmlspecialchars(
    $brand['brand_code']
) ?>

-

<?= htmlspecialchars(
    $brand['brand_name']
) ?>

</option>

<?php endforeach; ?>

</select>

<?php if ($brandError): ?>

<div class="validation-message">
    <?= htmlspecialchars($brandError) ?>
</div>

<?php endif; ?>

</div>


<!-- IMAGES -->

<div class="col-12">

<label class="form-label">

    Product Image

    <span class="text-danger">*</span>

</label>

<input
    type="file"
    name="product_images[]"
    class="form-control <?= $imageError ? 'is-invalid' : '' ?>"
    accept="image/jpeg,image/png,image/gif,image/webp"
    multiple
    required
>

<small class="text-muted">
    You can upload multiple JPG, JPEG, PNG, GIF or WEBP images.
</small>

<?php if ($imageError): ?>

<div class="validation-message">
    <?= htmlspecialchars($imageError) ?>
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
    id="descriptionCount"
>
    0 / 2000
</small>

</div>

<textarea
    name="description"
    id="description"
    class="form-control"
    rows="4"
    maxlength="2000"
    placeholder="Enter product description"
><?= htmlspecialchars(
    $old['description'] ?? ''
) ?></textarea>

</div>


</div>

</div>

</div>


<!-- ========================================================= -->
<!-- SECTION 2 SALES -->
<!-- ========================================================= -->

<div class="card shadow-sm mb-4">

<div class="card-header">

<h5 class="mb-0">
    2. Sales
</h5>

</div>


<div class="card-body">

<div class="row g-3">


<!-- SALE AVAILABLE -->

<div class="col-12 col-md-4">

<label class="form-label">

    Sale Available

    <span class="text-danger">*</span>

</label>

<select
    name="sale_available"
    id="saleAvailable"
    class="form-select"
    required
>

<option value="">
    Select
</option>

<option value="yes">
    Yes
</option>

<option value="no">
    No
</option>

</select>

</div>


<!-- PURCHASE -->

<div class="col-12 col-md-4">

<label class="form-label">
    Purchase Price
</label>

<input
    type="number"
    name="purchase_price"
    class="form-control"
    step="0.01"
    min="0"
    placeholder="Enter purchase price"
>

</div>


<!-- SELLING -->

<div class="col-12 col-md-4">

<label class="form-label">

    Selling Price

    <span
        class="text-danger sale-required"
        style="display:none"
    >*</span>

</label>

<input
    type="number"
    name="selling_price"
    id="sellingPrice"
    class="form-control"
    step="0.01"
    min="0"
    placeholder="Enter selling price"
>

<?php if ($sellingPriceError): ?>

<div class="validation-message">
    <?= htmlspecialchars($sellingPriceError) ?>
</div>

<?php endif; ?>

</div>


<!-- DISCOUNT -->

<div class="col-12 col-md-4">

<label class="form-label">
    Discount Allow
</label>

<select
    name="discount_allowed"
    id="discountAllowed"
    class="form-select"
>

<option value="no">
    No
</option>

<option value="yes">
    Yes
</option>

</select>

</div>


<!-- DISCOUNT % -->

<div
    class="col-12 col-md-4"
    id="discountPercentWrapper"
    style="display:none"
>

<label class="form-label">
    Discount %
</label>

<input
    type="number"
    name="discount_percent"
    class="form-control"
    step="0.01"
    min="0"
    max="100"
    placeholder="Enter discount percentage"
>

</div>


<!-- UNIT OF SALE -->

<div class="col-12 col-md-4">

<label class="form-label">
    Unit of Sale
</label>

<input
    type="number"
    name="sale_unit"
    class="form-control"
    step="any"
    min="0"
    placeholder="Enter number"
>

</div>


</div>

</div>

</div>


<!-- ========================================================= -->
<!-- SECTION 3 RENTAL -->
<!-- ========================================================= -->

<div class="card shadow-sm mb-4">

<div class="card-header">

<h5 class="mb-0">
    3. Rental
</h5>

</div>


<div class="card-body">


<div class="row g-3">


<div class="col-12 col-md-4">

<label class="form-label">

    Rental Available

    <span class="text-danger">*</span>

</label>

<select
    name="rental_available"
    id="rentalAvailable"
    class="form-select"
    required
>

<option value="">
    Select
</option>

<option value="yes">
    Yes
</option>

<option value="no">
    No
</option>

</select>

</div>

</div>


<div
    id="rentalSection"
    style="display:none"
    class="mt-4"
>


<ul
    class="nav nav-tabs"
    role="tablist"
>


<?php

$periods = [
    'hourly' => 'Hourly',
    'daily' => 'Daily',
    'weekly' => 'Weekly',
    'monthly' => 'Monthly'
];

$first = true;

foreach ($periods as $key => $label):

?>

<li class="nav-item">

<button
    type="button"
    class="nav-link <?= $first ? 'active' : '' ?>"
    data-bs-toggle="tab"
    data-bs-target="#<?= $key ?>Tab"
>

<?= $label ?>

</button>

</li>

<?php

$first = false;

endforeach;

?>

</ul>


<div class="tab-content border border-top-0 p-3">


<?php

$first = true;

foreach ($periods as $key => $label):

?>

<div
    class="tab-pane fade <?= $first ? 'show active' : '' ?>"
    id="<?= $key ?>Tab"
>


<div class="form-check form-switch mb-3">

<input
    class="form-check-input rental-available-checkbox"
    type="checkbox"
    name="rental_available_period[<?= $key ?>]"
    id="rentalAvailable<?= ucfirst($key) ?>"
    data-period="<?= $key ?>"
>

<label
    class="form-check-label"
    for="rentalAvailable<?= ucfirst($key) ?>"
>
    Available
</label>

</div>


<div
    class="rental-fields"
    id="rentalFields<?= ucfirst($key) ?>"
    style="display:none"
>


<div class="row g-3">


<!-- RENTAL UNIT -->

<div class="col-12 col-md-4">

<label class="form-label">

    Rental Unit

    <span class="text-danger">*</span>

</label>

<select
    name="rental_unit_id[<?= $key ?>]"
    class="form-select rental-required"
>

<option value="">
    Select Unit
</option>

<?php foreach ($units as $unit): ?>

<option value="<?= (int)$unit['id'] ?>">

<?= htmlspecialchars(
    $unit['unit_code']
) ?>

-

<?= htmlspecialchars(
    $unit['unit_name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- SECURITY DEPOSIT -->

<div class="col-12 col-md-4">

<label class="form-label">

    Security Deposit

    <span class="text-danger">*</span>

</label>

<input
    type="number"
    name="security_deposit[<?= $key ?>]"
    class="form-control rental-required"
    step="0.01"
    min="0"
    placeholder="Enter security deposit"
>

</div>


<!-- RENTAL RATE -->

<div class="col-12 col-md-4">

<label class="form-label">

    Rental Rate

    <span class="text-danger">*</span>

</label>

<input
    type="number"
    name="rental_rate[<?= $key ?>]"
    class="form-control rental-required"
    step="0.01"
    min="0"
    placeholder="Enter rental rate"
>

</div>


</div>

</div>


</div>

<?php

$first = false;

endforeach;

?>


</div>

</div>

</div>

</div>


<!-- ========================================================= -->
<!-- SECTION 4 -->
<!-- ========================================================= -->

<div class="card shadow-sm mb-4">

<div class="card-header">

<h5 class="mb-0">
    4. Specifications
</h5>

</div>


<div class="card-body">

<div class="row g-3">


<div class="col-12 col-md-4">

<label class="form-label">
    Power Rating
</label>

<input
    type="text"
    name="power_rating"
    class="form-control"
    placeholder="e.g. 750W"
>

</div>


<div class="col-12 col-md-4">

<label class="form-label">
    Voltage
</label>

<input
    type="text"
    name="voltage"
    class="form-control"
    placeholder="e.g. 220V"
>

</div>


<div class="col-12 col-md-4">

<label class="form-label">
    RPM
</label>

<input
    type="text"
    name="rpm"
    class="form-control"
    placeholder="e.g. 11000 RPM"
>

</div>


<div class="col-12 col-md-4">

<label class="form-label">
    Chuck / Disc Size
</label>

<input
    type="text"
    name="chuck_disc_size"
    class="form-control"
    placeholder="Enter size"
>

</div>


<div class="col-12 col-md-4">

<label class="form-label">
    Weight
</label>

<input
    type="text"
    name="weight"
    class="form-control"
    placeholder="e.g. 2.5 KG"
>

</div>


<div class="col-12 col-md-4">

<label class="form-label">
    Battery Capacity
</label>

<input
    type="text"
    name="battery_capacity"
    class="form-control"
    placeholder="e.g. 5Ah"
>

</div>


<div class="col-12">

<label class="form-label">
    Warranty Period
</label>

<input
    type="text"
    name="warranty_period"
    class="form-control"
    placeholder="e.g. 1 Year"
>

</div>


</div>

</div>

</div>


<!-- ========================================================= -->
<!-- SECTION 5 -->
<!-- ========================================================= -->

<div class="card shadow-sm mb-4">

<div class="card-header">

<h5 class="mb-0">
    5. Warranty & Maintenance
</h5>

</div>


<div class="card-body">

<div class="row g-3">


<div class="col-12 col-md-4">

<label class="form-label">
    Warranty Applicable
</label>

<select
    name="warranty_applicable"
    id="warrantyApplicable"
    class="form-select"
>

<option value="no">
    No
</option>

<option value="yes">
    Yes
</option>

</select>

</div>


<div
    class="col-12 col-md-4"
    id="warrantyMonthsWrapper"
    style="display:none"
>

<label class="form-label">
    Warranty Period
</label>

<div class="input-group">

<input
    type="number"
    name="warranty_months"
    class="form-control"
    min="0"
    step="1"
    placeholder="Enter months"
>

<span class="input-group-text">
    Month
</span>

</div>

</div>


</div>

</div>

</div>


<!-- BUTTONS -->

<div class="d-flex justify-content-end gap-2 mb-4">

<a
    href="manage_product.php"
    class="btn btn-outline-secondary"
>
    Back
</a>

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

</main>


<script>

document.addEventListener('DOMContentLoaded', function () {


    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    const status =
        document.getElementById('status');

    const statusLabel =
        document.getElementById('statusLabel');

    status.addEventListener('change', function () {

        statusLabel.textContent =
            this.checked ? 'Active' : 'Inactive';

    });


    /*
    |--------------------------------------------------------------------------
    | Description Counter
    |--------------------------------------------------------------------------
    */

    const description =
        document.getElementById('description');

    const descriptionCount =
        document.getElementById('descriptionCount');

    description.addEventListener('input', function () {

        descriptionCount.textContent =
            this.value.length + ' / 2000';

    });


    /*
    |--------------------------------------------------------------------------
    | Sale Available
    |--------------------------------------------------------------------------
    */

    const saleAvailable =
        document.getElementById('saleAvailable');

    const sellingPrice =
        document.getElementById('sellingPrice');

    const saleRequired =
        document.querySelector('.sale-required');


    function updateSaleFields() {

        if (saleAvailable.value === 'yes') {

            sellingPrice.required = true;

            saleRequired.style.display = 'inline';

        } else {

            sellingPrice.required = false;

            saleRequired.style.display = 'none';

        }

    }


    saleAvailable.addEventListener(
        'change',
        updateSaleFields
    );


    /*
    |--------------------------------------------------------------------------
    | Discount
    |--------------------------------------------------------------------------
    */

    const discountAllowed =
        document.getElementById('discountAllowed');

    const discountWrapper =
        document.getElementById(
            'discountPercentWrapper'
        );


    discountAllowed.addEventListener(
        'change',
        function () {

            discountWrapper.style.display =
                this.value === 'yes'
                    ? 'block'
                    : 'none';

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Rental Available
    |--------------------------------------------------------------------------
    */

    const rentalAvailable =
        document.getElementById(
            'rentalAvailable'
        );

    const rentalSection =
        document.getElementById(
            'rentalSection'
        );


    rentalAvailable.addEventListener(
        'change',
        function () {

            rentalSection.style.display =
                this.value === 'yes'
                    ? 'block'
                    : 'none';

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Rental Period Available
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            '.rental-available-checkbox'
        )
        .forEach(function (checkbox) {


            checkbox.addEventListener(
                'change',
                function () {


                    const period =
                        this.dataset.period;


                    const fields =
                        document.getElementById(
                            'rentalFields' +
                            period.charAt(0).toUpperCase() +
                            period.slice(1)
                        );


                    if (this.checked) {

                        fields.style.display =
                            'block';

                        fields
                            .querySelectorAll(
                                '.rental-required'
                            )
                            .forEach(function (input) {

                                input.required = true;

                            });

                    } else {

                        fields.style.display =
                            'none';

                        fields
                            .querySelectorAll(
                                '.rental-required'
                            )
                            .forEach(function (input) {

                                input.required = false;

                            });

                    }

                }
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Warranty
    |--------------------------------------------------------------------------
    */

    const warrantyApplicable =
        document.getElementById(
            'warrantyApplicable'
        );

    const warrantyWrapper =
        document.getElementById(
            'warrantyMonthsWrapper'
        );


    warrantyApplicable.addEventListener(
        'change',
        function () {

            warrantyWrapper.style.display =
                this.value === 'yes'
                    ? 'block'
                    : 'none';

        }
    );

});

</script>


<?php

require_once __DIR__ . '/includes/footer.php';

?>