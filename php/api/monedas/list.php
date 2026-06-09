<?php
/**
 * GET /api/monedas/list.php - List all currencies
 * Public: no auth required (used by checkout frontend)
 * Admin: ?all=1 includes inactive currencies
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/currency.php';

setCorsHeaders();

$showAll = isset($_GET['all']) && $_GET['all'] === '1';

if ($showAll) {
    // Admin only
    require_once __DIR__ . '/../../includes/auth.php';
    startAdminSession();
    if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
    $currencies = getCurrencies();
} else {
    $currencies = getActiveCurrencies();
}

$storeCurrency = getStoreCurrency();

jsonResponse([
    'currencies' => $currencies,
    'storeCurrency' => $storeCurrency['code'],
]);
