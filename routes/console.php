<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('report:daily-calls')->dailyAt('21:00');
Schedule::command('recordings:clean-old')->dailyAt('23:00');

// Binotel задачи
Schedule::command('binotel:fetch-calls')->everyMinute();
Schedule::command('binotel:fetch-recordings')->everyMinute();

// Zadarma задачи
Schedule::command('zadarma:fetch-calls')->everyMinute()->withoutOverlapping();

// Phonet задачи
Schedule::command('phonet:fetch-calls')->everyMinute()->withoutOverlapping();

// Unitalk задачи
Schedule::command('unitalk:fetch-calls')->everyMinute()->withoutOverlapping();

//Выставление счета на оплату
Schedule::command('payment-request:integration-all')
    ->monthlyOn(1, '09:00')
    ->timezone('Europe/Moscow');
