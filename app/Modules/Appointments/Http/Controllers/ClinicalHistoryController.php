<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Controllers;

use App\Modules\Appointments\ClinicalHistoryRecordUnavailable;
use App\Modules\Appointments\ClinicalHistoryService;
use App\Modules\Patients\PatientLinkRequired;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class ClinicalHistoryController
{
    public function __construct(private ClinicalHistoryService $history) {}

    public function getOwnClinicalHistory(Request $request): View
    {
        $enabled = $this->history->portalIsEnabled();
        if (! $enabled) {
            return view('appointments.clinical-history', [
                'enabled' => false,
                'linked' => false,
                'queryFailed' => false,
                'patient' => null,
                'records' => collect(),
            ]);
        }

        try {
            $payload = $this->history->getOwnClinicalHistory(
                (int) $request->attributes->get('identity')->getKey(),
            );

            return view('appointments.clinical-history', [
                'enabled' => true,
                'linked' => true,
                'queryFailed' => false,
                'patient' => $payload['patient'],
                'records' => $payload['records'],
            ]);
        } catch (PatientLinkRequired) {
            return view('appointments.clinical-history', [
                'enabled' => true,
                'linked' => false,
                'queryFailed' => false,
                'patient' => null,
                'records' => collect(),
            ]);
        } catch (Throwable $exception) {
            Log::error('[ClinicalHistoryController - getOwnClinicalHistory] '.$exception::class);

            return view('appointments.clinical-history', [
                'enabled' => true,
                'linked' => true,
                'queryFailed' => true,
                'patient' => null,
                'records' => collect(),
            ]);
        }
    }

    public function getOwnClinicalHistoryRecord(Request $request, int $cita): View
    {
        $enabled = $this->history->portalIsEnabled();
        if (! $enabled) {
            return view('appointments.clinical-history-show', [
                'enabled' => false,
                'linked' => false,
                'queryFailed' => false,
                'unavailable' => false,
                'patient' => null,
                'record' => null,
            ]);
        }

        try {
            $payload = $this->history->getOwnClinicalHistoryRecord(
                (int) $request->attributes->get('identity')->getKey(),
                $cita,
            );

            return view('appointments.clinical-history-show', [
                'enabled' => true,
                'linked' => true,
                'queryFailed' => false,
                'unavailable' => false,
                'patient' => $payload['patient'],
                'record' => $payload['record'],
            ]);
        } catch (PatientLinkRequired) {
            return view('appointments.clinical-history-show', [
                'enabled' => true,
                'linked' => false,
                'queryFailed' => false,
                'unavailable' => false,
                'patient' => null,
                'record' => null,
            ]);
        } catch (ClinicalHistoryRecordUnavailable) {
            return view('appointments.clinical-history-show', [
                'enabled' => true,
                'linked' => true,
                'queryFailed' => false,
                'unavailable' => true,
                'patient' => null,
                'record' => null,
            ]);
        } catch (Throwable $exception) {
            Log::error('[ClinicalHistoryController - getOwnClinicalHistoryRecord] '.$exception::class);

            return view('appointments.clinical-history-show', [
                'enabled' => true,
                'linked' => true,
                'queryFailed' => true,
                'unavailable' => false,
                'patient' => null,
                'record' => null,
            ]);
        }
    }
}
