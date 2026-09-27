<?php

/*
|--------------------------------------------------------------------------
| DukaPOS domain configuration
|--------------------------------------------------------------------------
|
| Permissions, default roles, default settings and document types. Roles and
| their permissions are seeded from here but remain editable from the UI.
|
*/

return [

    'version' => '1.0.0',

    /*
     | Permissions grouped by module. Keys are permission names, values are labels.
     */
    'permissions' => [
        'Dashboard' => [
            'dashboard.view' => 'View dashboard',
        ],
        'Branches' => [
            'branches.view' => 'View branches',
            'branches.manage' => 'Create / edit branches and registers',
            'branches.view_all' => 'View all branches',
        ],
        'Users & Security' => [
            'users.view' => 'View users',
            'users.manage' => 'Create / edit / deactivate users',
            'users.impersonate' => 'Login as another user',
            'roles.manage' => 'Manage roles & permissions',
            'activity.view' => 'View activity log',
        ],
        'Settings' => [
            'settings.manage' => 'Manage settings',
            'backups.manage' => 'Manage backups',
        ],
        'Catalog' => [
            'products.view' => 'View products',
            'products.create' => 'Create products',
            'products.edit' => 'Edit products',
            'products.delete' => 'Delete products',
            'products.edit_price' => 'Edit selling prices',
            'products.view_cost' => 'View cost prices',
            'products.import' => 'Import / export products',
            'products.labels' => 'Print barcode labels',
            'catalog.manage' => 'Manage categories, brands and units',
        ],
        'Inventory' => [
            'stock.view' => 'View stock levels & movements',
            'stock.value.view' => 'View stock value',
            'stock.adjust' => 'Create stock adjustments',
            'stock.adjust.approve' => 'Approve stock adjustments',
            'stock.transfer' => 'Create / dispatch / receive transfers',
            'stock.transfer.approve' => 'Approve transfers',
            'stock.take' => 'Run stock takes',
            'stock.take.approve' => 'Approve & post stock takes',
        ],
        'POS & Sales' => [
            'pos.access' => 'Access POS screen',
            'sales.create' => 'Create sales',
            'sales.view' => 'View own sales',
            'sales.view_all' => 'View all sales',
            'sales.void' => 'Void sales',
            'sales.discount' => 'Give discounts (within limit)',
            'sales.discount.above_limit' => 'Give discounts above limit',
            'sales.price_override' => 'Override selling price',
            'sales.below_cost' => 'Sell below cost',
            'sales.negative_stock' => 'Sell beyond available stock',
            'sales.return' => 'Process returns / refunds',
            'sales.credit.above_limit' => 'Exceed customer credit limit',
            'sales.reprint' => 'Reprint receipts',
            'quotations.manage' => 'Manage quotations',
            'layaway.manage' => 'Manage layaway / deposits',
        ],
        'Shifts' => [
            'shifts.open' => 'Open / close own shift',
            'shifts.manage' => 'View & force-close any shift',
            'cash.movements' => 'Record cash in / cash out',
        ],
        'Customers' => [
            'customers.view' => 'View customers',
            'customers.manage' => 'Create / edit customers',
            'customers.credit' => 'Set credit limits, wholesale status and opening balances',
            'customers.payments' => 'Receive customer payments',
        ],
        'Purchases' => [
            'suppliers.view' => 'View suppliers',
            'suppliers.manage' => 'Create / edit suppliers',
            'purchases.view' => 'View purchase orders & GRNs',
            'purchases.manage' => 'Create / edit purchase orders',
            'purchases.receive' => 'Receive goods (GRN)',
            'supplier.payments' => 'Record supplier bills & payments',
            'purchases.return' => 'Return goods to supplier',
        ],
        'Expenses' => [
            'expenses.view' => 'View expenses',
            'expenses.manage' => 'Record / edit expenses',
        ],
        'Reports' => [
            'reports.view' => 'View reports',
            'reports.profit.view' => 'View cost, profit & margins',
            'reports.export' => 'Export reports',
        ],
    ],

    /*
     | Default roles. "owner" bypasses every check via Gate::before.
     */
    'roles' => [
        'owner' => [
            'label' => 'Owner / Super Admin',
            'permissions' => ['*'],
        ],
        'manager' => [
            'label' => 'Manager',
            'permissions' => [
                'dashboard.view', 'branches.view',
                'users.view', 'activity.view',
                'products.*', 'catalog.manage',
                'stock.*',
                'pos.access', 'sales.*', 'quotations.manage', 'layaway.manage',
                'shifts.*', 'cash.movements',
                'customers.*',
                'suppliers.*', 'purchases.*', 'supplier.payments',
                'expenses.*',
                'reports.*',
            ],
        ],
        'cashier' => [
            'label' => 'Cashier',
            'permissions' => [
                'dashboard.view', 'products.view',
                'pos.access', 'sales.create', 'sales.view', 'sales.discount', 'sales.reprint',
                'quotations.manage',
                'shifts.open', 'cash.movements',
                'customers.view', 'customers.manage',
            ],
        ],
        'storekeeper' => [
            'label' => 'Storekeeper',
            'permissions' => [
                'dashboard.view',
                'products.view', 'products.create', 'products.edit', 'products.labels', 'catalog.manage',
                'stock.view', 'stock.adjust', 'stock.transfer', 'stock.take',
                'suppliers.view', 'purchases.view', 'purchases.receive',
            ],
        ],
        'accountant' => [
            'label' => 'Accountant',
            'permissions' => [
                'dashboard.view', 'branches.view', 'branches.view_all',
                'products.view', 'products.view_cost', 'stock.view', 'stock.value.view',
                'sales.view', 'sales.view_all',
                'customers.view', 'customers.payments',
                'suppliers.view', 'purchases.view', 'supplier.payments',
                'expenses.*',
                'reports.*',
                'shifts.manage',
            ],
        ],
    ],

    /*
     | Default settings (key => value). Stored overrides live in the `settings` table.
     */
    'settings' => [
        // Business
        'business.name' => 'DukaPOS Demo Store',
        'business.tin' => '',
        'business.vrn' => '',
        'business.address' => 'Dar es Salaam, Tanzania',
        'business.phone' => '',
        'business.email' => '',
        'business.logo' => null,

        // Currency & tax
        'currency.code' => 'TZS',
        'currency.symbol' => 'TSh',
        'currency.decimals' => 0,
        'currency.thousands_separator' => ',',
        'currency.decimal_separator' => '.',
        'currency.usd_rate' => 2500, // TZS per 1 USD, for Cash (USD) payments
        'tax.vat_rate' => 18,
        'tax.prices_include_vat' => true,

        // Receipt
        'receipt.paper' => '80mm',
        'receipt.header' => 'Karibu! Welcome',
        'receipt.footer' => 'Asante kwa kununua! Thank you for shopping with us.',
        'receipt.show_logo' => true,
        'receipt.auto_print' => false,
        'receipt.show_qr' => true,
        'receipt.print_mode' => 'browser', // browser | escpos (direct to USB/serial thermal printer)
        'receipt.drawer_kick' => true, // open the cash drawer on cash sales (escpos mode)

        // POS
        'pos.negative_stock' => 'block', // block | warn | allow
        'pos.max_discount_percent' => 10,
        'pos.below_cost' => 'approval', // block | approval | allow
        'pos.rounding' => 0, // 0 | 50 | 100
        'pos.scan_sound' => true,
        'pos.lock_minutes' => 10,
        'pos.default_customer_id' => null,

        // Loyalty
        'loyalty.enabled' => false,
        'loyalty.earn_per_amount' => 1000, // 1 point per TSh 1,000
        'loyalty.point_value' => 10, // 1 point = TSh 10 when redeemed

        // Inventory
        'inventory.costing' => 'average', // average | last
        'inventory.expiry_alert_days' => 30,

        // Payment methods
        'payments.cash' => true,
        'payments.cash_usd' => false,
        'payments.mpesa' => true,
        'payments.tigopesa' => true,
        'payments.airtel' => true,
        'payments.halopesa' => true,
        'payments.card' => true,
        'payments.bank' => true,
        'payments.credit' => true,
        'payments.store_credit' => true,
        'payments.gateway' => 'manual', // manual | fastlipa
        'payments.fastlipa_api_key' => null,
        'payments.fastlipa_base_url' => 'https://api.fastlipa.com',
        'payments.fastlipa_webhook_secret' => null,

        // SMS & notifications
        'sms.driver' => 'log', // log | beem
        'sms.sender_id' => 'DUKAPOS',
        'sms.api_key' => null,
        'sms.api_secret' => null,
        'notify.low_stock_email' => false,
        'notify.low_stock_sms' => false,
        'notify.over_short_threshold' => 5000,

        // Fiscal
        'fiscal.driver' => 'null',

        // Localisation
        'locale.language' => 'en',
        'locale.timezone' => 'Africa/Dar_es_Salaam',
        'locale.date_format' => 'd/m/Y',

        // Document prefixes
        'prefix.invoice' => 'INV',
        'prefix.purchase_order' => 'PO',
        'prefix.goods_receipt' => 'GRN',
        'prefix.return' => 'RET',
        'prefix.transfer' => 'TRF',
        'prefix.quotation' => 'QT',
        'prefix.expense' => 'EXP',
        'prefix.adjustment' => 'ADJ',
        'prefix.stock_take' => 'STK',
        'prefix.shift' => 'SH',
        'prefix.customer_payment' => 'RCP',
        'prefix.supplier_payment' => 'PAY',
        'prefix.purchase_return' => 'PRT',
        'prefix.layaway' => 'LAY',
    ],

    /*
     | Settings whose values are encrypted at rest.
     */
    'encrypted_settings' => [
        'payments.fastlipa_api_key',
        'payments.fastlipa_webhook_secret',
        'sms.api_key',
        'sms.api_secret',
    ],

    'denominations' => [10000, 5000, 2000, 1000, 500, 200, 100, 50],

    'quick_cash' => [1000, 2000, 5000, 10000, 20000, 50000],
];
