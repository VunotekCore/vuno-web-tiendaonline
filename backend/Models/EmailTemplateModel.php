<?php
declare(strict_types=1);

namespace App\Models;

final class EmailTemplateModel
{
    public function __construct(private \PDO $db) {}

    /** @return array{items: array<int, array<string, mixed>>, total: int, page: int, pages: int} */
    public function getAll(int $page = 1, int $limit = 10, string $search = ''): array
    {
        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(code LIKE ? OR name LIKE ? OR subject LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $limit;

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM email_templates WHERE {$whereClause}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmtParams = $params;
        $stmtParams[] = $limit;
        $stmtParams[] = $offset;
        $stmt = $this->db->prepare(
            "SELECT * FROM email_templates
             WHERE {$whereClause}
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($stmtParams);
        $items = $stmt->fetchAll();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
        ];
    }

    /** @return ?array<string, mixed> */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM email_templates WHERE id = ?');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r !== false && $r !== null ? $r : null;
    }

    /** @return ?array<string, mixed> */
    public function getByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM email_templates WHERE code = ?');
        $stmt->execute([$code]);
        $r = $stmt->fetch();
        return $r !== false && $r !== null ? $r : null;
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO email_templates (code, name, subject, body_html, body_text, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['subject'],
            $data['body_html'],
            $data['body_text'] ?? null,
            !empty($data['is_active']) ? 1 : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];

        foreach (['code', 'name', 'subject', 'body_html', 'body_text'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "{$key} = ?";
                $params[] = $data[$key];
            }
        }

        if (array_key_exists('is_active', $data)) {
            $fields[] = 'is_active = ?';
            $params[] = !empty($data['is_active']) ? 1 : 0;
        }

        if ($fields === []) return;

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;

        $this->db->prepare(
            'UPDATE email_templates SET ' . implode(', ', $fields) . ' WHERE id = ?'
        )->execute($params);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM email_templates WHERE id = ?')->execute([$id]);
    }

    /** @return array<string, mixed>|null */
    public static function loadFromFile(string $code): ?array
    {
        $path = self::getTemplateDir() . '/' . $code . '.html';
        if (!file_exists($path)) return null;

        $raw = file_get_contents($path);

        $subject = '';
        $preheader = '';
        if (preg_match('/^Subject:\s*(.+)$/m', $raw, $m)) $subject = trim($m[1]);
        if (preg_match('/^Preheader:\s*(.+)$/m', $raw, $m)) $preheader = trim($m[1]);

        $body = preg_replace('/^(Subject|Preheader):\s*.+\n+/m', '', $raw);
        $body = trim($body);

        return [
            'code'      => $code,
            'name'      => str_replace('_', ' ', ucfirst($code)),
            'subject'   => $subject,
            'preheader' => $preheader,
            'body_html' => $body,
            'is_active' => true,
        ];
    }

    public static function fileExists(string $code): ?string
    {
        $path = self::getTemplateDir() . '/' . $code . '.html';
        return file_exists($path) ? $path : null;
    }

    /** @return string[] */
    public static function getTemplateFiles(): array
    {
        $dir = self::getTemplateDir();
        $files = glob($dir . '/*.html');
        return $files !== false ? $files : [];
    }

    private static function getTemplateDir(): string
    {
        return dirname(__DIR__, 2) . '/email-templates';
    }
}
