<?php

declare(strict_types=1);

// Static regression checks. Run with: php crm/tests/deal_stage_history_test.php
// Integration verification against MySQL is required before deployment.
$root = dirname(__DIR__);
$deal = file_get_contents($root . '/app/models/Deal.php');
$schema = file_get_contents($root . '/database/schema.sql');
$migration = file_get_contents($root . '/database/add_deal_stage_events.sql');

function stage_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

stage_expect(substr_count($deal, 'INSERT INTO deal_stage_events') === 2, 'Create and stage-changing update must each write a history event.');
stage_expect(str_contains($deal, 'VALUES (?, NULL, ?, ?)'), 'New deals must create an initial event with no previous stage.');
stage_expect(str_contains($deal, 'SELECT deal_stage FROM deals WHERE id = ? AND deleted_at IS NULL FOR UPDATE'), 'Updates must lock the current stage.');
stage_expect(str_contains($deal, "array_key_exists('expected_stage', \$data)"), 'Updates must accept an optimistic-concurrency stage token.');
stage_expect(str_contains($deal, '(string) $previousStage !== $expectedStage'), 'Stale forms must not overwrite a newer stage.');
stage_expect(str_contains($deal, "if ((string) \$previousStage !== (string) \$payload['deal_stage'])"), 'Unchanged stages must not create duplicate events.');
stage_expect(substr_count($deal, '$pdo->beginTransaction();') >= 2, 'Create and update must be transactional.');
stage_expect(substr_count($deal, '$pdo->rollBack();') >= 2, 'Errors must roll back both the deal change and stage event.');
stage_expect(str_contains($deal, 'current_user_id()'), 'Events must attribute the authenticated actor.');
stage_expect(str_contains($deal, 'function stageHistory(int $dealId)'), 'Stage history must be queryable by deal.');

stage_expect(str_contains($schema, 'CREATE TABLE deal_stage_events'), 'Fresh installs must include stage events.');
stage_expect(strpos($schema, 'DROP TABLE IF EXISTS deal_stage_events;') < strpos($schema, 'DROP TABLE IF EXISTS deals;'), 'Child stage events must be dropped before deals on fresh installs.');
stage_expect(str_contains($migration, 'CREATE TABLE IF NOT EXISTS deal_stage_events'), 'Upgrade must add the table without replacing data.');
stage_expect(str_contains($migration, 'FOREIGN KEY (deal_id) REFERENCES deals(id)'), 'History must reference a real deal.');
stage_expect(str_contains($migration, 'changed_by_user_id'), 'History must retain actor attribution.');
stage_expect(!preg_match('/\\b(DROP|TRUNCATE|DELETE\\s+FROM|UPDATE\\s+deals)\\b/i', $migration), 'Migration must not modify existing deal data.');
stage_expect(!preg_match('/INSERT\\s+INTO\\s+deal_stage_events/i', $migration), 'Migration must not invent historical stage transitions.');

$form = file_get_contents($root . '/app/views/deals/_form.php');
$controller = file_get_contents($root . '/public/index.php');
stage_expect(str_contains($form, 'name="expected_stage"'), 'Edit forms must submit the original stage token.');
stage_expect(str_contains($controller, 'catch (DomainException $e)'), 'Concurrency conflicts must be shown without losing the form data.');

echo "Deal stage history static checks passed." . PHP_EOL;
