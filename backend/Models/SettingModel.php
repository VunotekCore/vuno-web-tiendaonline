<?php
declare(strict_types=1);

namespace App\Models;

final class SettingModel
{
    private ?SizeGuideModel $sizeGuideModel = null;

    public function __construct(private \PDO $db) {}

    private function getSizeGuideModel(): SizeGuideModel
    {
        if ($this->sizeGuideModel === null) {
            $this->sizeGuideModel = new SizeGuideModel($this->db);
        }
        return $this->sizeGuideModel;
    }

    /**
     * Get all settings grouped by section, with decryption, boolean coercion,
     * JSON decoding, legacy migration, and bank account injection.
     * Does NOT cache within the request (unlike legacy getSettings()).
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAll(): array
    {
        $rows = $this->db->query(
            'SELECT section, `key`, `value` FROM settings ORDER BY section, `key`'
        )->fetchAll();

        $settings = [];
        foreach ($rows as $row) {
            $section = $row['section'];
            $key = $row['key'];
            $value = $row['value'];
            if (!isset($settings[$section])) {
                $settings[$section] = [];
            }
            $settings[$section][$key] = $value;
        }

        $this->decryptSensitive($settings);
        $this->coerceBooleans($settings);
        $this->decodeJsonSections($settings);
        $this->migrateLegacySections($settings);
        $this->injectBankAccounts($settings);

        return $settings;
    }

    /**
     * Save settings from a nested input array.
     * Uses transaction + upsert pattern.
     *
     * @param array<string, array<string, mixed>> $input
     */
    public function save(array $input): void
    {
        $this->db->beginTransaction();
        try {
            foreach ($input as $section => $data) {
                if (!is_array($data)) {
                    continue;
                }

                if ($section === 'transfer' && isset($data['banks'])) {
                    $enabled = !empty($data['enabled']) ? '1' : '0';
                    $this->upsert('transfer', 'enabled', $enabled);
                    $this->saveBankAccounts($data['banks']);
                    continue;
                }

                if ($section === 'size_guide' && isset($data['rows'])) {
                    foreach (['title_es', 'title_en', 'footer_es', 'footer_en'] as $k) {
                        if (isset($data[$k])) {
                            $this->upsert('size_guide', $k, $data[$k]);
                        }
                    }
                    $this->getSizeGuideModel()->replaceAll($data['rows']);
                    continue;
                }

                foreach ($data as $key => $value) {
                    if (is_bool($value)) {
                        $value = $value ? '1' : '0';
                    }
                    if (in_array($key, SENSITIVE_KEYS, true) && $value !== '' && $value !== null) {
                        $value = \encryptSecret((string) $value);
                    }
                    if (is_array($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
                    $this->upsert($section, $key, (string) ($value ?? ''));
                }
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    //  Bank accounts
    // =========================================================================

    /** @return array<int, array<string, mixed>> */
    public function getBankAccounts(): array
    {
        $rows = $this->db->query(
            'SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY sort_order'
        )->fetchAll();

        return array_map(fn(array $r): array => [
            'id'             => (int) $r['id'],
            'bankName'       => $r['bank_name'],
            'accountHolder'  => $r['account_holder'],
            'accountNumber'  => $r['account_number'],
            'accountType'    => $r['account_type'] ?? '',
            'routingNumber'  => $r['routing_number'] ?? '',
            'instructions'   => $r['instructions'] ?? '',
        ], $rows);
    }

    /** @param array<int, array<string, mixed>> $banks */
    public function saveBankAccounts(array $banks): void
    {
        $this->db->prepare('DELETE FROM bank_accounts')->execute();
        if ($banks === []) return;

        $stmt = $this->db->prepare(
            'INSERT INTO bank_accounts (bank_name, account_holder, account_number, account_type, routing_number, instructions, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach (array_values($banks) as $i => $b) {
            $stmt->execute([
                $b['bankName'] ?? '',
                $b['accountHolder'] ?? '',
                $b['accountNumber'] ?? '',
                $b['accountType'] ?? '',
                $b['routingNumber'] ?? '',
                $b['instructions'] ?? '',
                $i,
            ]);
        }
    }

    // =========================================================================
    //  Private helpers
    // =========================================================================

    private function upsert(string $section, string $key, string $value): void
    {
        $this->db->prepare(
            'INSERT INTO settings (section, `key`, `value`) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        )->execute([$section, $key, $value]);
    }

    /**
     * @param array<string, array<string, mixed>> $settings
     */
    private function decryptSensitive(array &$settings): void
    {
        foreach ($settings as &$kv) {
            foreach (SENSITIVE_KEYS as $sk) {
                if (isset($kv[$sk]) && $kv[$sk] !== '') {
                    $decrypted = \decryptSecret((string) $kv[$sk]);
                    if ($decrypted !== '') {
                        $kv[$sk] = $decrypted;
                    }
                }
            }
        }
        unset($kv);
    }

    /**
     * @param array<string, array<string, mixed>> $settings
     */
    private function coerceBooleans(array &$settings): void
    {
        $boolKeys = ['enabled'];
        foreach ($settings as &$kv) {
            foreach ($boolKeys as $bk) {
                if (isset($kv[$bk])) {
                    $kv[$bk] = $kv[$bk] === '1' || $kv[$bk] === 'true';
                }
            }
        }
        unset($kv);
    }

    /**
     * @param array<string, array<string, mixed>> $settings
     */
    private function decodeJsonSections(array &$settings): void
    {
        $jsonSections = ['landing'];
        foreach ($jsonSections as $jsSection) {
            if (!isset($settings[$jsSection])) continue;
            foreach ($settings[$jsSection] as $subKey => &$subVal) {
                $decoded = json_decode((string) $subVal, true);
                if (is_array($decoded)) {
                    $subVal = $decoded;
                }
            }
            unset($subVal);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $settings
     */
    private function migrateLegacySections(array &$settings): void
    {
        $legacyMapping = [
            'brand_values' => ['label_es', 'label_en', 'title_es', 'title_en', 'paragraph_es', 'paragraph_en', 'cta_es', 'cta_en', 'image_url', 'cta_link', 'cta_category_slug', 'enabled'],
        ];
        foreach ($legacyMapping as $sectionKey => $fields) {
            if (!isset($settings[$sectionKey])) continue;
            if (!isset($settings['landing'][$sectionKey]) || !is_array($settings['landing'][$sectionKey])) {
                $settings['landing'][$sectionKey] = [];
            }
            foreach ($fields as $field) {
                if (isset($settings[$sectionKey][$field])) {
                    $settings['landing'][$sectionKey][$field] = $settings[$sectionKey][$field];
                }
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $settings
     */
    private function injectBankAccounts(array &$settings): void
    {
        $banks = $this->getBankAccounts();
        if (!isset($settings['transfer'])) {
            $settings['transfer'] = ['enabled' => true];
        }
        $settings['transfer']['banks'] = $banks;
    }
}
