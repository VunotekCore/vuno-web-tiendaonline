<?php
declare(strict_types=1);

namespace App\Models;

final class CustomerModel
{
    private ?CurrencyModel $currencyModel = null;
    private ?WishlistModel $wishlistModel = null;

    public function __construct(private \PDO $db) {}

    public function setCurrencyModel(?CurrencyModel $m): void
    {
        $this->currencyModel = $m;
    }

    public function setWishlistModel(?WishlistModel $m): void
    {
        $this->wishlistModel = $m;
    }

    private function getCurrencyModel(): CurrencyModel
    {
        if ($this->currencyModel === null) {
            $this->currencyModel = new CurrencyModel($this->db);
        }
        return $this->currencyModel;
    }

    private function getWishlistModel(): WishlistModel
    {
        if ($this->wishlistModel === null) {
            $this->wishlistModel = new WishlistModel($this->db);
        }
        return $this->wishlistModel;
    }

    // =========================================================================
    //  Customer CRUD
    // =========================================================================

    /** @return array{items: array<int, array<string, mixed>>, total: int} */
    public function getAll(?int $limit, ?int $offset, ?string $search): array
    {
        $countSql = 'SELECT COUNT(*) FROM customers';
        $sql = 'SELECT * FROM customers';
        $conditions = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $conditions[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where = $conditions !== [] ? ' WHERE ' . \implode(' AND ', $conditions) : '';

        $countStmt = $this->db->prepare($countSql . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql .= $where . ' ORDER BY created_at DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
        }
        if ($offset !== null) {
            $sql .= ' OFFSET ?';
            $params[] = $offset;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->buildCustomer($row);
        }
        return ['items' => $items, 'total' => $total];
    }

    /** @return array<string, mixed>|null */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $customer = $this->buildCustomer($row);

        // Addresses
        $addrStmt = $this->db->prepare(
            'SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default_shipping DESC, created_at DESC'
        );
        $addrStmt->execute([$id]);
        /** @var array<int, array<string, mixed>> $addresses */
        $addresses = $addrStmt->fetchAll();
        $customer['addresses'] = $addresses;

        // Order history
        $orderStmt = $this->db->prepare(
            'SELECT o.id, o.order_number, o.created_at, o.total, o.currency,
                    o.exchange_rate, os.code AS status_code,
                    pm.code AS payment_method_code
             FROM orders o
             JOIN order_statuses os ON os.id = o.status_id
             JOIN payment_methods pm ON pm.id = o.payment_method_id
             WHERE o.customer_id = ?
             ORDER BY o.created_at DESC
             LIMIT 20'
        );
        $orderStmt->execute([$id]);
        /** @var array<int, array<string, mixed>> $orders */
        $orders = $orderStmt->fetchAll();
        foreach ($orders as &$order) {
            /** @var mixed $exchangeRate */
            $exchangeRate = $order['exchange_rate'] ?? null;
            $rate = is_numeric($exchangeRate) ? (float) $exchangeRate : 1.0;
            /** @var mixed $currency */
            $currencyRaw = $order['currency'] ?? null;
            /** @var array<string, mixed> $currency */
            $currency = $this->getCurrencyModel()->getByCode(is_string($currencyRaw) ? $currencyRaw : 'USD');
            $currency = $currency ?? ['code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1.0, 'decimal_places' => 2];
            $currencySymbol = $currency['symbol'] ?? null;
            /** @var mixed $totalRaw */
            $totalRaw = $order['total'] ?? null;
            $order['display_total'] = $this->getCurrencyModel()->convertFromUsd(is_numeric($totalRaw) ? (float) $totalRaw : 0.0, $rate);
            $order['display_symbol'] = is_string($currencySymbol) ? $currencySymbol : '$';
        }
        unset($order);
        $customer['orders'] = $orders;

        // Wishlist
        /** @var array<int, array<string, mixed>> $wishlist */
        $wishlist = $this->getWishlistModel()->getByCustomer($id);
        $customer['wishlist'] = $wishlist;

        return $customer;
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->db->prepare('SELECT id FROM customers WHERE email = ? AND id != ?');
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
        }
        return $stmt->fetchColumn() !== false;
    }

    public function create(string $name, string $email, string $passwordHash, bool $isVerified = true): int
    {
        $this->db->prepare(
            'INSERT INTO customers (name, email, password_hash, is_verified) VALUES (?, ?, ?, ?)'
        )->execute([$name, $email, $passwordHash, $isVerified ? 1 : 0]);
        return (int) $this->db->lastInsertId();
    }

    public function linkGuestOrders(int $customerId, string $email): int
    {
        $stmt = $this->db->prepare(
            'UPDATE orders SET customer_id = ? WHERE customer_email = ? AND customer_id IS NULL'
        );
        $stmt->execute([$customerId, $email]);
        return $stmt->rowCount();
    }

    public function updateLastOrderAt(int $customerId): void
    {
        $this->db->prepare(
            'UPDATE customers c
             SET c.last_order_at = (
                 SELECT MAX(o.created_at) FROM orders o WHERE o.customer_id = ?
             )
             WHERE c.id = ?'
        )->execute([$customerId, $customerId]);
    }

    /** @param array<string, mixed> $fields */
    public function update(int $id, array $fields): void
    {
        $sets = [];
        $params = [];
        foreach ($fields as $col => $val) {
            $sets[] = "{$col} = ?";
            $params[] = $val;
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;
        $this->db->prepare(
            'UPDATE customers SET ' . \implode(', ', $sets) . ' WHERE id = ?'
        )->execute($params);
    }

    public function delete(int $id): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM customer_addresses WHERE customer_id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM customer_sessions WHERE customer_id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM wishlist_items WHERE customer_id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM cart_items WHERE customer_id = ?')->execute([$id]);
            $this->db->prepare('UPDATE orders SET customer_id = NULL WHERE customer_id = ?')->execute([$id]);
            $this->db->prepare('UPDATE product_reviews SET customer_id = NULL WHERE customer_id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function buildCustomer(array $row): array
    {
        /** @var mixed $lastOrderRaw */
        $lastOrderRaw = $row['last_order_at'] ?? null;
        $createdRaw = isset($row['created_at']) && \is_string($row['created_at']) ? $row['created_at'] : '';
        $updatedRaw = isset($row['updated_at']) && \is_string($row['updated_at']) ? $row['updated_at'] : '';

        /** @var int|false $lastOrderTs */
        $lastOrderTs = \is_string($lastOrderRaw) && $lastOrderRaw !== ''
            ? \strtotime($lastOrderRaw)
            : false;
        /** @var int|false $createdTs */
        $createdTs = $createdRaw !== '' ? \strtotime($createdRaw) : false;
        /** @var int|false $updatedTs */
        $updatedTs = $updatedRaw !== '' ? \strtotime($updatedRaw) : false;

        return [
            'id'            => is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0,
            'email'         => isset($row['email']) && \is_string($row['email']) ? $row['email'] : '',
            'name'          => isset($row['name']) && \is_string($row['name']) ? $row['name'] : '',
            'phone'         => isset($row['phone']) && \is_string($row['phone']) ? $row['phone'] : '',
            'is_verified'   => !empty($row['is_verified']),
            'notes'         => isset($row['notes']) && \is_string($row['notes']) ? $row['notes'] : '',
            'last_order_at' => $lastOrderTs !== false
                ? \date('c', $lastOrderTs)
                : null,
            'created_at'    => $createdTs !== false
                ? \date('c', $createdTs)
                : \date('c'),
            'updated_at'    => $updatedTs !== false
                ? \date('c', $updatedTs)
                : \date('c'),
        ];
    }

    // =========================================================================
    //  Customer Sessions
    // =========================================================================

    public function createSession(int $customerId, string $token, string $ip, string $userAgent, string $expiresAt): void
    {
        $this->db->prepare(
            'INSERT INTO customer_sessions (customer_id, token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([$customerId, $token, $ip, $userAgent, $expiresAt]);
    }

    /** @return array<string, mixed>|null */
    public function getSessionByToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT cs.*, c.id AS customer_id, c.name, c.email
             FROM customer_sessions cs
             JOIN customers c ON c.id = cs.customer_id
             WHERE cs.token = ? AND cs.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$token]);
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function updateSessionActivity(int $sessionId): void
    {
        $this->db->prepare(
            'UPDATE customer_sessions SET last_activity = NOW() WHERE id = ?'
        )->execute([$sessionId]);
    }

    public function deleteSession(string $token): void
    {
        $this->db->prepare('DELETE FROM customer_sessions WHERE token = ?')->execute([$token]);
    }

    public function deleteAllSessions(int $customerId): void
    {
        $this->db->prepare('DELETE FROM customer_sessions WHERE customer_id = ?')->execute([$customerId]);
    }

    public function getCustomerIdFromToken(string $token): ?int
    {
        if ($token === '') {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT c.id FROM customer_sessions cs
             JOIN customers c ON c.id = cs.customer_id
             WHERE cs.token = ? AND cs.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $id = $stmt->fetchColumn();
        return $id !== false && $id !== null ? (int) $id : null;
    }

    // =========================================================================
    //  Password Resets
    // =========================================================================

    public function createPasswordReset(string $email, string $token, string $expiresAt): void
    {
        $this->db->prepare(
            'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)'
        )->execute([$email, $token, $expiresAt]);
    }

    /** @return array<string, mixed>|null */
    public function getPasswordResetByToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used_at IS NULL LIMIT 1'
        );
        $stmt->execute([$token]);
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function markPasswordResetUsed(int $resetId): void
    {
        $this->db->prepare(
            'UPDATE password_resets SET used_at = NOW() WHERE id = ?'
        )->execute([$resetId]);
    }

    public function deleteOldPasswordResets(string $email): void
    {
        $this->db->prepare(
            'DELETE FROM password_resets WHERE email = ? AND used_at IS NULL'
        )->execute([$email]);
    }

    // =========================================================================
    //  Customer Orders
    // =========================================================================

    /** @return array<int, array<string, mixed>> */
    public function getCustomerOrders(int $customerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*, os.code AS status_code, pm.code AS payment_method_code
             FROM orders o
             JOIN order_statuses os ON os.id = o.status_id
             JOIN payment_methods pm ON pm.id = o.payment_method_id
             WHERE o.customer_id = ?
             ORDER BY o.created_at DESC'
        );
        $stmt->execute([$customerId]);
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        $orders = [];
        foreach ($rows as $row) {
            $order = (new OrderModel($this->db))->buildOrder($row);
            if ($order !== []) {
                $orders[] = $order;
            }
        }
        return $orders;
    }

    /** @return array<string, mixed>|null */
    public function getCustomerOrder(int $customerId, string $orderNumber): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*, os.code AS status_code, pm.code AS payment_method_code
             FROM orders o
             JOIN order_statuses os ON os.id = o.status_id
             JOIN payment_methods pm ON pm.id = o.payment_method_id
             WHERE o.order_number = ? AND o.customer_id = ?
             LIMIT 1'
        );
        $stmt->execute([$orderNumber, $customerId]);
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return (new OrderModel($this->db))->buildOrder($row);
    }
}
