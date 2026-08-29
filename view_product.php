<?php

$pageTitle = 'View Product';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$conn = $pdo;

$id = (int)($_GET['id'] ?? 0);


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
    SELECT
        p.*,

        c.category_code,
        c.category_name,

        b.brand_code,
        b.brand_name,

        creator.user_name AS created_by_name,
        updater.user_name AS updated_by_name

    FROM product_master p

    LEFT JOIN category_master c
        ON c.id = p.category_id

    LEFT JOIN brand_master b
        ON b.id = p.brand_id

    LEFT JOIN user_master creator
        ON creator.id = p.created_by

    LEFT JOIN user_master updater
        ON updater.id = p.updated_by

    WHERE p.id = :id
");

$stmt->execute([
    ':id' => $id
]);

$product =
    $stmt->fetch(PDO::FETCH_ASSOC);


if (!$product) {

    header('Location: manage_product.php');
    exit;

}


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

$images =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Rental Rates
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        r.*,

        u.unit_code,
        u.unit_name

    FROM product_rental_rates r

    LEFT JOIN unit_master u
        ON u.id = r.rental_unit_id

    WHERE r.product_id = :product_id

    ORDER BY
        FIELD(
            r.rental_period,
            'hourly',
            'daily',
            'weekly',
            'monthly'
        )
");

$stmt->execute([
    ':product_id' => $id
]);

$rentalRates =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


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
    ['title' => 'View']
]);
?>

<!-- PAGE HEADING -->

<div class="page-heading d-flex justify-content-between align-items-center mb-4">

<div>

<h1 class="page-title mb-1">
    View Product
</h1>

<p class="text-muted mb-0">
    View complete product details.
</p>

</div>


<div class="d-flex gap-2">

<a
    href="manage_product.php"
    class="btn btn-outline-secondary"
>
    Back
</a>

<a
    href="edit_product.php?id=<?= $id ?>"
    class="btn btn-primary"
>

<i class="bi bi-pencil me-1"></i>

Edit

</a>

</div>

</div>


<!-- ========================================================= -->
<!-- BASIC INFORMATION -->
<!-- ========================================================= -->

<div class="card shadow-sm mb-4">

<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

<div class="card-title mb-0">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-box-seam me-2 text-primary"></i>1. Basic Information
    </h5>
</div>

<div class="card-header-actions">
    <span class="badge <?= (int)$product['status'] === 1 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> px-3 py-1.5 rounded-pill border">
        <span class="status-dot <?= (int)$product['status'] === 1 ? 'active' : 'inactive' ?>"></span>
        <?= (int)$product['status'] === 1 ? 'Active' : 'Inactive' ?>
    </span>
</div>

</div>


<div class="card-body">

<div class="row g-4">


<div class="col-6 col-md-3">

<label class="form-label text-muted">
    Product ID
</label>

<div class="fw-semibold">
    <?= htmlspecialchars(
        $product['product_code']
    ) ?>
</div>

</div>


<div class="col-6 col-md-3">

<label class="form-label text-muted">
    Status
</label>

<div>

<?php if ((int)$product['status'] === 1): ?>

<span class="badge bg-success">
    Active
</span>

<?php else: ?>

<span class="badge bg-secondary">
    Inactive
</span>

<?php endif; ?>

</div>

</div>


<div class="col-12 col-md-6">

<label class="form-label text-muted">
    Product Name
</label>

<div class="fw-semibold">
    <?= htmlspecialchars(
        $product['product_name']
    ) ?>
</div>

</div>


<div class="col-12 col-md-4">

<label class="form-label text-muted">
    Short Name
</label>

<div>
    <?= htmlspecialchars(
        $product['short_name']
    ) ?>
</div>

</div>


<div class="col-12 col-md-4">

<label class="form-label text-muted">
    Category
</label>

<div>

<?= htmlspecialchars(
    $product['category_code']
) ?>

-

<?= htmlspecialchars(
    $product['category_name']
) ?>

</div>

</div>


<div class="col-12 col-md-4">

<label class="form-label text-muted">
    Brand
</label>

<div>

<?= htmlspecialchars(
    $product['brand_code']
) ?>

-

<?= htmlspecialchars(
    $product['brand_name']
) ?>

</div>

</div>


<div class="col-12">

<label class="form-label text-muted">
    Description
</label>

<div class="border rounded p-3 bg-light">

<?= !empty($product['description'])
    ? nl2br(
        htmlspecialchars(
            $product['description']
        )
    )
    : '<span class="text-muted">No description available.</span>'
?>

</div>

</div>


<!-- IMAGES -->

<div class="col-12">

<label class="form-label text-muted">
    Product Images
</label>


<div class="row g-3">


<?php if (empty($images)): ?>

<div class="col-12 text-muted">
    No product images available.
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
    style="width:100%;height:180px;object-fit:cover"
>

<?php if ((int)$image['is_primary'] === 1): ?>

<span class="badge bg-primary mt-2">
    Primary Image
</span>

<?php endif; ?>

</div>

</div>

<?php endforeach; ?>

<?php endif; ?>


</div>

</div>


</div>

</div>

</div>


<!-- ========================================================= -->
<!-- SALES -->
<!-- ========================================================= -->

<div class="card shadow-sm mb-4">

<div class="card-header">

<h5 class="mb-0">
    2. Sales
</h5>

</div>


<div class="card-body">

<div class="row g-4">


<div class="col-6 col-md-3">

<label class="form-label text-muted">
    Sale Available
</label>

<div>

<?= (int)$product['sale_available'] === 1
    ? '<span class="badge bg-success">Yes</span>'
    : '<span class="badge bg-secondary">No</span>'
?>

</div>

</div>


<div class="col-6 col-md-3">

<label class="form-label text-muted">
    Purchase Price
</label>

<div>

<?= $product['purchase_price'] !== null
    ? number_format(
        (float)$product['purchase_price'],
        2
    )
    : '-'
?>

</div>

</div>


<div class="col-6 col-md-3">

<label class="form-label text-muted">
    Selling Price
</label>

<div>

<?= $product['selling_price'] !== null
    ? number_format(
        (float)$product['selling_price'],
        2
    )
    : '-'
?>

</div>

</div>


<div class="col-6 col-md-3">

<label class="form-label text-muted">
    Discount Allow
</label>

<div>

<?= (int)$product['discount_allowed'] === 1
    ? '<span class="badge bg-success">Yes</span>'
    : '<span class="badge bg-secondary">No</span>'
?>

</div>

</div>


<div class="col-6 col-md-3">

<label class="form-label text-muted">
    Discount %
</label>

<div>

<?= $product['discount_percent'] !== null
    ? htmlspecialchars(
        $product['discount_percent']
    ) . ' %'
    : '-'
?>

</div>

</div>


<div class="col-6 col-md-3">

<label class="form-label text-muted">
    Unit of Sale
</label>

<div>

<?= $product['sale_unit'] !== null
    ? htmlspecialchars(
        $product['sale_unit']
    )
    : '-'
?>

</div>

</div>


</div>

</div>

</div>


<!-- ========================================================= -->
<!-- RENTAL -->
<!-- ========================================================= -->

<div class="card shadow-sm mb-4">

<div class="card-header">

<h5 class="mb-0">
    3. Rental
</h5>

</div>


<div class="card-body">


<div class="mb-4">

<label class="form-label text-muted">
    Rental Available
</label>

<div>

<?= (int)$product['rental_available'] === 1
    ? '<span class="badge bg-success">Yes</span>'
    : '<span class="badge bg-secondary">No</span>'
?>

</div>

</div>


<?php if (
    (int)$product['rental_available'] === 1
): ?>


<div class="table-responsive">

<table class="table table-bordered align-middle">

<thead>

<tr>

<th>
    Period
</th>

<th>
    Available
</th>

<th>
    Rental Unit
</th>

<th>
    Security Deposit
</th>

<th>
    Rental Rate
</th>

</tr>

</thead>


<tbody>


<?php foreach ($rentalRates as $rate): ?>


<tr>


<td>

<?= ucfirst(
    htmlspecialchars(
        $rate['rental_period']
    )
) ?>

</td>


<td>

<?= (int)$rate['available'] === 1
    ? '<span class="badge bg-success">Yes</span>'
    : '<span class="badge bg-secondary">No</span>'
?>

</td>


<td>

<?php if (!empty($rate['unit_code'])): ?>

<?= htmlspecialchars(
    $rate['unit_code']
) ?>

-

<?= htmlspecialchars(
    $rate['unit_name']
) ?>

<?php else: ?>

-

<?php endif; ?>

</td>


<td>

<?= $rate['security_deposit'] !== null
    ? number_format(
        (float)$rate['security_deposit'],
        2
    )
    : '-'
?>

</td>


<td>

<?= $rate['rental_rate'] !== null
    ? number_format(
        (float)$rate['rental_rate'],
        2
    )
    : '-'
?>

</td>


</tr>


<?php endforeach; ?>


</tbody>

</table>

</div>


<?php endif; ?>


</div>

</div>


<!-- ========================================================= -->
<!-- SPECIFICATIONS -->
<!-- ========================================================= -->

<div class="card shadow-sm mb-4">

<div class="card-header">

<h5 class="mb-0">
    4. Specifications
</h5>

</div>


<div class="card-body">

<div class="row g-4">


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

foreach (
    $specifications
    as $field => $label
):

?>

<div class="col-6 col-md-3">

<label class="form-label text-muted">

<?= $label ?>

</label>

<div>

<?= !empty($product[$field])
    ? htmlspecialchars(
        $product[$field]
    )
    : '-'
?>

</div>

</div>

<?php endforeach; ?>


</div>

</div>

</div>


<!-- ========================================================= -->
<!-- WARRANTY -->
<!-- ========================================================= -->

<div class="card shadow-sm mb-4">

<div class="card-header">

<h5 class="mb-0">
    5. Warranty & Maintenance
</h5>

</div>


<div class="card-body">

<div class="row g-4">


<div class="col-6 col-md-4">

<label class="form-label text-muted">
    Warranty Applicable
</label>

<div>

<?= (int)$product['warranty_applicable'] === 1
    ? '<span class="badge bg-success">Yes</span>'
    : '<span class="badge bg-secondary">No</span>'
?>

</div>

</div>


<?php if (
    (int)$product['warranty_applicable'] === 1
): ?>

<div class="col-6 col-md-4">

<label class="form-label text-muted">
    Warranty Period
</label>

<div>

<?= (int)$product['warranty_months'] ?>

Month

</div>

</div>

<?php endif; ?>


</div>

</div>

</div>


<!-- ========================================================= -->
<!-- AUDIT -->
<!-- ========================================================= -->

<div class="card shadow-sm mb-4">

<div class="card-header">

<h5 class="mb-0">
    Audit Information
</h5>

</div>


<div class="card-body">

<div class="row g-4">


<div class="col-6 col-md-3">

<label class="form-label text-muted">
    Created By
</label>

<div>

<?= htmlspecialchars(
    $product['created_by_name'] ?? $product['created_by'] ?? '-'
) ?>

</div>

</div>


<div class="col-6 col-md-3">

<label class="form-label text-muted">
    Created At
</label>

<div>

<?= htmlspecialchars(
    $product['created_at']
) ?>

</div>

</div>


<div class="col-6 col-md-3">

<label class="form-label text-muted">
    Updated By
</label>

<div>

<?= !empty($product['updated_by_name'])
    ? htmlspecialchars(
        $product['updated_by_name']
    )
    : (!empty($product['updated_by']) ? htmlspecialchars($product['updated_by']) : '-')
?>

</div>

</div>


<div class="col-6 col-md-3">

<label class="form-label text-muted">
    Updated At
</label>

<div>

<?= !empty($product['updated_at'])
    ? htmlspecialchars(
        $product['updated_at']
    )
    : '-'
?>

</div>

</div>


</div>

</div>

</div>


</div>

</main>


<?php

require_once __DIR__ . '/includes/footer.php';

?>