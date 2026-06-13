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
        'store_logo_block' => '<p style="margin:0;font-family:\'Playfair Display\',Georgia,serif;font-size:20px;color:#1a1a1a;letter-spacing:0.02em">Ram;Lop</p>',
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
            $vars['store_logo_block'] = '<p style="margin:0;font-family:\'Playfair Display\',Georgia,serif;font-size:20px;color:#1a1a1a;letter-spacing:0.02em">' . $name . '</p>';
        }

        // --- discount_block ---
        $discountCode = $store['newsletter_discount_code'] ?? '';
        if ($discountCode) {
            $vars['discount_block'] = '<div style="background-color:#faf9f8;border:1px solid #e0ddd9;border-radius:2px;padding:24px 16px;margin:24px 0;text-align:center">
                <p style="margin:0 0 4px;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A9A;font-weight:700">Your Welcome Discount</p>
                <p style="margin:0;font-family:\'Playfair Display\',Georgia,serif;font-size:28px;color:#1a1a1a;letter-spacing:0.04em">' . htmlspecialchars($discountCode) . '</p>
                <p style="margin:8px 0 0;font-size:12px;color:#6b6b6b">Use code at checkout</p>
            </div>';
        } else {
            $vars['discount_block'] = '';
        }

        // --- social_links_block ---
        $social = $settings['landing']['social'] ?? [];
        if (is_array($social) && !empty($social['enabled'])) {
            $links = [];
            $networks = [
                'facebook'  => ['url' => $social['facebook_url'] ?? '', 'label' => 'Facebook'],
                'instagram' => ['url' => $social['instagram_url'] ?? '', 'label' => 'Instagram'],
                'tiktok'    => ['url' => $social['tiktok_url'] ?? '', 'label' => 'TikTok'],
            ];
            foreach ($networks as $net) {
                if (!empty($net['url'])) {
                    $url = htmlspecialchars($net['url']);
                    $links[] = '<td style="padding:0 4px"><a href="' . $url . '" style="display:inline-block;padding:8px 16px;background-color:#1a1a1a;color:#f5f3f0;text-decoration:none;font-size:11px;letter-spacing:0.05em;border-radius:2px;text-transform:uppercase">' . $net['label'] . '</a></td>';
                }
            }
            $vars['social_links_block'] = $links ? implode('', $links) : '';
        } else {
            $vars['social_links_block'] = '';
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

    // Strip any remaining {{...}} placeholders that weren't provided
    $body = preg_replace('/\{\{[^}]+}}/', '', $body);
    $subject = preg_replace('/\{\{[^}]+}}/', '', $subject);
    $preheader = preg_replace('/\{\{[^}]+}}/', '', $preheader);

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
function sendTemplatedEmail(string $templateCode, string $to, array $vars, ?string $fromEmail = null, ?string $subjectOverride = null): array
{
    $template = renderTemplate($templateCode, $vars);
    if ($subjectOverride !== null && $subjectOverride !== '') {
        $template['subject'] = $subjectOverride;
    }

    $settings = getSettings();
    $smtp = $settings['smtp'] ?? [];

    $host = $smtp['host'] ?? '';
    $user = $smtp['user'] ?? '';
    $pass = $smtp['pass'] ?? '';
    $port = $smtp['port'] ?? '587';
    $from = $fromEmail ?: ($smtp['fromEmail'] ?? 'noreply@vuno.com');
    $fromName = $smtp['fromName'] ?? 'Ram;Lop';

    if (!$host || !$user) {
        error_log("[Ram;Lop Email] SMTP not configured. Would send to: $to, Subject: {$template['subject']}");
        return ['success' => true, 'note' => 'Email not sent (SMTP not configured)'];
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int)$port;
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->SMTPSecure = (int)$port === 587
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($from, $fromName);
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
function sendOrderConfirmation(array $order, array $bankAccounts = []): array
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

    // Build transfer details block if payment method is transfer
    $transferBlock = '';
    if (($order['paymentMethod'] ?? '') === 'transfer' && !empty($bankAccounts)) {
        $rows = '';
        foreach ($bankAccounts as $ba) {
            $name = htmlspecialchars($ba['bankName'] ?? '');
            $holder = htmlspecialchars($ba['accountHolder'] ?? '');
            $number = htmlspecialchars($ba['accountNumber'] ?? '');
            $type = htmlspecialchars($ba['accountType'] ?? '');
            $routing = htmlspecialchars($ba['routingNumber'] ?? '');
            $instr = htmlspecialchars($ba['instructions'] ?? '');

            $rows .= '<tr>';
            $rows .= '<td style="padding:12px 16px;border-bottom:1px solid #e0ddd9;font-size:13px;color:#1a1a1a">' . $name . '</td>';
            $rows .= '<td style="padding:12px 16px;border-bottom:1px solid #e0ddd9;font-size:13px;color:#1a1a1a">' . $holder . '</td>';
            $rows .= '<td style="padding:12px 16px;border-bottom:1px solid #e0ddd9;font-size:13px;color:#1a1a1a">' . $number . '</td>';
            $rows .= '<td style="padding:12px 16px;border-bottom:1px solid #e0ddd9;font-size:13px;color:#9A9A9A">' . ($type ?: '—') . '</td>';
            $rows .= '</tr>';
            if ($routing) {
                $rows .= '<tr><td colspan="4" style="padding:0 16px 8px;font-size:11px;color:#9A9A9A">Routing: ' . $routing . '</td></tr>';
            }
            if ($instr) {
                $rows .= '<tr><td colspan="4" style="padding:0 16px 12px;font-size:12px;color:#6b6b6b;font-style:italic;border-bottom:1px solid #e0ddd9">' . $instr . '</td></tr>';
            }
        }

        $transferBlock = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;border:1px solid #e0ddd9;border-radius:2px">
<tr><td style="padding:12px 16px;background-color:#f5f3f0;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A9A">Bank Transfer Details</td></tr>
<tr><td style="padding:0">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr style="background-color:#faf9f8">
<th style="padding:8px 16px;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A9A;text-align:left;border-bottom:1px solid #e0ddd9">Bank</th>
<th style="padding:8px 16px;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A9A;text-align:left;border-bottom:1px solid #e0ddd9">Holder</th>
<th style="padding:8px 16px;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A9A;text-align:left;border-bottom:1px solid #e0ddd9">Account</th>
<th style="padding:8px 16px;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A9A;text-align:left;border-bottom:1px solid #e0ddd9">Type</th>
</tr>' . $rows . '</table>
</td></tr></table>';
    }

    $vars = [
        'customer_name'          => $name,
        'order_id'               => $orderId,
        'order_items_html'       => $itemsHtml,
        'order_subtotal'         => $subtotal,
        'order_shipping'         => $shipping,
        'order_total'            => $total,
        'coupon_discount_block'  => $couponBlock,
        'transfer_details_block' => $transferBlock,
        'currency_symbol'        => $currencySymbol,
        'preheader'              => "Your order #{$orderId} has been confirmed",
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
        'order_total'      => $total,
        'payment_method'   => $paymentMethod,
        'order_status'     => $status,
        'items_count'      => (string)$itemsCount,
        'receipt_block'    => $receiptBlock,
        'admin_order_url'  => $adminUrl,
        'currency_symbol'  => $currencySymbol,
        'preheader'        => "New order #{$orderId}: {$name}",
    ];

    $settings = getSettings();
    $adminEmail = $settings['smtp']['adminEmail'] ?? '';
    if (!$adminEmail) {
        error_log('[Ram;Lop Email] Notificaciones: no hay email configurado en Admin → SMTP → Email de Notificaciones.');
        return ['success' => false, 'error' => 'Admin notification email not configured'];
    }
    return sendTemplatedEmail('new_order_notification', $adminEmail, $vars);
}

/**
 * Legacy: send plain email with custom HTML.
 */
function sendEmail(string $to, string $subject, string $html, ?string $fromEmail = null): array
{
    $settings = getSettings();
    $smtp = $settings['smtp'] ?? [];

    $host = $smtp['host'] ?? '';
    $user = $smtp['user'] ?? '';
    $pass = $smtp['pass'] ?? '';
    $port = $smtp['port'] ?? '587';
    $from = $fromEmail ?: ($smtp['fromEmail'] ?? 'noreply@vuno.com');
    $fromName = $smtp['fromName'] ?? 'Ram;Lop';

    if (!$host || !$user) {
        error_log("[Ram;Lop Email] SMTP not configured. Would send to: $to, Subject: $subject");
        return ['success' => true, 'note' => 'Email not sent (SMTP not configured)'];
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int)$port;
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->SMTPSecure = (int)$port === 587
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($from, $fromName);
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
