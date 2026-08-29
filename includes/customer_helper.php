<?php

/**
 * Customer Master Helper Functions
 * Provides dynamic balance calculation from financial transactions.
 */

function generateCustomerCode(PDO $conn): string
{
    $stmt = $conn->prepare("SELECT customer_code FROM customer_master ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $lastCode = $stmt->fetchColumn();

    if (!$lastCode) {
        return 'CUS-001';
    }

    $number = (int) str_replace('CUS-', '', $lastCode) + 1;
    return 'CUS-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
}

/**
 * Calculates dynamic financial summary for a customer
 * Outstanding = (Opening Debit - Opening Credit) + Total Sales + Total Rentals - Total Payments - Total Returns
 */
function getCustomerFinancialSummary(PDO $conn, int $customerId, array $customerData = []): array
{
    if (empty($customerData)) {
        $stmt = $conn->prepare("SELECT * FROM customer_master WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $customerId]);
        $customerData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $openingBalance = (float) ($customerData['opening_balance'] ?? 0.00);
    $openingType = $customerData['opening_balance_type'] ?? 'Debit';
    $creditLimit = (float) ($customerData['credit_limit'] ?? 0.00);
    $creditAllowed = (int) ($customerData['credit_allowed'] ?? 0);

    // Initial base from opening balance
    $netDebit = ($openingType === 'Debit') ? $openingBalance : -$openingBalance;

    $totalSales = 0.00;
    $totalRentals = 0.00;
    $totalPayments = 0.00;
    $totalReturns = 0.00;

    try {
        // Query transactions if transaction table exists
        $stmt = $conn->prepare("
            SELECT 
                transaction_type,
                COALESCE(SUM(debit_amount), 0) AS sum_debit,
                COALESCE(SUM(credit_amount), 0) AS sum_credit,
                COALESCE(SUM(total_amount), 0) AS sum_total
            FROM customer_transactions 
            WHERE customer_id = :customer_id
            GROUP BY transaction_type
        ");
        $stmt->execute([':customer_id' => $customerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $type = strtolower($r['transaction_type']);
            if ($type === 'sale') {
                $totalSales = (float) $r['sum_total'];
                $netDebit += (float) $r['sum_debit'];
            } elseif ($type === 'rental') {
                $totalRentals = (float) $r['sum_total'];
                $netDebit += (float) $r['sum_debit'];
            } elseif ($type === 'payment') {
                $totalPayments = (float) $r['sum_credit'];
                $netDebit -= (float) $r['sum_credit'];
            } elseif ($type === 'return' || $type === 'adjustment') {
                $totalReturns += (float) $r['sum_credit'];
                $netDebit -= (float) $r['sum_credit'];
            }
        }
    } catch (PDOException $e) {
        // If customer_transactions query fails, fallback to opening balance
    }

    $currentOutstanding = $netDebit;
    $availableCredit = ($creditAllowed && $creditLimit > 0) ? max(0.00, $creditLimit - max(0.00, $currentOutstanding)) : 0.00;

    return [
        'opening_balance' => $openingBalance,
        'opening_balance_type' => $openingType,
        'credit_allowed' => $creditAllowed,
        'credit_limit' => $creditLimit,
        'total_sales' => $totalSales,
        'total_rentals' => $totalRentals,
        'total_payments' => $totalPayments,
        'total_returns' => $totalReturns,
        'current_outstanding' => $currentOutstanding,
        'available_credit' => $availableCredit,
        'is_settled' => (abs($currentOutstanding) < 0.001),
        'is_debit' => ($currentOutstanding > 0.001),
        'is_credit' => ($currentOutstanding < -0.001),
    ];
}

/**
 * Format balance string with badge for display
 */
function formatCustomerBalance(array $summary): string
{
    $bal = $summary['current_outstanding'];
    if ($summary['is_settled']) {
        return '<span class="badge bg-success-subtle text-success border border-success-subtle">₹0.00 Settled</span>';
    } elseif ($summary['is_debit']) {
        return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-semibold">Debit: ₹' . number_format($bal, 2) . '</span>';
    } else {
        return '<span class="badge bg-info-subtle text-info border border-info-subtle fw-semibold">Credit: ₹' . number_format(abs($bal), 2) . '</span>';
    }
}

/**
 * Checks if a customer has financial transaction history (prevent hard delete)
 */
function customerHasTransactions(PDO $conn, int $customerId): bool
{
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM customer_transactions WHERE customer_id = :id");
        $stmt->execute([':id' => $customerId]);
        return ((int) $stmt->fetchColumn() > 0);
    } catch (PDOException $e) {
        return false;
    }
}
