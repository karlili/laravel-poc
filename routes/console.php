<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('activitylog:clean --force')->daily();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
