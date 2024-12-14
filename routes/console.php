<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:traffic-router-maintain')->onOneServer()->withoutOverlapping()->everyMinute();

Schedule::command('app:take-credit')->onOneServer()->withoutOverlapping()->hourly();
Schedule::command('app:turn-off-free-instance')->onOneServer()->withoutOverlapping()->hourly();

Schedule::command('app:user-cleanup')->onOneServer()->withoutOverlapping()->daily();
