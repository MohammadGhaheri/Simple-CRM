<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $appSettings = class_exists('Setting') ? Setting::all() : ['app_title' => 'Elm Simple CRM', 'primary_color' => '#155eef', 'sidebar_color' => '#111827']; ?>
    <title><?= e($title ?? $appSettings['app_title']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <style>
        :root {
            --primary: <?= e($appSettings['primary_color']) ?>;
            --primary-dark: <?= e($appSettings['primary_color']) ?>;
            --sidebar: <?= e($appSettings['sidebar_color']) ?>;
        }
    </style>
</head>
<body>
<div class="app-shell">
