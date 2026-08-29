<?php

/**
 * Sales Note Helper Functions - Sun PowerTools
 * Integrates customer credit management, stock ledger, financial status & analytics
 */

require_once __DIR__ . '/customer_helper.php';

/**
 * Generate Next Sales Note Number
 * Pattern: SN-001 or SN-2026-0001
 */
function generateSalesNoteNumber(PDO $conn): string
{
    $stmt = $conn->prepare("SELECT sales_note_no FROM sales_notes ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $lastCode = $stmt->fetchColumn();

    if (!$lastCode) {
        return 'SN-' . date('Y') . '-0001';
    }

    // Support both SN-YYYY-XXXX and SN-XXXX patterns
    if (preg_match('/SN-(\d{4})-(\d+)/', $lastCode, $matches)) {
        $year = $matches[1];
        $seq = (int) $matches[2];
        $currentYear = date('Y');
        if ($year === $currentYear) {
            $nextSeq = $seq + 1;
            return 'SN-' . $currentYear . '-' . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
        } else {
            return 'SN-' . $currentYear . '-0001';
        }
    } elseif (preg_match('/SN-(\d+)/', $lastCode, $matches)) {
        $seq = (int) $matches[1] + 1;
        return 'SN-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    return 'SN-' . date('Y') . '-0001';
}

/**
 * Evaluate Customer Credit Status & Utilization
 * 
 * Thresholds:
 * 0–79.99%  → Normal (Within Limit)
 * 80–89.99% → Warning
 * 90–99.99% → Near Limit
 * 100%      → Limit Reached
 * > 100%    → Limit Exceeded
 */
function evaluateCreditStatus(float $newOutstanding, float $creditLimit, int $creditAllowed): array
{
    if ($creditAllowed === 0) {
        return [
            'status' => 'Credit Not Allowed',
            'code' => 'NOT_ALLOWED',
            'percent' => 0.0,
            'badge_class' => 'bg-danger-subtle text-danger border border-danger-subtle',
            'row_class' => '',
            'is_exceeded' => false,
            'is_near_limit' => false,
            'is_reached' => false,
            'can_credit' => false,
            'message' => 'Credit is not allowed for this customer.'
        ];
    }

    if ($creditLimit <= 0) {
        if ($newOutstanding > 0.001) {
            return [
                'status' => 'Limit Exceeded',
                'code' => 'EXCEEDED',
                'percent' => 100.0,
                'badge_class' => 'bg-danger text-white border border-danger',
                'row_class' => 'table-danger',
                'is_exceeded' => true,
                'is_near_limit' => false,
                'is_reached' => true,
                'can_credit' => false,
                'message' => 'Customer has ₹0 credit limit. Any credit sale exceeds limit.'
            ];
        }
        return [
            'status' => 'Within Limit',
            'code' => 'NORMAL',
            'percent' => 0.0,
            'badge_class' => 'bg-success-subtle text-success border border-success-subtle',
            'row_class' => '',
            'is_exceeded' => false,
            'is_near_limit' => false,
            'is_reached' => false,
            'can_credit' => true,
            'message' => 'Within credit limit.'
        ];
    }

    $percent = round(($newOutstanding / $creditLimit) * 100, 1);

    if ($percent > 100.0) {
        return [
            'status' => 'Limit Exceeded',
            'code' => 'EXCEEDED',
            'percent' => $percent,
            'badge_class' => 'bg-danger text-white border border-danger fw-bold',
            'row_class' => 'table-danger',
            'is_exceeded' => true,
            'is_near_limit' => false,
            'is_reached' => true,
            'can_credit' => false,
            'message' => "Credit limit exceeded by ₹" . number_format($newOutstanding - $creditLimit, 2) . " ({$percent}% utilized)."
        ];
    } elseif (abs($percent - 100.0) < 0.01) {
        return [
            'status' => 'Limit Reached',
            'code' => 'REACHED',
            'percent' => 100.0,
            'badge_class' => 'bg-danger-subtle text-danger border border-danger fw-semibold',
            'row_class' => 'table-warning',
            'is_exceeded' => false,
            'is_near_limit' => false,
            'is_reached' => true,
            'can_credit' => true,
            'message' => 'Credit limit has reached 100% capacity.'
        ];
    } elseif ($percent >= 90.0) {
        return [
            'status' => 'Near Limit',
            'code' => 'NEAR_LIMIT',
            'percent' => $percent,
            'badge_class' => 'bg-warning text-dark border border-warning-subtle fw-semibold',
            'row_class' => 'table-warning-subtle',
            'is_exceeded' => false,
            'is_near_limit' => true,
            'is_reached' => false,
            'can_credit' => true,
            'message' => "Approaching credit limit ({$percent}% utilized)."
        ];
    } elseif ($percent >= 80.0) {
        return [
            'status' => 'Warning (80%+)',
            'code' => 'WARNING',
            'percent' => $percent,
            'badge_class' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold',
            'row_class' => '',
            'is_exceeded' => false,
            'is_near_limit' => true,
            'is_reached' => false,
            'can_credit' => true,
            'message' => "Customer credit utilization is high ({$percent}% utilized)."
        ];
    } else {
        return [
            'status' => 'Within Limit',
            'code' => 'NORMAL',
            'percent' => max(0.0, $percent),
            'badge_class' => 'bg-success-subtle text-success border border-success-subtle fw-medium',
            'row_class' => '',
            'is_exceeded' => false,
            'is_near_limit' => false,
            'is_reached' => false,
            'can_credit' => true,
            'message' => "Normal credit state ({$percent}% utilized)."
        ];
    }
}

/**
 * Format Payment Type Badge
 */
function getPaymentTypeBadge(string $type): string
{
    $map = [
        'Cash'          => 'bg-success-subtle text-success border-success-subtle',
        'UPI'           => 'bg-info-subtle text-info border-info-subtle',
        'Card'          => 'bg-primary-subtle text-primary border-primary-subtle',
        'Bank Transfer' => 'bg-secondary-subtle text-secondary border-secondary-subtle',
        'Credit'        => 'bg-danger-subtle text-danger border-danger-subtle',
        'Mixed'         => 'bg-warning-subtle text-warning-emphasis border-warning-subtle'
    ];

    $cls = $map[$type] ?? 'bg-light text-dark border';
    return '<span class="badge ' . $cls . ' border px-2 py-1 rounded-pill">' . htmlspecialchars($type) . '</span>';
}

/**
 * Fetch Comprehensive Sales & Business Analytics Summary
 */
function getSalesAnalytics(PDO $conn): array
{
    $analytics = [
        'sales_summary' => [
            'today_sales' => 0.0,
            'today_count' => 0,
            'week_sales' => 0.0,
            'week_count' => 0,
            'month_sales' => 0.0,
            'month_count' => 0,
            'total_sales' => 0.0,
            'total_count' => 0,
            'avg_sale_value' => 0.0,
            'total_paid' => 0.0,
            'total_credit' => 0.0,
        ],
        'payment_breakdown' => [],
        'credit_analysis' => [
            'total_outstanding' => 0.0,
            'total_credit_sales' => 0.0,
            'customers_with_balance' => 0,
            'customers_near_limit' => 0,
            'customers_reached_limit' => 0,
            'customers_exceeded_limit' => 0,
        ],
        'top_products' => [],
        'low_stock_products' => []
    ];

    try {
        // 1. Overall Sales Summary
        $stmt = $conn->query("
            SELECT 
                COUNT(*) AS total_count,
                COALESCE(SUM(total_amount), 0) AS total_sales,
                COALESCE(SUM(paid_amount), 0) AS total_paid,
                COALESCE(SUM(credit_amount), 0) AS total_credit,
                COALESCE(AVG(total_amount), 0) AS avg_sale_value
            FROM sales_notes
            WHERE status = 1
        ");
        $sum = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sum) {
            $analytics['sales_summary']['total_count'] = (int) $sum['total_count'];
            $analytics['sales_summary']['total_sales'] = (float) $sum['total_sales'];
            $analytics['sales_summary']['total_paid'] = (float) $sum['total_paid'];
            $analytics['sales_summary']['total_credit'] = (float) $sum['total_credit'];
            $analytics['sales_summary']['avg_sale_value'] = (float) $sum['avg_sale_value'];
        }

        // Today's Sales
        $stmt = $conn->query("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total 
            FROM sales_notes 
            WHERE status = 1 AND sales_date = CURDATE()
        ");
        $today = $stmt->fetch(PDO::FETCH_ASSOC);
        $analytics['sales_summary']['today_count'] = (int) ($today['cnt'] ?? 0);
        $analytics['sales_summary']['today_sales'] = (float) ($today['total'] ?? 0.0);

        // This Week's Sales
        $stmt = $conn->query("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total 
            FROM sales_notes 
            WHERE status = 1 AND YEARWEEK(sales_date, 1) = YEARWEEK(CURDATE(), 1)
        ");
        $week = $stmt->fetch(PDO::FETCH_ASSOC);
        $analytics['sales_summary']['week_count'] = (int) ($week['cnt'] ?? 0);
        $analytics['sales_summary']['week_sales'] = (float) ($week['total'] ?? 0.0);

        // This Month's Sales
        $stmt = $conn->query("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total 
            FROM sales_notes 
            WHERE status = 1 AND MONTH(sales_date) = MONTH(CURDATE()) AND YEAR(sales_date) = YEAR(CURDATE())
        ");
        $month = $stmt->fetch(PDO::FETCH_ASSOC);
        $analytics['sales_summary']['month_count'] = (int) ($month['cnt'] ?? 0);
        $analytics['sales_summary']['month_sales'] = (float) ($month['total'] ?? 0.0);

        // 2. Payment Method Breakdown
        $stmt = $conn->query("
            SELECT 
                payment_type,
                COUNT(*) AS tx_count,
                COALESCE(SUM(total_amount), 0) AS total_amount,
                COALESCE(SUM(paid_amount), 0) AS paid_amount,
                COALESCE(SUM(credit_amount), 0) AS credit_amount
            FROM sales_notes
            WHERE status = 1
            GROUP BY payment_type
        ");
        $analytics['payment_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Customer Credit Analytics
        $stmt = $conn->query("
            SELECT id, customer_code, customer_name, credit_allowed, credit_limit, opening_balance, opening_balance_type
            FROM customer_master
            WHERE status = 1
        ");
        $allCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totOutstanding = 0.0;
        $custWithBalance = 0;
        $custNear = 0;
        $custReached = 0;
        $custExceeded = 0;

        foreach ($allCustomers as $cust) {
            $fSummary = getCustomerFinancialSummary($conn, (int) $cust['id'], $cust);
            $curOut = (float) $fSummary['current_outstanding'];
            $cLimit = (float) $cust['credit_limit'];
            $cAllowed = (int) $cust['credit_allowed'];

            if ($curOut > 0.001) {
                $totOutstanding += $curOut;
                $custWithBalance++;

                $cEval = evaluateCreditStatus($curOut, $cLimit, $cAllowed);
                if ($cEval['code'] === 'EXCEEDED') {
                    $custExceeded++;
                } elseif ($cEval['code'] === 'REACHED') {
                    $custReached++;
                } elseif ($cEval['code'] === 'NEAR_LIMIT' || $cEval['code'] === 'WARNING') {
                    $custNear++;
                }
            }
        }

        $analytics['credit_analysis']['total_outstanding'] = $totOutstanding;
        $analytics['credit_analysis']['total_credit_sales'] = $analytics['sales_summary']['total_credit'];
        $analytics['credit_analysis']['customers_with_balance'] = $custWithBalance;
        $analytics['credit_analysis']['customers_near_limit'] = $custNear;
        $analytics['credit_analysis']['customers_reached_limit'] = $custReached;
        $analytics['credit_analysis']['customers_exceeded_limit'] = $custExceeded;

        // 4. Top Selling Products
        $stmt = $conn->query("
            SELECT 
                sni.product_id,
                sni.product_code,
                sni.product_name,
                COALESCE(SUM(sni.quantity), 0) AS total_qty_sold,
                COALESCE(SUM(sni.line_total), 0) AS total_revenue
            FROM sales_note_items sni
            INNER JOIN sales_notes sn ON sn.id = sni.sales_note_id
            WHERE sn.status = 1
            GROUP BY sni.product_id, sni.product_code, sni.product_name
            ORDER BY total_revenue DESC
            LIMIT 5
        ");
        $analytics['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Low Stock Products (Stock <= 10)
        $stmt = $conn->query("
            SELECT 
                p.id,
                p.product_code,
                p.product_name,
                p.stock_quantity,
                p.selling_price,
                c.category_name,
                b.brand_name
            FROM product_master p
            LEFT JOIN category_master c ON c.id = p.category_id
            LEFT JOIN brand_master b ON b.id = p.brand_id
            WHERE p.status = 1 AND p.sale_available = 1 AND p.stock_quantity <= 10
            ORDER BY p.stock_quantity ASC
            LIMIT 5
        ");
        $analytics['low_stock_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Gracefully handle query failures
    }

    return $analytics;
}
