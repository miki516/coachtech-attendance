<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Support\Carbon;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('role', 'user')->first();
        if (!$user) return;

        // 基準はJSTの今月1日
        $base = Carbon::now('Asia/Tokyo')->startOfMonth();

        // 先月と先々月だけ作る
        for ($m = 1; $m <= 2; $m++) {
            $month = $base->copy()->subMonths($m);

            // 1〜10日分
            for ($i = 0; $i < 10; $i++) {
                $date = $month->copy()->addDays($i);

                $attendance = Attendance::create([
                    'user_id'   => $user->id,
                    'work_date' => $date->toDateString(),
                    'clock_in'  => $date->copy()->setTime(9, 0),
                    'clock_out' => $date->copy()->setTime(18, 0),
                ]);

                if ($i % 2 === 0) {
                    BreakTime::create([
                        'attendance_id' => $attendance->id,
                        'break_start'   => $date->copy()->setTime(12, 0),
                        'break_end'     => $date->copy()->setTime(13, 0),
                    ]);
                } else {
                    BreakTime::create([
                        'attendance_id' => $attendance->id,
                        'break_start'   => $date->copy()->setTime(12, 0),
                        'break_end'     => $date->copy()->setTime(12, 30),
                    ]);
                    BreakTime::create([
                        'attendance_id' => $attendance->id,
                        'break_start'   => $date->copy()->setTime(15, 0),
                        'break_end'     => $date->copy()->setTime(15, 15),
                    ]);
                }
            }
        }
    }
}
