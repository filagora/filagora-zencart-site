<?php

/*
 * Overrides of the Filagora core configuration. The site's tables have no prefix (production
 * already has them as `threads`, `forums`, `plugins` and so on), so every environment, including a
 * fresh local or test database, builds them the same way. Fixed here, not read from the
 * environment, because a prefix other than this one would point the app at tables that do not exist.
 */
return [
    'table_prefix' => '',
];
