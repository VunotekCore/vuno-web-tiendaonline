<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/email.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$code = trim($input['code'] ?? '');
$subjectOverride = trim($input['subject'] ?? '');
$bodyOverride = trim($input['body_html'] ?? '');

if (!$code && !$bodyOverride) {
    jsonError('Provide code or body_html to preview');
}

$testVars = [
    'customer_name'     => 'María García',
    'customer_email'    => 'maria@example.com',
    'order_id'          => 'ORD-2026-0001',
    'order_items_html'  => renderOrderItemsHtml([
        ['product' => ['name' => 'Mule Sofía', 'display_price' => 225, 'images' => ['https://placehold.co/48x48/1a1a1a/f5f3f0?text=S']], 'selectedColor' => 'Black', 'selectedSize' => '38', 'quantity' => 1],
        ['product' => ['name' => 'Sandalia Nómada', 'display_price' => 195, 'images' => ['https://placehold.co/48x48/1a1a1a/f5f3f0?text=N']], 'selectedColor' => 'Nude', 'selectedSize' => '39', 'quantity' => 2],
    ], '$'),
    'order_subtotal'    => '615.00',
    'order_shipping'    => 'Free',
    'order_total'       => '615.00',
    'coupon_discount_block' => '',
    'order_tax'         => '$0.00',
    'currency_symbol'   => '$',
    'payment_method'    => 'stripe',
    'order_status'      => 'paid',
    'items_count'       => '3',
    'receipt_block'     => '',
    'admin_order_url'   => 'http://localhost:4321/admin/pedidos/detalle?id=1',
    'preheader'         => 'Vista previa de plantilla',
    'transfer_details_block' => '',
    'subscriber_name'        => 'María García',
    'unsubscribe_url'        => 'http://localhost:4321/api/email/unsubscribe.php?email=maria@example.com',
    'title'                  => 'New Collection: Artisan Summer',
    'message'                => 'Discover our latest handcrafted collection, made with love by artisans.',
    'content_block'          => '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px"><tr><td style="background:#f5f3f0;padding:20px;border-radius:2px"><p style="margin:0;font-size:15px;color:#1a1a1a">This is a sample content block for preview purposes.</p></td></tr></table>',
];

try {
    if ($bodyOverride) {
        $subject = $subjectOverride ?: 'Test Subject';
        $storeVars = getStoreTemplateVars();
        $allVars = array_merge($storeVars, $testVars);
        $replacements = [];
        foreach ($allVars as $key => $value) {
            $replacements['{{' . $key . '}}'] = $value;
        }
        $bodyRendered = strtr($bodyOverride, $replacements);
        $subjectRendered = strtr($subject, $replacements);
    } else {
        $rendered = renderTemplate($code, $testVars);
        $bodyRendered = $rendered['body_html'];
        $subjectRendered = $rendered['subject'];
    }

    jsonResponse([
        'success' => true,
        'subject' => $subjectRendered,
        'body_html' => $bodyRendered,
    ]);
} catch (\Exception $e) {
    jsonError('Preview failed: ' . $e->getMessage(), 500);
}
