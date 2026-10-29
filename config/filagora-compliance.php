<?php

/*
 * Overrides of the compliance package's configuration. A top-level key replaces the package's whole
 * array, so each key listed here carries all of its own values.
 */
return [
    // The site sends its own privacy request mail (App\Listeners\SendPrivacyMail).
    'mail' => [
        'enabled' => false,
    ],
];
