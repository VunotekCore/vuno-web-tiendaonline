<?php
/**
 * Newsletter Subscription Endpoint
 * POST /api/email/newsletter.php
 * Body: { "email": "user@example.com" }
 * Returns: { "success": true, "message": "..." }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Please enter a valid email address');
}

try {
    $db = getDb();

    $stmt = $db->prepare('SELECT id, is_active FROM newsletter_subscribers WHERE email = ?');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['is_active']) {
            jsonResponse(['success' => true, 'message' => 'You are already subscribed!']);
        } else {
            $stmt = $db->prepare('UPDATE newsletter_subscribers SET is_active = 1, unsubscribed_at = NULL, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$existing['id']]);
            jsonResponse(['success' => true, 'message' => 'Welcome back! You have been re-subscribed.']);
        }
        return;
    }

    $stmt = $db->prepare('INSERT INTO newsletter_subscribers (email) VALUES (?)');
    $stmt->execute([$email]);
    $subscriberId = $db->lastInsertId();

    // Build welcome email vars
    $settings = getSettings();
    $landing = $settings['landing'] ?? [];
    $social = $landing['social'] ?? [];

    $socialLinks = '';
    $socialUrls = [
        'facebook'  => $social['facebook_url'] ?? '',
        'instagram' => $social['instagram_url'] ?? '',
        'tiktok'    => $social['tiktok_url'] ?? '',
    ];
    foreach ($socialUrls as $name => $url) {
        if ($url) {
            $socialLinks .= '<td style="padding-right:12px"><a href="' . htmlspecialchars($url) . '" style="color:#1a1a1a;text-decoration:none;font-size:13px;font-weight:600">' . ucfirst($name) . '</a></td>';
        }
    }
    if (!$socialLinks) {
        $socialLinks = '<td style="font-size:13px;color:#9A9A9A">Follow us on social media</td>';
    }

    $discountCode = $settings['store']['newsletter_discount_code'] ?? '';
    $discountBlock = '';
    if ($discountCode) {
        $discountBlock = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0;border-radius:2px;margin:0 0 24px;width:100%"><tr><td style="padding:20px;text-align:center;font-family:\'Hanken Grotesk\',Arial,Helvetica,sans-serif">
            <p style="margin:0 0 4px;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A9A">Your exclusive welcome discount</p>
            <p style="margin:0;font-family:\'Playfair Display\',Georgia,serif;font-size:28px;color:#1a1a1a;letter-spacing:0.04em">' . htmlspecialchars($discountCode) . '</p>
            <p style="margin:8px 0 0;font-size:13px;color:#6b6b6b">Use this code at checkout for your first order.</p>
        </td></tr></table>';
    }

    $name = explode('@', $email)[0];

    $appUrl = env('APP_URL', 'http://localhost:4321');
    $unsubscribeUrl = $appUrl . '/api/email/unsubscribe.php?email=' . urlencode($email);

    $vars = [
        'subscriber_name'     => $name,
        'discount_block'      => $discountBlock,
        'social_links_block'  => $socialLinks,
        'unsubscribe_url'     => $unsubscribeUrl,
    ];

    $result = sendTemplatedEmail('newsletter_welcome', $email, $vars);

    $msg = 'You\'re subscribed! Check your inbox for a welcome email.';
    if ($discountCode) {
        $msg .= ' Use code <strong>' . htmlspecialchars($discountCode) . '</strong> for your first order.';
    }

    jsonResponse(['success' => true, 'message' => $msg]);

} catch (\PDOException $e) {
    error_log("[Newsletter] DB error: " . $e->getMessage());
    jsonError('Something went wrong. Please try again later.', 500);
} catch (\Throwable $e) {
    error_log("[Newsletter] Error: " . $e->getMessage());
    jsonError('Something went wrong. Please try again later.', 500);
}
