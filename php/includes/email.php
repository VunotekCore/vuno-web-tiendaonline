<?php
/**
 * Email Service - PHP
 * Uses PHPMailer (composer require phpmailer/phpmailer)
 * Uses file-based HTML email templates with {{variable}} placeholders.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

define('EMAIL_TEMPLATE_DIR', __DIR__ . '/../email-templates');

/**
 * Load and render an email template file.
 * Template files have a header section with Subject: and Preheader: lines,
 * followed by the HTML body with {{variable}} placeholders.
 */
function renderTemplate(string $templateCode, array $vars): array
{
    $filePath = EMAIL_TEMPLATE_DIR . '/' . $templateCode . '.html';
    if (!file_exists($filePath)) {
        throw new RuntimeException("Email template not found: {$templateCode}");
    }

    $raw = file_get_contents($filePath);

    // Extract Subject and Preheader from header lines
    $subject = '';
    $preheader = '';
    if (preg_match('/^Subject:\s*(.+)$/m', $raw, $m)) $subject = trim($m[1]);
    if (preg_match('/^Preheader:\s*(.+)$/m', $raw, $m)) $preheader = trim($m[1]);

    // Remove header lines (Subject:, Preheader:, and the blank line after)
    $body = preg_replace('/^(Subject|Preheader):\s*.+\n+/m', '', $raw);
    $body = trim($body);

    // Replace {{variable}} placeholders
    $replacements = [];
    foreach ($vars as $key => $value) {
        $replacements['{{' . $key . '}}'] = $value;
    }
    $body = strtr($body, $replacements);

    // Replace subject variables too
    $subject = strtr($subject, $replacements);

    return [
        'subject'   => $subject,
        'preheader' => $preheader,
        'body_html' => $body,
    ];
}

/**
 * Build the HTML items table rows for order confirmation.
 */
function renderOrderItemsHtml(array $items): string
{
    $html = '';
    foreach ($items as $item) {
        $p = $item['product'];
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
            <td style="padding:12px 0;border-bottom:1px solid #f0eeeb;vertical-align:middle;text-align:right;font-size:14px;color:#1a1a1a;font-weight:600">$' . number_format(($p['price'] ?? 0) * ($item['quantity'] ?? 1), 2) . '</td>
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
    $discount = (float)($order['discountTotal'] ?? 0);
    $subtotal = number_format((float)($order['subtotal'] ?? 0), 2);
    $total = number_format((float)($order['total'] ?? 0), 2);
    $shipping = (float)($order['shipping'] ?? 0) > 0
        ? '$' . number_format((float)($order['shipping'] ?? 0), 2)
        : 'Free';

    $itemsHtml = renderOrderItemsHtml($order['items'] ?? []);

    $couponBlock = '';
    if ($discount > 0) {
        $couponBlock = '<p style="margin:-16px 0 24px;font-size:13px;color:#6b6b6b">Discount applied: -$' . number_format($discount, 2) . '</p>';
    }

    $vars = [
        'customer_name'        => $name,
        'order_id'             => $orderId,
        'order_items_html'     => $itemsHtml,
        'order_subtotal'       => $subtotal,
        'order_shipping'       => $shipping,
        'order_total'          => $total,
        'coupon_discount_block' => $couponBlock,
        'transfer_details_block' => '',
        'preheader'            => "Your order #{$orderId} has been confirmed",
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
    $total = number_format((float)($order['total'] ?? 0), 2);
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
        'customer_name'   => $name,
        'customer_email'  => $email,
        'order_id'        => $orderId,
        'order_total'     => $total,
        'payment_method'  => $paymentMethod,
        'order_status'    => $status,
        'items_count'     => (string)$itemsCount,
        'receipt_block'   => $receiptBlock,
        'admin_order_url' => $adminUrl,
        'preheader'       => "New order #{$orderId}: {$name}",
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
