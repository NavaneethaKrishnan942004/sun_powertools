<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Please log in.'
    ]);
    exit;
}

require_once __DIR__ . '/includes/customer_helper.php';
require_once __DIR__ . '/includes/sales_note_helper.php';

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$conn = $pdo;

try {
    switch ($action) {
        case 'get_customer':
            $customerId = (int) ($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
            if ($customerId <= 0) {
                throw new InvalidArgumentException('Invalid Customer ID');
            }

            $stmt = $conn->prepare("
                SELECT * FROM customer_master 
                WHERE id = :id AND status = 1 
                LIMIT 1
            ");
            $stmt->execute([':id' => $customerId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                throw new RuntimeException('Customer not found or inactive');
            }

            // Build dynamic financial summary
            $summary = getCustomerFinancialSummary($conn, $customerId, $customer);
            $creditEval = evaluateCreditStatus(
                (float) $summary['current_outstanding'],
                (float) $summary['credit_limit'],
                (int) $summary['credit_allowed']
            );

            // Format address
            $addressParts = array_filter([
                $customer['address'] ?? '',
                $customer['area'] ?? '',
                $customer['city'] ?? '',
                $customer['state'] ?? '',
                $customer['pincode'] ?? ''
            ]);
            $formattedAddress = implode(', ', $addressParts);

            echo json_encode([
                'success' => true,
                'customer' => [
                    'id' => (int) $customer['id'],
                    'customer_code' => $customer['customer_code'],
                    'customer_name' => $customer['customer_name'],
                    'company_name' => $customer['company_name'] ?? '',
                    'mobile_number' => $customer['mobile_number'],
                    'email' => $customer['email'] ?? '',
                    'gst_number' => $customer['gst_number'] ?? '',
                    'address' => $formattedAddress ?: ($customer['address'] ?? 'N/A'),
                    'credit_allowed' => (int) $customer['credit_allowed'],
                    'credit_limit' => (float) $customer['credit_limit'],
                    'payment_terms' => $customer['payment_terms'] ?? 'Immediate',
                    'current_outstanding' => (float) $summary['current_outstanding'],
                    'available_credit' => (float) $summary['available_credit'],
                    'credit_utilization_percent' => (float) $creditEval['percent'],
                    'credit_status' => $creditEval['status'],
                    'credit_status_code' => $creditEval['code'],
                    'can_credit' => $creditEval['can_credit'],
                    'badge_class' => $creditEval['badge_class'],
                    'credit_message' => $creditEval['message']
                ]
            ]);
            break;

        case 'get_product':
            $productId = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
            if ($productId <= 0) {
                throw new InvalidArgumentException('Invalid Product ID');
            }

            $stmt = $conn->prepare("
                SELECT 
                    p.*,
                    c.category_name,
                    b.brand_name,
                    u.unit_name
                FROM product_master p
                LEFT JOIN category_master c ON c.id = p.category_id
                LEFT JOIN brand_master b ON b.id = p.brand_id
                LEFT JOIN unit_master u ON u.id = p.sale_unit
                WHERE p.id = :id AND p.status = 1
                LIMIT 1
            ");
            $stmt->execute([':id' => $productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new RuntimeException('Product not found or inactive');
            }

            echo json_encode([
                'success' => true,
                'product' => [
                    'id' => (int) $product['id'],
                    'product_code' => $product['product_code'],
                    'product_name' => $product['product_name'],
                    'short_name' => $product['short_name'] ?? '',
                    'category_name' => $product['category_name'] ?? 'General',
                    'brand_name' => $product['brand_name'] ?? 'Generic',
                    'unit_name' => $product['unit_name'] ?? 'Unit',
                    'sale_available' => (int) $product['sale_available'],
                    'selling_price' => (float) ($product['selling_price'] ?? 0.0),
                    'discount_allowed' => (int) ($product['discount_allowed'] ?? 0),
                    'discount_percent' => (float) ($product['discount_percent'] ?? 0.0),
                    'stock_quantity' => (int) ($product['stock_quantity'] ?? 0)
                ]
            ]);
            break;

        case 'search_customers':
            $q = trim($_GET['q'] ?? '');
            $sql = "
                SELECT id, customer_code, customer_name, company_name, mobile_number, credit_allowed, credit_limit
                FROM customer_master
                WHERE status = 1
            ";
            $params = [];
            if ($q !== '') {
                $sql .= " AND (customer_code LIKE :q OR customer_name LIKE :q OR company_name LIKE :q OR mobile_number LIKE :q)";
                $params[':q'] = "%{$q}%";
            }
            $sql .= " ORDER BY customer_name ASC LIMIT 20";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'customers' => $customers
            ]);
            break;

        case 'search_products':
            $q = trim($_GET['q'] ?? '');
            $sql = "
                SELECT 
                    p.id, 
                    p.product_code, 
                    p.product_name, 
                    p.selling_price, 
                    p.stock_quantity,
                    p.discount_percent,
                    p.discount_allowed,
                    c.category_name,
                    b.brand_name
                FROM product_master p
                LEFT JOIN category_master c ON c.id = p.category_id
                LEFT JOIN brand_master b ON b.id = p.brand_id
                WHERE p.status = 1 AND p.sale_available = 1
            ";
            $params = [];
            if ($q !== '') {
                $sql .= " AND (p.product_code LIKE :q OR p.product_name LIKE :q OR p.short_name LIKE :q)";
                $params[':q'] = "%{$q}%";
            }
            $sql .= " ORDER BY p.product_name ASC LIMIT 25";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'products' => $products
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
