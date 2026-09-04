<?php
// routes/console.php

use Illuminate\Support\Facades\Schedule;

// ============================================================
// PUBLICATION AUTOMATIQUE DES ARTICLES PROGRAMMÉS
// ============================================================
Schedule::command('articles:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

// ============================================================
// PUBLICATION AUTOMATIQUE DES MÉDIAS PROGRAMMÉS
// ============================================================
Schedule::command('media:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();