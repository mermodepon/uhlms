<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reservations:expire-alternative-offers')->hourly();
