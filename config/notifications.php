<?php

return [
    'shared_transactions' => [
        'mode' => env('SHARED_TRANSACTIONS_NOTIFICATION_MODE', 'immediate'),
        'debounce_minutes' => (int) env('SHARED_TRANSACTIONS_NOTIFICATION_DEBOUNCE_MINUTES', 5),
    ],
];
