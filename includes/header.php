<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';
requireLogin();

/*
|--------------------------------------------------------------------------
| Logged-in User Details
|--------------------------------------------------------------------------
*/

$loggedInUsername = 'User';
$loggedInRole = 'User';
$loggedInAvatar = '/sun_powertools/assets/images/avatar/avatar.jpg';

/*
|--------------------------------------------------------------------------
| Get Logged-in User
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                user_name,
                role,
                avatar
            FROM user_master
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $_SESSION['user_id']
        ]);

        $loggedInUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($loggedInUser) {
            if (!empty($loggedInUser['user_name'])) {
                $loggedInUsername = $loggedInUser['user_name'];
            }

            if (!empty($loggedInUser['role'])) {
                $loggedInRole = ucfirst(strtolower($loggedInUser['role']));
            }

            if (!empty($loggedInUser['avatar'])) {
                $loggedInAvatar = '/sun_powertools/uploads/avatars/' . basename($loggedInUser['avatar']);
            }
        }
    } catch (PDOException $e) {
        $loggedInUsername = 'User';
        $loggedInRole = 'User';
        $loggedInAvatar = '/sun_powertools/assets/images/avatar/avatar.jpg';
    }
}

/*
|--------------------------------------------------------------------------
| Escape Function
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Reusable Breadcrumbs Renderer
|--------------------------------------------------------------------------
*/

function renderBreadcrumbs(array $items): void
{
    if (empty($items)) {
        return;
    }
    echo '<nav aria-label="breadcrumb" class="admin-breadcrumb-nav">';
    echo '<ol class="admin-breadcrumb">';
    $total = count($items);
    $i = 0;
    foreach ($items as $item) {
        $i++;
        $isLast = ($i === $total);
        $title = $item['title'] ?? '';
        $link = $item['link'] ?? null;
        $icon = $item['icon'] ?? null;

        if ($isLast || empty($link)) {
            echo '<li class="breadcrumb-item active" aria-current="page">';
            if ($icon) {
                echo '<i class="' . e($icon) . ' me-1"></i>';
            }
            if ($title !== '') {
                echo e($title);
            }
            echo '</li>';
        } else {
            echo '<li class="breadcrumb-item">';
            echo '<a href="' . e($link) . '">';
            if ($icon) {
                echo '<i class="' . e($icon) . '"></i>';
            }
            if ($title !== '') {
                if ($icon) {
                    echo ' <span class="d-none d-sm-inline">' . e($title) . '</span>';
                } else {
                    echo e($title);
                }
            }
            echo '</a>';
            echo '<i class="bi bi-chevron-right breadcrumb-separator" aria-hidden="true"></i>';
            echo '</li>';
        }
    }
    echo '</ol>';
    echo '</nav>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> | Sun PowerTools ERP</title>

    <!-- Synchronous Anti-FOUC Theme Script -->
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('admin-theme');
                var theme = (saved === 'dark' || saved === 'light') ? saved :
                    (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', theme);
                document.documentElement.setAttribute('data-bs-theme', theme);
            } catch (e) {}
        })();
    </script>

    <!-- Modern Typography: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS Framework & Icons -->
    <link rel="stylesheet" href="/sun_powertools/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/sun_powertools/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="/sun_powertools/assets/css/master.css">
    <link rel="stylesheet" href="/sun_powertools/assets/css/style.css">
</head>
<body>

    <div class="admin-shell">

        <!-- Mobile Sidebar Backdrop -->
        <div class="sidebar-backdrop" data-sidebar-close></div>

        <!-- Sidebar Navigation -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="admin-main">

            <!-- Navbar -->
            <nav class="navbar admin-navbar navbar-expand">
                <div class="container-fluid px-3 px-lg-4">

                    <!-- Left: Sidebar Toggle & Brand Tag -->
                    <div class="d-flex align-items-center gap-2 gap-sm-3">
                        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-label="Toggle sidebar" aria-expanded="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </button>

                        <div class="d-none d-md-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill fw-semibold">
                                <i class="bi bi-tools me-1"></i> Sun PowerTools
                            </span>
                        </div>
                    </div>

                    <!-- Right: Actions Cluster (Theme Toggle + Profile) -->
                    <div class="navbar-actions ms-auto d-flex align-items-center gap-2 gap-sm-3">

                        <!-- THEME TOGGLE BUTTON -->
                        <button
                            type="button"
                            class="theme-toggle"
                            id="themeToggle"
                            data-theme-toggle
                            aria-label="Switch color theme"
                            title="Switch color theme"
                        >
                            <i class="bi bi-moon-stars-fill" id="themeIcon" data-theme-icon></i>
                        </button>

                        <!-- PROFILE DROPDOWN -->
                        <div class="dropdown profile-dropdown">
                            <button class="profile-button" type="button" id="profileButton" aria-expanded="false" aria-label="User Profile Menu">
                                <div class="avatar-wrapper position-relative">
                                    <img class="avatar-img avatar-sm" src="<?= e($loggedInAvatar) ?>"
                                        alt="<?= e($loggedInUsername) ?>"
                                        onerror="this.src='/sun_powertools/assets/images/avatar/avatar.jpg';">
                                    <span class="status-indicator online"></span>
                                </div>

                                <div class="profile-info d-none d-md-flex flex-column text-start">
                                    <span class="profile-name"><?= e($loggedInUsername) ?></span>
                                    <span class="profile-role"><?= e($loggedInRole) ?></span>
                                </div>

                                <i class="bi bi-chevron-down profile-arrow ms-1 small"></i>
                            </button>

                            <!-- Profile Dropdown Menu -->
                            <ul class="dropdown-menu dropdown-menu-end profile-dropdown-menu shadow-lg" id="profileMenu">
                                <!-- Profile -->
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="/sun_powertools/profile.php">
                                        <span class="dropdown-item-icon bg-primary-subtle text-primary">
                                            <i class="bi bi-person-fill"></i>
                                        </span>
                                        <span>My Profile</span>
                                    </a>
                                </li>

                                <li><hr class="dropdown-divider my-1"></li>

                                <!-- Sign Out -->
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" href="/sun_powertools/logout.php">
                                        <span class="dropdown-item-icon bg-danger-subtle text-danger">
                                            <i class="bi bi-box-arrow-right"></i>
                                        </span>
                                        <span>Sign Out</span>
                                    </a>
                                </li>

                            </ul>
                        </div>

                    </div>

                </div>
            </nav>

            <!-- Page Content Starts Here -->