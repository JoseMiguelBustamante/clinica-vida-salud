<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Appointments\AppointmentRepository;
use App\Modules\MedicalStaff\PhysicianRepository;
use App\Modules\MedicalStaff\SpecialtyRepository;
use App\Modules\Patients\PatientRepository;
use App\Modules\Schedules\ScheduleRepository;
use Illuminate\Support\Carbon;

final class PanelDashboardService
{
    public function __construct(
        private PatientRepository $patients,
        private PhysicianRepository $physicians,
        private SpecialtyRepository $specialties,
        private ScheduleRepository $schedules,
        private AppointmentRepository $appointments,
        private IdentityRepository $identities,
        private ActivityLogRepository $activityLogs,
    ) {}

    /**
     * @param  array{id: int, username: string, email: ?string, role: array{id: int, name: string}, permissions: list<string>}  $user
     * @return list<array{label: string, value: int, hint: string, href: ?string}>
     */
    public function indicatorsFor(array $user): array
    {
        $codes = $user['permissions'];
        $has = static fn (string $code): bool => in_array($code, $codes, true);
        $today = Carbon::now('UTC')->toDateString();
        $indicators = [];

        if ($has(PermissionCatalog::CONSULT_AGENDA) || $has(PermissionCatalog::MARK_ATTENDED)
            || $has(PermissionCatalog::BOOK_APPOINTMENTS) || $has(PermissionCatalog::CONSULT_AVAILABILITY)
            || $has(PermissionCatalog::RESCHEDULE_APPOINTMENTS)) {
            $physicianId = null;
            $restrictedToOwnAgenda = ($has(PermissionCatalog::CONSULT_AGENDA) || $has(PermissionCatalog::MARK_ATTENDED))
                && ! $has(PermissionCatalog::BOOK_APPOINTMENTS)
                && ! $has(PermissionCatalog::RESCHEDULE_APPOINTMENTS)
                && ! $has(PermissionCatalog::MANAGE_ACCOUNTS);

            if ($restrictedToOwnAgenda) {
                $physician = $this->appointments->getPhysicianByUserId((int) $user['id']);
                $physicianId = $physician !== null ? (int) $physician->id_medico : null;
            }

            if (! $restrictedToOwnAgenda || $physicianId !== null) {
                $indicators[] = [
                    'label' => 'Citas de hoy',
                    'value' => $this->appointments->countAppointmentsOnDate(
                        $today,
                        $physicianId,
                        ['Programada', 'Confirmada', 'Atendida', 'Inasistencia'],
                    ),
                    'hint' => $restrictedToOwnAgenda
                        ? 'Agenda del día para este médico'
                        : 'Citas programadas o atendidas hoy',
                    'href' => $has(PermissionCatalog::CONSULT_AGENDA) || $has(PermissionCatalog::MARK_ATTENDED)
                        ? route('appointments.agenda')
                        : ($has(PermissionCatalog::RESCHEDULE_APPOINTMENTS)
                            ? route('appointments.index')
                            : route('availability.consult')),
                ];
            }
        }

        if ($has(PermissionCatalog::MANAGE_PATIENTS)) {
            $indicators[] = [
                'label' => 'Pacientes activos',
                'value' => $this->patients->countActivePatients(),
                'hint' => 'Registros habilitados para atención',
                'href' => route('patients.index'),
            ];
        }

        if ($has(PermissionCatalog::MANAGE_PHYSICIANS)) {
            $indicators[] = [
                'label' => 'Médicos activos',
                'value' => $this->physicians->countActivePhysicians(),
                'hint' => 'Profesionales disponibles en el catálogo',
                'href' => route('physicians.index'),
            ];
        }

        if ($has(PermissionCatalog::MANAGE_ACCOUNTS)) {
            $indicators[] = [
                'label' => 'Cuentas activas',
                'value' => $this->identities->countActiveAccounts(),
                'hint' => 'Usuarios habilitados para iniciar sesión',
                'href' => route('accounts.index'),
            ];
        }

        if ($has(PermissionCatalog::MANAGE_SPECIALTIES)) {
            $indicators[] = [
                'label' => 'Especialidades',
                'value' => $this->specialties->countActiveSpecialties(),
                'hint' => 'Especialidades clínicas activas',
                'href' => route('specialties.index'),
            ];
        }

        if ($has(PermissionCatalog::MANAGE_SCHEDULES)) {
            $indicators[] = [
                'label' => 'Horarios activos',
                'value' => $this->schedules->countActiveSchedules(),
                'hint' => 'Franjas semanales vigentes',
                'href' => route('schedules.index'),
            ];
        }

        if ($has(PermissionCatalog::CONSULT_ACTIVITY_LOG)) {
            $indicators[] = [
                'label' => 'Bitácora (7 días)',
                'value' => $this->activityLogs->countActivityLogs(
                    null,
                    null,
                    null,
                    Carbon::now('UTC')->startOfDay()->subDays(6)->toDateString(),
                    $today,
                ),
                'hint' => 'Movimientos registrados en la última semana',
                'href' => route('activity-log.index'),
            ];
        }

        if ($has(PermissionCatalog::CONSULT_CLINICAL_HISTORY) || $has(PermissionCatalog::UPDATE_PROFILE)) {
            $indicators[] = [
                'label' => 'Próximas citas',
                'value' => $this->appointments->countUpcomingAppointmentsForPatientAccount((int) $user['id']),
                'hint' => 'Citas propias programadas o confirmadas',
                'href' => $has(PermissionCatalog::CONSULT_CLINICAL_HISTORY)
                    ? route('appointments.clinical-history')
                    : route('portal.profile'),
            ];
        }

        return array_slice($indicators, 0, 4);
    }
}
