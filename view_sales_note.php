<?php

$pageTitle = 'View Sales Note';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/includes/customer_helper.php';
require_once __DIR__ . '/includes/sales_note_helper.php';

$conn = $pdo;

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: manage_sales_note.php?error=' . urlencode('Invalid Sales Note ID'));
    exit;
}

// Fetch sales note details
$stmt = $conn->prepare("
    SELECT 
        sn.*,
        cm.customer_code,
        cm.customer_name,
        cm.company_name,
        cm.mobile_number,
        cm.email,
        cm.gst_number,
        cm.address,
        cm.area,
        cm.city,
        cm.state,
        cm.pincode,
        cm.credit_allowed AS cust_credit_allowed,
        cm.credit_limit AS cust_credit_limit,
        cm.payment_terms,
        creator.user_name AS created_by_name,
        updater.user_name AS updated_by_name
    FROM sales_notes sn
    LEFT JOIN customer_master cm ON cm.id = sn.customer_id
    LEFT JOIN user_master creator ON creator.id = sn.created_by
    LEFT JOIN user_master updater ON updater.id = sn.updated_by
    WHERE sn.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    header('Location: manage_sales_note.php?error=' . urlencode('Sales Note not found'));
    exit;
}

// Fetch itemized lines
$stmtItems = $conn->prepare("
    SELECT sni.*, p.short_name, c.category_name, b.brand_name
    FROM sales_note_items sni
    LEFT JOIN product_master p ON p.id = sni.product_id
    LEFT JOIN category_master c ON c.id = p.category_id
    LEFT JOIN brand_master b ON b.id = p.brand_id
    WHERE sni.sales_note_id = :id
    ORDER BY sni.id ASC
");
$stmtItems->execute([':id' => $id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Calculate dynamic live customer financial summary
$fSummary = getCustomerFinancialSummary($conn, (int)$sale['customer_id']);
$evalCredit = evaluateCreditStatus((float)$sale['new_outstanding'], (float)$sale['credit_limit'], (int)$sale['cust_credit_allowed']);

$successMsg = $_GET['success'] ?? '';
$autoPrint = isset($_GET['print']) && $_GET['print'] == '1';

require_once __DIR__ . '/includes/header.php';
?>

<style>
@media print {
    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 13px !important;
    }
    .admin-navbar, .admin-sidebar, .sidebar-backdrop, .admin-breadcrumb-nav, .page-heading, .no-print, .footer {
        display: none !important;
    }
    .admin-main {
        margin-left: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .container-fluid {
        padding: 0 !important;
        max-width: 100% !important;
    }
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }
    .print-invoice-card {
        border: none !important;
    }
    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
        background: transparent !important;
    }
    a {
        text-decoration: none !important;
        color: #000 !important;
    }
}
</style>

<main class="main-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Breadcrumb Navigation -->
        <div class="no-print">
            <?php
            renderBreadcrumbs([
                ['title' => '', 'icon' => 'bi bi-house-door', 'link' => 'index.php'],
                ['title' => 'Sales Notes', 'link' => 'manage_sales_note.php'],
                ['title' => $sale['sales_note_no']]
            ]);
            ?>
        </div>

        <!-- PAGE TITLE & ACTIONS -->
        <div class="page-heading d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4 no-print">
            <div>
                <h1 class="page-title mb-1">
                    <i class="bi bi-receipt-cutoff text-primary me-2"></i>Sales Note: <?= htmlspecialchars($sale['sales_note_no']) ?>
                </h1>
                <p class="text-muted mb-0">Generated on <?= date('d M Y, h:i A', strtotime($sale['sales_date'] . ' ' . $sale['sales_time'])) ?></p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print Sales Note
                </button>
                <?php if ((int)$sale['status'] === 1): ?>
                    <a href="edit_sales_note.php?id=<?= (int)$sale['id'] ?>" class="btn btn-outline-warning">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </a>
                <?php endif; ?>
                <a href="manage_sales_note.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to List
                </a>
            </div>
        </div>

        <!-- SUCCESS ALERT -->
        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show auto-hide-alert d-flex align-items-center gap-2 shadow-sm mb-4 no-print" role="alert">
                <i class="bi bi-check-circle-fill text-success fs-5"></i>
                <div class="fw-medium"><?= htmlspecialchars($successMsg) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ((int)$sale['status'] === 0): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 shadow-sm mb-4" role="alert">
                <i class="bi bi-exclamation-octagon-fill text-danger fs-5"></i>
                <div>
                    <strong>This Sales Note is CANCELLED.</strong> Product stock and customer ledger were reverted.
                </div>
            </div>
        <?php endif; ?>

        <!-- INVOICE CARD CONTAINER -->
        <div class="card border-0 shadow-sm rounded-4 print-invoice-card p-4 p-lg-5 mb-4 bg-surface">

            <!-- HEADER / STORE INFO -->
            <div class="row align-items-start pb-4 mb-4 border-bottom g-3">
                <div class="col-12 col-sm-7">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="bg-primary text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                            <i class="bi bi-tools fs-5"></i>
                        </div>
                        <h3 class="fw-bold text-primary mb-0">Sun PowerTools</h3>
                    </div>
                    <p class="text-muted small mb-1">Heavy Machinery & Power Tools Sales, Spares and Service</p>
                    <p class="text-muted small mb-0">Coimbatore, Tamil Nadu, India &bull; Phone: +91 93459 88594</p>
                </div>
                <div class="col-12 col-sm-5 text-sm-end">
                    <h4 class="fw-bold text-body mb-1">SALES NOTE</h4>
                    <div class="text-primary fw-bold fs-5"><?= htmlspecialchars($sale['sales_note_no']) ?></div>
                    <div class="text-muted small">Date: <?= date('d M Y', strtotime($sale['sales_date'])) ?></div>
                    <div class="text-muted small">Time: <?= date('h:i A', strtotime($sale['sales_time'])) ?></div>
                    <div class="mt-2">
                        <?= getPaymentTypeBadge($sale['payment_type']) ?>
                        <span class="badge <?= $evalCredit['badge_class'] ?> ms-1"><?= htmlspecialchars($sale['credit_status']) ?></span>
                        <?php if (!empty($sale['credit_override'])): ?>
                            <span class="badge bg-danger text-white ms-1">Override</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- CUSTOMER & INVOICE DETAILS -->
            <div class="row g-4 pb-4 mb-4 border-bottom">
                <!-- Customer Details -->
                <div class="col-12 col-md-6">
                    <h6 class="fw-bold text-muted text-uppercase small mb-2">Customer Details:</h6>
                    <h5 class="fw-bold text-body mb-1">
                        <?= htmlspecialchars($sale['customer_name']) ?>
                        <?php if (!empty($sale['company_name'])): ?>
                            <span class="text-muted fs-6 fw-normal">(<?= htmlspecialchars($sale['company_name']) ?>)</span>
                        <?php endif; ?>
                    </h5>
                    <div class="small text-muted mb-1">
                        <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($sale['customer_code']) ?></span>
                        <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($sale['mobile_number']) ?>
                        <?php if (!empty($sale['email'])): ?>
                            &bull; <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($sale['email']) ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($sale['gst_number'])): ?>
                        <div class="small text-muted mb-1">GSTIN: <strong><?= htmlspecialchars($sale['gst_number']) ?></strong></div>
                    <?php endif; ?>
                    <div class="small text-muted">
                        <i class="bi bi-geo-alt me-1"></i>
                        <?= htmlspecialchars(implode(', ', array_filter([$sale['address'], $sale['area'], $sale['city'], $sale['state'], $sale['pincode']]))) ?: 'N/A' ?>
                    </div>
                </div>

                <!-- Credit & Audit Info -->
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-surface-soft rounded-3 border small">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-credit-card-2-front me-1"></i> Customer Financial & Credit Ledger</h6>
                        <div class="row g-2 text-center">
                            <div class="col-6 col-sm-3">
                                <span class="text-muted d-block small">Credit Limit</span>
                                <strong class="text-body">₹<?= number_format((float)$sale['credit_limit'], 2) ?></strong>
                            </div>
                            <div class="col-6 col-sm-3">
                                <span class="text-muted d-block small">Prev Outstanding</span>
                                <strong class="text-danger">₹<?= number_format((float)$sale['previous_outstanding'], 2) ?></strong>
                            </div>
                            <div class="col-6 col-sm-3">
                                <span class="text-muted d-block small">Credit Applied</span>
                                <strong class="text-danger">₹<?= number_format((float)$sale['credit_amount'], 2) ?></strong>
                            </div>
                            <div class="col-6 col-sm-3">
                                <span class="text-muted d-block small">New Outstanding</span>
                                <strong class="text-primary fw-bold">₹<?= number_format((float)$sale['new_outstanding'], 2) ?></strong>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top text-muted">
                            <span>Remaining Avail Credit: <strong class="text-success">₹<?= number_format(max(0.00, (float)$sale['credit_limit'] - (float)$sale['new_outstanding']), 2) ?></strong></span>
                            <span>Payment Terms: <strong><?= htmlspecialchars($sale['payment_terms'] ?? 'Immediate') ?></strong></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PRODUCT ITEMS TABLE -->
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 5%;">#</th>
                            <th style="width: 35%;">Product Description</th>
                            <th class="text-center" style="width: 10%;">Unit</th>
                            <th class="text-center" style="width: 10%;">Qty</th>
                            <th class="text-end" style="width: 12%;">Rate (₹)</th>
                            <th class="text-end" style="width: 10%;">Disc (₹)</th>
                            <th class="text-center" style="width: 8%;">Tax %</th>
                            <th class="text-end" style="width: 10%;">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $idx => $it): ?>
                                <tr>
                                    <td class="text-center text-muted"><?= $idx + 1 ?></td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary me-1"><?= htmlspecialchars($it['product_code']) ?></span>
                                        <strong class="text-body"><?= htmlspecialchars($it['product_name']) ?></strong>
                                        <?php if (!empty($it['brand_name'])): ?>
                                            <div class="small text-muted"><?= htmlspecialchars($it['brand_name']) ?> &bull; <?= htmlspecialchars($it['category_name'] ?? '') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-muted"><?= htmlspecialchars($it['unit_name'] ?? 'Qty') ?></td>
                                    <td class="text-center fw-bold"><?= number_format((float)$it['quantity']) ?></td>
                                    <td class="text-end">₹<?= number_format((float)$it['unit_price'], 2) ?></td>
                                    <td class="text-end text-success">
                                        <?= (float)$it['discount'] > 0 ? '-₹' . number_format((float)$it['discount'], 2) : '—' ?>
                                    </td>
                                    <td class="text-center text-muted">
                                        <?= (float)$it['tax_percent'] > 0 ? number_format((float)$it['tax_percent']) . '%' : '0%' ?>
                                    </td>
                                    <td class="text-end fw-bold text-body">
                                        ₹<?= number_format((float)$it['line_total'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center py-3 text-muted">No items found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAYMENT SUMMARY & AUDIT -->
            <div class="row g-4">
                <!-- Left: Notes & Audit Trails -->
                <div class="col-12 col-md-6">
                    <?php if (!empty($sale['notes'])): ?>
                        <div class="p-3 bg-surface-soft rounded-3 border mb-3">
                            <h6 class="fw-bold small text-muted text-uppercase mb-1">Notes / Remarks:</h6>
                            <p class="small text-body mb-0"><?= nl2br(htmlspecialchars($sale['notes'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="small text-muted">
                        <div>Created By: <strong><?= htmlspecialchars($sale['created_by_name'] ?? 'Admin') ?></strong> on <?= date('d M Y, h:i A', strtotime($sale['created_at'])) ?></div>
                        <?php if (!empty($sale['updated_at'])): ?>
                            <div>Updated By: <strong><?= htmlspecialchars($sale['updated_by_name'] ?? 'Admin') ?></strong> on <?= date('d M Y, h:i A', strtotime($sale['updated_at'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right: Financial Grand Totals -->
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-surface-soft rounded-3 border">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal:</span>
                            <span class="fw-bold text-body">₹<?= number_format((float)$sale['subtotal'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Discount:</span>
                            <span class="text-success fw-semibold">- ₹<?= number_format((float)$sale['discount'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Tax / GST:</span>
                            <span class="text-body fw-semibold">+ ₹<?= number_format((float)$sale['tax'], 2) ?></span>
                        </div>
                        <?php if ((float)$sale['other_charges'] > 0): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Other Charges:</span>
                                <span class="text-body fw-semibold">+ ₹<?= number_format((float)$sale['other_charges'], 2) ?></span>
                            </div>
                        <?php endif; ?>

                        <hr class="my-2">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fs-5 fw-bold text-body">Grand Total:</span>
                            <span class="fs-4 fw-extrabold text-primary">₹<?= number_format((float)$sale['total_amount'], 2) ?></span>
                        </div>

                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-success fw-semibold">Amount Paid:</span>
                            <strong class="text-success">₹<?= number_format((float)$sale['paid_amount'], 2) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-danger fw-semibold">Credit Balance Added:</span>
                            <strong class="text-danger">₹<?= number_format((float)$sale['credit_amount'], 2) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INVOICE FOOTER NOTE -->
            <div class="mt-4 pt-3 border-top text-center text-muted small">
                <span>Thank you for choosing <strong>Sun PowerTools</strong>! Goods once sold can be serviced as per warranty terms.</span>
            </div>

        </div>

    </div>
</main>

<?php if ($autoPrint): ?>
<script>
window.addEventListener('load', function() {
    window.print();
});
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
