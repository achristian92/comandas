<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Socket Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout in seconds for socket connections to the printer.
    | Increase this value if you have a slow network or distant printers.
    |
    */

    'socket_timeout' => env('PRINTER_SOCKET_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic retry behavior when printer is offline.
    |
    */

    'max_attempts' => env('PRINTER_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Connectivity Check
    |--------------------------------------------------------------------------
    |
    | Enable/disable connectivity check before attempting to print.
    | When enabled, the system will verify the printer is reachable
    | before sending the print job.
    |
    */

    'check_connectivity' => env('PRINTER_CHECK_CONNECTIVITY', true),

];
