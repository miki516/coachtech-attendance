<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        // その日の9:00〜18:00をデフォルトに
        $date = Carbon::today('Asia/Tokyo')->subDays(rand(0, 30))->startOfDay();
        $in   = $date->copy()->setTime(9, 0);
        $out  = $date->copy()->setTime(18, 0);

        return [
            'user_id'   => User::factory(),
            'work_date' => $date->toDateString(),
            'clock_in'  => $in,
            'clock_out' => $out,
        ];
    }

    /** 任意：日付を固定したい時に使えるヘルパ */
    public function forDate(Carbon $date): self
    {
        return $this->state(fn () => ['work_date' => $date->toDateString()]);
    }

    /** 任意：時刻を指定したい時に使えるヘルパ */
    public function withTimes(?Carbon $in, ?Carbon $out): self
    {
        return $this->state(fn () => ['clock_in' => $in, 'clock_out' => $out]);
    }

    /** 任意：ユーザーを指定 */
    public function forUser(User $user): self
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}
