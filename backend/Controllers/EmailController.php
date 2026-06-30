<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SettingModel;
use App\Models\SubscriberModel;
use App\Services\EmailService;
use App\Traits\ApiResponse;

final class EmailController
{
    use ApiResponse;

    private ?SettingModel $settingModel = null;

    public function __construct(
        private SubscriberModel $subscriberModel,
        private EmailService $emailService,
        ?SettingModel $settingModel = null,
    ) {
        $this->settingModel = $settingModel;
    }

    public function setSettingModel(?SettingModel $m): void
    {
        $this->settingModel = $m;
    }

    private function getSettingModel(): SettingModel
    {
        if ($this->settingModel === null) {
            $this->settingModel = new SettingModel(\App\Config\Database::getConnection());
        }
        return $this->settingModel;
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        $raw = json_decode((string) file_get_contents('php://input'), true);
        return is_array($raw) ? $raw : [];
    }

    public function newsletterSubscribe(): void
    {
        /** @var mixed $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if (!is_string($method) || $method !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        /** @var mixed $rawEmail */
        $rawEmail = $body['email'] ?? '';
        $email = is_string($rawEmail) ? trim($rawEmail) : '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->jsonError('Please enter a valid email address');
        }

        try {
            $existing = $this->subscriberModel->getByEmail($email);

            if ($existing !== null) {
                if (!empty($existing['is_active'])) {
                    $this->jsonResponse(['success' => true, 'message' => 'You are already subscribed!']);
                    return;
                }
                $this->subscriberModel->setActive((int) $existing['id'], true);
                $this->jsonResponse(['success' => true, 'message' => 'Welcome back! You have been re-subscribed.']);
                return;
            }

            $this->subscriberModel->insert($email);

            $settings = $this->getSettingModel()->getAll();
            $appUrl = \env('APP_URL', 'http://localhost:4321');
            $discountCode = $settings['store']['newsletter_discount_code'] ?? '';
            $unsubscribeUrl = $appUrl . '/api/email/unsubscribe.php?email=' . urlencode($email);
            $name = explode('@', $email)[0];

            $socialLinks = $this->buildSocialLinks($settings);
            $discountBlock = '';
            if ($discountCode) {
                $discountBlock = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0;border-radius:2px;margin:0 0 24px;width:100%"><tr><td style="padding:20px;text-align:center;font-family:\'Hanken Grotesk\',Arial,Helvetica,sans-serif">
                    <p style="margin:0 0 4px;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A9A">Your exclusive welcome discount</p>
                    <p style="margin:0;font-family:\'Playfair Display\',Georgia,serif;font-size:28px;color:#1a1a1a;letter-spacing:0.04em">' . htmlspecialchars($discountCode) . '</p>
                    <p style="margin:8px 0 0;font-size:13px;color:#6b6b6b">Use this code at checkout for your first order.</p>
                </td></tr></table>';
            }

            $this->emailService->sendTemplatedEmail('newsletter_welcome', $email, [
                'subscriber_name'    => $name,
                'discount_block'     => $discountBlock,
                'social_links_block' => $socialLinks,
                'unsubscribe_url'    => $unsubscribeUrl,
            ]);

            $msg = 'You\'re subscribed! Check your inbox for a welcome email.';
            if ($discountCode) {
                $msg .= ' Use code <strong>' . htmlspecialchars($discountCode) . '</strong> for your first order.';
            }

            $this->jsonResponse(['success' => true, 'message' => $msg]);
        } catch (\PDOException $e) {
            \error_log("[Newsletter] DB error: " . $e->getMessage());
            $this->jsonError('Something went wrong. Please try again later.', 500);
        } catch (\Throwable $e) {
            \error_log("[Newsletter] Error: " . $e->getMessage());
            $this->jsonError('Something went wrong. Please try again later.', 500);
        }
    }

    public function unsubscribePage(): never
    {
        /** @var mixed $rawEmail */
        $rawEmail = $_GET['email'] ?? '';
        $email = is_string($rawEmail) ? trim($rawEmail) : '';
        $success = false;
        $message = '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'The link you used is invalid. Please check the URL and try again.';
        } else {
            try {
                $subscriber = $this->subscriberModel->getByEmail($email);

                if ($subscriber !== null) {
                    if (empty($subscriber['is_active'])) {
                        $message = 'You are already unsubscribed. No further action is needed.';
                        $success = true;
                    } else {
                        $this->subscriberModel->setActive((int) $subscriber['id'], false);
                        $message = 'You have been successfully unsubscribed. You will no longer receive our emails.';
                        $success = true;
                        \error_log("[Newsletter] Unsubscribed: {$email}");
                    }
                } else {
                    $message = 'This email address is not in our subscription list.';
                }
            } catch (\PDOException $e) {
                \error_log("[Newsletter] Unsubscribe DB error: " . $e->getMessage());
                $message = 'Something went wrong. Please try again later.';
            }
        }

        $appUrl = \env('APP_URL', 'http://localhost:4321');
        $storeName = 'Vunotek';

        try {
            $settings = $this->getSettingModel()->getAll();
            $storeName = $settings['store']['name'] ?? 'Vunotek';
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
  .icon { font-size: 48px; margin-bottom: 16px; display: block; }
  h1 { font-family: 'Playfair Display', Georgia, serif; font-size: 22px; font-weight: 400; margin-bottom: 12px; color: #1a1a1a; }
  p { font-size: 15px; line-height: 1.6; color: #6b6b6b; margin-bottom: 24px; }
  .btn { display: inline-block; background: #1a1a1a; color: #f5f3f0; text-decoration: none; padding: 12px 28px; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; border-radius: 2px; transition: opacity 0.2s; }
  .btn:hover { opacity: 0.85; }
  .footer { margin-top: 24px; font-size: 12px; color: #9A9A9A; }
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
<?php
        exit;
    }

    /** @param array<string, mixed> $settings */
    private function buildSocialLinks(array $settings): string
    {
        $landing = $settings['landing'] ?? [];
        $social = $landing['social'] ?? [];

        $socialLinks = '';

        $socialPlatforms = $social['platforms'] ?? [];
        if ($socialPlatforms !== [] && is_array($socialPlatforms)) {
            foreach ($socialPlatforms as $name => $cfg) {
                $url = $cfg['url'] ?? '';
                $enabled = $cfg['enabled'] ?? false;
                if ($enabled && $url) {
                    $label = ucfirst((string) $name);
                    $socialLinks .= '<td style="padding-right:12px"><a href="' . htmlspecialchars($url) . '" style="color:#1a1a1a;text-decoration:none;font-size:13px;font-weight:600">' . htmlspecialchars($label) . '</a></td>';
                }
            }
        } else {
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
        }

        if ($socialLinks === '') {
            $socialLinks = '<td style="font-size:13px;color:#9A9A9A">Follow us on social media</td>';
        }

        return $socialLinks;
    }
}
