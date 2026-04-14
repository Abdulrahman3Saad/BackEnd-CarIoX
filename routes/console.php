<?php

use Illuminate\Support\Facades\Schedule;

// ✅ Scheduled Job — بدل الـ autoExpire endpoint المكشوف
// بيشتغل كل يوم الساعة 2 الصبح تلقائياً
Schedule::command('orders:auto-expire')->dailyAt('02:00');
