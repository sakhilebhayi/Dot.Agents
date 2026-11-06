<?php

namespace App\Services\Triggers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/** Works out when a schedule next falls, in the business's own time zone, so daylight saving is the calendar's problem. */
final class ScheduleClock
{
    public const FREQUENCIES = ['once' => 'Once', 'daily' => 'Every day', 'weekdays' => 'Every weekday', 'weekly' => 'Every week', 'monthly' => 'Every month'];

    public const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /** @param  array<string, mixed>  $settings */
    public static function next(array $settings, ?Carbon $after = null): ?Carbon
    {
        $zone = (string) ($settings['timezone'] ?? 'UTC');
        $now = CarbonImmutable::instance($after ?? now())->setTimezone($zone);
        [$hour, $minute] = array_map('intval', explode(':', (string) ($settings['time'] ?? '08:00')));
        $start = filled($settings['start_date'] ?? null) ? CarbonImmutable::parse($settings['start_date'], $zone)->startOfDay() : null;
        $from = $start !== null && $start->greaterThan($now) ? $start->subMinute() : $now;

        $next = match ($settings['frequency'] ?? 'daily') {
            'once' => self::once($settings, $zone, $hour, $minute, $now),
            'weekdays' => self::firstDay($from, $hour, $minute, fn (CarbonImmutable $day) => $day->isWeekday()),
            'weekly' => self::firstDay($from, $hour, $minute, fn (CarbonImmutable $day) => $day->dayOfWeek === (int) ($settings['weekday'] ?? 1)),
            'monthly' => self::firstDay($from, $hour, $minute, fn (CarbonImmutable $day) => $day->day === min((int) ($settings['monthday'] ?? 1), $day->daysInMonth)),
            default => self::firstDay($from, $hour, $minute, fn () => true),
        };

        if ($next === null || (filled($settings['end_date'] ?? null) && $next->greaterThan(CarbonImmutable::parse($settings['end_date'], $zone)->endOfDay()))) {
            return null;
        }

        return Carbon::instance($next->setTimezone('UTC'));
    }

    /** @param  array<string, mixed>  $settings */
    private static function once(array $settings, string $zone, int $hour, int $minute, CarbonImmutable $now): ?CarbonImmutable
    {
        $when = CarbonImmutable::parse($settings['start_date'] ?? $now->toDateString(), $zone)->setTime($hour, $minute);

        return $when->greaterThan($now) ? $when : null;
    }

    private static function firstDay(CarbonImmutable $from, int $hour, int $minute, callable $fits): ?CarbonImmutable
    {
        for ($offset = 0; $offset < 400; $offset++) {
            $day = $from->startOfDay()->addDays($offset);
            $when = $day->setTime($hour, $minute);

            if ($when->greaterThan($from) && $fits($day)) {
                return $when;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $settings */
    public static function words(array $settings): string
    {
        $time = (string) ($settings['time'] ?? '08:00');

        return match ($settings['frequency'] ?? 'daily') {
            'once' => "Once, on {$settings['start_date']} at {$time}",
            'weekdays' => "Weekdays at {$time}",
            'weekly' => 'Every '.(self::DAYS[(int) ($settings['weekday'] ?? 1)])." at {$time}",
            'monthly' => 'On day '.(int) ($settings['monthday'] ?? 1)." of each month at {$time}",
            default => "Every day at {$time}",
        }.' ('.($settings['timezone'] ?? 'UTC').')';
    }
}
