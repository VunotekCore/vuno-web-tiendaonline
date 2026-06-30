<?php
declare(strict_types=1);

namespace App\Services;

final class ImageKitService
{
    public function __construct(
        private ?string $privateKey = null,
    ) {}

    public function upload(string $filePath, string $fileName, string $folder = ''): array
    {
        $key = $this->resolveKey();
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';

        $data = [
            'file' => curl_file_create($filePath, $mime, $fileName),
            'fileName' => $fileName,
            'useUniqueFileName' => 'true',
        ];
        if ($folder !== '') {
            $data['folder'] = $folder;
        }

        $ch = curl_init('https://upload.imagekit.io/api/v1/files/upload');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . $this->auth($key)],
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            throw new \RuntimeException('ImageKit upload cURL error: ' . $error);
        }
        if ($response === false || $response === '') {
            throw new \RuntimeException('ImageKit upload error: empty response');
        }

        $result = json_decode($response, true);
        if ($httpCode >= 400) {
            throw new \RuntimeException(
                'ImageKit upload error (HTTP ' . $httpCode . '): ' . ($result['message'] ?? $response)
            );
        }

        return is_array($result) ? $result : [];
    }

    public function delete(string $fileId): array
    {
        $key = $this->resolveKey();
        return $this->request('DELETE', '/v1/files/' . $fileId, [], $key);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function resolveKey(): string
    {
        $key = $this->privateKey ?? (string) \env('IMAGEKIT_PRIVATE_KEY');
        if ($key === '') {
            throw new \RuntimeException('ImageKit private key not configured');
        }
        return $key;
    }

    private function auth(string $privateKey): string
    {
        return 'Basic ' . base64_encode($privateKey . ':');
    }

    /** @param array<string, mixed> $options */
    private function request(string $method, string $endpoint, array $options = [], ?string $privateKey = null): array
    {
        $key = $privateKey ?? $this->resolveKey();
        $url = 'https://api.imagekit.io' . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->auth($key),
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($options['json'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['json']));
            }
        }
        if ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('ImageKit cURL error: ' . curl_error($ch));
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400) {
            throw new \RuntimeException('ImageKit error: ' . ($data['message'] ?? $response));
        }

        return is_array($data) ? $data : [];
    }
}
