<?php

use Illuminate\Support\Facades\Schedule;

// Automatic bill fetching. Needs this cron entry on the server:
//   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('bills:fetch-due')->everyTenMinutes()->withoutOverlapping();
