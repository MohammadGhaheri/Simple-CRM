<?php

declare(strict_types=1);

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/models/Setting.php';
require __DIR__ . '/../app/models/User.php';
require __DIR__ . '/../app/models/Contact.php';
require __DIR__ . '/../app/models/Activity.php';
require __DIR__ . '/../app/models/Ticket.php';
require __DIR__ . '/../app/services/SmsService.php';

echo SmsService::sendDailySummary() ? "Daily summary SMS sent\n" : "Daily summary SMS skipped or failed\n";
