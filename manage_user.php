<?php
$pageTitle = 'User Master';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$roleFilter = $_GET['role'] ?? '';

$sql = "SELECT * FROM user_master WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (user_id LIKE :search OR user_name LIKE :search OR user_email LIKE :search OR user_phone LIKE :search)";
    $params[':search'] = "%{$search}%";
}
if (in_array($statusFilter, ['0', '1'], true)) {
    $sql .= " AND status = :status";
    $params[':status'] = (int) $statusFilter;
}
if (in_array($roleFilter, ['user', 'admin'], true)) {
    $sql .= " AND role = :role";
    $params[':role'] = $roleFilter;
}
$sql .= " ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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


        <div class="page-heading d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="page-title mb-1">User Master</h1>
                <p class="text-muted mb-0">Manage application users and access roles.</p>
            </div>
            <a href="create_user.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create User</a>
        </div>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show auto-hide-alert">
                <span><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <div class="alert-time-line"></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show auto-hide-alert">
                <span><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($_GET['error']) ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <div class="alert-time-line"></div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" id="userFilterForm">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-7">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" id="userSearch" class="form-control"
                                placeholder="Search ID, name, mail or phone" value="<?= htmlspecialchars($search) ?>"
                                autocomplete="off">
                        </div>
                        <?php if ($search !== '' || $statusFilter !== '' || $roleFilter !== ''): ?>
                            <div class="col-6 col-md-2">
                                <a href="manage_user.php" class="btn btn-outline-secondary w-100"><i
                                        class="bi bi-x-circle me-1"></i>Clear</a>
                            </div>
                        <?php endif; ?>
                        <div
                            class="<?= ($search !== '' || $statusFilter !== '' || $roleFilter !== '') ? 'col-6' : 'col-12' ?> col-md-3">
                            <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="collapse"
                                data-bs-target="#userFilterSection" aria-expanded="false">
                                <i class="bi bi-funnel me-1"></i>Filter <i class="bi bi-chevron-down ms-1"
                                    id="userFilterIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="collapse mt-3" id="userFilterSection">
                        <div class="filter-section-box">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Role</label>
                                    <select name="role" id="userRoleFilter" class="form-select">
                                        <option value="">All Roles</option>
                                        <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin
                                        </option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="status" id="userStatusFilter" class="form-select">
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
                        <table class="table category-table align-middle mb-0 user-table">
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>User Name</th>
                                    <th>User Mail</th>
                                    <th>Phone No</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Updated At</th>
                                    <th class="action-column text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$users): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted"><i
                                                class="bi bi-people fs-2 d-block mb-2"></i>No users found.</td>
                                    </tr>
                                <?php else:
                                    foreach ($users as $row): ?>
                                        <tr>
                                            <td><span
                                                    class="status-dot <?= (int) $row['status'] === 1 ? 'active' : 'inactive' ?>"
                                                    title="<?= (int) $row['status'] === 1 ? 'Active' : 'Inactive' ?>"></span><strong><?= htmlspecialchars($row['user_id']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($row['user_name']) ?></td>
                                            <td><?= htmlspecialchars($row['user_email']) ?></td>
                                            <td><?= htmlspecialchars($row['user_phone']) ?></td>
                                            <td><span
                                                    class="badge <?= $row['role'] === 'admin' ? 'text-bg-primary' : 'text-bg-secondary' ?>"><?= ucfirst(htmlspecialchars($row['role'])) ?></span>
                                            </td>
                                            <td><span
                                                    class="badge <?= (int) $row['status'] === 1 ? 'text-bg-success' : 'text-bg-danger' ?>"><?= (int) $row['status'] === 1 ? 'Active' : 'Inactive' ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                                            <td><?= !empty($row['updated_at']) ? htmlspecialchars($row['updated_at']) : '-' ?>
                                            </td>
                                            <td class="action-column text-end text-nowrap">
                                                <a href="view_user.php?id=<?= (int) $row['id'] ?>"
                                                    class="btn btn-sm btn-outline-secondary" title="View"><i
                                                        class="bi bi-eye"></i></a>
                                                <a href="edit_user.php?id=<?= (int) $row['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary" title="Edit"><i
                                                        class="bi bi-pencil"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>