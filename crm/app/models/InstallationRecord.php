<?php

declare(strict_types=1);

final class InstallationRecord
{
    private const CAPACITY_STATUSES = ['installed', 'pending_approval', 'approved'];

    public static function byItem(int $itemId): array
    {
        $sql = 'SELECT ir.*, tech.name AS technician_name, assignee.name AS assignee_name,
                creator.name AS creator_name, approver.name AS approver_name,
                EXISTS(SELECT 1 FROM delivery_receipt_items dri
                    JOIN delivery_receipts dr ON dr.id = dri.delivery_receipt_id AND dr.status = \'approved\'
                    WHERE dri.installation_record_id = ir.id AND dri.revoked_at IS NULL) AS is_delivered
            FROM installation_records ir
            JOIN contracts ct ON ct.id = ir.contract_id AND ct.deleted_at IS NULL
            LEFT JOIN users tech ON tech.id = ir.technician_user_id
            LEFT JOIN users assignee ON assignee.id = ir.assigned_operations_user_id
            LEFT JOIN users creator ON creator.id = ir.created_by_user_id
            LEFT JOIN users approver ON approver.id = ir.approved_by_user_id
            WHERE ir.contract_item_id = ? AND ir.deleted_at IS NULL';
        $params = [$itemId];
        self::applyReadVisibility($sql, $params, 'ct', 'ir');
        $stmt = db()->prepare($sql . ' ORDER BY ir.created_at DESC, ir.id DESC');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create(int $contractItemId, array $data, int $actorId): int
    {
        self::assertOperationsMutation();
        $imei = self::normalizeIdentity((string) ($data['imei'] ?? ''), 64);
        $serial = self::normalizeIdentity((string) ($data['serial_number'] ?? ''), 120);
        if ($contractItemId <= 0 || $actorId <= 0 || ($imei === null && $serial === null)) {
            throw new InvalidArgumentException('قلم قرارداد و حداقل یکی از IMEI یا Serial الزامی است.');
        }
        $status = (string) ($data['status'] ?? 'scheduled');
        $kind = (string) ($data['installation_kind'] ?? 'sale');
        if (!in_array($kind, ['sale', 'warranty_replacement', 'reinstallation'], true)) {
            throw new InvalidArgumentException('نوع گردش نصب معتبر نیست.');
        }
        $replacesId = self::nullableId($data['replaces_installation_id'] ?? null);
        if ($kind !== 'sale' && $replacesId === null) {
            throw new InvalidArgumentException('نصب جایگزین باید به نصب قبلی ارجاع داشته باشد.');
        }
        if (!in_array($status, ['scheduled', 'in_progress'], true)) {
            throw new InvalidArgumentException('وضعیت اولیه نصب معتبر نیست.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $item = self::lockInstallableItem($pdo, $contractItemId);
            $deviceModel = trim((string) ($data['device_model'] ?? $item['item_title']));
            if ($deviceModel === '' || mb_strlen($deviceModel) > 190) {
                throw new InvalidArgumentException('مدل دستگاه معتبر نیست.');
            }
            $stmt = $pdo->prepare('INSERT INTO installation_records
                (contract_id, contract_item_id, customer_id, device_model, installation_kind, replaces_installation_id, imei, serial_number,
                 license_plate, vin, vehicle_type, technician_user_id, assigned_operations_user_id,
                 scheduled_at, status, technical_result, notes, created_by_user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $item['contract_id'], $contractItemId, $item['customer_id'], $deviceModel, $kind, $replacesId, $imei, $serial,
                self::nullableText($data['license_plate'] ?? null, 40), self::nullableText($data['vin'] ?? null, 80),
                self::nullableText($data['vehicle_type'] ?? null, 120), self::nullableId($data['technician_user_id'] ?? null),
                self::nullableId($data['assigned_operations_user_id'] ?? null), self::normalizeDateTime($data['scheduled_at'] ?? null),
                $status, self::nullableText($data['technical_result'] ?? null, 500),
                trim((string) ($data['notes'] ?? '')) ?: null, $actorId,
            ]);
            $id = (int) $pdo->lastInsertId();
            self::insertEvent($pdo, $id, null, $status, 'ثبت اولیه نصب', $actorId);
            $pdo->commit();
            return $id;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public static function transition(int $id, string $toStatus, string $reason, int $actorId): void
    {
        self::assertOperationsMutation();
        $reason = trim($reason);
        if ($id <= 0 || $actorId <= 0 || $reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('شناسه، وضعیت یا دلیل تغییر معتبر نیست.');
        }
        $allowed = [
            'scheduled' => ['in_progress', 'cancelled'],
            'in_progress' => ['installed', 'failed', 'cancelled'],
            'failed' => ['scheduled', 'cancelled'],
            'installed' => ['pending_approval', 'failed'],
            'pending_approval' => ['approved', 'failed'],
            'approved' => [],
            'cancelled' => [],
        ];
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT ir.*, i.quantity FROM installation_records ir
                JOIN contract_items i ON i.id = ir.contract_item_id AND i.contract_id = ir.contract_id
                WHERE ir.id = ? AND ir.deleted_at IS NULL FOR UPDATE');
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            if (!$record || !in_array($toStatus, $allowed[$record['status']] ?? [], true)) {
                throw new DomainException('تغییر وضعیت نصب مجاز نیست.');
            }
            $pdo->prepare('SELECT id FROM contracts WHERE id = ? AND deleted_at IS NULL FOR UPDATE')
                ->execute([$record['contract_id']]);
            $pdo->prepare('SELECT id FROM contract_items WHERE id = ? AND contract_id = ? AND deleted_at IS NULL FOR UPDATE')
                ->execute([$record['contract_item_id'], $record['contract_id']]);
            if (in_array($toStatus, self::CAPACITY_STATUSES, true)
                && !in_array($record['status'], self::CAPACITY_STATUSES, true)) {
                $count = $pdo->prepare("SELECT COUNT(*) FROM installation_records
                    WHERE contract_item_id = ? AND deleted_at IS NULL
                    AND installation_kind = 'sale'
                    AND status IN ('installed','pending_approval','approved') AND id <> ?");
                $count->execute([$record['contract_item_id'], $id]);
                if ($record['installation_kind'] === 'sale' && (int) $count->fetchColumn() >= (int) $record['quantity']) {
                    throw new DomainException('ظرفیت تعهد این قلم تکمیل شده است.');
                }
            }
            $approved = $toStatus === 'approved';
            $installed = $toStatus === 'installed';
            $pdo->prepare('UPDATE installation_records SET status = ?,
                installed_at = CASE WHEN ? = 1 THEN COALESCE(installed_at, CURRENT_TIMESTAMP) ELSE installed_at END,
                approved_by_user_id = CASE WHEN ? = 1 THEN ? ELSE approved_by_user_id END,
                approved_at = CASE WHEN ? = 1 THEN CURRENT_TIMESTAMP ELSE approved_at END
                WHERE id = ?')->execute([$toStatus, $installed ? 1 : 0, $approved ? 1 : 0, $actorId, $approved ? 1 : 0, $id]);
            self::insertEvent($pdo, $id, (string) $record['status'], $toStatus, $reason, $actorId);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    private static function lockInstallableItem(PDO $pdo, int $itemId): array
    {
        $stmt = $pdo->prepare("SELECT i.id, i.contract_id, i.item_title, i.quantity, ct.customer_id
            FROM contract_items i JOIN contracts ct ON ct.id = i.contract_id
            WHERE i.id = ? AND i.deleted_at IS NULL AND ct.deleted_at IS NULL
              AND i.item_type = 'device' AND i.requires_installation = 1 FOR UPDATE");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) {
            throw new RuntimeException('قلم دستگاه نصب‌پذیر پیدا نشد.');
        }
        return $item;
    }

    private static function insertEvent(PDO $pdo, int $recordId, ?string $from, string $to, string $reason, int $actorId): void
    {
        $pdo->prepare('INSERT INTO installation_status_events
            (installation_record_id, from_status, to_status, reason, changed_by_user_id) VALUES (?, ?, ?, ?, ?)')
            ->execute([$recordId, $from, $to, $reason, $actorId]);
    }

    private static function assertOperationsMutation(): void
    {
        if (!in_array(current_user_role(), ['admin', 'operations'], true)) {
            throw new RuntimeException('اجازه تغییر اطلاعات نصب را ندارید.');
        }
    }

    private static function applyReadVisibility(string &$sql, array &$params, string $contractAlias, string $recordAlias): void
    {
        $role = current_user_role();
        if (in_array($role, ['admin', 'operations', 'finance'], true)) {
            return;
        }
        if ($role === 'sales') {
            $sql .= " AND {$contractAlias}.owner_user_id = ?";
            $params[] = current_user_id();
            return;
        }
        if ($role === 'support') {
            $sql .= " AND EXISTS (SELECT 1 FROM delivery_receipt_items vdri
                JOIN delivery_receipts vdr ON vdr.id = vdri.delivery_receipt_id AND vdr.status = 'approved'
                WHERE vdri.installation_record_id = {$recordAlias}.id AND vdri.revoked_at IS NULL)";
            return;
        }
        $sql .= ' AND 1 = 0';
    }

    private static function normalizeIdentity(string $value, int $max): ?string
    {
        $value = strtoupper(preg_replace('/[\s-]+/', '', trim($value)) ?? '');
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException('شناسه دستگاه طولانی است.');
        }
        return $value;
    }

    private static function nullableText(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException('مقدار متنی طولانی است.');
        }
        return $value === '' ? null : $value;
    }

    private static function nullableId(mixed $value): ?int
    {
        $id = (int) $value;
        return $id > 0 ? $id : null;
    }

    private static function normalizeDateTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new InvalidArgumentException('تاریخ برنامه‌ریزی معتبر نیست.');
        }
        return date('Y-m-d H:i:s', $timestamp);
    }
}
