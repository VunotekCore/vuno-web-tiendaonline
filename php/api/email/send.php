<?php
/**
 * POST /api/email/send.php
 * Sends an email notification
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/email.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$to = $input['to'] ?? '';
$subject = $input['subject'] ?? '';
$html = $input['html'] ?? '';

if (empty($to) || empty($subject) || empty($html)) {
    jsonError('Missing required fields: to, subject, html');
}

$result = sendEmail($to, $subject, $html);
jsonResponse($result);
