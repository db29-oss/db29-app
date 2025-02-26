<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:machine-ipaddress-update')->onOneServer()->withoutOverlapping(10)->everyMinute();

Schedule::command('app:filesystem-monitor')->onOneServer()->withoutOverlapping(10)->hourly();

Schedule::command('app:take-credit')->onOneServer()->withoutOverlapping(10)->hourly();
Schedule::command('app:turn-off-free-instance')->onOneServer()->withoutOverlapping(10)->hourly();

Schedule::command('app:instance-cleanup')->onOneServer()->withoutOverlapping(10)->daily();
Schedule::command('app:user-cleanup')->onOneServer()->withoutOverlapping(10)->daily();
