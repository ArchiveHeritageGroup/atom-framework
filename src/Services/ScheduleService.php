<?php

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

class ScheduleService
{
    public function getSchedules(): array
    {
        try {
            return DB::table('backup_schedule')
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function createSchedule(array $data): int
    {
        return DB::table('backup_schedule')->insertGetId([
            'name' => $data['name'],
            'frequency' => $data['frequency'],
            'time' => $data['time'] ?? '02:00:00',
            'day_of_week' => $data['day_of_week'] ?? null,
            'day_of_month' => $data['day_of_month'] ?? null,
            'include_database' => $data['include_database'] ?? true,
            'include_uploads' => $data['include_uploads'] ?? true,
            'include_plugins' => $data['include_plugins'] ?? true,
            'include_framework' => $data['include_framework'] ?? true,
            'retention_days' => $data['retention_days'] ?? 30,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function deleteSchedule(int $id): bool
    {
        return DB::table('backup_schedule')->where('id', $id)->delete() > 0;
    }

    public function toggleSchedule(int $id): bool
    {
        $schedule = DB::table('backup_schedule')->where('id', $id)->first();
        if (!$schedule) {
            return false;
        }
        
        DB::table('backup_schedule')->where('id', $id)->update([
            'is_active' => !$schedule->is_active,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        
        return true;
    }
}
