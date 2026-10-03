<?php

declare(strict_types=1);

final class Setting
{
    public static function get(string $key): string { return $key === 'contract_renewal_reminder_days' ? '30' : ''; }
}

final class Activity
{
    public static array $renewals = [];
    public static function createOrUpdateContractRenewal(array $contract): void
    {
        self::$renewals[(int) $contract['id']] = ($contract['contract_type'] ?? 'formal') === 'formal' ? 'Open' : 'Cancelled';
    }
}

final class ContractTypeStatement
{
    private mixed $result = false;
    public function __construct(private ContractTypeDatabase $database, private string $sql) {}
    public function execute(array $params = []): bool
    {
        $sql = preg_replace('/\s+/', ' ', trim($this->sql));
        if (str_starts_with($sql, 'INSERT INTO contracts')) {
            $id = ++$this->database->lastId;
            $this->database->contracts[$id] = ['id' => $id, 'deleted_at' => null] + $params;
        } elseif (str_starts_with($sql, 'UPDATE contracts SET contract_type=')) {
            $id = (int) $params['id'];
            $this->database->contracts[$id] = ['id' => $id, 'deleted_at' => null] + $params;
        } elseif (str_starts_with($sql, 'SELECT id FROM contracts')) {
            $this->result = isset($this->database->contracts[(int) $params[0]]) ? (int) $params[0] : false;
        } elseif (str_contains($sql, 'WHERE ct.id = ?')) {
            $row = $this->database->contracts[(int) $params[0]] ?? null;
            $this->result = $row ? $row + ['customer_name' => 'Customer', 'deal_name' => null, 'owner_name' => 'Owner'] : false;
        }
        return true;
    }
    public function fetch(): mixed { return $this->result; }
    public function fetchColumn(): mixed { return $this->result; }
}

final class ContractTypeDatabase
{
    public int $lastId = 0;
    public array $contracts = [];
    private bool $transaction = false;
    public function prepare(string $sql): ContractTypeStatement { return new ContractTypeStatement($this, $sql); }
    public function lastInsertId(): string { return (string) $this->lastId; }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
}

$contractTypeDb = new ContractTypeDatabase();
function db(): ContractTypeDatabase { global $contractTypeDb; return $contractTypeDb; }
function current_user_id(): int { return 1; }
function normalize_digits(string $value): string { return $value; }
function normalize_money_decimal(string $value, bool $allowZero = true): string
{
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) { throw new InvalidArgumentException('invalid money'); }
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    return (ltrim($whole, '0') ?: '0') . '.' . str_pad($fraction, 2, '0');
}
function db_date(?string $value): ?string
{
    $value = trim((string) $value);
    return $value === '' ? null : str_replace('/', '-', $value);
}
function fa_date(?string $value): string { return (string) $value; }
function contract_status_options(): array { return ['Active', 'Renewal Due', 'Renewed', 'Expired', 'Cancelled']; }

require __DIR__ . '/../app/models/Contract.php';

function contract_type_expect(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$base = ['contract_title' => 'Record', 'customer_id' => 1, 'contract_amount' => '100.00', 'owner_user_id' => 1, 'status' => 'Active'];

try { Contract::create($base + ['contract_type' => 'formal', 'end_date' => '2027-01-01']); throw new RuntimeException('Formal number was not required.'); } catch (InvalidArgumentException) {}
try { Contract::create($base + ['contract_type' => 'formal', 'contract_number' => 'C-1']); throw new RuntimeException('Formal end date was not required.'); } catch (InvalidArgumentException) {}

$formal = Contract::create($base + ['contract_type' => 'formal', 'contract_number' => 'C-1', 'end_date' => '2027-01-31']);
contract_type_expect(Activity::$renewals[$formal] === 'Open', 'Formal record must create renewal behavior.');

$sale = Contract::create($base + ['contract_type' => 'direct_sale', 'renewal_reminder_date' => '2026-12-01']);
$saleRow = Contract::find($sale);
contract_type_expect($saleRow['contract_number'] === null && $saleRow['end_date'] === null, 'Direct sale number/end date must be optional and null.');
contract_type_expect($saleRow['renewal_reminder_date'] === null, 'Direct sale renewal date must be forced to null.');
contract_type_expect(Activity::$renewals[$sale] === 'Cancelled', 'Direct sale must not schedule renewal.');

try { Contract::create(array_merge($base, ['contract_type' => 'direct_sale', 'status' => 'Expired'])); throw new RuntimeException('Invalid direct sale status was accepted.'); } catch (InvalidArgumentException) {}
$fallback = Contract::create($base + ['contract_type' => 'invalid', 'contract_number' => 'C-3', 'end_date' => '2027-01-31']);
contract_type_expect(Contract::find($fallback)['contract_type'] === 'formal', 'Invalid type must safely fall back to formal.');

$migration = file_get_contents(__DIR__ . '/../database/add_contract_types.sql');
contract_type_expect(str_contains($migration, 'contract_type VARCHAR(40) NOT NULL DEFAULT \'formal\''), 'Migration type/default is missing.');
contract_type_expect(str_contains($migration, 'contract_number VARCHAR(80) NULL') && str_contains($migration, 'end_date DATE NULL'), 'Migration nullable columns are missing.');
contract_type_expect(!preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $migration), 'Migration contains destructive SQL.');
contract_type_expect(!str_contains($migration, 'contract_payments'), 'Contract type migration must not alter finance tables.');

$activitySource = file_get_contents(__DIR__ . '/../app/models/Activity.php');
contract_type_expect(str_contains($activitySource, "contract_type'] ?? 'formal') !== 'formal'"), 'Renewal cancellation must be enforced for direct sales.');
$contractSource = file_get_contents(__DIR__ . '/../app/models/Contract.php');
contract_type_expect(str_contains($contractSource, 'ORDER BY ct.end_date IS NULL ASC'), 'Null end-date sorting must be deterministic.');
contract_type_expect(str_contains($contractSource, 'Activity::createOrUpdateContractRenewal($contract)'), 'Create/update must synchronize renewal behavior after type conversion.');
contract_type_expect(str_contains($contractSource, "if (\$type === 'formal' && \$number === '')") && str_contains($contractSource, "if (\$type === 'formal' && !\$endDate)"), 'Conversion to formal must enforce number and end date.');
$sidebarSource = file_get_contents(__DIR__ . '/../app/views/layouts/sidebar.php');
$indexSource = file_get_contents(__DIR__ . '/../app/views/contracts/index.php');
$formSource = file_get_contents(__DIR__ . '/../app/views/contracts/_form.php');
$detailSource = file_get_contents(__DIR__ . '/../app/views/contracts/show.php');
$customerSource = file_get_contents(__DIR__ . '/../app/views/customers/show.php');
$financeSource = file_get_contents(__DIR__ . '/../app/views/finance/index.php');
$javascriptSource = file_get_contents(__DIR__ . '/../public/assets/js/app.js');
contract_type_expect(str_contains($sidebarSource, 'قراردادها و فروش‌ها'), 'Navigation terminology was not updated.');
contract_type_expect(str_contains($indexSource, 'name="contract_type"') && str_contains($indexSource, 'badge-muted'), 'Contract type filter or badge is missing.');
contract_type_expect(str_contains($formSource, 'data-contract-type') && str_contains($formSource, 'data-renewal-field'), 'Contract type form controls are missing.');
contract_type_expect(str_contains($detailSource, 'Contract::typeLabel') && str_contains($customerSource, 'Contract::typeLabel'), 'Type is missing from record or customer details.');
contract_type_expect(str_contains($financeSource, 'name="contract_type"') && str_contains($financeSource, 'Contract::typeLabel'), 'Finance type support is missing.');
contract_type_expect(str_contains($javascriptSource, '[data-contract-type]'), 'Dynamic contract type form behavior is missing.');

$config = require __DIR__ . '/../app/config/database.php';
$database = 'simple_crm_contract_type_' . bin2hex(random_bytes(4));
try {
    $server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port'] ?? 3306), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'] ?? 3306, $database), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('CREATE TABLE contracts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, contract_number VARCHAR(80) NOT NULL, end_date DATE NOT NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE contract_payments (id BIGINT UNSIGNED PRIMARY KEY, contract_id INT UNSIGNED NOT NULL, amount DECIMAL(18,2) NOT NULL) ENGINE=InnoDB');
    $financeDefinitionBefore = $pdo->query('SHOW CREATE TABLE contract_payments')->fetch(PDO::FETCH_NUM)[1];
    foreach (array_filter(array_map('trim', explode(';', $migration))) as $statement) { $pdo->exec($statement); }
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM contracts')->fetchAll() as $column) { $columns[$column['Field']] = $column; }
    contract_type_expect(($columns['contract_type']['Default'] ?? '') === 'formal', 'MySQL migration default failed.');
    contract_type_expect(($columns['contract_number']['Null'] ?? '') === 'YES' && ($columns['end_date']['Null'] ?? '') === 'YES', 'MySQL nullable migration failed.');
    $financeDefinitionAfter = $pdo->query('SHOW CREATE TABLE contract_payments')->fetch(PDO::FETCH_NUM)[1];
    contract_type_expect($financeDefinitionAfter === $financeDefinitionBefore, 'Migration changed the existing finance table.');
    $pdo->exec("INSERT INTO contracts (contract_type,contract_number,end_date) VALUES ('direct_sale',NULL,NULL)");
    echo "Contract type MySQL migration test passed.\n";
} catch (PDOException $e) {
    if (isset($pdo)) { throw $e; }
    fwrite(STDERR, "MYSQL_INTEGRATION_SKIPPED: {$e->getMessage()}\n");
} finally {
    if (isset($server)) { $server->exec("DROP DATABASE IF EXISTS `$database`"); }
}

echo "Contract type behavior tests passed.\n";
