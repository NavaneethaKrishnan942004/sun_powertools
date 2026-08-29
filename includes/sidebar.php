<?php

$currentPage = basename($_SERVER['PHP_SELF']);

$menuItems = [

    [
        'title' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'link' => 'index.php'
    ],

    [
        'title' => 'Product',
        'icon' => 'bi bi-box-seam',
        'link' => 'manage_product.php'
    ],
    [
        'title' => 'Customers',
        'icon' => 'bi-people',
        'link' => 'manage_customer.php'
    ],
    [
        'title' => 'Users',
        'icon' => 'bi-person-badge',
        'link' => 'manage_user.php'
    ],
    [
        'title' => 'Profile',
        'icon' => 'bi-person-badge',
        'link' => 'profile.php'
    ],

    [
        'title' => 'Settings',
        'icon' => 'bi-gear',
        'link' => 'masters.php'
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
            <a class="nav-link <?= $currentPage === $item['link'] ? 'active' : '' ?>" href="<?= e($item['link']) ?>"
                <?= $currentPage === $item['link'] ? 'aria-current="page"' : '' ?>>
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