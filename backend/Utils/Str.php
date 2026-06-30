<?php
declare(strict_types=1);

namespace App\Utils;

final class Str
{
    public static function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = (string) preg_replace('/[^\w\s-]/u', '', $text);
        $text = (string) preg_replace('/[\s_]+/', '-', $text);
        return trim($text, '-');
    }

    public static function generateOrderId(): string
    {
        $timestamp = strtoupper(base_convert((string) time(), 10, 36));
        $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        return "RL-{$timestamp}{$random}";
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public static function calculateSubtotal(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += (float) (is_array($item['product'] ?? null) ? ($item['product']['price'] ?? 0) : 0) * (int) ($item['quantity'] ?? 1);
        }
        return $total;
    }

    public static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
