<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$userId = $_SESSION['user_id'];
$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Get Current User Details
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        user_id,
        first_name,
        last_name,
        user_name,
        user_email,
        user_phone,
        gender,
        date_of_birth,
        address,
        city,
        state,
        pincode,
        avatar,
        role,
        status,
        created_at
    FROM user_master
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Update Profile Submission
|--------------------------------------------------------------------------
*/

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $userName  = trim($_POST['user_name'] ?? '');
        $userEmail = trim($_POST['user_email'] ?? '');
        $userPhone = trim($_POST['user_phone'] ?? '');
        $gender    = $_POST['gender'] ?? null;
        $dob       = $_POST['date_of_birth'] ?? null;
        $address   = trim($_POST['address'] ?? '');
        $city      = trim($_POST['city'] ?? '');
        $state     = trim($_POST['state'] ?? '');
        $pincode   = trim($_POST['pincode'] ?? '');

        // Validation
        if ($userName === '') {
            $error = 'Username is required.';
        } elseif ($userEmail === '') {
            $error = 'Email is required.';
        } elseif (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($userPhone === '') {
            $error = 'Phone number is required.';
        } elseif ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
            $error = 'Pincode must contain exactly 6 digits.';
        }

        // Duplicate Checks (exclude current user)
        if (!$error) {
            $checkStmt = $pdo->prepare("
                SELECT id FROM user_master 
                WHERE (user_email = :email OR user_phone = :phone OR user_name = :username) 
                AND id != :id 
                LIMIT 1
            ");
            $checkStmt->execute([
                ':email'    => $userEmail,
                ':phone'    => $userPhone,
                ':username' => $userName,
                ':id'       => $userId
            ]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                $error = 'Email, phone number, or username is already in use by another account.';
            }
        }

        // Avatar Upload Handling
        $avatarName = $user['avatar'] ?? null;

        if (!$error && !empty($_FILES['avatar']['name'])) {
            $file = $_FILES['avatar'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Failed to upload avatar image.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = 'Avatar size must be less than 2 MB.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($file['tmp_name']);
                $allowedMimes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($allowedMimes[$mimeType])) {
                    $error = 'Only JPG, PNG, and WEBP image formats are supported.';
                } else {
                    $ext = $allowedMimes[$mimeType];
                    $uploadDir = __DIR__ . '/uploads/avatars/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $newAvatarName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                    $targetPath = $uploadDir . $newAvatarName;

                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        // Remove old avatar if exists
                        if (!empty($user['avatar'])) {
                            $oldFile = $uploadDir . $user['avatar'];
                            if (file_exists($oldFile) && is_file($oldFile)) {
                                @unlink($oldFile);
                            }
                        }
                        $avatarName = $newAvatarName;
                    } else {
                        $error = 'Failed to save uploaded avatar.';
                    }
                }
            }
        }

        // Save to Database
        if (!$error) {
            $updateStmt = $pdo->prepare("
                UPDATE user_master SET
                    first_name    = :first_name,
                    last_name     = :last_name,
                    user_name     = :user_name,
                    user_email    = :user_email,
                    user_phone    = :user_phone,
                    gender        = :gender,
                    date_of_birth = :date_of_birth,
                    address       = :address,
                    city          = :city,
                    state         = :state,
                    pincode       = :pincode,
                    avatar        = :avatar,
                    updated_at    = NOW()
                WHERE id = :id
            ");

            $updateStmt->execute([
                ':first_name'    => $firstName ?: null,
                ':last_name'     => $lastName ?: null,
                ':user_name'     => $userName,
                ':user_email'    => $userEmail,
                ':user_phone'    => $userPhone,
                ':gender'        => $gender ?: null,
                ':date_of_birth' => $dob ?: null,
                ':address'       => $address ?: null,
                ':city'          => $city ?: null,
                ':state'         => $state ?: null,
                ':pincode'       => $pincode ?: null,
                ':avatar'        => $avatarName,
                ':id'            => $userId
            ]);

            $_SESSION['user_name'] = $userName;
            $message = 'Profile updated successfully.';

            // Reload user data
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/header.php';

$avatarSrc = !empty($user['avatar'])
    ? '/sun_powertools/uploads/avatars/' . htmlspecialchars($user['avatar'])
    : '/sun_powertools/assets/images/avatar/avatar.jpg';
?>

<main class="main-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Breadcrumb Navigation -->
        <?php
        renderBreadcrumbs([
            ['title' => '', 'icon' => 'bi bi-house-door', 'link' => 'index.php'],
            ['title' => 'Profile']
        ]);
        ?>

        <!-- Page Heading -->
        <div class="page-heading d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
            <div>
                <h1 class="page-title mb-1">My Profile</h1>
                <p class="text-muted mb-0">Manage your personal information, contact details, and account security.</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Feedback Alerts -->
        <?php if ($message): ?>
            <div id="profileAlert" class="alert alert-success d-flex align-items-center gap-2 mb-4 py-3 px-4 rounded-3 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                <div class="fw-medium"><?= htmlspecialchars($message) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div id="profileAlert" class="alert alert-danger d-flex align-items-center gap-2 mb-4 py-3 px-4 rounded-3 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
                <div class="fw-medium"><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">

            <div class="row g-4">

                <!-- Left Column: User Summary & Avatar Card -->
                <div class="col-12 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 text-center p-4">
                        <div class="avatar-container position-relative mx-auto mb-3" style="width: 120px; height: 120px;">
                            <img src="<?= $avatarSrc ?>" alt="<?= htmlspecialchars($user['user_name']) ?>" 
                                 id="avatarPreview"
                                 class="rounded-circle w-100 h-100 object-fit-cover shadow border border-3 border-primary"
                                 onerror="this.src='/sun_powertools/assets/images/avatar/avatar.jpg';">
                            <label for="avatarInput" class="avatar-edit-btn position-absolute bottom-0 end-0 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow" 
                                   style="width: 38px; height: 38px; cursor: pointer;" title="Change Photo">
                                <i class="bi bi-camera-fill"></i>
                            </label>
                            <input type="file" id="avatarInput" name="avatar" class="d-none" accept=".jpg,.jpeg,.png,.webp">
                        </div>

                        <h4 class="fw-bold mb-1">
                            <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['user_name']) ?>
                        </h4>
                        <div class="text-muted small mb-2">@<?= htmlspecialchars($user['user_name']) ?></div>

                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill fw-semibold">
                                <i class="bi bi-shield-check me-1"></i> <?= htmlspecialchars(ucfirst($user['role'])) ?>
                            </span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill fw-semibold">
                                <i class="bi bi-circle-fill me-1" style="font-size: 6px;"></i> Active
                            </span>
                        </div>

                        <hr class="my-3 opacity-10">

                        <div class="text-start small">
                            <div class="d-flex justify-content-between py-2 border-bottom border-light-subtle">
                                <span class="text-muted">User Code</span>
                                <span class="fw-semibold font-monospace"><?= htmlspecialchars($user['user_id']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-bottom border-light-subtle">
                                <span class="text-muted">Email</span>
                                <span class="fw-semibold text-truncate ms-2" style="max-width: 180px;"><?= htmlspecialchars($user['user_email']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-bottom border-light-subtle">
                                <span class="text-muted">Phone</span>
                                <span class="fw-semibold"><?= htmlspecialchars($user['user_phone']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-2">
                                <span class="text-muted">Member Since</span>
                                <span class="fw-semibold"><?= !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'N/A' ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Personal & Contact Information -->
                <div class="col-12 col-lg-8">

                    <!-- Personal Information Card -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                            <div class="d-flex align-items-center gap-2">
                                <div class="p-2 rounded-3 bg-primary-subtle text-primary">
                                    <i class="bi bi-person-badge fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="card-title fw-bold mb-0">Personal Information</h5>
                                    <p class="text-muted small mb-0">Update your public profile details</p>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <label for="first_name" class="form-label fw-semibold small">First Name</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control"
                                           value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" placeholder="Enter first name" maxlength="50">
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label for="last_name" class="form-label fw-semibold small">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control"
                                           value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" placeholder="Enter last name" maxlength="50">
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label for="user_name" class="form-label fw-semibold small">Username <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-at"></i></span>
                                        <input type="text" id="user_name" name="user_name" class="form-control"
                                               value="<?= htmlspecialchars($user['user_name']) ?>" required maxlength="100">
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label for="user_email" class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" id="user_email" name="user_email" class="form-control"
                                               value="<?= htmlspecialchars($user['user_email']) ?>" required maxlength="150">
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label for="user_phone" class="form-label fw-semibold small">Phone Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                        <input type="text" id="user_phone" name="user_phone" class="form-control"
                                               value="<?= htmlspecialchars($user['user_phone']) ?>" required maxlength="20">
                                    </div>
                                </div>

                                <div class="col-12 col-sm-3">
                                    <label for="gender" class="form-label fw-semibold small">Gender</label>
                                    <select id="gender" name="gender" class="form-select">
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($user['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>

                                <div class="col-12 col-sm-3">
                                    <label for="date_of_birth" class="form-label fw-semibold small">Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control"
                                           value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Address Information Card -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                            <div class="d-flex align-items-center gap-2">
                                <div class="p-2 rounded-3 bg-info-subtle text-info">
                                    <i class="bi bi-geo-alt fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="card-title fw-bold mb-0">Location & Address</h5>
                                    <p class="text-muted small mb-0">Your mailing and residential details</p>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="address" class="form-label fw-semibold small">Street Address</label>
                                    <textarea id="address" name="address" class="form-control" rows="2" 
                                              placeholder="Enter street address" maxlength="500"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                </div>

                                <div class="col-12 col-sm-4">
                                    <label for="city" class="form-label fw-semibold small">City</label>
                                    <input type="text" id="city" name="city" class="form-control"
                                           value="<?= htmlspecialchars($user['city'] ?? '') ?>" placeholder="Enter city" maxlength="100">
                                </div>

                                <div class="col-12 col-sm-4">
                                    <label for="state" class="form-label fw-semibold small">State / Province</label>
                                    <input type="text" id="state" name="state" class="form-control"
                                           value="<?= htmlspecialchars($user['state'] ?? '') ?>" placeholder="Enter state" maxlength="100">
                                </div>

                                <div class="col-12 col-sm-4">
                                    <label for="pincode" class="form-label fw-semibold small">Pincode / Postal Code</label>
                                    <input type="text" id="pincode" name="pincode" class="form-control"
                                           value="<?= htmlspecialchars($user['pincode'] ?? '') ?>" placeholder="6-digit pincode" maxlength="6">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-light px-4 py-2 rounded-3 fw-semibold">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-semibold shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Save Changes
                        </button>
                    </div>

                </div>

            </div>
        </form>

    </div>
</main>

<script>
    // Live Avatar Preview
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreview = document.getElementById('avatarPreview');

    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            if (file.size > 2 * 1024 * 1024) {
                alert('Avatar image size must be less than 2 MB.');
                this.value = '';
                return;
            }

            const allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowed.includes(file.type)) {
                alert('Only JPG, PNG and WEBP image files are allowed.');
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                avatarPreview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    // Auto-dismiss Alerts
    const alertEl = document.getElementById('profileAlert');
    if (alertEl) {
        setTimeout(function () {
            alertEl.style.transition = 'opacity 0.35s ease';
            alertEl.style.opacity = '0';
            setTimeout(function () { alertEl.remove(); }, 350);
        }, 3500);
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>