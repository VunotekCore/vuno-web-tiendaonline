<?php
/**
 * Email Service - PHP
 * Uses PHPMailer (composer require phpmailer/phpmailer)
 * Supports DB-backed templates with file-based fallback.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/storage.php';

define('EMAIL_TEMPLATE_DIR', __DIR__ . '/../email-templates');

/**
 * Load an email template from DB or file.
 * Checks DB first (email_templates table), falls back to file in email-templates/.
 */
function loadTemplateSource(string $templateCode): array
{
    // Try DB first
    try {
        $dbTemplate = getEmailTemplateByCode($templateCode);
        if ($dbTemplate && $dbTemplate['is_active']) {
            return [
                'subject'   => $dbTemplate['subject'],
                'body_html' => $dbTemplate['body_html'],
                'preheader' => '',
                'source'    => 'db',
            ];
        }
    } catch (\Exception $e) {
        // DB not available, fall through to file
    }

    // Fallback to file
    $filePath = EMAIL_TEMPLATE_DIR . '/' . $templateCode . '.html';
    if (!file_exists($filePath)) {
        throw new RuntimeException("Email template not found: {$templateCode}");
    }

    $raw = file_get_contents($filePath);

    $subject = '';
    $preheader = '';
    if (preg_match('/^Subject:\s*(.+)$/m', $raw, $m)) $subject = trim($m[1]);
    if (preg_match('/^Preheader:\s*(.+)$/m', $raw, $m)) $preheader = trim($m[1]);

    $body = preg_replace('/^(Subject|Preheader):\s*.+\n+/m', '', $raw);
    $body = trim($body);

    return [
        'subject'   => $subject,
        'body_html' => $body,
        'preheader' => $preheader,
        'source'    => 'file',
    ];
}

/**
 * Get global store variables for email templates.
 * Loaded from settings table and auto-injected into every template.
 */
function getStoreTemplateVars(): array
{
    $vars = [
        'store_name'       => 'Ram;Lop',
        'store_logo_url'   => '',
        'store_slogan'     => 'Architectural Minimalism in Footwear',
        'store_email'      => '',
        'store_logo_block' => '<p style="margin:0;font-family:\'Playfair Display\',Georgia,serif;font-size:20px;color:#f5f3f0;letter-spacing:0.02em">Ram;Lop</p>',
    ];

    try {
        $settings = getSettings();
        $store = $settings['store'] ?? [];

        if (!empty($store['name'])) {
            $vars['store_name'] = htmlspecialchars($store['name']);
            $name = $vars['store_name'];
        } else {
            $name = 'Ram;Lop';
        }

        $vars['store_slogan'] = !empty($store['slogan'])
            ? htmlspecialchars($store['slogan'])
            : 'Architectural Minimalism in Footwear';

        $vars['store_email'] = !empty($store['email'])
            ? htmlspecialchars($store['email'])
            : '';

        $vars['store_logo_url'] = !empty($store['logo'])
            ? htmlspecialchars($store['logo'])
            : '';

        // Build logo block: image if URL exists, else styled text
        if ($vars['store_logo_url']) {
            $vars['store_logo_block'] = '<img src="' . $vars['store_logo_url'] . '" alt="' . $name . '" height="28" style="border:0;height:28px;line-height:100%;outline:none;text-decoration:none;display:block" />';
        } else {
            $vars['store_logo_block'] = '<p style="margin:0;font-family:\'Playfair Display\',Georgia,serif;font-size:20px;color:#f5f3f0;letter-spacing:0.02em">' . $name . '</p>';
        }
    } catch (\Exception $e) {
        // Defaults already set above
    }

    return $vars;
}

/**
 * Load and render an email template.
 * Uses loadTemplateSource() to get template from DB or file,
 * then replaces {{variable}} placeholders.
 * Auto-injects global store variables (store_name, store_logo_block, etc.).
 */
function renderTemplate(string $templateCode, array $vars): array
{
    $source = loadTemplateSource($templateCode);

    // Merge global store vars (user vars take precedence)
    $globalVars = getStoreTemplateVars();
    $allVars = array_merge($globalVars, $vars);

    // Build replacements map
    $replacements = [];
    foreach ($allVars as $key => $value) {
        $replacements['{{' . $key . '}}'] = $value;
    }

    $body = strtr($source['body_html'], $replacements);
    $subject = strtr($source['subject'], $replacements);
    $preheader = strtr($source['preheader'], $replacements);

    return [
        'subject'   => $subject,
        'preheader' => $preheader,
        'body_html' => $body,
        'source'    => $source['source'],
    ];
}

/**
 * Build the HTML items table rows for order confirmation.
 */
function renderOrderItemsHtml(array $items, string $currencySymbol = '$'): string
{
    $html = '';
    foreach ($items as $item) {
        $p = $item['product'];
        $unitPrice = $p['display_price'] ?? $p['price'] ?? 0;
        $img = !empty($p['images'][0])
            ? '<img src="' . htmlspecialchars($p['images'][0]) . '" alt="" width="48" height="48" style="border-radius:2px;object-fit:cover;display:block" />'
            : '';
        $html .= '<tr>
            <td style="padding:12px 0;border-bottom:1px solid #f0eeeb;vertical-align:middle">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td style="padding-right:12px;vertical-align:middle">' . $img . '</td>
                    <td style="vertical-align:middle">
                        <p style="margin:0;font-size:14px;color:#1a1a1a;font-weight:600">' . htmlspecialchars($p['name'] ?? '') . '</p>
                        <p style="margin:2px 0 0;font-size:12px;color:#9A9A9A">' . htmlspecialchars($item['selectedColor'] ?? '') . ' / ' . htmlspecialchars($item['selectedSize'] ?? '') . '</p>
                    </td>
                </tr></table>
            </td>
            <td style="padding:12px 0;border-bottom:1px solid #f0eeeb;vertical-align:middle;text-align:center;font-size:14px;color:#1a1a1a">' . (int)$item['quantity'] . '</td>
            <td style="padding:12px 0;border-bottom:1px solid #f0eeeb;vertical-align:middle;text-align:right;font-size:14px;color:#1a1a1a;font-weight:600">' . htmlspecialchars($currencySymbol) . number_format($unitPrice * ($item['quantity'] ?? 1), 2) . '</td>
        </tr>';
    }
    return $html;
}

/**
 * Send an email using a template file.
 */
function sendTemplatedEmail(string $templateCode, string $to, array $vars, ?string $fromEmail = null): array
{
    $template = renderTemplate($templateCode, $vars);

    $host = env('SMTP_HOST');
    $user = env('SMTP_USER');
    $from = $fromEmail ?: env('FROM_EMAIL', 'noreply@vuno.com');

    if (!$host || !$user) {
        error_log("[Ram;Lop Email] SMTP not configured. Would send to: $to, Subject: {$template['subject']}");
        return ['success' => true, 'note' => 'Email not sent (SMTP not configured)'];
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int)env('SMTP_PORT', '587');
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = env('SMTP_PASS');
        $mail->SMTPSecure = (int)env('SMTP_PORT', '587') === 587
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($from, 'Ram;Lop');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $template['subject'];
        $mail->Body = $template['body_html'];
        $mail->AltBody = strip_tags($template['body_html']);

        $mail->send();
        return ['success' => true];
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("[Ram;Lop Email] Failed: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Send order confirmation email to the customer.
 */
function sendOrderConfirmation(array $order): array
{
    $name = htmlspecialchars($order['customer']['name'] ?? 'Customer');
    $orderId = htmlspecialchars($order['id']);
    $currencySymbol = $order['display_symbol'] ?? '$';
    $discount = (float)($order['discountTotal'] ?? 0);

    // Use display values if available, otherwise format USD
    $subtotal = number_format((float)($order['display_subtotal'] ?? $order['subtotal'] ?? 0), 2);
    $total = number_format((float)($order['display_total'] ?? $order['total'] ?? 0), 2);
    $shipping = (float)($order['display_shipping'] ?? $order['shipping'] ?? 0) > 0
        ? $currencySymbol . number_format((float)($order['display_shipping'] ?? $order['shipping'] ?? 0), 2)
        : 'Free';

    $itemsHtml = renderOrderItemsHtml($order['items'] ?? [], $currencySymbol);

    $couponBlock = '';
    if ($discount > 0) {
        $displayDiscount = $order['display_discountTotal'] ?? $discount;
        $couponBlock = '<p style="margin:-16px 0 24px;font-size:13px;color:#6b6b6b">Discount applied: -' . $currencySymbol . number_format((float)$displayDiscount, 2) . '</p>';
    }

    $vars = [
        'customer_name'         => $name,
        'order_id'              => $orderId,
        'order_items_html'      => $itemsHtml,
        'order_subtotal'        => $subtotal,
        'order_shipping'        => $shipping,
        'order_total'           => $total,
        'coupon_discount_block' => $couponBlock,
        'transfer_details_block' => '',
        'currency_symbol'       => $currencySymbol,
        'preheader'             => "Your order #{$orderId} has been confirmed",
    ];

    return sendTemplatedEmail('order_confirmation', $order['customer']['email'], $vars);
}

/**
 * Send new order notification to the admin.
 */
function sendNewOrderNotification(array $order): array
{
    $name = htmlspecialchars($order['customer']['name'] ?? '');
    $email = htmlspecialchars($order['customer']['email'] ?? '');
    $orderId = htmlspecialchars($order['id']);
    $currencySymbol = $order['display_symbol'] ?? '$';
    $total = number_format((float)($order['display_total'] ?? $order['total'] ?? 0), 2);
    $paymentMethod = htmlspecialchars($order['paymentMethod'] ?? '');
    $status = htmlspecialchars($order['status'] ?? '');
    $itemsCount = count($order['items'] ?? []);

    $receiptBlock = '';
    if (!empty($order['transferReceipt'])) {
        $receiptBlock = '<p style="margin:0 0 8px">Receipt: <a href="' . htmlspecialchars($order['transferReceipt']) . '" style="color:#1a1a1a">View payment receipt</a></p>';
    }

    $appUrl = env('APP_URL', 'http://localhost:4321');
    $adminUrl = $appUrl . '/admin/pedidos/detalle?id=' . urlencode($orderId);

    $vars = [
        'customer_name'    => $name,
        'customer_email'   => $email,
        'order_id'         => $orderId,
        'order_total'      => $currencySymbol . $total,
        'payment_method'   => $paymentMethod,
        'order_status'     => $status,
        'items_count'      => (string)$itemsCount,
        'receipt_block'    => $receiptBlock,
        'admin_order_url'  => $adminUrl,
        'currency_symbol'  => $currencySymbol,
        'preheader'        => "New order #{$orderId}: {$name}",
    ];

    $adminEmail = env('ADMIN_EMAIL', 'admin@vuno.com');
    return sendTemplatedEmail('new_order_notification', $adminEmail, $vars);
}

/**
 * Legacy: send plain email with custom HTML.
 */
function sendEmail(string $to, string $subject, string $html, ?string $fromEmail = null): array
{
    $host = env('SMTP_HOST');
    $user = env('SMTP_USER');
    $from = $fromEmail ?: env('FROM_EMAIL', 'noreply@vuno.com');

    if (!$host || !$user) {
        error_log("[Ram;Lop Email] SMTP not configured. Would send to: $to, Subject: $subject");
        return ['success' => true, 'note' => 'Email not sent (SMTP not configured)'];
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int)env('SMTP_PORT', '587');
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = env('SMTP_PASS');
        $mail->SMTPSecure = (int)env('SMTP_PORT', '587') === 587
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($from, 'Ram;Lop');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = strip_tags($html);

        $mail->send();
        return ['success' => true];
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("[Ram;Lop Email] Failed: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
