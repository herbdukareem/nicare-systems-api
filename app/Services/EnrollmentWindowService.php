<?php

namespace App\Services;

use App\Exceptions\EnrollmentWindowClosedException;
use App\Models\Configuration;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class EnrollmentWindowService
{
    public const KEY_PREFIX = 'ENROLLMENT_WINDOW_';
    public const DEFAULT_TIMEZONE = 'Africa/Lagos';

    /**
     * @var array<int, string>
     */
    public const DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $enabled = $this->toBoolean(
            Configuration::getValue(self::KEY_PREFIX . 'ENABLED', false)
        );
        $timezone = trim((string) Configuration::getValue(self::KEY_PREFIX . 'TIMEZONE', self::DEFAULT_TIMEZONE));

        return [
            'enabled' => $enabled,
            'timezone' => $timezone !== '' ? $timezone : self::DEFAULT_TIMEZONE,
            'schedule' => $this->normalizeSchedule(
                $this->decodeSchedule(Configuration::getValue(self::KEY_PREFIX . 'SCHEDULE', null))
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function save(array $attributes): array
    {
        $settings = [
            'enabled' => (bool) ($attributes['enabled'] ?? false),
            'timezone' => trim((string) ($attributes['timezone'] ?? self::DEFAULT_TIMEZONE)) ?: self::DEFAULT_TIMEZONE,
            'schedule' => $this->normalizeSchedule((array) ($attributes['schedule'] ?? [])),
        ];

        Configuration::setValue(
            self::KEY_PREFIX . 'ENABLED',
            $settings['enabled'] ? '1' : '0',
            'Whether the daily mobile enrollment time window is enforced.'
        );
        Configuration::setValue(
            self::KEY_PREFIX . 'TIMEZONE',
            $settings['timezone'],
            'Timezone used to evaluate the daily mobile enrollment time window.'
        );
        Configuration::setValue(
            self::KEY_PREFIX . 'SCHEDULE',
            json_encode($settings['schedule'], JSON_THROW_ON_ERROR),
            'Daily mobile enrollment opening and closing times by weekday.'
        );

        return $this->currentState();
    }

    /**
     * @return array<string, mixed>
     */
    public function currentState(?CarbonInterface $now = null): array
    {
        $settings = $this->getSettings();
        $timezone = (string) $settings['timezone'];
        $current = $now
            ? Carbon::parse($now->toIso8601String(), $now->getTimezone())->setTimezone($timezone)
            : now($timezone);

        $dayKey = strtolower($current->englishDayOfWeek);
        $dayWindow = $settings['schedule'][$dayKey] ?? $this->defaultDayWindow();
        $currentTime = $current->format('H:i');
        $isOpenForToday = (bool) $dayWindow['enabled']
            && $currentTime >= $dayWindow['start_time']
            && $currentTime < $dayWindow['end_time'];

        $status = !$settings['enabled']
            ? 'not_enforced'
            : (!$dayWindow['enabled']
                ? 'closed_day'
                : ($currentTime < $dayWindow['start_time']
                    ? 'before_start'
                    : ($currentTime >= $dayWindow['end_time'] ? 'after_end' : 'open')));

        $nextOpenAt = $settings['enabled'] && $status !== 'open'
            ? $this->nextOpenAt($settings['schedule'], $current)
            : null;

        $closesAt = $status === 'open'
            ? $current->copy()->setTimeFromTimeString((string) $dayWindow['end_time'])->toIso8601String()
            : null;

        return [
            ...$settings,
            'server_time' => $current->toIso8601String(),
            'is_open' => !$settings['enabled'] || $isOpenForToday,
            'status' => $status,
            'today' => [
                'key' => $dayKey,
                'label' => ucfirst($dayKey),
                'enabled' => (bool) $dayWindow['enabled'],
                'start_time' => $dayWindow['start_time'],
                'end_time' => $dayWindow['end_time'],
            ],
            'window_label' => (bool) $dayWindow['enabled']
                ? "{$dayWindow['start_time']} - {$dayWindow['end_time']}"
                : 'Closed',
            'next_open_at' => $nextOpenAt?->toIso8601String(),
            'closes_at' => $closesAt,
            'message' => $this->messageForStatus($status, $dayWindow, $nextOpenAt),
        ];
    }

    public function assertOpen(?CarbonInterface $now = null): void
    {
        $state = $this->currentState($now);

        if (($state['enabled'] ?? false) && !($state['is_open'] ?? false)) {
            throw new EnrollmentWindowClosedException(
                (string) ($state['message'] ?? 'Enrollment is currently closed.'),
                $state
            );
        }
    }

    /**
     * @param  mixed  $raw
     * @return array<string, mixed>
     */
    private function decodeSchedule(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array<string, array<string, mixed>>
     */
    private function normalizeSchedule(array $schedule): array
    {
        $normalized = [];

        foreach (self::DAYS as $day) {
            $normalized[$day] = $this->normalizeDayWindow((array) ($schedule[$day] ?? []));
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $dayWindow
     * @return array<string, mixed>
     */
    private function normalizeDayWindow(array $dayWindow = []): array
    {
        return [
            'enabled' => array_key_exists('enabled', $dayWindow)
                ? (bool) $dayWindow['enabled']
                : true,
            'start_time' => $this->normalizeTimeValue($dayWindow['start_time'] ?? '08:00'),
            'end_time' => $this->normalizeTimeValue($dayWindow['end_time'] ?? '17:00'),
        ];
    }

    private function normalizeTimeValue(mixed $value): string
    {
        $time = trim((string) $value);

        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time;
        }

        return '08:00';
    }

    /**
     * @param  array<string, array<string, mixed>>  $schedule
     */
    private function nextOpenAt(array $schedule, CarbonInterface $current): ?Carbon
    {
        for ($offset = 0; $offset < 8; $offset++) {
            $candidateDate = $current->copy()->addDays($offset);
            $candidateDayKey = strtolower($candidateDate->englishDayOfWeek);
            $candidateWindow = $schedule[$candidateDayKey] ?? $this->defaultDayWindow();

            if (!($candidateWindow['enabled'] ?? false)) {
                continue;
            }

            $candidateStart = $candidateDate->copy()->setTimeFromTimeString((string) $candidateWindow['start_time']);

            if ($offset === 0 && $current->gte($candidateStart)) {
                continue;
            }

            return $candidateStart;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $dayWindow
     */
    private function messageForStatus(string $status, array $dayWindow, ?CarbonInterface $nextOpenAt): string
    {
        return match ($status) {
            'not_enforced' => 'Daily enrollment time restriction is currently disabled.',
            'open' => sprintf('Enrollment is open until %s today.', $dayWindow['end_time']),
            'before_start' => sprintf('Enrollment opens today at %s.', $dayWindow['start_time']),
            'after_end' => $nextOpenAt
                ? sprintf(
                    'Enrollment closed at %s. Next window opens %s.',
                    $dayWindow['end_time'],
                    $this->formatWindowDateTime($nextOpenAt)
                )
                : sprintf('Enrollment closed at %s. No future opening day is configured.', $dayWindow['end_time']),
            default => $nextOpenAt
                ? sprintf('Enrollment is closed today. Next window opens %s.', $this->formatWindowDateTime($nextOpenAt))
                : 'Enrollment is closed today and no future opening day is configured.',
        };
    }

    private function formatWindowDateTime(CarbonInterface $dateTime): string
    {
        return $dateTime->format('d M Y, H:i');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultDayWindow(): array
    {
        return [
            'enabled' => true,
            'start_time' => '08:00',
            'end_time' => '17:00',
        ];
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
