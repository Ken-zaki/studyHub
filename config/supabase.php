<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supabase Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Supabase integration.
    | These values are loaded from your .env file.
    |
    */

    'url' => env('SUPABASE_URL', ''),

    'anon_key' => env('SUPABASE_ANON_KEY', ''),

    'service_key' => env('SUPABASE_SERVICE_KEY', ''),

];
