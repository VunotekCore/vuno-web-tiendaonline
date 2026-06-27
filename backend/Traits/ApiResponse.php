<?php
declare(strict_types=1);

namespace App\Traits;

trait ApiResponse
{
    /** @return never */
    protected function jsonResponse(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($data, \JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @return never */
    protected function jsonError(string $message, int $status = 400): never
    {
        $this->jsonResponse(['error' => $message], $status);
    }
}
