<?php
/**
 * Global Helpers & Application Utility Functions
 * MOMAI PLYWOOD - Core PHP
 */

// Start session securely if not already active
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

require_once __DIR__ . '/../config/db.php';

/**
 * Sanitize string for HTML output
 */
function e(?string $string): string
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Return currently authenticated user array or null
 */
function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Check if user is logged in
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Set flash session message
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

/**
 * Get and clear flash message
 */
function flash_get(string $type): ?string
{
    if (isset($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

/**
 * Generate or get CSRF token
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output CSRF hidden input HTML
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Validate CSRF token from POST request
 */
function csrf_verify(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            die("Page Expired / Invalid CSRF Token. Please refresh and try again.");
        }
    }
}

/**
 * Redirect helper
 */
function redirect(string $url): void
{
    header("Location: " . $url);
    exit;
}

/**
 * Format currency in Indian Rupees
 */
function format_inr(float $amount, int $decimals = 2): string
{
    return '₹' . number_format($amount, $decimals);
}

/**
 * Generate sequential bill number: INV-ymd-0501
 */
function generate_bill_number(PDO $pdo): string
{
    $date = date('ymd');
    $todayStart = date('Y-m-d 00:00:00');
    $todayEnd = date('Y-m-d 23:59:59');

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bills WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$todayStart, $todayEnd]);
    $count = (int) $stmt->fetchColumn() + 501;

    return 'INV-' . $date . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
}

/**
 * Convert integer to Indian Currency Words (Crore, Lakh, Thousand, Hundred)
 */
function convert_number_to_words(int $number): string
{
    if ($number === 0) {
        return 'Zero';
    }

    $units = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    $words = [];

    if ($number >= 10000000) {
        $crore = intdiv($number, 10000000);
        $words[] = convert_number_to_words($crore) . ' Crore';
        $number %= 10000000;
    }

    if ($number >= 100000) {
        $lakh = intdiv($number, 100000);
        $words[] = convert_number_to_words($lakh) . ' Lakh';
        $number %= 100000;
    }

    if ($number >= 1000) {
        $thousand = intdiv($number, 1000);
        $words[] = convert_number_to_words($thousand) . ' Thousand';
        $number %= 1000;
    }

    if ($number >= 100) {
        $hundred = intdiv($number, 100);
        $words[] = convert_number_to_words($hundred) . ' Hundred';
        $number %= 100;
    }

    if ($number > 0) {
        if ($number < 20) {
            $words[] = $units[$number];
        } else {
            $part = $tens[intdiv($number, 10)];
            if ($number % 10 > 0) {
                $part .= ' ' . $units[$number % 10];
            }
            $words[] = $part;
        }
    }

    return implode(' ', $words);
}
