<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Schedules\ScheduleService;
use Illuminate\Console\Command;
use Throwable;

class EnsurePhysicianSchedules extends Command
{
    protected $signature = 'clinic:ensure-schedules';

    protected $description = 'Create missing Mon–Sat 08:00–18:00 schedules for active physicians';

    protected $help = <<<'HELP'
        Requires an already migrated PostgreSQL database. For each active physician,
        creates an 08:00–18:00 / 30-minute window on any weekday Mon–Sat that still
        has no active schedule. Existing day rows are not changed. Re-run safely.
        No password prompts, arguments that write secrets, or environment changes.
        HELP;

    public function __construct(private ScheduleService $schedules)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $created = $this->schedules->ensureMissingWeekdaySchedulesForActivePhysicians();
        } catch (Throwable) {
            $this->error('Unable to ensure physician schedules; stop and contact the operator.');

            return self::FAILURE;
        }

        if ($created === 0) {
            $this->info('No missing weekday schedules were created.');
        } else {
            $this->info('Created '.$created.' missing weekday schedule(s).');
        }

        return self::SUCCESS;
    }
}
