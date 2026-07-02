<?php
declare(strict_types=1);

namespace App\Models;

final class AddressModel
{
    public function __construct(private \PDO $db) {}

    /** @return array<int, array<string, mixed>> */
    public function getByCustomer(int $customerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default_shipping DESC, is_default_billing DESC, created_at ASC'
        );
        $stmt->execute([$customerId]);
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function getById(int $addressId, int $customerId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM customer_addresses WHERE id = ? AND customer_id = ? LIMIT 1'
        );
        $stmt->execute([$addressId, $customerId]);
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $customerId, array $data): int
    {
        $dupStmt = $this->db->prepare(
            'SELECT id FROM customer_addresses WHERE customer_id = ? AND address_line1 = ? AND city = ? LIMIT 1'
        );
        $dupStmt->execute([$customerId, $data['address_line1'] ?? '', $data['city'] ?? '']);
        if ($dupStmt->fetch()) {
            throw new \RuntimeException('This address is already saved');
        }

        $existing = $this->getByCustomer($customerId);
        $isDefaultShipping = !empty($data['is_default_shipping']) || $existing === [];
        $isDefaultBilling = !empty($data['is_default_billing']) || $existing === [];

        if ($isDefaultShipping) {
            $this->db->prepare(
                'UPDATE customer_addresses SET is_default_shipping = FALSE WHERE customer_id = ?'
            )->execute([$customerId]);
        }
        if ($isDefaultBilling) {
            $this->db->prepare(
                'UPDATE customer_addresses SET is_default_billing = FALSE WHERE customer_id = ?'
            )->execute([$customerId]);
        }

        $this->db->prepare(
            'INSERT INTO customer_addresses (customer_id, label, address_line1, address_line2, city, state, zip, country, phone, is_default_shipping, is_default_billing)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $customerId,
            $data['label'] ?? '',
            $data['address_line1'] ?? '',
            $data['address_line2'] ?? '',
            $data['city'] ?? '',
            $data['state'] ?? '',
            $data['zip'] ?? '',
            $data['country'] ?? 'GT',
            $data['phone'] ?? '',
            $isDefaultShipping ? 1 : 0,
            $isDefaultBilling ? 1 : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $addressId, int $customerId, array $data): void
    {
        $fields = ['label', 'address_line1', 'address_line2', 'city', 'state', 'zip', 'country', 'phone'];
        $sets = [];
        $params = [];
        foreach ($fields as $f) {
            if (\array_key_exists($f, $data)) {
                $sets[] = "{$f} = ?";
                $params[] = $data[$f];
            }
        }

        if ($sets !== []) {
            $params[] = $addressId;
            $params[] = $customerId;
            $this->db->prepare(
                'UPDATE customer_addresses SET ' . \implode(', ', $sets) . ' WHERE id = ? AND customer_id = ?'
            )->execute($params);
        }

        if (!empty($data['is_default_shipping'])) {
            $this->db->prepare(
                'UPDATE customer_addresses SET is_default_shipping = FALSE WHERE customer_id = ?'
            )->execute([$customerId]);
            $this->db->prepare(
                'UPDATE customer_addresses SET is_default_shipping = TRUE WHERE id = ? AND customer_id = ?'
            )->execute([$addressId, $customerId]);
        }
        if (!empty($data['is_default_billing'])) {
            $this->db->prepare(
                'UPDATE customer_addresses SET is_default_billing = FALSE WHERE customer_id = ?'
            )->execute([$customerId]);
            $this->db->prepare(
                'UPDATE customer_addresses SET is_default_billing = TRUE WHERE id = ? AND customer_id = ?'
            )->execute([$addressId, $customerId]);
        }
    }

    public function delete(int $addressId, int $customerId): void
    {
        $this->db->prepare(
            'DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?'
        )->execute([$addressId, $customerId]);
    }
}
