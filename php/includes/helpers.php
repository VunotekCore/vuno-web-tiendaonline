<?php
declare(strict_types=1);

/**
 * Utility helpers - equivalent to src/lib/utils.ts and src/lib/cart.ts
 */

function generateOrderId(): string
{
    $timestamp = strtoupper(base_convert((string)time(), 10, 36));
    $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    return "RL-{$timestamp}{$random}";
}

function slugify(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\w\s-]/u', '', $text);
    $text = preg_replace('/[\s_]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

function calculateSubtotal(array $items): float
{
    $total = 0;
    foreach ($items as $item) {
        $total += ($item['product']['price'] ?? 0) * ($item['quantity'] ?? 1);
    }
    return $total;
}

function escapeHtml(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
