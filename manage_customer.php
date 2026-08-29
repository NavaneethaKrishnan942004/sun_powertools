<?php

$pageTitle = 'Customer Master';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/customer_helper.php';

$search = trim($_GET['search'] ?? '');
$customerTypeFilter = $_GET['customer_type'] ?? '';
$creditAllowedFilter = $_GET['credit_allowed'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Handle Status Toggle Action (Activate / Deactivate)
if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $toggleId = (int) $_GET['id'];

    if ($toggleId > 0 && in_array($action, ['activate', 'deactivate'], true)) {
        $newStatus = ($action === 'activate') ? 1 : 0;
        $updatedBy = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 1;
        $updatedAt = date('Y-m-d H:i:s');

        $stmt = $conn->prepare("
            UPDATE customer_master 
            SET status = :status, updated_by = :updated_by, updated_at = :updated_at 
            WHERE id = :id
        ");
        $stmt->execute([
            ':status' => $newStatus,
            ':updated_by' => $updatedBy,
            ':updated_at' => $updatedAt,
            ':id' => $toggleId
        ]);

        $statusText = ($newStatus === 1) ? 'activated' : 'deactivated';
        header('Location: manage_customer.php?success=' . urlencode("Customer record {$statusText} successfully."));
        exit;
    }
}

// Build Search & Filter SQL Query
$sql = "
    SELECT 
        cm.*,
        creator.user_name AS created_by_name,
        updater.user_name AS updated_by_name
    FROM customer_master cm
    LEFT JOIN user_master creator ON creator.id = cm.created_by
    LEFT JOIN user_master updater ON updater.id = cm.updated_by
    WHERE 1=1
";

$params = [];

if ($search !== '') {
    $sql .= " AND (
        cm.customer_code LIKE :search 
        OR cm.customer_name LIKE :search 
        OR cm.company_name LIKE :search 
        OR cm.mobile_number LIKE :search
    )";
    $params[':search'] = "%{$search}%";
}

if ($customerTypeFilter !== '' && in_array($customerTypeFilter, ['Individual', 'Business'], true)) {
    $sql .= " AND cm.customer_type = :customer_type";
    $params[':customer_type'] = $customerTypeFilter;
}

if ($creditAllowedFilter !== '' && in_array($creditAllowedFilter, ['0', '1'], true)) {
    $sql .= " AND cm.credit_allowed = :credit_allowed";
    $params[':credit_allowed'] = (int) $creditAllowedFilter;
}

if ($statusFilter !== '' && in_array($statusFilter, ['0', '1'], true)) {
    $sql .= " AND cm.status = :status";
    $params[':status'] = (int) $statusFilter;
}

$sql .= " ORDER BY cm.id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';

?>

<main class="main-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Breadcrumb Navigation -->
        <?php
        renderBreadcrumbs([
            ['title' => '', 'icon' => 'bi bi-house-door', 'link' => 'index.php'],
            ['title' => 'Masters', 'link' => 'masters.php'],
            ['title' => 'Customer Master']
        ]);
        ?>

        <!-- PAGE HEADING -->
        <div class="page-heading d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="page-title mb-1">Customer Master</h1>
                <p class="text-muted mb-0">Manage customer accounts, credit limits, and financial balances.</p>
            </div>
            <a href="create_customer.php" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Customer
            </a>
        </div>

        <!-- SUCCESS / ERROR FLASH MESSAGES -->
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

        <!-- SEARCH & FILTER SECTION -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" id="customerFilterForm">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-7">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" id="customerSearch" class="form-control"
                                placeholder="Search by customer code, name, company, or mobile number"
                                value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                        </div>

                        <?php if ($search !== '' || $customerTypeFilter !== '' || $creditAllowedFilter !== '' || $statusFilter !== ''): ?>
                            <div class="col-6 col-md-2">
                                <a href="manage_customer.php" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-x-circle me-1"></i> Clear
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="<?= ($search !== '' || $customerTypeFilter !== '' || $creditAllowedFilter !== '' || $statusFilter !== '') ? 'col-6' : 'col-12' ?> col-md-3">
                            <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="collapse"
                                data-bs-target="#customerFilterSection" aria-expanded="false">
                                <i class="bi bi-funnel me-1"></i> Filter <i class="bi bi-chevron-down ms-1" id="customerFilterIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="collapse mt-3" id="customerFilterSection">
                        <div class="filter-section-box">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Customer Type</label>
                                    <select name="customer_type" class="form-select" onchange="this.form.submit()">
                                        <option value="">All Types</option>
                                        <option value="Individual" <?= $customerTypeFilter === 'Individual' ? 'selected' : '' ?>>Individual</option>
                                        <option value="Business" <?= $customerTypeFilter === 'Business' ? 'selected' : '' ?>>Business</option>
                                    </select>
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label">Credit Allowed</label>
                                    <select name="credit_allowed" class="form-select" onchange="this.form.submit()">
                                        <option value="">All Credit Status</option>
                                        <option value="1" <?= $creditAllowedFilter === '1' ? 'selected' : '' ?>>Credit Allowed</option>
                                        <option value="0" <?= $creditAllowedFilter === '0' ? 'selected' : '' ?>>No Credit</option>
                                    </select>
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select" onchange="this.form.submit()">
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

        <!-- CUSTOMER TABLE -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="category-table-wrapper">
                    <div class="category-table-scroll">
                        <table class="table category-table align-middle mb-0 customer-table">
                            <thead>
                                <tr>
                                    <th>Customer Code</th>
                                    <th>Customer Name</th>
                                    <th>Company Name</th>
                                    <th>Mobile Number</th>
                                    <th>Customer Type</th>
                                    <th>Credit Allowed</th>
                                    <th>Debit / Outstanding</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Updated By</th>
                                    <th class="action-column text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($customers)): ?>
                                    <tr>
                                        <td colspan="11" class="text-center py-5 text-muted">
                                            <i class="bi bi-people fs-2 d-block mb-2"></i>
                                            No customer records found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($customers as $row):
                                        $summary = getCustomerFinancialSummary($conn, (int)$row['id'], $row);
                                    ?>
                                        <tr>
                                            <!-- CODE -->
                                            <td>
                                                <span class="status-dot <?= ((int) $row['status'] === 1) ? 'active' : 'inactive' ?>"
                                                    title="<?= ((int) $row['status'] === 1) ? 'Active' : 'Inactive' ?>"></span>
                                                <strong><?= htmlspecialchars($row['customer_code']) ?></strong>
                                            </td>

                                            <!-- NAME -->
                                            <td>
                                                <a href="view_customer.php?id=<?= (int)$row['id'] ?>" class="fw-semibold text-decoration-none text-body">
                                                    <?= htmlspecialchars($row['customer_name']) ?>
                                                </a>
                                            </td>

                                            <!-- COMPANY -->
                                            <td class="text-muted">
                                                <?= !empty($row['company_name']) ? htmlspecialchars($row['company_name']) : '-' ?>
                                            </td>

                                            <!-- MOBILE -->
                                            <td><?= htmlspecialchars($row['mobile_number']) ?></td>

                                            <!-- TYPE -->
                                            <td>
                                                <span class="badge <?= ($row['customer_type'] === 'Business') ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' ?>">
                                                    <?= htmlspecialchars($row['customer_type']) ?>
                                                </span>
                                            </td>

                                            <!-- CREDIT ALLOWED -->
                                            <td>
                                                <?php if ((int)$row['credit_allowed'] === 1): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle" title="Limit: ₹<?= number_format((float)$row['credit_limit'], 2) ?>">
                                                        Yes (₹<?= number_format((float)$row['credit_limit'], 0) ?>)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">No</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- DEBIT / OUTSTANDING BALANCE -->
                                            <td>
                                                <?= formatCustomerBalance($summary) ?>
                                            </td>

                                            <!-- STATUS -->
                                            <td>
                                                <span class="badge <?= ((int) $row['status'] === 1) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                                    <?= ((int) $row['status'] === 1) ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>

                                            <!-- CREATED BY -->
                                            <td><?= htmlspecialchars($row['created_by_name'] ?? $row['created_by'] ?? '-') ?></td>

                                            <!-- UPDATED BY -->
                                            <td><?= !empty($row['updated_by_name']) ? htmlspecialchars($row['updated_by_name']) : (!empty($row['updated_by']) ? htmlspecialchars($row['updated_by']) : '-') ?></td>

                                            <!-- ACTION -->
                                            <td class="action-column text-end text-nowrap">
                                                <!-- View -->
                                                <a href="view_customer.php?id=<?= (int) $row['id'] ?>"
                                                    class="btn btn-sm btn-outline-secondary" title="View Customer Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>

                                                <!-- Edit -->
                                                <a href="edit_customer.php?id=<?= (int) $row['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary" title="Edit Customer">
                                                    <i class="bi bi-pencil"></i>
                                                </a>

                                                <!-- Activate / Deactivate Toggle -->
                                                <?php if ((int)$row['status'] === 1): ?>
                                                    <a href="manage_customer.php?action=deactivate&id=<?= (int)$row['id'] ?>"
                                                        class="btn btn-sm btn-outline-warning" title="Deactivate Customer"
                                                        onclick="return confirm('Are you sure you want to deactivate customer <?= htmlspecialchars(addslashes($row['customer_name'])) ?>?');">
                                                        <i class="bi bi-slash-circle"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="manage_customer.php?action=activate&id=<?= (int)$row['id'] ?>"
                                                        class="btn btn-sm btn-outline-success" title="Activate Customer">
                                                        <i class="bi bi-check-circle"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterBtn = document.querySelector('[data-bs-target="#customerFilterSection"]');
    const filterIcon = document.getElementById('customerFilterIcon');
    const filterSection = document.getElementById('customerFilterSection');
    if (filterBtn && filterIcon && filterSection) {
        filterSection.addEventListener('shown.bs.collapse', function () {
            filterIcon.classList.replace('bi-chevron-down', 'bi-chevron-up');
        });
        filterSection.addEventListener('hidden.bs.collapse', function () {
            filterIcon.classList.replace('bi-chevron-up', 'bi-chevron-down');
        });
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
