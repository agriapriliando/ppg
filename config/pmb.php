<?php

return [
    'name' => 'PMB PPG IAKN Palangka Raya',
    'business_timezone' => 'Asia/Jakarta',
    'temporary_password_minutes' => (int) env('PMB_TEMPORARY_PASSWORD_MINUTES', 1440),
    'activation_session_minutes' => (int) env('PMB_ACTIVATION_SESSION_MINUTES', 10),
    'whatsapp_challenge_minutes' => (int) env('PMB_WHATSAPP_CHALLENGE_MINUTES', 15),
    'whatsapp_challenge_attempts' => 5,
    'import_max_kilobytes' => 5120,
    'import_max_rows' => 5000,
    'import_retention_days' => (int) env('PMB_IMPORT_RETENTION_DAYS', 30),
    'neo_enabled' => false,
];
