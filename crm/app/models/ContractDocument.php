<?php

declare(strict_types=1);

class ContractDocument
{
    public static function byContract(int $contractId): array
    {
        $stmt = db()->prepare(
            'SELECT cd.*, u.name AS uploaded_by_name
             FROM contract_documents cd
             LEFT JOIN users u ON u.id = cd.uploaded_by_user_id
             WHERE cd.contract_id = ? AND cd.deleted_at IS NULL
             ORDER BY cd.created_at DESC, cd.id DESC'
        );
        $stmt->execute([$contractId]);
        return $stmt->fetchAll();
    }

    public static function byCustomer(int $customerId): array
    {
        $stmt = db()->prepare(
            'SELECT cd.*, ct.contract_number, ct.contract_title, u.name AS uploaded_by_name
             FROM contract_documents cd
             JOIN contracts ct ON ct.id = cd.contract_id AND ct.deleted_at IS NULL
             JOIN customers c ON c.id = ct.customer_id AND c.deleted_at IS NULL
             LEFT JOIN users u ON u.id = cd.uploaded_by_user_id
             WHERE c.id = ? AND cd.deleted_at IS NULL
             ORDER BY ct.end_date DESC, ct.id DESC, cd.created_at DESC, cd.id DESC'
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT cd.*, ct.contract_number, ct.contract_title, ct.customer_id, u.name AS uploaded_by_name
             FROM contract_documents cd
             JOIN contracts ct ON ct.id = cd.contract_id AND ct.deleted_at IS NULL
             JOIN customers c ON c.id = ct.customer_id AND c.deleted_at IS NULL
             LEFT JOIN users u ON u.id = cd.uploaded_by_user_id
             WHERE cd.id = ? AND cd.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO contract_documents
             (contract_id, document_type, title, notes, file_path, original_name, mime_type, file_size, uploaded_by_user_id)
             VALUES (:contract_id, :document_type, :title, :notes, :file_path, :original_name, :mime_type, :file_size, :uploaded_by_user_id)'
        );
        $stmt->execute([
            'contract_id' => (int) $data['contract_id'],
            'document_type' => (string) $data['document_type'],
            'title' => (string) $data['title'],
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'file_path' => (string) $data['file_path'],
            'original_name' => (string) $data['original_name'],
            'mime_type' => (string) $data['mime_type'],
            'file_size' => (int) $data['file_size'],
            'uploaded_by_user_id' => (int) $data['uploaded_by_user_id'],
        ]);
        return (int) db()->lastInsertId();
    }

    public static function softDelete(int $id): void
    {
        db()->prepare('UPDATE contract_documents SET deleted_at = COALESCE(deleted_at, CURRENT_TIMESTAMP) WHERE id = ?')->execute([$id]);
    }

    public static function documentTypes(): array
    {
        $types = option_pairs('options_contract_document_types');
        return $types ?: [
            'Contract' => 'نسخه قرارداد',
            'Addendum' => 'الحاقیه',
            'Proposal' => 'پیشنهاد',
            'Minutes' => 'صورتجلسه',
            'Correspondence' => 'مکاتبات',
            'Other' => 'سایر',
        ];
    }

    public static function label(string $value): string
    {
        return self::documentTypes()[$value] ?? $value;
    }
}
