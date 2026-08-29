<?php

$pageTitle = 'View Customer';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/customer_helper.php';
require_once __DIR__ . '/includes/header.php';

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: manage_customer.php?error=' . urlencode('Invalid customer ID.'));
    exit;
}

// Fetch Customer details with User Master joins
$sql = "
    SELECT 
        cm.*,
        creator.user_name AS created_by_name,
        updater.user_name AS updated_by_name
    FROM customer_master cm
    LEFT JOIN user_master creator ON creator.id = cm.created_by
    LEFT JOIN user_master updater ON updater.id = cm.updated_by
    WHERE cm.id = :id
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    echo '
        <main class="main-content">
            <div class="container-fluid px-3 px-lg-4 py-4">
                <div class="alert alert-danger">
                    Customer record not found.
                </div>
                <a href="manage_customer.php" class="btn btn-secondary">
                    Back to Customer Master
                </a>
            </div>
        </main>
    ';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Calculate dynamic Financial Summary
$summary = getCustomerFinancialSummary($conn, $id, $customer);

// Fetch transaction history
$salesTransactions = [];
$rentalTransactions = [];
$paymentTransactions = [];
$adjustmentTransactions = [];

try {
    $stmt = $conn->prepare("
        SELECT ct.*, creator.user_name AS created_by_name
        FROM customer_transactions ct
        LEFT JOIN user_master creator ON creator.id = ct.created_by
        WHERE ct.customer_id = :id
        ORDER BY ct.transaction_date DESC
    ");
    $stmt->execute([':id' => $id]);
    $allTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allTransactions as $t) {
        $type = strtolower($t['transaction_type']);
        if ($type === 'sale') {
            $salesTransactions[] = $t;
        } elseif ($type === 'rental') {
            $rentalTransactions[] = $t;
        } elseif ($type === 'payment') {
            $paymentTransactions[] = $t;
        } elseif ($type === 'return' || $type === 'adjustment') {
            $adjustmentTransactions[] = $t;
        }
    }
} catch (PDOException $e) {
    // Graceful fallback if transaction tables are empty or being initialized
}

?>

<main class="main-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Breadcrumb Navigation -->
        <?php
        renderBreadcrumbs([
            ['title' => '', 'icon' => 'bi bi-house-door', 'link' => 'index.php'],
            ['title' => 'Masters', 'link' => 'masters.php'],
            ['title' => 'Customer Master', 'link' => 'manage_customer.php'],
            ['title' => 'View']
        ]);
        ?>

        <!-- PAGE HEADING -->
        <div class="page-heading d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="page-title mb-1">Customer Profile</h1>
                <p class="text-muted mb-0">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1"><?= htmlspecialchars($customer['customer_code']) ?></span>
                    <?= htmlspecialchars($customer['customer_name']) ?>
                    <?php if (!empty($customer['company_name'])): ?>
                        &bull; <span class="text-muted"><?= htmlspecialchars($customer['company_name']) ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="manage_customer.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
                <a href="edit_customer.php?id=<?= (int)$customer['id'] ?>" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit Customer
                </a>
            </div>
        </div>

        <!-- FINANCIAL SUMMARY HERO METRICS -->
        <div class="card shadow-sm mb-4 border-primary-subtle">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-graph-up-arrow me-2"></i>Financial & Outstanding Summary</h5>
                <span class="badge <?= ($summary['is_settled']) ? 'bg-success' : (($summary['is_debit']) ? 'bg-danger' : 'bg-info') ?> fs-6 px-3 py-2">
                    <?= ($summary['is_settled']) ? 'Account Settled' : (($summary['is_debit']) ? 'Current Outstanding: ₹' . number_format($summary['current_outstanding'], 2) . ' Debit' : 'Credit Balance: ₹' . number_format(abs($summary['current_outstanding']), 2)) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded-3 bg-body">
                            <small class="text-muted text-uppercase fw-semibold d-block mb-1">Opening Balance</small>
                            <h5 class="fw-bold mb-0">₹<?= number_format($summary['opening_balance'], 2) ?></h5>
                            <span class="badge bg-secondary-subtle text-secondary mt-1"><?= htmlspecialchars($summary['opening_balance_type']) ?></span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded-3 bg-body">
                            <small class="text-muted text-uppercase fw-semibold d-block mb-1">Total Sales</small>
                            <h5 class="fw-bold mb-0 text-primary">₹<?= number_format($summary['total_sales'], 2) ?></h5>
                            <small class="text-muted"><?= count($salesTransactions) ?> invoices</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded-3 bg-body">
                            <small class="text-muted text-uppercase fw-semibold d-block mb-1">Total Rentals</small>
                            <h5 class="fw-bold mb-0 text-info">₹<?= number_format($summary['total_rentals'], 2) ?></h5>
                            <small class="text-muted"><?= count($rentalTransactions) ?> contracts</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded-3 bg-body">
                            <small class="text-muted text-uppercase fw-semibold d-block mb-1">Total Payments</small>
                            <h5 class="fw-bold mb-0 text-success">₹<?= number_format($summary['total_payments'], 2) ?></h5>
                            <small class="text-muted"><?= count($paymentTransactions) ?> receipts</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="p-3 border rounded-3 bg-body">
                            <small class="text-muted text-uppercase fw-semibold d-block mb-1">Returns / Adjustments</small>
                            <h5 class="fw-bold mb-0 text-secondary">₹<?= number_format($summary['total_returns'], 2) ?></h5>
                            <small class="text-muted"><?= count($adjustmentTransactions) ?> adjustments</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="p-3 border rounded-3 bg-body">
                            <small class="text-muted text-uppercase fw-semibold d-block mb-1">Credit Limit</small>
                            <h5 class="fw-bold mb-0"><?= ($customer['credit_allowed']) ? '₹' . number_format($summary['credit_limit'], 2) : '<span class="text-muted">Not Allowed</span>' ?></h5>
                            <small class="text-muted"><?= htmlspecialchars($customer['payment_terms'] ?? 'Immediate') ?></small>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="p-3 border rounded-3 bg-body">
                            <small class="text-muted text-uppercase fw-semibold d-block mb-1">Available Credit Limit</small>
                            <h5 class="fw-bold mb-0 <?= ($summary['available_credit'] > 0) ? 'text-success' : 'text-danger' ?>">
                                <?= ($customer['credit_allowed']) ? '₹' . number_format($summary['available_credit'], 2) : '₹0.00' ?>
                            </h5>
                            <small class="text-muted">Dynamic Balance Calculation</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- SECTION 1: CUSTOMER INFORMATION -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Customer Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label text-muted">Customer Code</label>
                                <div class="fw-bold fs-6 text-primary"><?= htmlspecialchars($customer['customer_code']) ?></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted">Status</label>
                                <div>
                                    <?php if ((int)$customer['status'] === 1): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted">Customer Name</label>
                                <div class="fw-semibold fs-6"><?= htmlspecialchars($customer['customer_name']) ?></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted">Customer Type</label>
                                <div>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <?= htmlspecialchars($customer['customer_type']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted">Company Name</label>
                                <div><?= !empty($customer['company_name']) ? htmlspecialchars($customer['company_name']) : '-' ?></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted">Mobile Number</label>
                                <div>
                                    <a href="tel:<?= htmlspecialchars($customer['mobile_number']) ?>" class="text-decoration-none fw-semibold">
                                        <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($customer['mobile_number']) ?>
                                    </a>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted">Alternate Mobile</label>
                                <div><?= !empty($customer['alternate_mobile_number']) ? htmlspecialchars($customer['alternate_mobile_number']) : '-' ?></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted">Email Address</label>
                                <div>
                                    <?php if (!empty($customer['email'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($customer['email']) ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($customer['email']) ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted">GST Number</label>
                                <div><?= !empty($customer['gst_number']) ? '<span class="badge bg-body-tertiary text-body border">' . htmlspecialchars($customer['gst_number']) . '</span>' : '-' ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: LOCATION & BILLING ADDRESS -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-geo-alt me-2 text-primary"></i>Location & Addresses</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label text-muted">Address</label>
                                <div><?= !empty($customer['address']) ? nl2br(htmlspecialchars($customer['address'])) : '-' ?></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label text-muted">Area</label>
                                <div><?= !empty($customer['area']) ? htmlspecialchars($customer['area']) : '-' ?></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label text-muted">City</label>
                                <div><?= !empty($customer['city']) ? htmlspecialchars($customer['city']) : '-' ?></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label text-muted">District</label>
                                <div><?= !empty($customer['district']) ? htmlspecialchars($customer['district']) : '-' ?></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label text-muted">Pincode</label>
                                <div><?= !empty($customer['pincode']) ? htmlspecialchars($customer['pincode']) : '-' ?></div>
                            </div>
                            <div class="col-12"><hr class="my-2"></div>
                            <div class="col-12 col-md-6">
                                <label class="form-label text-muted fw-semibold">Billing Address</label>
                                <div class="p-2 bg-body-tertiary rounded border small">
                                    <?= !empty($customer['billing_address']) ? nl2br(htmlspecialchars($customer['billing_address'])) : '<span class="text-muted">Not specified</span>' ?>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label text-muted fw-semibold">Shipping Address</label>
                                <div class="p-2 bg-body-tertiary rounded border small">
                                    <?= !empty($customer['shipping_address']) ? nl2br(htmlspecialchars($customer['shipping_address'])) : '<span class="text-muted">Not specified</span>' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 3: TRANSACTION HISTORY TABS -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="customerTransactionTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold" id="sales-tab" data-bs-toggle="tab" data-bs-target="#salesTabPane" type="button" role="tab">
                            <i class="bi bi-cart me-1"></i> Sales (<?= count($salesTransactions) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="rentals-tab" data-bs-toggle="tab" data-bs-target="#rentalsTabPane" type="button" role="tab">
                            <i class="bi bi-tools me-1"></i> Rentals (<?= count($rentalTransactions) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="payments-tab" data-bs-toggle="tab" data-bs-target="#paymentsTabPane" type="button" role="tab">
                            <i class="bi bi-cash-stack me-1"></i> Payments (<?= count($paymentTransactions) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="returns-tab" data-bs-toggle="tab" data-bs-target="#returnsTabPane" type="button" role="tab">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Returns / Adjustments (<?= count($adjustmentTransactions) ?>)
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="customerTabContent">

                    <!-- 1. SALES TAB -->
                    <div class="tab-pane fade show active" id="salesTabPane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Invoice Number</th>
                                        <th>Sale Date</th>
                                        <th>Total Amount</th>
                                        <th>Paid Amount</th>
                                        <th>Balance</th>
                                        <th>Payment Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($salesTransactions)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="bi bi-receipt fs-3 d-block mb-1"></i>
                                                No sales transactions recorded yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($salesTransactions as $st): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($st['reference_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($st['transaction_date']) ?></td>
                                                <td>₹<?= number_format((float)$st['total_amount'], 2) ?></td>
                                                <td>₹<?= number_format((float)$st['paid_amount'], 2) ?></td>
                                                <td class="text-danger fw-semibold">₹<?= number_format((float)$st['debit_amount'], 2) ?></td>
                                                <td><span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($st['payment_status'] ?? 'Unpaid') ?></span></td>
                                                <td class="text-end"><button class="btn btn-sm btn-outline-secondary" title="View Invoice"><i class="bi bi-eye"></i></button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 2. RENTALS TAB -->
                    <div class="tab-pane fade" id="rentalsTabPane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Rental Number</th>
                                        <th>Rental Date</th>
                                        <th>Return Due Date</th>
                                        <th>Rental Amount</th>
                                        <th>Paid Amount</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rentalTransactions)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                <i class="bi bi-tools fs-3 d-block mb-1"></i>
                                                No rental contracts recorded yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($rentalTransactions as $rt): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($rt['reference_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($rt['transaction_date']) ?></td>
                                                <td><?= htmlspecialchars($rt['due_date'] ?? '-') ?></td>
                                                <td>₹<?= number_format((float)$rt['total_amount'], 2) ?></td>
                                                <td>₹<?= number_format((float)$rt['paid_amount'], 2) ?></td>
                                                <td class="text-danger fw-semibold">₹<?= number_format((float)$rt['debit_amount'], 2) ?></td>
                                                <td><span class="badge bg-info-subtle text-info"><?= htmlspecialchars($rt['payment_status'] ?? 'Active') ?></span></td>
                                                <td class="text-end"><button class="btn btn-sm btn-outline-secondary" title="View Rental"><i class="bi bi-eye"></i></button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 3. PAYMENTS TAB -->
                    <div class="tab-pane fade" id="paymentsTabPane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Payment Number</th>
                                        <th>Payment Date</th>
                                        <th>Payment Method</th>
                                        <th>Amount</th>
                                        <th>Reference Number</th>
                                        <th>Created By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($paymentTransactions)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="bi bi-credit-card fs-3 d-block mb-1"></i>
                                                No payment receipts recorded yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($paymentTransactions as $pt): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($pt['reference_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($pt['transaction_date']) ?></td>
                                                <td><?= htmlspecialchars($pt['payment_method'] ?? 'Cash') ?></td>
                                                <td class="text-success fw-semibold">₹<?= number_format((float)$pt['credit_amount'], 2) ?></td>
                                                <td><?= htmlspecialchars($pt['notes'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($pt['created_by_name'] ?? 'Admin') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 4. RETURNS / ADJUSTMENTS TAB -->
                    <div class="tab-pane fade" id="returnsTabPane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Reference Number</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Reason</th>
                                        <th>Created By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($adjustmentTransactions)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="bi bi-arrow-counterclockwise fs-3 d-block mb-1"></i>
                                                No returns or credit adjustments recorded yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($adjustmentTransactions as $at): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($at['reference_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($at['transaction_date']) ?></td>
                                                <td><span class="badge bg-secondary-subtle text-secondary"><?= ucfirst(htmlspecialchars($at['transaction_type'])) ?></span></td>
                                                <td class="text-success fw-semibold">₹<?= number_format((float)$at['credit_amount'], 2) ?></td>
                                                <td><?= htmlspecialchars($at['reason'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($at['created_by_name'] ?? 'Admin') ?></td>
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

        <!-- SECTION 4: AUDIT INFORMATION -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Audit Information</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Created By</label>
                        <div><?= htmlspecialchars($customer['created_by_name'] ?? $customer['created_by'] ?? '-') ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Created At</label>
                        <div><?= htmlspecialchars($customer['created_at']) ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Updated By</label>
                        <div><?= !empty($customer['updated_by_name']) ? htmlspecialchars($customer['updated_by_name']) : (!empty($customer['updated_by']) ? htmlspecialchars($customer['updated_by']) : '-') ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted">Updated At</label>
                        <div><?= !empty($customer['updated_at']) ? htmlspecialchars($customer['updated_at']) : '-' ?></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
