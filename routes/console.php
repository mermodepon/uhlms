<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reservations:expire-alternative-offers')->hourly();
Schedule::command('rooms:reconcile-availability')->dailyAt('00:05');
