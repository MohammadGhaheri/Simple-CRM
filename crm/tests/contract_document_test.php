<?php

declare(strict_types=1);

final class Setting
{
    public static function get(string $key): string
    {
        return $key === 'options_contract_document_types'
            ? "Contract|نسخه قرارداد\nAddendum|الحاقیه\nOther|سایر"
            : '';
    }
}

final class ContractDocumentTestStatement
{
    private mixed $result = false;

    public function __construct(private ContractDocumentTestDatabase $database, private string $sql)
    {
    }

    public function execute(array $params = []): bool
    {
        $sql = preg_replace('/\s+/', ' ', trim($this->sql));
        if (str_starts_with($sql, 'INSERT INTO contract_documents')) {
            $id = ++$this->database->lastId;
            $this->database->documents[$id] = ['id' => $id, 'deleted_at' => null, 'created_at' => '2026-10-01 10:00:00'] + $params;
        } elseif (str_starts_with($sql, 'UPDATE contract_documents')) {
            if (isset($this->database->documents[(int) $params[0]])) {
                $this->database->documents[(int) $params[0]]['deleted_at'] = '2026-10-01 11:00:00';
            }
        } elseif (str_contains($sql, 'WHERE cd.contract_id = ?')) {
            $this->result = $this->database->visibleDocuments((int) $params[0], null);
        } elseif (str_contains($sql, 'WHERE c.id = ?')) {
            $this->result = $this->database->visibleDocuments(null, (int) $params[0]);
        } elseif (str_contains($sql, 'WHERE cd.id = ?')) {
            $rows = $this->database->visibleDocuments(null, null, (int) $params[0]);
            $this->result = $rows[0] ?? false;
        }
        return true;
    }

    public function fetchAll(): array { return is_array($this->result) ? $this->result : []; }
    public function fetch(): mixed { return $this->result; }
}

final class ContractDocumentTestDatabase
{
    public int $lastId = 0;
    public array $documents = [];
    public array $contracts = [10 => ['id' => 10, 'customer_id' => 1, 'contract_title' => 'A', 'contract_number' => 'C-1', 'end_date' => '2027-01-01', 'deleted_at' => null], 20 => ['id' => 20, 'customer_id' => 2, 'contract_title' => 'B', 'contract_number' => 'C-2', 'end_date' => '2027-01-01', 'deleted_at' => null]];
    public array $customers = [1 => ['deleted_at' => null], 2 => ['deleted_at' => null]];
    public array $users = [7 => ['name' => 'Uploader']];

    public function prepare(string $sql): ContractDocumentTestStatement { return new ContractDocumentTestStatement($this, $sql); }
    public function lastInsertId(): string { return (string) $this->lastId; }

    public function visibleDocuments(?int $contractId, ?int $customerId, ?int $documentId = null): array
    {
        $rows = [];
        foreach ($this->documents as $row) {
            $contract = $this->contracts[(int) $row['contract_id']] ?? null;
            if (!$contract || $row['deleted_at'] !== null || $contract['deleted_at'] !== null || ($this->customers[(int) $contract['customer_id']]['deleted_at'] ?? null) !== null) {
                continue;
            }
            if ($contractId !== null && (int) $row['contract_id'] !== $contractId) { continue; }
            if ($customerId !== null && (int) $contract['customer_id'] !== $customerId) { continue; }
            if ($documentId !== null && (int) $row['id'] !== $documentId) { continue; }
            $rows[] = $row + $contract + ['uploaded_by_name' => $this->users[(int) $row['uploaded_by_user_id']]['name'] ?? null];
        }
        return $rows;
    }
}

$contractDocumentDb = new ContractDocumentTestDatabase();
function db(): ContractDocumentTestDatabase { global $contractDocumentDb; return $contractDocumentDb; }
function option_pairs(string $key): array
{
    $pairs = [];
    foreach (explode("\n", Setting::get($key)) as $line) {
        [$value, $label] = array_pad(explode('|', $line, 2), 2, '');
        if ($value !== '') { $pairs[$value] = $label ?: $value; }
    }
    return $pairs;
}

require __DIR__ . '/../app/models/ContractDocument.php';

function document_expect(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$first = ContractDocument::create(['contract_id' => 10, 'document_type' => 'Contract', 'title' => 'Main', 'notes' => '', 'file_path' => '2026/10/a.pdf', 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'file_size' => 123, 'uploaded_by_user_id' => 7]);
$second = ContractDocument::create(['contract_id' => 10, 'document_type' => 'Addendum', 'title' => 'Addendum', 'notes' => '', 'file_path' => '2026/10/b.pdf', 'original_name' => 'b.pdf', 'mime_type' => 'application/pdf', 'file_size' => 456, 'uploaded_by_user_id' => 7]);
ContractDocument::create(['contract_id' => 20, 'document_type' => 'Other', 'title' => 'Other customer', 'notes' => '', 'file_path' => '2026/10/c.pdf', 'original_name' => 'c.pdf', 'mime_type' => 'application/pdf', 'file_size' => 10, 'uploaded_by_user_id' => 7]);
document_expect(count(ContractDocument::byContract(10)) === 2, 'A contract must support multiple documents.');
document_expect(count(ContractDocument::byCustomer(1)) === 2, 'Customer query must only return its contract documents.');
document_expect((int) ContractDocument::find($first)['uploaded_by_user_id'] === 7, 'Uploader must be stored server-side.');
ContractDocument::softDelete($second);
document_expect(count(ContractDocument::byContract(10)) === 1, 'Soft-deleted documents must be hidden.');
$contractDocumentDb->contracts[10]['deleted_at'] = '2026-10-01 12:00:00';
document_expect(ContractDocument::byCustomer(1) === [], 'Deleted contracts must hide their documents.');
document_expect(ContractDocument::find($first) === null, 'Deleted contract documents must not remain downloadable.');
document_expect(ContractDocument::label('Contract') === 'نسخه قرارداد', 'Persian configured label must be used.');

$migration = file_get_contents(__DIR__ . '/../database/add_contract_documents.sql');
document_expect(str_contains($migration, 'ON DELETE CASCADE'), 'Contract FK must cascade on hard delete.');
document_expect(str_contains($migration, 'ON DELETE SET NULL'), 'Uploader FK must set null on user delete.');
document_expect(str_contains($migration, 'idx_contract_documents_contract'), 'Contract document index is required.');

$controller = file_get_contents(__DIR__ . '/../public/index.php');
$portal = file_get_contents(__DIR__ . '/../public/portal.php');
$contractView = file_get_contents(__DIR__ . '/../app/views/contracts/show.php');
$customerView = file_get_contents(__DIR__ . '/../app/views/customers/show.php');
document_expect(str_contains($controller, "action === 'document_upload'") && str_contains($controller, 'verify_csrf()'), 'Upload must be CSRF protected.');
document_expect(str_contains($controller, '$contract = Contract::find($id)') && str_contains($controller, "http_response_code(404)"), 'Upload must reject a missing or deleted contract.');
document_expect(str_contains($controller, 'array_key_exists($documentType, ContractDocument::documentTypes())'), 'Upload must reject unknown document types.');
document_expect(str_contains($controller, "'uploaded_by_user_id' => current_user_id()"), 'Uploader identity must never come from POST.');
document_expect(str_contains($controller, "action === 'document_delete'") && str_contains($controller, 'require_admin()'), 'Delete must be admin-only.');
document_expect(str_contains($controller, 'document_uploaded') && str_contains($controller, 'redirect('), 'Successful upload must use PRG.');
document_expect(str_contains($contractView, 'هنوز سندی برای این قرارداد ثبت نشده است.'), 'Contract empty state is missing.');
document_expect(str_contains($customerView, 'اسناد قراردادها') && str_contains($customerView, '$documentsByContract'), 'Customer profile must group contract documents.');
document_expect(!str_contains($portal, 'ContractDocument') && !str_contains($portal, 'contract-documents'), 'Portal must not expose contract documents.');
document_expect(str_contains($controller, "if (\$action === 'create')") && str_contains($controller, "if (\$action === 'edit')") && str_contains($controller, "if (\$action === 'show')"), 'Existing contract CRUD routes must remain present.');
document_expect(str_contains($controller, "'contracts' => Contract::byDeal(\$id)"), 'Deal to contract navigation must remain present.');

echo "Contract document behavior tests passed." . PHP_EOL;
