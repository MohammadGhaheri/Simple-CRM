<?php

declare(strict_types=1);

final class InstallationObligation
{
    public static function search(array $filters = []): array
    {
        $sql = "SELECT i.id AS contract_item_id, i.contract_id, i.item_title, i.product, i.sku,
                i.quantity AS committed_quantity, ct.contract_number, ct.contract_title,
                c.id AS customer_id, c.customer_name, ct.owner_user_id,
                COUNT(DISTINCT CASE WHEN ir.installation_kind = 'sale' AND ir.status IN ('installed','pending_approval','approved') AND ir.deleted_at IS NULL THEN ir.id END) AS installed_quantity,
                COUNT(DISTINCT CASE WHEN ir.installation_kind = 'sale' AND ir.status = 'approved' AND ir.deleted_at IS NULL THEN ir.id END) AS approved_quantity,
                COUNT(DISTINCT CASE WHEN ir.installation_kind = 'sale' AND dr.status = 'approved' AND dri.revoked_at IS NULL THEN ir.id END) AS delivered_quantity,
                GREATEST(i.quantity - COUNT(DISTINCT CASE WHEN ir.installation_kind = 'sale' AND dr.status = 'approved' AND dri.revoked_at IS NULL THEN ir.id END), 0) AS remaining_quantity,
                CASE
                  WHEN COUNT(DISTINCT CASE WHEN ir.installation_kind = 'sale' AND dr.status = 'approved' AND dri.revoked_at IS NULL THEN ir.id END) >= i.quantity THEN 'completed'
                  WHEN COUNT(DISTINCT CASE WHEN ir.status = 'pending_approval' AND ir.deleted_at IS NULL THEN ir.id END) > 0 THEN 'pending_approval'
                  WHEN COUNT(DISTINCT CASE WHEN ir.status IN ('in_progress','installed','approved') AND ir.deleted_at IS NULL THEN ir.id END) > 0 THEN 'in_progress'
                  ELSE 'ready'
                END AS obligation_status,
                MAX(COALESCE(dr.created_at, ir.updated_at, i.updated_at)) AS last_activity_at,
                GROUP_CONCAT(DISTINCT assignee.name ORDER BY assignee.name SEPARATOR '، ') AS operations_assignees
            FROM contract_items i
            JOIN contracts ct ON ct.id = i.contract_id AND ct.deleted_at IS NULL
            JOIN customers c ON c.id = ct.customer_id AND c.deleted_at IS NULL
            LEFT JOIN installation_records ir ON ir.contract_item_id = i.id AND ir.contract_id = i.contract_id AND ir.deleted_at IS NULL
            LEFT JOIN users assignee ON assignee.id = ir.assigned_operations_user_id
            LEFT JOIN delivery_receipt_items dri ON dri.installation_record_id = ir.id AND dri.contract_id = i.contract_id AND dri.revoked_at IS NULL
            LEFT JOIN delivery_receipts dr ON dr.id = dri.delivery_receipt_id AND dr.contract_id = i.contract_id AND dr.status = 'approved'
            WHERE i.deleted_at IS NULL AND i.item_type = 'device' AND i.requires_installation = 1";
        $params = [];
        self::applyVisibility($sql, $params, 'ct', 'i');
        if (!empty($filters['customer_id'])) {
            $sql .= ' AND c.id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['device'])) {
            $sql .= ' AND (i.item_title LIKE ? OR i.product LIKE ? OR i.sku LIKE ?)';
            $like = '%' . trim((string) $filters['device']) . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($filters['date_from'])) {
            $sql .= ' AND COALESCE(ir.installed_at, ir.scheduled_at, i.created_at) >= ?';
            $params[] = db_date((string) $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND COALESCE(ir.installed_at, ir.scheduled_at, i.created_at) < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = db_date((string) $filters['date_to']);
        }
        $sql .= ' GROUP BY i.id, i.contract_id, i.item_title, i.product, i.sku, i.quantity,
            ct.contract_number, ct.contract_title, c.id, c.customer_name, ct.owner_user_id';
        if (!empty($filters['status'])) {
            $status = (string) $filters['status'];
            $having = [
                'completed' => 'delivered_quantity >= committed_quantity',
                'pending_approval' => "obligation_status = 'pending_approval'",
                'in_progress' => "obligation_status = 'in_progress'",
                'ready' => "obligation_status = 'ready'",
            ];
            if (isset($having[$status])) {
                $sql .= ' HAVING ' . $having[$status];
            }
        }
        $sql .= ' ORDER BY remaining_quantity DESC, last_activity_at DESC, i.id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $itemId): ?array
    {
        foreach (self::search() as $row) {
            if ((int) $row['contract_item_id'] === $itemId) {
                return $row;
            }
        }
        return null;
    }

    private static function applyVisibility(string &$sql, array &$params, string $contractAlias, string $itemAlias): void
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
            $sql .= " AND EXISTS (SELECT 1 FROM installation_records sir
                JOIN delivery_receipt_items sdri ON sdri.installation_record_id = sir.id AND sdri.revoked_at IS NULL
                JOIN delivery_receipts sdr ON sdr.id = sdri.delivery_receipt_id AND sdr.status = 'approved'
                WHERE sir.contract_item_id = {$itemAlias}.id AND sir.deleted_at IS NULL)";
            return;
        }
        $sql .= ' AND 1 = 0';
    }
}
