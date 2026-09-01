<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::command('reservations:expire-alternative-offers')->hourly();
Schedule::command('rooms:reconcile-availability')->dailyAt('00:05');
Schedule::command('reservations:report-stale-checkins')->dailyAt('00:20');
Schedule::call(fn () => DB::table('guest_password_reset_tokens')
	->where('created_at', '<', now()->subDay())
	->delete())->dailyAt('00:15');
