<?php
declare(strict_types=1);

namespace App\Models;

final class SizeGuideModel
{
    public function __construct(private \PDO $db) {}

    /** @return array<int, array{us: string, eu: string, uk: string, cm: string}> */
    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT us_size, eu_size, uk_size, cm_size FROM size_guide_rows ORDER BY sort_order ASC');
        if ($stmt === false) {
            return [];
        }
        $rows = $stmt->fetchAll();
        return array_map(fn(array $r): array => [
            'us' => $r['us_size'],
            'eu' => $r['eu_size'],
            'uk' => $r['uk_size'],
            'cm' => $r['cm_size'],
        ], $rows);
    }

    /** @param array<int, array{us: string, eu: string, uk: string, cm: string}> $rows */
    public function replaceAll(array $rows): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->exec('DELETE FROM size_guide_rows');
            $stmt = $this->db->prepare(
                'INSERT INTO size_guide_rows (us_size, eu_size, uk_size, cm_size, sort_order) VALUES (?, ?, ?, ?, ?)'
            );
            $order = 1;
            foreach ($rows as $r) {
                $stmt->execute([
                    $r['us'] ?? '',
                    $r['eu'] ?? '',
                    $r['uk'] ?? '',
                    $r['cm'] ?? '',
                    $order++,
                ]);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
