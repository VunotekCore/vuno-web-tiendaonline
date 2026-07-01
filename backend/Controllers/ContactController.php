<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SettingModel;
use App\Services\EmailService;
use App\Services\RateLimiter;
use App\Traits\ApiResponse;

final class ContactController
{
    use ApiResponse;

    public function __construct(
        private EmailService $emailService,
        private SettingModel $settingModel,
    ) {}

    public function send(): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                $this->jsonError('Method not allowed', 405);
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $db = \App\Config\Database::getConnection();
            if (RateLimiter::isLimited($db, 'contact', (string) $ip, 1, 60)) {
                $this->jsonError('Por favor esperá un minuto antes de enviar otro mensaje.', 429);
            }

            $input = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($input)) {
                $this->jsonError('Datos inválidos');
            }

            $name = trim((string) ($input['name'] ?? ''));
            $email = trim((string) ($input['email'] ?? ''));
            $phone = trim((string) ($input['phone'] ?? ''));
            $subject = trim((string) ($input['subject'] ?? ''));
            $message = trim((string) ($input['message'] ?? ''));

            if (!empty($input['website'])) {
                $this->jsonResponse(['success' => true]);
            }
            if ($name === '' || $email === '' || $subject === '' || $message === '') {
                $this->jsonError('Completá todos los campos requeridos.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->jsonError('El email ingresado no es válido.');
            }
            if (strlen($message) < 10) {
                $this->jsonError('El mensaje debe tener al menos 10 caracteres.');
            }

            $settings = $this->settingModel->getAll();
            $toEmail = !empty($settings['smtp']['adminEmail'])
                ? $settings['smtp']['adminEmail']
                : ($settings['store']['email'] ?? '');

            if ($toEmail === '') {
                $this->jsonError('Email de contacto no configurado.');
            }

            $vars = [
                'name'    => htmlspecialchars($name),
                'email'   => htmlspecialchars($email),
                'phone'   => htmlspecialchars($phone ?: '-'),
                'subject' => htmlspecialchars($subject),
                'message' => nl2br(htmlspecialchars($message)),
            ];

            $result = $this->emailService->sendTemplatedEmail('contact_notification', $toEmail, $vars);

            RateLimiter::record($db, 'contact', (string) $ip);

            if ($result['success']) {
                $this->jsonResponse(['success' => true, 'message' => 'Mensaje enviado correctamente. Te responderemos a la brevedad.']);
            } else {
                $this->jsonError('Error al enviar el mensaje. Intentalo de nuevo más tarde.');
            }
        } catch (\Throwable $e) {
            $this->jsonError('Error sending contact message: ' . $e->getMessage(), 500);
        }
    }
}
