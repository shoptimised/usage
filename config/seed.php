<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Test User
    |--------------------------------------------------------------------------
    |
    | Credentials for the first user created by the database seeder, used to
    | log into local/staging environments. Set these in your ".env" file.
    |
    */

    'test_user' => [
        'email' => env('TEST_USER_EMAIL'),
        'password' => env('TEST_USER_PASSWORD'),
    ],

];
