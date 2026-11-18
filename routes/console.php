<?php

use Illuminate\Support\Facades\Schedule;

// Automatic daily database backup at 2:00 AM. Keeps the 14 most recent scheduled backups.
Schedule::command('supershop:backup --type=scheduled')
    ->dailyAt('02:00')
    ->withoutOverlapping();
