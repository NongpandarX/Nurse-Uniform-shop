<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduler — ร้านชุดพยาบาล มินลดา
|--------------------------------------------------------------------------
| §8 Scheduler
*/

// push สรุปของใกล้หมดทุกสาขาให้ admin ตอน 09:00
Schedule::command('stock:low-digest')->dailyAt('09:00');

// ถึง close_time - 30 น. และยังไม่ส่งนับ → เตือนพนักงานสาขานั้น
Schedule::command('stock:count-reminder')->everyTenMinutes();

// เลย close_time + 30 น. ยังไม่แจ้งปิด → เตือนพนักงาน + admin
Schedule::command('closing:reminder')->everyTenMinutes();

// 00:10 ตีขาดงานเมื่อสาขาเปิดแต่พนักงานไม่มีเช็คชื่อ
Schedule::command('attendance:mark-absent')->dailyAt('00:10');

// 02:00 ลบรูปหมดอายุ (ยกเว้น is_pinned)
Schedule::command('media:purge')->dailyAt('02:00');
