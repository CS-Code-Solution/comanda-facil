<?php

return [
    // Opt-in para um cluster local já existente. Não cria bancos nem altera serviços.
    'enabled' => (bool) env('LOCAL_POSTGRES_MANAGED', false),
    'bin' => env('LOCAL_POSTGRES_BIN', ''),
    'data' => env('LOCAL_POSTGRES_DATA', ''),
];
