<?php

declare(strict_types=1);

final class Setting
{
    public static function get(string $key): string
    {
        return $key === 'options_products'
            ? "cro|بهینه‌سازی نرخ تبدیل\nifms|سامانه مدیریت ناوگان\nOther|سایر"
            : '';
    }
}

require __DIR__ . '/../app/core/helpers.php';

function product_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

product_expect(product_options() === ['cro', 'ifms', 'Other'], 'Product option values must remain internal database values.');
product_expect(product_label('cro') === 'بهینه‌سازی نرخ تبدیل', 'Configured CRO label was not returned.');
product_expect(product_label('ifms') === 'سامانه مدیریت ناوگان', 'Configured fleet label was not returned.');
product_expect(product_label('Other') === 'سایر', 'Configured Other label was not returned.');
product_expect(product_label('legacy_product') === 'legacy_product', 'Unknown legacy values must remain readable.');
product_expect(product_label('') === '', 'Empty product values must be handled safely.');

$dealForm = file_get_contents(__DIR__ . '/../app/views/deals/_form.php');
product_expect(str_contains($dealForm, 'value="<?= e($option) ?>"'), 'Deal form must submit internal product values.');
product_expect(str_contains($dealForm, 'product_label($option)'), 'Deal form must display configured labels.');
foreach ([
    'deals/index.php',
    'deals/show.php',
    'customers/show.php',
    'contracts/index.php',
    'contracts/show.php',
    'contracts/_form.php',
] as $view) {
    $source = file_get_contents(__DIR__ . '/../app/views/' . $view);
    product_expect(str_contains($source, 'product_label('), $view . ' must display the configured product label.');
}

echo "Product label tests passed." . PHP_EOL;
