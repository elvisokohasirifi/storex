<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disallow deleting logs from the user interface.
    |--------------------------------------------------------------------------
    */

    'allow_delete' => env('BACKPACK_LOGMANAGER_ALLOW_DELETE', false),

    // when false the `backpack.base.default_date_format` will be used.
    // alternatively provide your custom format
    'date_format' => false,
];
