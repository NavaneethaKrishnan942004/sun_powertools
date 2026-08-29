<?php

$currentPage = basename($_SERVER['PHP_SELF']);

$salesPages = ['manage_sales_note.php', 'create_sales_note.php', 'edit_sales_note.php', 'view_sales_note.php'];
$productPages = ['manage_product.php', 'create_product.php', 'edit_product.php', 'view_product.php'];
$customerPages = ['manage_customer.php', 'create_customer.php', 'edit_customer.php', 'view_customer.php'];
$userPages = ['manage_user.php', 'create_user.php', 'edit_user.php', 'view_user.php'];

$menuItems = [
    [
        'title' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'link' => 'index.php',
        'active' => ($currentPage === 'index.php')
    ],
    [
        'title' => 'Sales Notes',
        'icon' => 'bi-receipt',
        'link' => 'manage_sales_note.php',
        'active' => in_array($currentPage, $salesPages, true)
    ],
    [
        'title' => 'Product',
        'icon' => 'bi-box-seam',
        'link' => 'manage_product.php',
        'active' => in_array($currentPage, $productPages, true)
    ],
    [
        'title' => 'Customers',
        'icon' => 'bi-people',
        'link' => 'manage_customer.php',
        'active' => in_array($currentPage, $customerPages, true)
    ],
    [
        'title' => 'Users',
        'icon' => 'bi-person-badge',
        'link' => 'manage_user.php',
        'active' => in_array($currentPage, $userPages, true)
    ],
    [
        'title' => 'Profile',
        'icon' => 'bi-person-circle',
        'link' => 'profile.php',
        'active' => ($currentPage === 'profile.php')
    ],
    [
        'title' => 'Settings',
        'icon' => 'bi-gear',
        'link' => 'masters.php',
        'active' => in_array($currentPage, ['masters.php', 'manage_category.php', 'manage_brand.php', 'manaage_unit.php', 'manage_producttype.php'], true)
    ]
];
?>

<aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">

    <!-- Logo -->
    <div class="sidebar-header">
        <a class="brand-mark" href="index.php" aria-label="Dashboard">
            <span class="brand-icon">
                <i class="bi bi-tools"></i>
            </span>

            <span class="brand-copy">
                <span class="brand-title">
                    Sun PowerTools
                </span>
            </span>
        </a>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <?php foreach ($menuItems as $item): ?>
            <a class="nav-link <?= !empty($item['active']) ? 'active' : '' ?>" href="<?= e($item['link']) ?>"
                <?= !empty($item['active']) ? 'aria-current="page"' : '' ?>>
                <span class="nav-icon">
                    <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
                </span>
                <span class="nav-text">
                    <?= e($item['title']) ?>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>