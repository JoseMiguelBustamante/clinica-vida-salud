<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Appointments\AppointmentReminderService;
use Illuminate\Console\Command;
use Throwable;

class SendAppointmentReminders extends Command
{
    protected $signature = 'clinic:send-reminders';

    protected $description = 'Send due appointment reminders without changing the appointment status';

    protected $help = <<<'HELP'
        Requires an already migrated PostgreSQL database. The task scheduler is the actor:
        it processes recordatorio_cita rows whose planned time has arrived, checks that the
        appointment is still current, that the start remains in the future and that the
        patient still has verified, enabled reminder preferences, then requests send from
        the mail provider. Provider acceptance is not proof of delivery or that the patient
        read the message. Failed, unknown or already accepted notices are not retried
        automatically. The appointment status is not changed. Confirmation, arrival and
        medical care are not recorded. No password prompts, arguments, defaults or
        environment writes. On any error, stop and contact the operator.
        HELP;

    public function __construct(private AppointmentReminderService $reminders)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $processed = $this->reminders->sendDueReminders();
        } catch (Throwable) {
            $this->error('Unable to send appointment reminders; stop and contact the operator.');

            return self::FAILURE;
        }

        if ($processed === 0) {
            $this->info('No reminders were due.');
        } else {
            $this->info('Processed '.$processed.' reminder(s). Sending does not confirm the appointment.');
        }

        return self::SUCCESS;
    }
}
