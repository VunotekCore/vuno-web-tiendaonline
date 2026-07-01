<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SettingModel;
use App\Services\ImageKitService;
use App\Services\RateLimiter;
use App\Traits\ApiResponse;

final class ImageKitController
{
    use ApiResponse;

    public function __construct(
        private SettingModel $settingModel,
    ) {}

    public function upload(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $folder = isset($_POST['folder']) && is_string($_POST['folder'])
            ? $_POST['folder']
            : 'products';

        try {
            if ($folder === 'receipts') {
                $this->validateReceiptUpload();
            } else {
                if (!\isAdminLoggedIn()) {
                    $this->jsonError('Unauthorized', 401);
                }
                \requireRole('superadmin', 'editor');
            }

            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $this->jsonError('No file uploaded or upload error');
            }

            $privateKey = $this->resolveImageKitKey();
            $service = new ImageKitService($privateKey);
            $result = $service->upload(
                $_FILES['file']['tmp_name'],
                $_FILES['file']['name'],
                $folder
            );
            $this->jsonResponse($result);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    public function deleteImage(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }
        if (!\isAdminLoggedIn()) {
            $this->jsonError('Unauthorized', 401);
        }
        \requireRole('superadmin', 'editor');

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !is_array($input)) {
            $this->jsonError('Invalid JSON');
        }

        $imageId = isset($input['imageId']) && is_string($input['imageId'])
            ? $input['imageId']
            : '';
        if ($imageId === '') {
            $this->jsonError('imageId required');
        }

        $db = \App\Config\Database::getConnection();
        $stmt = $db->prepare('SELECT id, product_id, url, file_id FROM product_images WHERE id = ?');
        $stmt->execute([$imageId]);
        $image = $stmt->fetch();

        if (!$image || !is_array($image)) {
            $this->jsonError('Image not found', 404);
        }

        $fileId = isset($image['file_id']) && is_string($image['file_id']) ? $image['file_id'] : '';
        if ($fileId !== '') {
            try {
                $privateKey = $this->resolveImageKitKey();
                $service = new ImageKitService($privateKey);
                $service->delete($fileId);
            } catch (\RuntimeException $e) {
                $this->jsonError('ImageKit delete failed: ' . $e->getMessage(), 502);
            }
        }

        $db->prepare('DELETE FROM product_images WHERE id = ?')->execute([$imageId]);
        \logAdminAction('delete', 'product_image', $imageId, 'Deleted product image from product_images and ImageKit');

        $this->jsonResponse(['success' => true]);
    }

    private function validateReceiptUpload(): void
    {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $db = \App\Config\Database::getConnection();
            if (RateLimiter::checkAndRecord($db, 'receipt_upload', (string) $ip, 5, 3600)) {
                $this->jsonError('Demasiados intentos. Intenta de nuevo más tarde.', 429);
            }
        } catch (\Throwable $e) {
            error_log('Rate limit check failed for receipt upload (allowing): ' . $e->getMessage());
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $tmpName = $_FILES['file']['tmp_name'] ?? '';
        $mime = is_string($tmpName) && $tmpName !== '' ? mime_content_type($tmpName) : '';
        if (!in_array($mime, $allowedMimes, true)) {
            $this->jsonError('Tipo de archivo no permitido. Solo JPG, PNG, WebP o PDF.', 400);
        }
        $maxSize = 10 * 1024 * 1024;
        if (($_FILES['file']['size'] ?? 0) > $maxSize) {
            $this->jsonError('El archivo excede el tamaño máximo de 10MB.', 400);
        }
    }

    private function resolveImageKitKey(): string
    {
        $settings = $this->settingModel->getAll();
        $ikSettings = isset($settings['imagekit']) && is_array($settings['imagekit'])
            ? $settings['imagekit']
            : [];
        $privateKey = $ikSettings['privateKey'] ?? $ikSettings['private_key'] ?? null;
        if (!$privateKey || !is_string($privateKey)) {
            throw new \RuntimeException('ImageKit not configured');
        }
        return $privateKey;
    }

    private function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }
}
