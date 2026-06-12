<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/email.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Rate limiting by IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = 'contact_rate_' . md5($ip);
$rateFile = sys_get_temp_dir() . '/' . $rateKey;
$cooldown = 60; // 1 minute between submissions from same IP

if (file_exists($rateFile) && (time() - filemtime($rateFile)) < $cooldown) {
    jsonError('Por favor esperá un minuto antes de enviar otro mensaje.', 429);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    jsonError('Datos inválidos');
}

$name    = trim($input['name'] ?? '');
$email   = trim($input['email'] ?? '');
$phone   = trim($input['phone'] ?? '');
$subject = trim($input['subject'] ?? '');
$message = trim($input['message'] ?? '');

// Honeypot: if the hidden field is filled, silently reject
if (!empty($input['website'])) {
    jsonResponse(['success' => true]);
}

if ($name === '' || $email === '' || $subject === '' || $message === '') {
    jsonError('Completá todos los campos requeridos.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('El email ingresado no es válido.');
}

if (strlen($message) < 10) {
    jsonError('El mensaje debe tener al menos 10 caracteres.');
}

// Get destination email from settings (adminEmail > store email)
$settings = getSettings();
$toEmail = !empty($settings['smtp']['adminEmail'])
    ? $settings['smtp']['adminEmail']
    : ($settings['store']['email'] ?? '');

if (!$toEmail) {
    jsonError('Email de contacto no configurado.');
}

$vars = [
    'name'    => htmlspecialchars($name),
    'email'   => htmlspecialchars($email),
    'phone'   => htmlspecialchars($phone ?: '-'),
    'subject' => htmlspecialchars($subject),
    'message' => nl2br(htmlspecialchars($message)),
];

$result = sendTemplatedEmail('contact_notification', $toEmail, $vars);

// Touch rate limit file
touch($rateFile);

if ($result['success']) {
    jsonResponse(['success' => true, 'message' => 'Mensaje enviado correctamente. Te responderemos a la brevedad.']);
} else {
    jsonError('Error al enviar el mensaje. Intentalo de nuevo más tarde.');
}
