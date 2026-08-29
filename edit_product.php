<?php

$pageTitle = 'Edit Product';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$conn = $pdo;

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {

    header('Location: manage_product.php');
    exit;

}


/*
|--------------------------------------------------------------------------
| Product
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM product_master
    WHERE id = :id
");

$stmt->execute([
    ':id' => $id
]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {

    header('Location: manage_product.php');
    exit;

}


/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT id, category_code, category_name
    FROM category_master
    WHERE status = 1
    ORDER BY category_name
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
    ORDER BY brand_name
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
    ORDER BY unit_name
");

$units = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Images
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM product_images
    WHERE product_id = :product_id
    ORDER BY is_primary DESC, id ASC
");

$stmt->execute([
    ':product_id' => $id
]);

$images = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Rental Rates
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM product_rental_rates
    WHERE product_id = :product_id
");

$stmt->execute([
    ':product_id' => $id
]);

$rentalRates = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rate) {

    $rentalRates[$rate['rental_period']] = $rate;

}


$errors = [];

$generalError = '';


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
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($productName === '') {

        $errors[] =
            'Product Name is required.';

    } elseif (mb_strlen($productName) < 2) {

        $errors[] =
            'Product Name must be at least 2 characters.';

    } elseif (mb_strlen($productName) > 200) {

        $errors[] =
            'Product Name cannot exceed 200 characters.';

    }


    if ($shortName === '') {

        $errors[] =
            'Short Name is required.';

    } elseif (mb_strlen($shortName) < 2) {

        $errors[] =
            'Short Name must be at least 2 characters.';

    } elseif (mb_strlen($shortName) > 100) {

        $errors[] =
            'Short Name cannot exceed 100 characters.';

    }


    if ($categoryId <= 0) {

        $errors[] =
            'Category is required.';

    }


    if ($brandId <= 0) {

        $errors[] =
            'Brand is required.';

    }


    if (
        $saleAvailable === 1 &&
        $sellingPrice === ''
    ) {

        $errors[] =
            'Selling Price is required when Sale Available is Yes.';

    }


    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $conn->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Product
            |--------------------------------------------------------------------------
            */

            $updatedBy = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

            $updatedAt =
                date('Y-m-d H:i:s');


            $stmt = $conn->prepare("
                UPDATE product_master
                SET
                    product_name = :product_name,
                    short_name = :short_name,
                    category_id = :category_id,
                    brand_id = :brand_id,
                    description = :description,
                    sale_available = :sale_available,
                    purchase_price = :purchase_price,
                    selling_price = :selling_price,
                    discount_allowed = :discount_allowed,
                    discount_percent = :discount_percent,
                    sale_unit = :sale_unit,
                    rental_available = :rental_available,
                    power_rating = :power_rating,
                    voltage = :voltage,
                    rpm = :rpm,
                    chuck_disc_size = :chuck_disc_size,
                    weight = :weight,
                    battery_capacity = :battery_capacity,
                    warranty_period = :warranty_period,
                    warranty_applicable = :warranty_applicable,
                    warranty_months = :warranty_months,
                    status = :status,
                    updated_by = :updated_by,
                    updated_at = :updated_at
                WHERE id = :id
            ");


            $stmt->execute([

                ':product_name' =>
                    $productName,

                ':short_name' =>
                    $shortName,

                ':category_id' =>
                    $categoryId,

                ':brand_id' =>
                    $brandId,

                ':description' =>
                    $description,

                ':sale_available' =>
                    $saleAvailable,

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

                ':updated_by' =>
                    $updatedBy,

                ':updated_at' =>
                    $updatedAt,

                ':id' =>
                    $id

            ]);


            /*
            |--------------------------------------------------------------------------
            | Rental Rates
            |--------------------------------------------------------------------------
            */

            $periods = [
                'hourly',
                'daily',
                'weekly',
                'monthly'
            ];


            if ($rentalAvailable === 1) {

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
                        ON DUPLICATE KEY UPDATE
                            available = VALUES(available),
                            rental_unit_id = VALUES(rental_unit_id),
                            security_deposit = VALUES(security_deposit),
                            rental_rate = VALUES(rental_rate)
                    ");


                    $stmt->execute([

                        ':product_id' =>
                            $id,

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

            } else {

                $stmt = $conn->prepare("
                    DELETE FROM product_rental_rates
                    WHERE product_id = :product_id
                ");

                $stmt->execute([
                    ':product_id' => $id
                ]);

            }


            /*
            |--------------------------------------------------------------------------
            | New Images
            |--------------------------------------------------------------------------
            */

            if (
                isset($_FILES['product_images'])
                &&
                !empty(
                    $_FILES['product_images']['name'][0]
                )
            ) {


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


                for (
                    $i = 0;
                    $i < count($files['name']);
                    $i++
                ) {


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


                    $allowedExtensions = [
                        'jpg',
                        'jpeg',
                        'png',
                        'gif',
                        'webp'
                    ];


                    if (
                        !in_array(
                            $extension,
                            $allowedExtensions,
                            true
                        )
                    ) {
                        continue;
                    }


                    $fileName =
                        uniqid(
                            'product_',
                            true
                        )
                        . '.'
                        . $extension;


                    $destination =
                        $uploadDir . $fileName;


                    move_uploaded_file(
                        $files['tmp_name'][$i],
                        $destination
                    );


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
                            0
                        )
                    ");


                    $stmt->execute([

                        ':product_id' =>
                            $id,

                        ':image_name' =>
                            $files['name'][$i],

                        ':image_path' =>
                            'uploads/products/' .
                            $fileName

                    ]);

                }

            }


            $conn->commit();


            header(
                'Location: manage_product.php?success=' .
                urlencode(
                    'Product updated successfully.'
                )
            );

            exit;


        } catch (Throwable $e) {

            if ($conn->inTransaction()) {

                $conn->rollBack();

            }

            $generalError =
                'Unable to update product. ' .
                $e->getMessage();

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Reload Data
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT *
        FROM product_master
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $product =
        $stmt->fetch(PDO::FETCH_ASSOC);


    $stmt = $conn->prepare("
        SELECT *
        FROM product_rental_rates
        WHERE product_id = :product_id
    ");

    $stmt->execute([
        ':product_id' => $id
    ]);

    $rentalRates = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $rate
    ) {

        $rentalRates[
            $rate['rental_period']
        ] = $rate;

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
    ['title' => 'Edit']
]);
?>

<div class="page-heading d-flex justify-content-between align-items-center mb-4">

<div>

<h1 class="page-title mb-1">
    Edit Product
</h1>

<p class="text-muted mb-0">
    Update product information.
</p>

</div>

<div class="d-flex gap-2">

<a
    href="view_product.php?id=<?= $id ?>"
    class="btn btn-outline-secondary"
>
    View
</a>

<a
    href="manage_product.php"
    class="btn btn-outline-secondary"
>
    Back
</a>

</div>

</div>


<?php if ($generalError !== ''): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($generalError) ?>

</div>

<?php endif; ?>


<form
    method="POST"
    enctype="multipart/form-data"
>

<input
    type="hidden"
    name="id"
    value="<?= $id ?>"
>


<!-- BASIC INFORMATION -->

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
                id="editStatus"
                <?= (int)$product['status'] === 1 ? 'checked' : '' ?>
            >
            <label
                class="form-check-label mb-0 fw-semibold"
                for="editStatus"
                id="editStatusLabel"
            >
                <?= (int)$product['status'] === 1 ? 'Active' : 'Inactive' ?>
            </label>
        </div>
    </div>
</div>


<div class="card-body">

<div class="row g-3">


<!-- PRODUCT ID -->

<div class="col-12 col-md-6">

<label class="form-label">
    Product ID
</label>

<input
    type="text"
    class="form-control"
    value="<?= htmlspecialchars(
        $product['product_code']
    ) ?>"
    readonly
>

</div>


<!-- PRODUCT NAME -->

<div class="col-12">

<label class="form-label">

Product Name

<span class="text-danger">*</span>

</label>

<input
    type="text"
    name="product_name"
    class="form-control"
    value="<?= htmlspecialchars(
        $product['product_name']
    ) ?>"
    minlength="2"
    maxlength="200"
    required
>

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
    class="form-control"
    value="<?= htmlspecialchars(
        $product['short_name']
    ) ?>"
    minlength="2"
    maxlength="100"
    required
>

</div>


<!-- CATEGORY -->

<div class="col-12 col-md-6">

<label class="form-label">

Category

<span class="text-danger">*</span>

</label>

<select
    name="category_id"
    class="form-select"
    required
>

<option value="">
    Select Category
</option>

<?php foreach ($categories as $category): ?>

<option
    value="<?= (int)$category['id'] ?>"
    <?= (int)$product['category_id'] ===
        (int)$category['id']
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

</div>


<!-- BRAND -->

<div class="col-12 col-md-6">

<label class="form-label">

Brand

<span class="text-danger">*</span>

</label>

<select
    name="brand_id"
    class="form-select"
    required
>

<option value="">
    Select Brand
</option>

<?php foreach ($brands as $brand): ?>

<option
    value="<?= (int)$brand['id'] ?>"
    <?= (int)$product['brand_id'] ===
        (int)$brand['id']
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

</div>


<!-- EXISTING IMAGES -->

<div class="col-12">

<label class="form-label">
    Existing Product Images
</label>

<div class="row g-3">

<?php if (empty($images)): ?>

<div class="col-12 text-muted">
    No images available.
</div>

<?php else: ?>

<?php foreach ($images as $image): ?>

<div class="col-6 col-md-3">

<div class="border rounded p-2">

<img
    src="<?= htmlspecialchars(
        $image['image_path']
    ) ?>"
    class="img-fluid rounded"
    style="height:150px;width:100%;object-fit:cover"
>

<?php if ((int)$image['is_primary'] === 1): ?>

<span class="badge bg-primary mt-2">
    Primary
</span>

<?php endif; ?>

</div>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

</div>


<!-- ADD IMAGES -->

<div class="col-12">

<label class="form-label">
    Add Product Images
</label>

<input
    type="file"
    name="product_images[]"
    class="form-control"
    accept="image/jpeg,image/png,image/gif,image/webp"
    multiple
>

<small class="text-muted">
    Upload additional JPG, JPEG, PNG, GIF or WEBP images.
</small>

</div>


<!-- DESCRIPTION -->

<div class="col-12">

<label class="form-label">
    Description
</label>

<textarea
    name="description"
    class="form-control"
    rows="4"
    maxlength="2000"
><?= htmlspecialchars(
    $product['description'] ?? ''
) ?></textarea>

</div>


</div>

</div>

</div>


<!-- SALES -->

<div class="card shadow-sm mb-4">

<div class="card-header">

<h5 class="mb-0">
    2. Sales
</h5>

</div>

<div class="card-body">

<div class="row g-3">


<div class="col-12 col-md-4">

<label class="form-label">

Sale Available

<span class="text-danger">*</span>

</label>

<select
    name="sale_available"
    id="editSaleAvailable"
    class="form-select"
    required
>

<option
    value="yes"
    <?= (int)$product['sale_available'] === 1
        ? 'selected'
        : '' ?>
>
    Yes
</option>

<option
    value="no"
    <?= (int)$product['sale_available'] === 0
        ? 'selected'
        : '' ?>
>
    No
</option>

</select>

</div>


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
    value="<?= htmlspecialchars(
        $product['purchase_price'] ?? ''
    ) ?>"
>

</div>


<div class="col-12 col-md-4">

<label class="form-label">
    Selling Price
</label>

<input
    type="number"
    name="selling_price"
    class="form-control"
    step="0.01"
    min="0"
    value="<?= htmlspecialchars(
        $product['selling_price'] ?? ''
    ) ?>"
>

</div>


<div class="col-12 col-md-4">

<label class="form-label">
    Discount Allow
</label>

<select
    name="discount_allowed"
    id="editDiscountAllowed"
    class="form-select"
>

<option
    value="yes"
    <?= (int)$product['discount_allowed'] === 1
        ? 'selected'
        : '' ?>
>
    Yes
</option>

<option
    value="no"
    <?= (int)$product['discount_allowed'] === 0
        ? 'selected'
        : '' ?>
>
    No
</option>

</select>

</div>


<div
    class="col-12 col-md-4"
    id="editDiscountWrapper"
    style="<?= (int)$product['discount_allowed'] === 1
        ? ''
        : 'display:none' ?>"
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
    value="<?= htmlspecialchars(
        $product['discount_percent'] ?? ''
    ) ?>"
>

</div>


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
    value="<?= htmlspecialchars(
        $product['sale_unit'] ?? ''
    ) ?>"
>

</div>


</div>

</div>

</div>


<!-- RENTAL -->

<div class="card shadow-sm mb-4">

<div class="card-header">

<h5 class="mb-0">
    3. Rental
</h5>

</div>

<div class="card-body">


<div class="row">

<div class="col-12 col-md-4">

<label class="form-label">

Rental Available

<span class="text-danger">*</span>

</label>

<select
    name="rental_available"
    id="editRentalAvailable"
    class="form-select"
    required
>

<option
    value="yes"
    <?= (int)$product['rental_available'] === 1
        ? 'selected'
        : '' ?>
>
    Yes
</option>

<option
    value="no"
    <?= (int)$product['rental_available'] === 0
        ? 'selected'
        : '' ?>
>
    No
</option>

</select>

</div>

</div>


<div
    id="editRentalSection"
    class="mt-4"
    style="<?= (int)$product['rental_available'] === 1
        ? ''
        : 'display:none' ?>"
>


<ul class="nav nav-tabs">

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
    data-bs-target="#edit<?= ucfirst($key) ?>Tab"
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

$rate =
    $rentalRates[$key] ?? null;

?>

<div
    class="tab-pane fade <?= $first
        ? 'show active'
        : '' ?>"
    id="edit<?= ucfirst($key) ?>Tab"
>


<div class="form-check form-switch mb-3">

<input
    class="form-check-input edit-rental-checkbox"
    type="checkbox"
    name="rental_available_period[<?= $key ?>]"
    data-period="<?= $key ?>"
    id="editAvailable<?= ucfirst($key) ?>"
    <?= $rate && (int)$rate['available'] === 1
        ? 'checked'
        : '' ?>
>

<label
    class="form-check-label"
    for="editAvailable<?= ucfirst($key) ?>"
>
    Available
</label>

</div>


<div
    class="edit-rental-fields"
    id="editRentalFields<?= ucfirst($key) ?>"
    style="<?= $rate && (int)$rate['available'] === 1
        ? ''
        : 'display:none' ?>"
>


<div class="row g-3">


<div class="col-12 col-md-4">

<label class="form-label">
    Rental Unit
    <span class="text-danger">*</span>
</label>

<select
    name="rental_unit_id[<?= $key ?>]"
    class="form-select edit-rental-required"
>

<option value="">
    Select Unit
</option>

<?php foreach ($units as $unit): ?>

<option
    value="<?= (int)$unit['id'] ?>"
    <?= $rate &&
        (int)$rate['rental_unit_id'] ===
        (int)$unit['id']
        ? 'selected'
        : '' ?>
>

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


<div class="col-12 col-md-4">

<label class="form-label">

Security Deposit

<span class="text-danger">*</span>

</label>

<input
    type="number"
    name="security_deposit[<?= $key ?>]"
    class="form-control edit-rental-required"
    step="0.01"
    min="0"
    value="<?= htmlspecialchars(
        $rate['security_deposit'] ?? ''
    ) ?>"
>

</div>


<div class="col-12 col-md-4">

<label class="form-label">

Rental Rate

<span class="text-danger">*</span>

</label>

<input
    type="number"
    name="rental_rate[<?= $key ?>]"
    class="form-control edit-rental-required"
    step="0.01"
    min="0"
    value="<?= htmlspecialchars(
        $rate['rental_rate'] ?? ''
    ) ?>"
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


<!-- SPECIFICATIONS -->

<div class="card shadow-sm mb-4">

<div class="card-header">

<h5 class="mb-0">
    4. Specifications
</h5>

</div>

<div class="card-body">

<div class="row g-3">


<?php

$specifications = [

    'power_rating' =>
        'Power Rating',

    'voltage' =>
        'Voltage',

    'rpm' =>
        'RPM',

    'chuck_disc_size' =>
        'Chuck / Disc Size',

    'weight' =>
        'Weight',

    'battery_capacity' =>
        'Battery Capacity',

    'warranty_period' =>
        'Warranty Period'

];

foreach ($specifications as $field => $label):

?>

<div class="col-12 col-md-4">

<label class="form-label">
    <?= $label ?>
</label>

<input
    type="text"
    name="<?= $field ?>"
    class="form-control"
    value="<?= htmlspecialchars(
        $product[$field] ?? ''
    ) ?>"
>

</div>

<?php endforeach; ?>


</div>

</div>

</div>


<!-- WARRANTY -->

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
    id="editWarrantyApplicable"
    class="form-select"
>

<option
    value="yes"
    <?= (int)$product['warranty_applicable'] === 1
        ? 'selected'
        : '' ?>
>
    Yes
</option>

<option
    value="no"
    <?= (int)$product['warranty_applicable'] === 0
        ? 'selected'
        : '' ?>
>
    No
</option>

</select>

</div>


<div
    class="col-12 col-md-4"
    id="editWarrantyWrapper"
    style="<?= (int)$product['warranty_applicable'] === 1
        ? ''
        : 'display:none' ?>"
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
    value="<?= htmlspecialchars(
        $product['warranty_months'] ?? ''
    ) ?>"
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

Update

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
        document.getElementById('editStatus');

    const statusLabel =
        document.getElementById('editStatusLabel');


    if (status) {

        status.addEventListener(
            'change',
            function () {

                statusLabel.textContent =
                    this.checked
                        ? 'Active'
                        : 'Inactive';

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Discount
    |--------------------------------------------------------------------------
    */

    const discount =
        document.getElementById(
            'editDiscountAllowed'
        );

    const discountWrapper =
        document.getElementById(
            'editDiscountWrapper'
        );


    discount.addEventListener(
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
    | Rental
    |--------------------------------------------------------------------------
    */

    const rental =
        document.getElementById(
            'editRentalAvailable'
        );

    const rentalSection =
        document.getElementById(
            'editRentalSection'
        );


    rental.addEventListener(
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
    | Rental Periods
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            '.edit-rental-checkbox'
        )
        .forEach(function (checkbox) {


            checkbox.addEventListener(
                'change',
                function () {


                    const period =
                        this.dataset.period;


                    const fields =
                        document.getElementById(
                            'editRentalFields' +
                            period.charAt(0).toUpperCase() +
                            period.slice(1)
                        );


                    fields.style.display =
                        this.checked
                            ? 'block'
                            : 'none';


                    fields
                        .querySelectorAll(
                            '.edit-rental-required'
                        )
                        .forEach(function (input) {

                            input.required =
                                checkbox.checked;

                        });


                }
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Warranty
    |--------------------------------------------------------------------------
    */

    const warranty =
        document.getElementById(
            'editWarrantyApplicable'
        );


    const warrantyWrapper =
        document.getElementById(
            'editWarrantyWrapper'
        );


    warranty.addEventListener(
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