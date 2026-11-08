<?php

declare(strict_types=1);

/**
 * Read-only schema preflight for a staged CRM/Mammut rollout.
 *
 * No application data rows are read, modified or returned.
 * Do not use a passing result as a substitute for a verified backup or an
 * upgrade rehearsal on a separate copy of the actual database.
 */
final class ReleaseSchemaPreflight
{
    private const MIGRATIONS = [
        [
            'file' => 'add_deal_stage_events.sql',
            'objects' => ['table:deal_stage_events'],
        ],
        [
            'file' => 'add_contract_items.sql',
            'objects' => ['table:contract_items', 'table:contract_invoice_refs', 'table:contract_invoice_lines'],
        ],
        [
            'file' => 'add_contract_item_amendments.sql',
            'objects' => ['table:contract_item_amendments'],
        ],
        [
            'file' => 'add_contract_invoice_reversals.sql',
            'objects' => [
                'column:contract_invoice_lines.reversed_at',
                'column:contract_invoice_lines.reversed_by_user_id',
                'column:contract_invoice_lines.reversal_reason',
                'index:contract_invoice_lines.idx_invoice_lines_ref_item',
                'index:contract_invoice_lines.idx_invoice_lines_reversal',
            ],
            'forbidden' => ['index:contract_invoice_lines.uq_contract_invoice_lines'],
        ],
        [
            'file' => 'add_contract_change_audits.sql',
            'objects' => ['table:contract_item_change_events', 'table:contract_invoice_ref_events'],
        ],
        [
            'file' => 'add_mammut_installation_fulfillment.sql',
            'objects' => [
                'table:installation_records', 'table:installation_status_events',
                'table:delivery_receipts', 'table:delivery_receipt_items',
                'table:fulfillment_events',
            ],
        ],
    ];

    public static function inspect(PDO $pdo): array
    {
        $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($database === '') {
            throw new RuntimeException('No explicit database selected; refusing preflight.');
        }
        $tablesStmt = $pdo->prepare('SELECT table_name FROM information_schema.tables
            WHERE table_schema = ?');
        $tablesStmt->execute([$database]);
        $tables = array_fill_keys(array_map('strtolower', $tablesStmt->fetchAll(PDO::FETCH_COLUMN)), true);

        $columnsStmt = $pdo->prepare('SELECT table_name, column_name FROM information_schema.columns
            WHERE table_schema = ?');
        $columnsStmt->execute([$database]);
        $columns = [];
        foreach ($columnsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[strtolower($row['table_name'] . '.' . $row['column_name'])] = true;
        }

        $indexesStmt = $pdo->prepare('SELECT DISTINCT table_name, index_name
            FROM information_schema.statistics WHERE table_schema = ?');
        $indexesStmt->execute([$database]);
        $indexes = [];
        foreach ($indexesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $indexes[strtolower($row['table_name'] . '.' . $row['index_name'])] = true;
        }

        $has = static function (string $object) use ($tables, $columns, $indexes): bool {
            [$type, $name] = explode(':', $object, 2);
            $name = strtolower($name);
            return match ($type) {
                'table' => isset($tables[$name]),
                'column' => isset($columns[$name]),
                'index' => isset($indexes[$name]),
                default => false,
            };
        };

        $completed = 0;
        $stages = [];
        $incomplete = false;
        foreach (self::MIGRATIONS as $i => $step) {
            $missing = array_values(array_filter(
                $step['objects'],
                static fn(string $object): bool => !$has($object)
            ));
            $presentForbidden = array_values(array_filter(
                $step['forbidden'] ?? [],
                static fn(string $object): bool => $has($object)
            ));
            $present = count($step['objects']) - count($missing);
            $complete = $missing === [] && $presentForbidden === [];
            $stages[] = [
                'step' => $i + 1,
                'migration' => $step['file'],
                'complete' => $complete,
                'present_count' => $present,
                'required_count' => count($step['objects']),
                'missing' => $missing,
                'obsolete_objects_present' => $presentForbidden,
            ];
            if (!$incomplete && $complete) {
                $completed++;
            } else {
                $incomplete = true;
            }
        }

        $drift = false;
        foreach ($stages as $index => $stage) {
            if ($index < $completed) {
                continue;
            }
            if ($stage['present_count'] > 0 && $stage['present_count'] < $stage['required_count']) {
                $drift = true; // interrupted migration or incompatible schema
            }
            if ($index > $completed && $stage['present_count'] > 0) {
                $drift = true; // later migrations apparently applied out of order
            }
            if ($stage['present_count'] === $stage['required_count'] && !$stage['complete']) {
                $drift = true; // e.g. old unique index retained after reversal
            }
        }

        $next = $completed < count(self::MIGRATIONS)
            ? self::MIGRATIONS[$completed]['file']
            : null;
        return [
            'state' => $drift ? 'manual_review_required'
                : ($completed === 0 ? 'legacy'
                    : ($completed === count(self::MIGRATIONS) ? 'mammut_ready' : 'partial_upgrade')),
            'completed_steps' => $completed,
            'total_steps' => count(self::MIGRATIONS),
            'next_migration' => $drift ? null : $next,
            'can_apply_next' => !$drift && $next !== null,
            'stages' => $stages,
            'production_ready' => false, // this tool cannot establish operational release safety
        ];
    }
}
