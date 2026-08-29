<?php

$pageTitle = 'Create Customer';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/customer_helper.php';

$errors = [];
$customerNameError = '';
$customerTypeError = '';
$companyNameError = '';
$mobileNumberError = '';
$emailError = '';
$gstError = '';
$creditLimitError = '';
$paymentTermsError = '';
$openingBalanceError = '';

$formData = [
    'customer_name' => '',
    'customer_type' => 'Individual',
    'company_name' => '',
    'mobile_number' => '',
    'alternate_mobile_number' => '',
    'email' => '',
    'gst_number' => '',
    'address' => '',
    'area' => '',
    'city' => '',
    'district' => '',
    'state' => '',
    'pincode' => '',
    'billing_address' => '',
    'shipping_address' => '',
    'same_as_billing' => 0,
    'credit_allowed' => 0,
    'credit_limit' => '0.00',
    'payment_terms' => 'Immediate',
    'opening_balance' => '0.00',
    'opening_balance_type' => 'Debit',
    'status' => 1
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['customer_name'] = trim($_POST['customer_name'] ?? '');
    $formData['customer_type'] = in_array($_POST['customer_type'] ?? '', ['Individual', 'Business'], true) ? $_POST['customer_type'] : 'Individual';
    $formData['company_name'] = trim($_POST['company_name'] ?? '');
    $formData['mobile_number'] = trim($_POST['mobile_number'] ?? '');
    $formData['alternate_mobile_number'] = trim($_POST['alternate_mobile_number'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['gst_number'] = trim(strtoupper($_POST['gst_number'] ?? ''));
    $formData['address'] = trim($_POST['address'] ?? '');
    $formData['area'] = trim($_POST['area'] ?? '');
    $formData['city'] = trim($_POST['city'] ?? '');
    $formData['district'] = trim($_POST['district'] ?? '');
    $formData['state'] = trim($_POST['state'] ?? '');
    $formData['pincode'] = trim($_POST['pincode'] ?? '');
    $formData['billing_address'] = trim($_POST['billing_address'] ?? '');
    $formData['shipping_address'] = trim($_POST['shipping_address'] ?? '');
    $formData['same_as_billing'] = isset($_POST['same_as_billing']) ? 1 : 0;
    if ($formData['same_as_billing']) {
        $formData['shipping_address'] = $formData['billing_address'];
    }

    $formData['credit_allowed'] = isset($_POST['credit_allowed']) ? 1 : 0;
    $formData['credit_limit'] = trim($_POST['credit_limit'] ?? '0.00');
    $formData['payment_terms'] = trim($_POST['payment_terms'] ?? 'Immediate');
    $formData['opening_balance'] = trim($_POST['opening_balance'] ?? '0.00');
    $formData['opening_balance_type'] = in_array($_POST['opening_balance_type'] ?? '', ['Debit', 'Credit'], true) ? $_POST['opening_balance_type'] : 'Debit';
    $formData['status'] = isset($_POST['status']) ? 1 : 0;

    $submitAction = $_POST['submit_action'] ?? 'save';

    // Validation
    if ($formData['customer_name'] === '') {
        $customerNameError = 'Customer Name is required.';
        $errors[] = $customerNameError;
    } elseif (mb_strlen($formData['customer_name']) < 2) {
        $customerNameError = 'Customer Name must be at least 2 characters.';
        $errors[] = $customerNameError;
    }

    if ($formData['customer_type'] === 'Business' && $formData['company_name'] === '') {
        $companyNameError = 'Company Name is required when Customer Type is Business.';
        $errors[] = $companyNameError;
    }

    if ($formData['mobile_number'] === '') {
        $mobileNumberError = 'Mobile Number is required.';
        $errors[] = $mobileNumberError;
    } elseif (!preg_match('/^[0-9+\-\s]{7,20}$/', $formData['mobile_number'])) {
        $mobileNumberError = 'Please enter a valid mobile number (7-20 digits).';
        $errors[] = $mobileNumberError;
    }

    if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $emailError = 'Please enter a valid email address.';
        $errors[] = $emailError;
    }

    if ($formData['gst_number'] !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $formData['gst_number'])) {
        $gstError = 'Invalid GST format (e.g. 22AAAAA0000A1Z5).';
        $errors[] = $gstError;
    }

    if ($formData['credit_allowed']) {
        if (!is_numeric($formData['credit_limit']) || (float) $formData['credit_limit'] < 0) {
            $creditLimitError = 'Credit Limit must be a valid positive amount.';
            $errors[] = $creditLimitError;
        }
        if ($formData['payment_terms'] === '') {
            $paymentTermsError = 'Payment Terms are required when credit is allowed.';
            $errors[] = $paymentTermsError;
        }
    } else {
        $formData['credit_limit'] = '0.00';
    }

    if (!is_numeric($formData['opening_balance']) || (float) $formData['opening_balance'] < 0) {
        $openingBalanceError = 'Opening Balance must be a valid non-negative number.';
        $errors[] = $openingBalanceError;
    }

    if (empty($errors)) {
        $customerCode = generateCustomerCode($conn);
        $createdBy = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 1;
        $createdAt = date('Y-m-d H:i:s');

        $stmt = $conn->prepare("
            INSERT INTO customer_master (
                customer_code,
                customer_name,
                customer_type,
                company_name,
                mobile_number,
                alternate_mobile_number,
                email,
                gst_number,
                address,
                area,
                city,
                district,
                state,
                pincode,
                billing_address,
                shipping_address,
                credit_allowed,
                credit_limit,
                payment_terms,
                opening_balance,
                opening_balance_type,
                status,
                created_by,
                created_at
            ) VALUES (
                :customer_code,
                :customer_name,
                :customer_type,
                :company_name,
                :mobile_number,
                :alternate_mobile_number,
                :email,
                :gst_number,
                :address,
                :area,
                :city,
                :district,
                :state,
                :pincode,
                :billing_address,
                :shipping_address,
                :credit_allowed,
                :credit_limit,
                :payment_terms,
                :opening_balance,
                :opening_balance_type,
                :status,
                :created_by,
                :created_at
            )
        ");

        $stmt->execute([
            ':customer_code' => $customerCode,
            ':customer_name' => $formData['customer_name'],
            ':customer_type' => $formData['customer_type'],
            ':company_name' => $formData['company_name'] ?: null,
            ':mobile_number' => $formData['mobile_number'],
            ':alternate_mobile_number' => $formData['alternate_mobile_number'] ?: null,
            ':email' => $formData['email'] ?: null,
            ':gst_number' => $formData['gst_number'] ?: null,
            ':address' => $formData['address'] ?: null,
            ':area' => $formData['area'] ?: null,
            ':city' => $formData['city'] ?: null,
            ':district' => $formData['district'] ?: null,
            ':state' => $formData['state'] ?: null,
            ':pincode' => $formData['pincode'] ?: null,
            ':billing_address' => $formData['billing_address'] ?: null,
            ':shipping_address' => $formData['shipping_address'] ?: null,
            ':credit_allowed' => $formData['credit_allowed'],
            ':credit_limit' => (float) $formData['credit_limit'],
            ':payment_terms' => $formData['payment_terms'] ?: null,
            ':opening_balance' => (float) $formData['opening_balance'],
            ':opening_balance_type' => $formData['opening_balance_type'],
            ':status' => $formData['status'],
            ':created_by' => $createdBy,
            ':created_at' => $createdAt
        ]);

        $successMsg = "Customer {$customerCode} created successfully.";

        if ($submitAction === 'save_and_add') {
            header('Location: create_customer.php?success=' . urlencode($successMsg));
            exit;
        } else {
            header('Location: manage_customer.php?success=' . urlencode($successMsg));
            exit;
        }
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
            ['title' => 'Masters', 'link' => 'masters.php'],
            ['title' => 'Customer Master', 'link' => 'manage_customer.php'],
            ['title' => 'Create Customer']
        ]);
        ?>

        <!-- Page Heading -->
        <div class="page-heading d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="page-title mb-1">Create Customer</h1>
            </div>
            <a href="manage_customer.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Customers
            </a>
        </div>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show auto-hide-alert">
                <span><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <div class="alert-time-line"></div>
            </div>
        <?php endif; ?>


        <form method="POST" id="createCustomerForm" novalidate>

            <!-- SECTION 1: CUSTOMER INFORMATION -->
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="card-title mb-0">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>1. Customer
                            Information</h5>
                    </div>
                    <div class="card-header-actions">
                        <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                            <input class="form-check-input" type="checkbox" name="status" id="customerStatus"
                                <?= $formData['status'] ? 'checked' : '' ?>>
                            <label class="form-check-label mb-0 fw-semibold" for="customerStatus"
                                id="statusLabel"><?= $formData['status'] ? 'Active' : 'Inactive' ?></label>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name"
                                class="form-control <?= $customerNameError ? 'is-invalid' : '' ?>"
                                placeholder="Enter customer / client name"
                                value="<?= htmlspecialchars($formData['customer_name']) ?>" required>
                            <?php if ($customerNameError): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($customerNameError) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label">Customer Type <span class="text-danger">*</span></label>
                            <select name="customer_type" id="customerTypeSelect"
                                class="form-select <?= $customerTypeError ? 'is-invalid' : '' ?>">
                                <option value="Individual" <?= $formData['customer_type'] === 'Individual' ? 'selected' : '' ?>>Individual</option>
                                <option value="Business" <?= $formData['customer_type'] === 'Business' ? 'selected' : '' ?>>Business</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-3" id="companyNameGroup">
                            <label class="form-label">Company Name <span class="text-danger"
                                    id="companyRequiredStar">*</span></label>
                            <input type="text" name="company_name" id="companyNameInput"
                                class="form-control <?= $companyNameError ? 'is-invalid' : '' ?>"
                                placeholder="Enter company name"
                                value="<?= htmlspecialchars($formData['company_name']) ?>">
                            <?php if ($companyNameError): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($companyNameError) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input type="text" name="mobile_number"
                                    class="form-control <?= $mobileNumberError ? 'is-invalid' : '' ?>"
                                    placeholder="Primary mobile number"
                                    value="<?= htmlspecialchars($formData['mobile_number']) ?>" required>
                            </div>
                            <?php if ($mobileNumberError): ?>
                                <div class="validation-message"><i
                                        class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($mobileNumberError) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Alternate Mobile Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                <input type="text" name="alternate_mobile_number" class="form-control"
                                    placeholder="Secondary mobile (optional)"
                                    value="<?= htmlspecialchars($formData['alternate_mobile_number']) ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email"
                                    class="form-control <?= $emailError ? 'is-invalid' : '' ?>"
                                    placeholder="customer@example.com"
                                    value="<?= htmlspecialchars($formData['email']) ?>">
                            </div>
                            <?php if ($emailError): ?>
                                <div class="validation-message"><i
                                        class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($emailError) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">GST Number</label>
                            <input type="text" name="gst_number"
                                class="form-control text-uppercase <?= $gstError ? 'is-invalid' : '' ?>"
                                placeholder="22AAAAA0000A1Z5 (optional)" maxlength="15"
                                value="<?= htmlspecialchars($formData['gst_number']) ?>">
                            <?php if ($gstError): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($gstError) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: ADDRESS -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-geo-alt me-2 text-primary"></i>2. Location & Address</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"
                                placeholder="Street name, building no., landmark"><?= htmlspecialchars($formData['address']) ?></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Area / Locality</label>
                            <input type="text" name="area" class="form-control" placeholder="Area or locality"
                                value="<?= htmlspecialchars($formData['area']) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" placeholder="City"
                                value="<?= htmlspecialchars($formData['city']) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">District</label>
                            <input type="text" name="district" class="form-control" placeholder="District"
                                value="<?= htmlspecialchars($formData['district']) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" placeholder="State"
                                value="<?= htmlspecialchars($formData['state']) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Pincode</label>
                            <input type="text" name="pincode" class="form-control" placeholder="Pincode" maxlength="10"
                                value="<?= htmlspecialchars($formData['pincode']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: BILLING & SHIPPING ADDRESS -->
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-truck me-2 text-primary"></i>3. Billing & Shipping Address
                    </h5>
                    <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" name="same_as_billing"
                            id="sameAsBillingCheckbox" <?= $formData['same_as_billing'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="sameAsBillingCheckbox">
                            Shipping Address Same as Billing Address
                        </label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Billing Address</label>
                            <textarea name="billing_address" id="billingAddressInput" class="form-control" rows="3"
                                placeholder="Full billing address for invoices"><?= htmlspecialchars($formData['billing_address']) ?></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Shipping Address</label>
                            <textarea name="shipping_address" id="shippingAddressInput" class="form-control" rows="3"
                                placeholder="Full delivery / shipping address"><?= htmlspecialchars($formData['shipping_address']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: CREDIT SETTINGS & OPENING BALANCE -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-credit-card-2-front me-2 text-primary"></i>4. Credit
                        Settings & Opening Balance</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="credit_allowed"
                                    id="creditAllowedToggle" <?= $formData['credit_allowed'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="creditAllowedToggle">Credit
                                    Allowed</label>
                            </div>
                            <small class="text-muted d-block mt-1">Enable to allow sales/rentals on credit with
                                limit.</small>
                        </div>

                        <div class="col-12 col-md-4" id="creditLimitGroup">
                            <label class="form-label">Credit Limit (₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0" name="credit_limit" id="creditLimitInput"
                                    class="form-control <?= $creditLimitError ? 'is-invalid' : '' ?>" placeholder="0.00"
                                    value="<?= htmlspecialchars($formData['credit_limit']) ?>"
                                    <?= !$formData['credit_allowed'] ? 'disabled' : '' ?>>
                            </div>
                            <?php if ($creditLimitError): ?>
                                <div class="validation-message"><i
                                        class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($creditLimitError) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-md-4" id="paymentTermsGroup">
                            <label class="form-label">Payment Terms <span class="text-danger">*</span></label>
                            <select name="payment_terms" id="paymentTermsSelect"
                                class="form-select <?= $paymentTermsError ? 'is-invalid' : '' ?>"
                                <?= !$formData['credit_allowed'] ? 'disabled' : '' ?>>
                                <option value="Immediate" <?= $formData['payment_terms'] === 'Immediate' ? 'selected' : '' ?>>Immediate</option>
                                <option value="7 Days" <?= $formData['payment_terms'] === '7 Days' ? 'selected' : '' ?>>7
                                    Days</option>
                                <option value="15 Days" <?= $formData['payment_terms'] === '15 Days' ? 'selected' : '' ?>>
                                    15 Days</option>
                                <option value="30 Days" <?= $formData['payment_terms'] === '30 Days' ? 'selected' : '' ?>>
                                    30 Days</option>
                                <option value="Custom" <?= $formData['payment_terms'] === 'Custom' ? 'selected' : '' ?>>
                                    Custom</option>
                            </select>
                            <?php if ($paymentTermsError): ?>
                                <div class="validation-message"><i
                                        class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($paymentTermsError) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12">
                            <hr class="my-2">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Opening Balance (₹)</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0" name="opening_balance"
                                    class="form-control <?= $openingBalanceError ? 'is-invalid' : '' ?>"
                                    placeholder="0.00" value="<?= htmlspecialchars($formData['opening_balance']) ?>">
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="bi bi-info-circle me-1"></i>Opening Balance is used only for entering an
                                existing customer's previous balance.
                            </small>
                            <?php if ($openingBalanceError): ?>
                                <div class="validation-message"><i
                                        class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($openingBalanceError) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Balance Type</label>
                            <select name="opening_balance_type" class="form-select">
                                <option value="Debit" <?= $formData['opening_balance_type'] === 'Debit' ? 'selected' : '' ?>>Debit (Customer owes money)</option>
                                <option value="Credit" <?= $formData['opening_balance_type'] === 'Credit' ? 'selected' : '' ?>>Credit (Advance / Company owes customer)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FORM ACTIONS -->
            <div class="d-flex justify-content-end align-items-center flex-wrap gap-2 mt-4 mb-5">
                <div class="d-flex gap-2">
                    <button type="submit" name="submit_action" value="save" class="btn btn-primary px-4">
                        <i class="bi bi-check-lg me-1"></i> Save Customer
                    </button>
                    <a href="manage_customer.php" class="btn btn-outline-secondary px-4">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </div>

        </form>

    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Status label toggle
        const statusSwitch = document.getElementById('customerStatus');
        const statusLabel = document.getElementById('statusLabel');
        if (statusSwitch && statusLabel) {
            statusSwitch.addEventListener('change', function () {
                statusLabel.textContent = this.checked ? 'Active' : 'Inactive';
            });
        }

        // Customer Type change -> Company Name requirement
        const typeSelect = document.getElementById('customerTypeSelect');
        const companyStar = document.getElementById('companyRequiredStar');
        function updateCompanyState() {
            if (typeSelect && companyStar) {
                if (typeSelect.value === 'Business') {
                    companyStar.style.display = 'inline';
                } else {
                    companyStar.style.display = 'none';
                }
            }
        }
        if (typeSelect) {
            typeSelect.addEventListener('change', updateCompanyState);
            updateCompanyState();
        }

        // Same as billing address copy
        const sameCheckbox = document.getElementById('sameAsBillingCheckbox');
        const billingInput = document.getElementById('billingAddressInput');
        const shippingInput = document.getElementById('shippingAddressInput');
        if (sameCheckbox && billingInput && shippingInput) {
            sameCheckbox.addEventListener('change', function () {
                if (this.checked) {
                    shippingInput.value = billingInput.value;
                    shippingInput.setAttribute('readonly', 'readonly');
                    shippingInput.classList.add('bg-body-tertiary');
                } else {
                    shippingInput.removeAttribute('readonly');
                    shippingInput.classList.remove('bg-body-tertiary');
                }
            });
            billingInput.addEventListener('input', function () {
                if (sameCheckbox.checked) {
                    shippingInput.value = this.value;
                }
            });
            if (sameCheckbox.checked) {
                shippingInput.setAttribute('readonly', 'readonly');
                shippingInput.classList.add('bg-body-tertiary');
            }
        }

        // Credit Allowed toggle
        const creditToggle = document.getElementById('creditAllowedToggle');
        const creditLimit = document.getElementById('creditLimitInput');
        const paymentTerms = document.getElementById('paymentTermsSelect');
        if (creditToggle && creditLimit && paymentTerms) {
            creditToggle.addEventListener('change', function () {
                if (this.checked) {
                    creditLimit.removeAttribute('disabled');
                    paymentTerms.removeAttribute('disabled');
                } else {
                    creditLimit.setAttribute('disabled', 'disabled');
                    paymentTerms.setAttribute('disabled', 'disabled');
                }
            });
        }
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>