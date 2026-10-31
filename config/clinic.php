<?php

return [
    'currency' => env('APP_CURRENCY', 'PEN'),
    'expense_approval_limit' => (float) env('CLINIC_EXPENSE_APPROVAL_LIMIT', 5000),
];
