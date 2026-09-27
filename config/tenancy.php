<?php

/*
|--------------------------------------------------------------------------
| Multi-tenancy (one database per business)
|--------------------------------------------------------------------------
|
| The central database (DB_* in .env) holds businesses, plans, billing and
| platform admins. Every business gets its own database, created on signup
| with the same server credentials.
|
*/

return [

    // MySQL/MariaDB: database names are "<prefix><id>", e.g. dukapos_t12.
    // The DB_USERNAME account needs permission to create them:
    //   GRANT ALL PRIVILEGES ON `dukapos\_t%`.* TO 'dukapos'@'localhost';
    'database_prefix' => env('TENANT_DB_PREFIX', 'dukapos_t'),

    // SQLite: one file per business in this folder.
    'sqlite_path' => env('TENANT_SQLITE_PATH', database_path('tenants')),

    'migrations_path' => 'database/migrations/tenant',

    // Cookie that remembers the business when "Remember me" outlives the session.
    'cookie' => 'dp_tenant',

];
