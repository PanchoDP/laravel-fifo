<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Transaction Reference Prefix
    |--------------------------------------------------------------------------
    |
    | This value is used as the default prefix for automatically generated
    | transaction references when none is provided.
    |
    */
    'default_reference_prefix' => env('FIFO_REFERENCE_PREFIX', 'TXN'),

    /*
    |--------------------------------------------------------------------------
    | Automatic Reference Generation
    |--------------------------------------------------------------------------
    |
    | When set to true, the system will automatically generate references
    | for transactions that don't have one provided.
    |
    */
    'auto_generate_reference' => env('FIFO_AUTO_GENERATE_REFERENCE', true),

    /*
    |--------------------------------------------------------------------------
    | Product Model
    |--------------------------------------------------------------------------
    |
    | This is the model class used for products. You must configure this to
    | point to your own Product model. The model must have an 'id' column.
    |
    | Example: App\Models\Product::class
    |
    */
    'product_model' => env('FIFO_PRODUCT_MODEL', null),

    /*
    |--------------------------------------------------------------------------
    | Default Product Table Name
    |--------------------------------------------------------------------------
    |
    | This is the table name used for products. You can change this if you
    | need to use a different table name for your products.
    |
    */
    'products_table' => env('FIFO_PRODUCTS_TABLE', 'products'),

    /*
    |--------------------------------------------------------------------------
    | Default Transaction Table Name
    |--------------------------------------------------------------------------
    |
    | This is the table name used for FIFO transactions. You can change this
    | if you need to use a different table name.
    |
    */
    'transactions_table' => env('FIFO_TRANSACTIONS_TABLE', 'fifo_transactions'),
];
