<?php

$pageTitle = 'Create Sales Note';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/includes/customer_helper.php';
require_once __DIR__ . '/includes/sales_note_helper.php';

$conn = $pdo;
$userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

$errors = [];
$successMessage = '';

// Pre-fill default values
$salesNoteNo = generateSalesNoteNumber($conn);
$salesDate = date('Y-m-d');
$salesTime = date('H:i');
$selectedCustomerId = (int)($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
$paymentType = $_POST['payment_type'] ?? 'Cash';
$notes = trim($_POST['notes'] ?? '');
$creditOverride = isset($_POST['credit_override']) ? 1 : 0;
$otherCharges = (float)($_POST['other_charges'] ?? 0.00);

/*
|--------------------------------------------------------------------------
| Handle Sales Note Creation Form Submission
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $salesDate = trim($_POST['sales_date'] ?? date('Y-m-d'));
    $salesTime = trim($_POST['sales_time'] ?? date('H:i'));
    $paymentType = trim($_POST['payment_type'] ?? 'Cash');
    $itemsInput = $_POST['items'] ?? [];
    $inputPaidAmount = isset($_POST['paid_amount']) ? (float)$_POST['paid_amount'] : 0.00;
    $inputOtherCharges = isset($_POST['other_charges']) ? max(0.00, (float)$_POST['other_charges']) : 0.00;
    $notes = trim($_POST['notes'] ?? '');
    $creditOverride = isset($_POST['credit_override']) ? 1 : 0;

    // 1. Validate Customer
    if ($customerId <= 0) {
        $errors[] = 'Please select a valid customer.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM customer_master WHERE id = :id AND status = 1 LIMIT 1");
        $stmt->execute([':id' => $customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            $errors[] = 'Selected customer does not exist or is inactive.';
        }
    }

    // 2. Validate Items
    if (empty($itemsInput) || !is_array($itemsInput)) {
        $errors[] = 'Please add at least one product to the sales note.';
    }

    $validatedItems = [];
    $serverSubtotal = 0.00;
    $serverTotalDiscount = 0.00;
    $serverTotalTax = 0.00;

    if (empty($errors)) {
        foreach ($itemsInput as $index => $itemData) {
            $productId = (int) ($itemData['product_id'] ?? 0);
            $qty = (float) ($itemData['quantity'] ?? 0);
            $itemDiscount = max(0.00, (float) ($itemData['discount'] ?? 0.00));
            $itemTaxPercent = max(0.00, (float) ($itemData['tax_percent'] ?? 0.00));

            if ($productId <= 0 || $qty <= 0) {
                $errors[] = "Item #" . ($index + 1) . ": Product and quantity must be greater than zero.";
                continue;
            }

            // Fetch product from DB to verify price and stock
            $pStmt = $conn->prepare("
                SELECT p.*, u.unit_name 
                FROM product_master p
                LEFT JOIN unit_master u ON u.id = p.sale_unit
                WHERE p.id = :id AND p.status = 1 AND p.sale_available = 1 
                LIMIT 1
            ");
            $pStmt->execute([':id' => $productId]);
            $product = $pStmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $errors[] = "Item #" . ($index + 1) . ": Product is not available for sale.";
                continue;
            }

            $availableStock = (float) ($product['stock_quantity'] ?? 0);
            if ($qty > $availableStock) {
                $errors[] = "Insufficient stock for '{$product['product_name']}'. Available: {$availableStock}, Requested: {$qty}.";
                continue;
            }

            $unitPrice = (float) ($product['selling_price'] ?? 0.00);
            $lineSubtotal = $qty * $unitPrice;
            $lineDiscount = min($lineSubtotal, $itemDiscount);
            $taxableAmount = max(0.00, $lineSubtotal - $lineDiscount);
            $lineTax = ($taxableAmount * $itemTaxPercent) / 100.0;
            $lineTotal = $taxableAmount + $lineTax;

            $serverSubtotal += $lineSubtotal;
            $serverTotalDiscount += $lineDiscount;
            $serverTotalTax += $lineTax;

            $validatedItems[] = [
                'product_id' => $product['id'],
                'product_code' => $product['product_code'],
                'product_name' => $product['product_name'],
                'unit_name' => $product['unit_name'] ?? 'Unit',
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount' => $lineDiscount,
                'tax_percent' => $itemTaxPercent,
                'tax_amount' => $lineTax,
                'line_total' => $lineTotal
            ];
        }
    }

    if (empty($errors)) {
        // Calculate Final Totals
        $grandTotal = max(0.00, ($serverSubtotal - $serverTotalDiscount) + $serverTotalTax + $inputOtherCharges);

        // Payment Calculation
        if (in_array($paymentType, ['Cash', 'UPI', 'Card', 'Bank Transfer'], true)) {
            $paidAmount = $grandTotal;
            $creditAmount = 0.00;
        } elseif ($paymentType === 'Credit') {
            $paidAmount = 0.00;
            $creditAmount = $grandTotal;
        } elseif ($paymentType === 'Mixed') {
            $paidAmount = min($grandTotal, max(0.00, $inputPaidAmount));
            $creditAmount = max(0.00, $grandTotal - $paidAmount);
        } else {
            $paidAmount = $grandTotal;
            $creditAmount = 0.00;
            $paymentType = 'Cash';
        }

        // Customer Financial Summary & Credit Limit Check
        $fSummary = getCustomerFinancialSummary($conn, $customerId, $customer);
        $prevOutstanding = (float) $fSummary['current_outstanding'];
        $creditLimit = (float) $customer['credit_limit'];
        $creditAllowed = (int) $customer['credit_allowed'];
        $newOutstanding = $prevOutstanding + $creditAmount;

        // Credit Limit Validation
        if ($creditAmount > 0.001) {
            if ($creditAllowed === 0) {
                $errors[] = "Credit is not allowed for customer '{$customer['customer_name']}'. Please select full payment method (Cash / UPI / Card).";
            } else {
                $creditEval = evaluateCreditStatus($newOutstanding, $creditLimit, $creditAllowed);
                if ($creditEval['is_exceeded']) {
                    if (!$creditOverride) {
                        $availableCredit = max(0.00, $creditLimit - $prevOutstanding);
                        $errors[] = "Credit limit exceeded! Customer Credit Limit: ₹" . number_format($creditLimit, 2) . 
                                    ", Current Outstanding: ₹" . number_format($prevOutstanding, 2) . 
                                    ", Available Credit: ₹" . number_format($availableCredit, 2) . 
                                    ", Requested Credit: ₹" . number_format($creditAmount, 2) . 
                                    ". Please reduce credit amount or check 'Manager Override'.";
                    }
                }
            }
        }

        $finalCreditEval = evaluateCreditStatus($newOutstanding, $creditLimit, $creditAllowed);
        $creditStatus = $finalCreditEval['status'];

        // Execute Database Transaction
        if (empty($errors)) {
            try {
                $conn->beginTransaction();

                // Regenerate note number inside transaction for concurrency safety
                $finalNoteNo = generateSalesNoteNumber($conn);

                // 1. Insert Sales Note
                $stmtSN = $conn->prepare("
                    INSERT INTO sales_notes (
                        sales_note_no, customer_id, sales_date, sales_time, payment_type,
                        subtotal, discount, tax, other_charges, total_amount,
                        paid_amount, credit_amount, previous_outstanding, new_outstanding,
                        credit_limit, credit_status, credit_override, notes, status,
                        created_by, created_at
                    ) VALUES (
                        :sales_note_no, :customer_id, :sales_date, :sales_time, :payment_type,
                        :subtotal, :discount, :tax, :other_charges, :total_amount,
                        :paid_amount, :credit_amount, :previous_outstanding, :new_outstanding,
                        :credit_limit, :credit_status, :credit_override, :notes, 1,
                        :created_by, NOW()
                    )
                ");

                $stmtSN->execute([
                    ':sales_note_no' => $finalNoteNo,
                    ':customer_id' => $customerId,
                    ':sales_date' => $salesDate,
                    ':sales_time' => $salesTime,
                    ':payment_type' => $paymentType,
                    ':subtotal' => $serverSubtotal,
                    ':discount' => $serverTotalDiscount,
                    ':tax' => $serverTotalTax,
                    ':other_charges' => $inputOtherCharges,
                    ':total_amount' => $grandTotal,
                    ':paid_amount' => $paidAmount,
                    ':credit_amount' => $creditAmount,
                    ':previous_outstanding' => $prevOutstanding,
                    ':new_outstanding' => $newOutstanding,
                    ':credit_limit' => $creditLimit,
                    ':credit_status' => $creditStatus,
                    ':credit_override' => $creditOverride,
                    ':notes' => $notes ?: null,
                    ':created_by' => $userId
                ]);

                $salesNoteId = (int) $conn->lastInsertId();

                // 2. Insert Items & Update Stock
                $stmtItem = $conn->prepare("
                    INSERT INTO sales_note_items (
                        sales_note_id, product_id, product_code, product_name, unit_name,
                        quantity, unit_price, discount, tax_percent, tax_amount, line_total,
                        created_at
                    ) VALUES (
                        :sales_note_id, :product_id, :product_code, :product_name, :unit_name,
                        :quantity, :unit_price, :discount, :tax_percent, :tax_amount, :line_total,
                        NOW()
                    )
                ");

                $stmtDeductStock = $conn->prepare("
                    UPDATE product_master 
                    SET stock_quantity = stock_quantity - :qty 
                    WHERE id = :product_id
                ");

                foreach ($validatedItems as $item) {
                    $stmtItem->execute([
                        ':sales_note_id' => $salesNoteId,
                        ':product_id' => $item['product_id'],
                        ':product_code' => $item['product_code'],
                        ':product_name' => $item['product_name'],
                        ':unit_name' => $item['unit_name'],
                        ':quantity' => $item['quantity'],
                        ':unit_price' => $item['unit_price'],
                        ':discount' => $item['discount'],
                        ':tax_percent' => $item['tax_percent'],
                        ':tax_amount' => $item['tax_amount'],
                        ':line_total' => $item['line_total']
                    ]);

                    $stmtDeductStock->execute([
                        ':qty' => $item['quantity'],
                        ':product_id' => $item['product_id']
                    ]);
                }

                // 3. Record in customer_transactions ledger
                $paymentStatus = ($paidAmount >= $grandTotal) ? 'Paid' : (($paidAmount > 0.001) ? 'Partial' : 'Unpaid');
                
                $stmtTrans = $conn->prepare("
                    INSERT INTO customer_transactions (
                        customer_id, transaction_type, reference_number, transaction_date,
                        total_amount, paid_amount, debit_amount, credit_amount,
                        payment_method, payment_status, reason, notes,
                        created_by, created_at
                    ) VALUES (
                        :customer_id, 'sale', :reference_number, :transaction_date,
                        :total_amount, :paid_amount, :debit_amount, 0.00,
                        :payment_method, :payment_status, :reason, :notes,
                        :created_by, NOW()
                    )
                ");

                $stmtTrans->execute([
                    ':customer_id' => $customerId,
                    ':reference_number' => $finalNoteNo,
                    ':transaction_date' => $salesDate . ' ' . $salesTime . ':00',
                    ':total_amount' => $grandTotal,
                    ':paid_amount' => $paidAmount,
                    ':debit_amount' => $creditAmount, // Unpaid credit portion increases customer outstanding
                    ':payment_method' => $paymentType,
                    ':payment_status' => $paymentStatus,
                    ':reason' => 'Sales Note ' . $finalNoteNo,
                    ':notes' => $notes ?: null,
                    ':created_by' => $userId
                ]);

                $conn->commit();

                header("Location: view_sales_note.php?id={$salesNoteId}&success=" . urlencode("Sales Note {$finalNoteNo} created successfully!"));
                exit;

            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $errors[] = 'Failed to save Sales Note: ' . $e->getMessage();
            }
        }
    }
}

// Fetch active products for selection
$productsList = $conn->query("
    SELECT 
        p.id, p.product_code, p.product_name, p.selling_price, p.stock_quantity, 
        p.discount_allowed, p.discount_percent,
        c.category_name, b.brand_name, u.unit_name
    FROM product_master p
    LEFT JOIN category_master c ON c.id = p.category_id
    LEFT JOIN brand_master b ON b.id = p.brand_id
    LEFT JOIN unit_master u ON u.id = p.sale_unit
    WHERE p.status = 1 AND p.sale_available = 1
    ORDER BY p.product_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch active customers
$customersList = $conn->query("
    SELECT id, customer_code, customer_name, mobile_number, credit_allowed, credit_limit 
    FROM customer_master 
    WHERE status = 1 
    ORDER BY customer_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<main class="main-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Breadcrumb Navigation -->
        <?php
        renderBreadcrumbs([
            ['title' => '', 'icon' => 'bi bi-house-door', 'link' => 'index.php'],
            ['title' => 'Sales Notes', 'link' => 'manage_sales_note.php'],
            ['title' => 'Create Sales Note']
        ]);
        ?>

        <!-- PAGE TITLE -->
        <div class="page-heading d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
            <div>
                <h1 class="page-title mb-1">
                    <i class="bi bi-cart-plus text-primary me-2"></i>Create Sales Note
                </h1>
                <p class="text-muted mb-0">Record a new sale, validate real-time stock & manage customer credit.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="manage_sales_note.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Sales Notes
                </a>
            </div>
        </div>

        <!-- VALIDATION ERRORS -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                    <strong class="fs-6">Please correct the following errors:</strong>
                </div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- MAIN SALES NOTE FORM -->
        <form method="POST" action="create_sales_note.php" id="salesNoteForm" novalidate>
            <div class="row g-4">

                <!-- LEFT COLUMN: CUSTOMER SELECTION & PRODUCT ITEMS -->
                <div class="col-12 col-xl-8">

                    <!-- 1. CUSTOMER & NOTE META CARD -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-header bg-surface py-3 px-4 border-bottom">
                            <h5 class="fw-bold mb-0 text-primary">
                                <i class="bi bi-person-badge me-2"></i>1. Customer & Sales Info
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <!-- Note Number (Auto-generated) -->
                                <div class="col-12 col-sm-6 col-md-4">
                                    <label class="form-label small fw-semibold">Sales Note #</label>
                                    <input type="text" class="form-control bg-surface-soft fw-bold text-primary" value="<?= htmlspecialchars($salesNoteNo) ?>" readonly>
                                    <small class="text-muted">Auto-generated</small>
                                </div>

                                <!-- Date -->
                                <div class="col-6 col-sm-6 col-md-4">
                                    <label class="form-label small fw-semibold">Sales Date <span class="text-danger">*</span></label>
                                    <input type="date" name="sales_date" id="salesDate" class="form-control" value="<?= htmlspecialchars($salesDate) ?>" required>
                                </div>

                                <!-- Time -->
                                <div class="col-6 col-sm-6 col-md-4">
                                    <label class="form-label small fw-semibold">Sales Time</label>
                                    <input type="time" name="sales_time" id="salesTime" class="form-control" value="<?= htmlspecialchars($salesTime) ?>" required>
                                </div>

                                <!-- Customer Selection -->
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Select Customer <span class="text-danger">*</span></label>
                                    <select name="customer_id" id="customerSelect" class="form-select select2-customer" required>
                                        <option value="">-- Choose Customer from Master --</option>
                                        <?php foreach ($customersList as $c): ?>
                                            <option value="<?= (int)$c['id'] ?>" <?= $selectedCustomerId === (int)$c['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['customer_code'] . ' - ' . $c['customer_name'] . ' (' . $c['mobile_number'] . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- DYNAMIC CUSTOMER CREDIT PROFILE WIDGET -->
                            <div id="customerProfileBox" class="mt-4 p-3 rounded-3 border bg-surface-soft d-none">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
                                    <div>
                                        <h6 class="fw-bold mb-0 text-body" id="dispCustName">Customer Name</h6>
                                        <span class="text-muted small" id="dispCustCode">CUS-001</span> &bull;
                                        <span class="text-muted small" id="dispCustMobile">9876543210</span>
                                    </div>
                                    <div id="dispCreditBadge">
                                        <span class="badge bg-success-subtle text-success">Credit Allowed</span>
                                    </div>
                                </div>

                                <div class="row g-2 text-center">
                                    <!-- Credit Limit -->
                                    <div class="col-6 col-sm-3">
                                        <div class="p-2 bg-surface rounded border">
                                            <span class="text-muted small d-block">Credit Limit</span>
                                            <strong class="fs-6 text-body" id="dispCreditLimit">₹0.00</strong>
                                        </div>
                                    </div>
                                    <!-- Existing Outstanding -->
                                    <div class="col-6 col-sm-3">
                                        <div class="p-2 bg-surface rounded border">
                                            <span class="text-muted small d-block">Existing Outstanding</span>
                                            <strong class="fs-6 text-danger" id="dispPrevOutstanding">₹0.00</strong>
                                        </div>
                                    </div>
                                    <!-- Available Credit -->
                                    <div class="col-6 col-sm-3">
                                        <div class="p-2 bg-surface rounded border">
                                            <span class="text-muted small d-block">Available Credit</span>
                                            <strong class="fs-6 text-success" id="dispAvailableCredit">₹0.00</strong>
                                        </div>
                                    </div>
                                    <!-- New Projected Outstanding -->
                                    <div class="col-6 col-sm-3">
                                        <div class="p-2 bg-surface rounded border">
                                            <span class="text-muted small d-block">New Outstanding</span>
                                            <strong class="fs-6 text-primary" id="dispNewOutstanding">₹0.00</strong>
                                        </div>
                                    </div>
                                </div>

                                <!-- Customer Address & Terms -->
                                <div class="mt-2 small text-muted d-flex flex-wrap justify-content-between">
                                    <span><i class="bi bi-geo-alt me-1"></i><span id="dispCustAddress">Address</span></span>
                                    <span><i class="bi bi-clock me-1"></i>Terms: <span id="dispPaymentTerms" class="fw-semibold">Immediate</span></span>
                                </div>

                                <!-- Real-time Credit Warning Box -->
                                <div id="creditAlertBanner" class="alert alert-warning d-flex align-items-center gap-2 mt-3 mb-0 d-none" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                                    <div id="creditAlertMessage" class="small fw-medium">Credit Limit Message</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. PRODUCT SELECTION & ITEMS LIST CARD -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-header bg-surface py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 text-primary">
                                <i class="bi bi-box-seam me-2"></i>2. Product Items Selection
                            </h5>
                            <span class="badge bg-primary-subtle text-primary rounded-pill" id="itemCountBadge">0 Items</span>
                        </div>
                        <div class="card-body p-4">

                            <!-- Product Adder Selector -->
                            <div class="p-3 bg-surface-soft rounded-3 border mb-4">
                                <div class="row g-2 align-items-end">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small fw-semibold">Choose Product to Add</label>
                                        <select id="quickProductSelect" class="form-select">
                                            <option value="">-- Search / Select Product --</option>
                                            <?php foreach ($productsList as $p): ?>
                                                <option value="<?= (int)$p['id'] ?>"
                                                    data-code="<?= htmlspecialchars($p['product_code']) ?>"
                                                    data-name="<?= htmlspecialchars($p['product_name']) ?>"
                                                    data-price="<?= (float)$p['selling_price'] ?>"
                                                    data-stock="<?= (float)$p['stock_quantity'] ?>"
                                                    data-unit="<?= htmlspecialchars($p['unit_name'] ?? 'Unit') ?>"
                                                    data-discount="<?= (float)($p['discount_percent'] ?? 0) ?>"
                                                    data-category="<?= htmlspecialchars($p['category_name'] ?? '') ?>"
                                                    data-brand="<?= htmlspecialchars($p['brand_name'] ?? '') ?>">
                                                    <?= htmlspecialchars($p['product_code'] . ' - ' . $p['product_name'] . ' (Stock: ' . (int)$p['stock_quantity'] . ' | ₹' . number_format((float)$p['selling_price'], 2) . ')') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <label class="form-label small fw-semibold">Stock Qty</label>
                                        <input type="text" id="quickStockDisplay" class="form-control bg-surface text-center fw-bold" readonly placeholder="-">
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <label class="form-label small fw-semibold">Qty to Add</label>
                                        <input type="number" id="quickQuantityInput" class="form-control text-center fw-bold" value="1" min="1" step="1">
                                    </div>
                                    <div class="col-12 col-md-2">
                                        <button type="button" id="addProductBtn" class="btn btn-primary w-100 fw-semibold">
                                            <i class="bi bi-plus-circle me-1"></i> Add
                                        </button>
                                    </div>
                                </div>
                                <div id="stockErrorAlert" class="text-danger small mt-2 d-none fw-semibold">
                                    <i class="bi bi-exclamation-circle me-1"></i><span id="stockErrorText">Insufficient stock.</span>
                                </div>
                            </div>

                            <!-- Line Items Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0" id="itemsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 35%;">Product</th>
                                            <th class="text-center" style="width: 12%;">Qty</th>
                                            <th class="text-end" style="width: 15%;">Rate (₹)</th>
                                            <th class="text-end" style="width: 12%;">Disc (₹)</th>
                                            <th class="text-center" style="width: 10%;">GST %</th>
                                            <th class="text-end" style="width: 16%;">Amount (₹)</th>
                                            <th class="text-center" style="width: 5%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsTableBody">
                                        <!-- Dynamic Product Rows Injected Here via JS -->
                                        <tr id="noItemsRow">
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="bi bi-cart-x fs-3 d-block mb-1 text-muted"></i>
                                                No products added yet. Select a product above to add to this sales note.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>

                </div>

                <!-- RIGHT COLUMN: PAYMENT, TOTALS & CONFIRMATION -->
                <div class="col-12 col-xl-4">

                    <!-- SUMMARY & FINANCIAL TOTALS CARD -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4 position-sticky" style="top: 20px;">
                        <div class="card-header bg-surface py-3 px-4 border-bottom">
                            <h5 class="fw-bold mb-0 text-primary">
                                <i class="bi bi-calculator me-2"></i>3. Payment & Totals
                            </h5>
                        </div>
                        <div class="card-body p-4">

                            <!-- Cost Breakdown -->
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal:</span>
                                <span class="fw-bold text-body" id="summarySubtotal">₹0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Item Discount:</span>
                                <span class="text-success fw-semibold" id="summaryDiscount">- ₹0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">GST / Tax:</span>
                                <span class="text-body fw-semibold" id="summaryTax">+ ₹0.00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Other Charges:</span>
                                <div style="width: 110px;">
                                    <input type="number" name="other_charges" id="otherChargesInput" class="form-control form-control-sm text-end" value="0.00" min="0" step="0.01">
                                </div>
                            </div>

                            <hr class="my-3">

                            <!-- Grand Total -->
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <span class="fs-5 fw-bold text-body">Grand Total:</span>
                                <span class="fs-4 fw-extrabold text-primary" id="summaryGrandTotal">₹0.00</span>
                            </div>

                            <!-- Payment Type Selection -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Payment Type <span class="text-danger">*</span></label>
                                <select name="payment_type" id="paymentTypeSelect" class="form-select fw-semibold" required>
                                    <option value="Cash">Cash (Full Payment)</option>
                                    <option value="UPI">UPI (Full Payment)</option>
                                    <option value="Card">Card (Full Payment)</option>
                                    <option value="Bank Transfer">Bank Transfer (Full Payment)</option>
                                    <option value="Credit" id="optCredit">Credit (Full Credit)</option>
                                    <option value="Mixed" id="optMixed">Mixed / Partial Payment</option>
                                </select>
                            </div>

                            <!-- Partial / Mixed Payment Input -->
                            <div id="mixedPaymentBox" class="mb-3 p-3 bg-surface-soft rounded-3 border d-none">
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Amount Paid Now (₹)</label>
                                    <input type="number" name="paid_amount" id="paidAmountInput" class="form-control fw-bold text-success" value="0.00" min="0" step="0.01">
                                </div>
                                <div class="d-flex justify-content-between small text-muted">
                                    <span>Remaining Credit:</span>
                                    <strong class="text-danger" id="dispCreditPortion">₹0.00</strong>
                                </div>
                            </div>

                            <!-- Customer Balance Impact Preview -->
                            <div class="p-3 bg-surface-soft rounded-3 border mb-3 small">
                                <div class="fw-bold mb-2 text-body">Customer Outstanding Impact:</div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Previous Balance:</span>
                                    <strong id="sidePrevBal">₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">+ New Credit Portion:</span>
                                    <strong class="text-danger" id="sideCreditAdded">₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between border-top pt-1 mt-1">
                                    <span class="fw-bold">New Total Balance:</span>
                                    <strong class="text-primary fw-bold" id="sideNewBal">₹0.00</strong>
                                </div>
                            </div>

                            <!-- Manager Credit Override Checkbox -->
                            <div id="overrideBox" class="form-check p-3 bg-danger-subtle text-danger rounded-3 border border-danger-subtle mb-3 d-none">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="credit_override" id="creditOverrideChk" value="1">
                                <label class="form-check-label small fw-bold" for="creditOverrideChk">
                                    Authorize Credit Limit Override
                                </label>
                                <div class="small mt-1 text-danger-emphasis">
                                    Sale exceeds customer's credit limit. Check this box to authorize and record this transaction.
                                </div>
                            </div>

                            <!-- Notes / Remarks -->
                            <div class="mb-4">
                                <label class="form-label small fw-semibold">Notes / Remarks</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes for customer or invoice..."><?= htmlspecialchars($notes) ?></textarea>
                            </div>

                            <!-- Save / Submit Button -->
                            <button type="submit" id="saveSaleBtn" class="btn btn-primary w-100 py-2.5 fw-bold shadow-sm">
                                <i class="bi bi-check2-circle me-1"></i> Save & Generate Sales Note
                            </button>

                        </div>
                    </div>

                </div>

            </div>
        </form>

    </div>
</main>

<!-- JAVASCRIPT LOGIC FOR SALES NOTE INTERACTION -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentCustomer = null;
    let items = []; // Line items array

    const customerSelect = document.getElementById('customerSelect');
    const customerProfileBox = document.getElementById('customerProfileBox');
    const dispCustName = document.getElementById('dispCustName');
    const dispCustCode = document.getElementById('dispCustCode');
    const dispCustMobile = document.getElementById('dispCustMobile');
    const dispCustAddress = document.getElementById('dispCustAddress');
    const dispPaymentTerms = document.getElementById('dispPaymentTerms');
    const dispCreditBadge = document.getElementById('dispCreditBadge');
    const dispCreditLimit = document.getElementById('dispCreditLimit');
    const dispPrevOutstanding = document.getElementById('dispPrevOutstanding');
    const dispAvailableCredit = document.getElementById('dispAvailableCredit');
    const dispNewOutstanding = document.getElementById('dispNewOutstanding');
    const creditAlertBanner = document.getElementById('creditAlertBanner');
    const creditAlertMessage = document.getElementById('creditAlertMessage');

    const quickProductSelect = document.getElementById('quickProductSelect');
    const quickStockDisplay = document.getElementById('quickStockDisplay');
    const quickQuantityInput = document.getElementById('quickQuantityInput');
    const addProductBtn = document.getElementById('addProductBtn');
    const stockErrorAlert = document.getElementById('stockErrorAlert');
    const stockErrorText = document.getElementById('stockErrorText');

    const itemsTableBody = document.getElementById('itemsTableBody');
    const itemCountBadge = document.getElementById('itemCountBadge');
    const summarySubtotal = document.getElementById('summarySubtotal');
    const summaryDiscount = document.getElementById('summaryDiscount');
    const summaryTax = document.getElementById('summaryTax');
    const otherChargesInput = document.getElementById('otherChargesInput');
    const summaryGrandTotal = document.getElementById('summaryGrandTotal');
    const paymentTypeSelect = document.getElementById('paymentTypeSelect');
    const mixedPaymentBox = document.getElementById('mixedPaymentBox');
    const paidAmountInput = document.getElementById('paidAmountInput');
    const dispCreditPortion = document.getElementById('dispCreditPortion');
    const sidePrevBal = document.getElementById('sidePrevBal');
    const sideCreditAdded = document.getElementById('sideCreditAdded');
    const sideNewBal = document.getElementById('sideNewBal');
    const overrideBox = document.getElementById('overrideBox');
    const optCredit = document.getElementById('optCredit');
    const optMixed = document.getElementById('optMixed');

    // 1. CUSTOMER SELECTION & AJAX FETCH
    function loadCustomerDetails(customerId) {
        if (!customerId) {
            customerProfileBox.classList.add('d-none');
            currentCustomer = null;
            recalculateAll();
            return;
        }

        fetch('ajax_sales_note.php?action=get_customer&customer_id=' + customerId)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.customer) {
                    currentCustomer = data.customer;
                    dispCustName.textContent = currentCustomer.customer_name + (currentCustomer.company_name ? ' (' + currentCustomer.company_name + ')' : '');
                    dispCustCode.textContent = currentCustomer.customer_code;
                    dispCustMobile.textContent = currentCustomer.mobile_number;
                    dispCustAddress.textContent = currentCustomer.address;
                    dispPaymentTerms.textContent = currentCustomer.payment_terms || 'Immediate';
                    dispCreditLimit.textContent = '₹' + parseFloat(currentCustomer.credit_limit).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    dispPrevOutstanding.textContent = '₹' + parseFloat(currentCustomer.current_outstanding).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    dispAvailableCredit.textContent = '₹' + parseFloat(currentCustomer.available_credit).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                    // Credit allowed toggle
                    if (parseInt(currentCustomer.credit_allowed) === 1) {
                        dispCreditBadge.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle">Credit Allowed</span>';
                        optCredit.disabled = false;
                        optMixed.disabled = false;
                    } else {
                        dispCreditBadge.innerHTML = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">No Credit Allowed</span>';
                        optCredit.disabled = true;
                        optMixed.disabled = true;
                        if (paymentTypeSelect.value === 'Credit' || paymentTypeSelect.value === 'Mixed') {
                            paymentTypeSelect.value = 'Cash';
                        }
                    }

                    customerProfileBox.classList.remove('d-none');
                    recalculateAll();
                } else {
                    alert('Error loading customer: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => console.error('AJAX Customer Error:', err));
    }

    customerSelect.addEventListener('change', function() {
        loadCustomerDetails(this.value);
    });

    if (customerSelect.value) {
        loadCustomerDetails(customerSelect.value);
    }

    // 2. PRODUCT SELECTOR CHANGE
    quickProductSelect.addEventListener('change', function() {
        stockErrorAlert.classList.add('d-none');
        const opt = this.options[this.selectedIndex];
        if (!opt || !opt.value) {
            quickStockDisplay.value = '-';
            return;
        }
        const stock = parseFloat(opt.dataset.stock) || 0;
        quickStockDisplay.value = stock;
        quickQuantityInput.max = stock;
    });

    // 3. ADD PRODUCT TO ITEMS TABLE
    addProductBtn.addEventListener('click', function() {
        stockErrorAlert.classList.add('d-none');
        const opt = quickProductSelect.options[quickProductSelect.selectedIndex];
        if (!opt || !opt.value) {
            alert('Please select a product first.');
            return;
        }

        const productId = parseInt(opt.value);
        const code = opt.dataset.code;
        const name = opt.dataset.name;
        const rate = parseFloat(opt.dataset.price) || 0;
        const stock = parseFloat(opt.dataset.stock) || 0;
        const unit = opt.dataset.unit || 'Unit';
        const defaultDiscount = parseFloat(opt.dataset.discount) || 0;
        const qty = parseFloat(quickQuantityInput.value) || 1;

        // Check if already in table
        const existingItem = items.find(i => i.product_id === productId);
        const currentQtyInTable = existingItem ? existingItem.quantity : 0;
        const newTotalQty = currentQtyInTable + qty;

        if (newTotalQty > stock) {
            stockErrorText.textContent = `Insufficient stock for "${name}". Available: ${stock}, Requested: ${newTotalQty}`;
            stockErrorAlert.classList.remove('d-none');
            return;
        }

        if (existingItem) {
            existingItem.quantity = newTotalQty;
        } else {
            // Calculate default item discount amount based on product default discount %
            const lineDisc = defaultDiscount > 0 ? (rate * qty * defaultDiscount) / 100 : 0.00;
            items.push({
                product_id: productId,
                product_code: code,
                product_name: name,
                unit_name: unit,
                stock: stock,
                quantity: qty,
                unit_price: rate,
                discount: lineDisc,
                tax_percent: 0.00
            });
        }

        // Reset quick adder
        quickProductSelect.value = '';
        quickStockDisplay.value = '-';
        quickQuantityInput.value = 1;

        renderItemsTable();
        recalculateAll();
    });

    // 4. RENDER ITEMS TABLE
    function renderItemsTable() {
        if (items.length === 0) {
            itemsTableBody.innerHTML = `
                <tr id="noItemsRow">
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="bi bi-cart-x fs-3 d-block mb-1 text-muted"></i>
                        No products added yet. Select a product above to add to this sales note.
                    </td>
                </tr>
            `;
            itemCountBadge.textContent = '0 Items';
            return;
        }

        let html = '';
        items.forEach((item, idx) => {
            const lineSub = item.quantity * item.unit_price;
            const lineDisc = Math.min(lineSub, parseFloat(item.discount) || 0);
            const taxable = Math.max(0, lineSub - lineDisc);
            const lineTax = (taxable * (parseFloat(item.tax_percent) || 0)) / 100;
            const lineTotal = taxable + lineTax;

            html += `
                <tr data-index="${idx}">
                    <td>
                        <input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}">
                        <span class="badge bg-secondary-subtle text-secondary me-1">${escapeHtml(item.product_code)}</span>
                        <strong class="text-body">${escapeHtml(item.product_name)}</strong>
                        <div class="small text-muted">Stock: ${item.stock} ${escapeHtml(item.unit_name)}</div>
                    </td>
                    <td class="text-center">
                        <input type="number" name="items[${idx}][quantity]" class="form-control form-control-sm text-center item-qty" 
                               value="${item.quantity}" min="1" max="${item.stock}" step="1" data-index="${idx}">
                    </td>
                    <td class="text-end">
                        <input type="hidden" name="items[${idx}][unit_price]" value="${item.unit_price}">
                        ₹${item.unit_price.toFixed(2)}
                    </td>
                    <td class="text-end">
                        <input type="number" name="items[${idx}][discount]" class="form-control form-control-sm text-end item-disc" 
                               value="${item.discount.toFixed(2)}" min="0" step="0.01" data-index="${idx}">
                    </td>
                    <td class="text-center">
                        <select name="items[${idx}][tax_percent]" class="form-select form-select-sm item-tax text-center" data-index="${idx}">
                            <option value="0" ${item.tax_percent === 0 ? 'selected' : ''}>0%</option>
                            <option value="5" ${item.tax_percent === 5 ? 'selected' : ''}>5%</option>
                            <option value="12" ${item.tax_percent === 12 ? 'selected' : ''}>12%</option>
                            <option value="18" ${item.tax_percent === 18 ? 'selected' : ''}>18%</option>
                            <option value="28" ${item.tax_percent === 28 ? 'selected' : ''}>28%</option>
                        </select>
                    </td>
                    <td class="text-end fw-bold text-body">
                        ₹${lineTotal.toFixed(2)}
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger item-remove-btn" data-index="${idx}" title="Remove Product">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        itemsTableBody.innerHTML = html;
        itemCountBadge.textContent = `${items.length} Item${items.length > 1 ? 's' : ''}`;

        // Bind table row inputs
        document.querySelectorAll('.item-qty').forEach(input => {
            input.addEventListener('change', function() {
                const idx = parseInt(this.dataset.index);
                let val = parseFloat(this.value) || 1;
                if (val > items[idx].stock) {
                    alert(`Quantity exceeds available stock (${items[idx].stock}). Adjusted to maximum.`);
                    val = items[idx].stock;
                    this.value = val;
                }
                items[idx].quantity = Math.max(1, val);
                renderItemsTable();
                recalculateAll();
            });
        });

        document.querySelectorAll('.item-disc').forEach(input => {
            input.addEventListener('change', function() {
                const idx = parseInt(this.dataset.index);
                items[idx].discount = Math.max(0, parseFloat(this.value) || 0);
                renderItemsTable();
                recalculateAll();
            });
        });

        document.querySelectorAll('.item-tax').forEach(select => {
            select.addEventListener('change', function() {
                const idx = parseInt(this.dataset.index);
                items[idx].tax_percent = parseFloat(this.value) || 0;
                renderItemsTable();
                recalculateAll();
            });
        });

        document.querySelectorAll('.item-remove-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = parseInt(this.dataset.index);
                items.splice(idx, 1);
                renderItemsTable();
                recalculateAll();
            });
        });
    }

    // 5. RECALCULATE FINANCIALS & CREDIT LOGIC
    function recalculateAll() {
        let subtotal = 0;
        let totalDiscount = 0;
        let totalTax = 0;

        items.forEach(item => {
            const lineSub = item.quantity * item.unit_price;
            const lineDisc = Math.min(lineSub, parseFloat(item.discount) || 0);
            const taxable = Math.max(0, lineSub - lineDisc);
            const lineTax = (taxable * (parseFloat(item.tax_percent) || 0)) / 100;

            subtotal += lineSub;
            totalDiscount += lineDisc;
            totalTax += lineTax;
        });

        const otherCharges = Math.max(0, parseFloat(otherChargesInput.value) || 0);
        const grandTotal = Math.max(0, (subtotal - totalDiscount) + totalTax + otherCharges);

        summarySubtotal.textContent = '₹' + subtotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        summaryDiscount.textContent = '- ₹' + totalDiscount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        summaryTax.textContent = '+ ₹' + totalTax.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        summaryGrandTotal.textContent = '₹' + grandTotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        // Payment Mode Handling
        const pType = paymentTypeSelect.value;
        let paidAmount = 0;
        let creditAmount = 0;

        if (pType === 'Cash' || pType === 'UPI' || pType === 'Card' || pType === 'Bank Transfer') {
            mixedPaymentBox.classList.add('d-none');
            paidAmount = grandTotal;
            creditAmount = 0;
        } else if (pType === 'Credit') {
            mixedPaymentBox.classList.add('d-none');
            paidAmount = 0;
            creditAmount = grandTotal;
        } else if (pType === 'Mixed') {
            mixedPaymentBox.classList.remove('d-none');
            let inputPaid = parseFloat(paidAmountInput.value) || 0;
            if (inputPaid > grandTotal) {
                inputPaid = grandTotal;
                paidAmountInput.value = inputPaid.toFixed(2);
            }
            paidAmount = inputPaid;
            creditAmount = Math.max(0, grandTotal - paidAmount);
            dispCreditPortion.textContent = '₹' + creditAmount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        // Outstanding & Credit Validation
        const prevBal = currentCustomer ? parseFloat(currentCustomer.current_outstanding) : 0;
        const creditLimit = currentCustomer ? parseFloat(currentCustomer.credit_limit) : 0;
        const creditAllowed = currentCustomer ? parseInt(currentCustomer.credit_allowed) : 0;
        const newBal = prevBal + creditAmount;
        const availCredit = (creditAllowed && creditLimit > 0) ? Math.max(0, creditLimit - newBal) : 0;

        sidePrevBal.textContent = '₹' + prevBal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        sideCreditAdded.textContent = '₹' + creditAmount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        sideNewBal.textContent = '₹' + newBal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        if (currentCustomer) {
            dispNewOutstanding.textContent = '₹' + newBal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            dispAvailableCredit.textContent = '₹' + availCredit.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            // Evaluate Alert Banner
            if (creditAmount > 0) {
                if (creditAllowed === 0) {
                    creditAlertBanner.className = 'alert alert-danger d-flex align-items-center gap-2 mt-3 mb-0';
                    creditAlertMessage.textContent = 'Credit is not allowed for this customer.';
                    creditAlertBanner.classList.remove('d-none');
                    overrideBox.classList.add('d-none');
                } else if (creditLimit > 0 && newBal > creditLimit) {
                    const excess = newBal - creditLimit;
                    creditAlertBanner.className = 'alert alert-danger d-flex align-items-center gap-2 mt-3 mb-0';
                    creditAlertMessage.innerHTML = `<strong>Credit limit exceeded!</strong> Available Credit was ₹${Math.max(0, creditLimit - prevBal).toFixed(2)}. Requested Credit exceeds limit by ₹${excess.toFixed(2)}.`;
                    creditAlertBanner.classList.remove('d-none');
                    overrideBox.classList.remove('d-none');
                } else if (creditLimit > 0 && newBal >= creditLimit * 0.8) {
                    creditAlertBanner.className = 'alert alert-warning d-flex align-items-center gap-2 mt-3 mb-0';
                    creditAlertMessage.textContent = `Warning: Customer is approaching credit limit (${((newBal/creditLimit)*100).toFixed(1)}% utilized).`;
                    creditAlertBanner.classList.remove('d-none');
                    overrideBox.classList.add('d-none');
                } else {
                    creditAlertBanner.classList.add('d-none');
                    overrideBox.classList.add('d-none');
                }
            } else {
                creditAlertBanner.classList.add('d-none');
                overrideBox.classList.add('d-none');
            }
        }
    }

    // Input event listeners
    otherChargesInput.addEventListener('input', recalculateAll);
    paymentTypeSelect.addEventListener('change', recalculateAll);
    paidAmountInput.addEventListener('input', recalculateAll);

    // Escape HTML helper
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
