<?php
/**
 * Newsletter Unsubscribe Endpoint
 * GET /api/email/unsubscribe.php?email=user@example.com
 * Renders a friendly HTML confirmation page.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

$email = trim($_GET['email'] ?? '');
$success = false;
$message = '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = 'The link you used is invalid. Please check the URL and try again.';
} else {
    try {
        $db = getDb();
        $stmt = $db->prepare('SELECT id, is_active FROM newsletter_subscribers WHERE email = ?');
        $stmt->execute([$email]);
        $subscriber = $stmt->fetch();

        if ($subscriber) {
            if (!$subscriber['is_active']) {
                $message = 'You are already unsubscribed. No further action is needed.';
                $success = true;
            } else {
                $stmt = $db->prepare('UPDATE newsletter_subscribers SET is_active = 0, unsubscribed_at = NOW(), updated_at = NOW() WHERE id = ?');
                $stmt->execute([$subscriber['id']]);
                $message = 'You have been successfully unsubscribed. You will no longer receive our emails.';
                $success = true;
                error_log("[Newsletter] Unsubscribed: {$email}");
            }
        } else {
            $message = 'This email address is not in our subscription list.';
        }
    } catch (\PDOException $e) {
        error_log("[Newsletter] Unsubscribe DB error: " . $e->getMessage());
        $message = 'Something went wrong. Please try again later.';
    }
}

$appUrl = env('APP_URL', 'http://localhost:4321');
$storeName = 'Ram;Lop';

try {
    $settings = getSettings();
    $storeName = $settings['store']['name'] ?? 'Ram;Lop';
} catch (\Exception $e) {
    // Use defaults
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Unsubscribed — <?= htmlspecialchars($storeName) ?></title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Hanken Grotesk', Arial, Helvetica, sans-serif;
    background-color: #f5f3f0;
    color: #1a1a1a;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 24px;
  }
  .card {
    background: #ffffff;
    max-width: 480px;
    width: 100%;
    padding: 40px 32px;
    border-radius: 2px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
    text-align: center;
  }
  .icon {
    font-size: 48px;
    margin-bottom: 16px;
    display: block;
  }
  h1 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 22px;
    font-weight: 400;
    margin-bottom: 12px;
    color: #1a1a1a;
  }
  p {
    font-size: 15px;
    line-height: 1.6;
    color: #6b6b6b;
    margin-bottom: 24px;
  }
  .btn {
    display: inline-block;
    background: #1a1a1a;
    color: #f5f3f0;
    text-decoration: none;
    padding: 12px 28px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    border-radius: 2px;
    transition: opacity 0.2s;
  }
  .btn:hover { opacity: 0.85; }
  .footer {
    margin-top: 24px;
    font-size: 12px;
    color: #9A9A9A;
  }
  .footer a { color: #9A9A9A; }
</style>
</head>
<body>
  <div class="card">
    <span class="icon"><?= $success ? '&#10003;' : '&#9888;' ?></span>
    <h1><?= $success ? 'You&#8217;re Unsubscribed' : 'Something Went Wrong' ?></h1>
    <p><?= htmlspecialchars($message) ?></p>
    <a href="<?= htmlspecialchars($appUrl) ?>" class="btn">Back to <?= htmlspecialchars($storeName) ?></a>
    <p class="footer">
      If you changed your mind, you can <a href="<?= htmlspecialchars($appUrl) ?>">subscribe again</a> anytime.
    </p>
  </div>
</body>
</html>
