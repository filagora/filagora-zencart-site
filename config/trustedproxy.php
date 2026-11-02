<?php

$trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));

return [
    'proxies' => $trustedProxies === '' ? null : $trustedProxies,
];
