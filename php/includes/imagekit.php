<?php

function getImageKitAuth(?string $privateKey = null): string
{
    $privateKey ??= env('IMAGEKIT_PRIVATE_KEY');
    if (!$privateKey) {
        throw new \RuntimeException('ImageKit private key not configured');
    }
    return 'Basic ' . base64_encode("{$privateKey}:");
}

function imageKitRequest(string $method, string $endpoint, array $options = [], ?string $privateKey = null): array
{
    $privateKey ??= env('IMAGEKIT_PRIVATE_KEY');
    $urlEndpoint = env('IMAGEKIT_URL_ENDPOINT', 'https://ik.imagekit.io');
    $url = rtrim($urlEndpoint, '/') . $endpoint;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . getImageKitAuth($privateKey),
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

    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        throw new \RuntimeException('ImageKit error: ' . ($data['message'] ?? $response ?? 'Unknown'));
    }

    return $data ?: [];
}

function uploadImage(string $filePath, string $fileName, string $folder = '', ?string $privateKey = null): array
{
    $privateKey ??= env('IMAGEKIT_PRIVATE_KEY');
    if (!$privateKey) throw new \RuntimeException('ImageKit private key not configured');

    $mime = mime_content_type($filePath) ?: 'application/octet-stream';
    $data = [
        'file' => curl_file_create($filePath, $mime, $fileName),
        'fileName' => $fileName,
        'useUniqueFileName' => 'true',
    ];
    if ($folder) $data['folder'] = $folder;

    $ch = curl_init('https://upload.imagekit.io/api/v1/files/upload');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: ' . getImageKitAuth($privateKey)],
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_TIMEOUT => 60,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        throw new \RuntimeException('ImageKit cURL error: ' . $error);
    }

    $result = json_decode($response, true);
    if ($httpCode >= 400) {
        throw new \RuntimeException('ImageKit upload error (HTTP ' . $httpCode . '): ' . ($result['message'] ?? $response ?? 'Unknown'));
    }

    return $result ?: [];
}

function getImageKitFile(string $fileId): array
{
    return imageKitRequest('GET', "/api/v1/files/{$fileId}");
}

function getImageKitFiles(array $options = []): array
{
    $query = http_build_query($options);
    return imageKitRequest('GET', "/api/v1/files?{$query}");
}

function deleteImageKitFile(string $fileId): array
{
    return imageKitRequest('DELETE', "/api/v1/files/{$fileId}");
}
