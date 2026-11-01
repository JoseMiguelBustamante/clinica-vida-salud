<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\AccountNotFound;
use App\Modules\Identity\DuplicateAccountIdentifier;
use App\Modules\Identity\Http\Requests\RegisterPatientAccountRequest;
use App\Modules\Identity\Http\Requests\UpdatePatientProfileRequest;
use App\Modules\Identity\InvalidAccountInput;
use App\Modules\Identity\PatientPortalDisabled;
use App\Modules\Identity\PatientPortalService;
use App\Modules\Identity\PatientProfileNotAllowed;
use App\Modules\Identity\PatientRegistrationDisabled;
use App\Modules\Identity\ReminderPreferenceRejected;
use App\Modules\Identity\RoleNotFound;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class PatientPortalController
{
    public function __construct(private PatientPortalService $portal) {}

    public function showPatientAccountForm(Request $request): View|RedirectResponse
    {
        if ($request->session()->has(Auth::guard('web')->getName())) {
            return redirect()->route('panel');
        }

        return view('identity.portal.register', [
            'enabled' => $this->portal->registrationIsEnabled(),
        ]);
    }

    public function registerPatientAccount(RegisterPatientAccountRequest $request): RedirectResponse
    {
        if ($request->session()->has(Auth::guard('web')->getName())) {
            return redirect()->route('panel');
        }

        $input = $request->validated();
        try {
            $this->portal->registerPatientAccount([
                'nombre_usuario' => $input['nombre_usuario'],
                'correo_acceso' => $input['correo_acceso'] ?? null,
                'password' => $input['password'],
            ]);
        } catch (PatientRegistrationDisabled) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['nombre_usuario' => 'El registro de cuentas no está habilitado.']);
        } catch (DuplicateAccountIdentifier) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['nombre_usuario' => 'El nombre de usuario solicitado ya está utilizado.']);
        } catch (InvalidAccountInput) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['nombre_usuario' => 'Revise los datos de la cuenta, incluida la contraseña.']);
        } catch (RoleNotFound) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['nombre_usuario' => 'No fue posible completar el registro.']);
        } catch (Throwable $exception) {
            Log::error('[PatientPortalController - registerPatientAccount] '.$exception::class);

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['nombre_usuario' => 'No fue posible completar el registro.']);
        }

        return redirect()->route('login')->with(
            'status',
            'La cuenta fue registrada. El acceso a las citas requiere un vínculo aprobado con el registro de paciente.',
        );
    }

    public function showPatientProfile(Request $request): View
    {
        $enabled = $this->portal->portalIsEnabled();
        $profile = [
            'account' => null,
            'preferences' => [
                'recordatorios_habilitados' => false,
                'canal' => 'CorreoElectronico',
                'destino' => null,
                'destino_verificado' => false,
            ],
        ];
        if ($enabled) {
            $profile = $this->portal->getPatientProfile((int) $request->attributes->get('identity')->getKey());
        }

        return view('identity.portal.profile', [
            'enabled' => $enabled,
            'account' => $profile['account'],
            'preferences' => $profile['preferences'],
        ]);
    }

    public function updatePatientProfile(UpdatePatientProfileRequest $request): RedirectResponse
    {
        $input = $request->validated();
        try {
            $this->portal->updatePatientProfile((int) $request->attributes->get('identity')->getKey(), [
                'nombre_usuario' => $input['nombre_usuario'],
                'correo_acceso' => $input['correo_acceso'] ?? null,
                'password' => $input['password'] ?? null,
                'recordatorios_habilitados' => (bool) (int) $input['recordatorios_habilitados'],
            ]);
        } catch (PatientPortalDisabled) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['nombre_usuario' => 'El portal no está habilitado.']);
        } catch (PatientProfileNotAllowed) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['nombre_usuario' => 'Esta operación corresponde a la cuenta de paciente.']);
        } catch (ReminderPreferenceRejected) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['recordatorios_habilitados' => 'Para habilitar recordatorios elija esa opción y use un correo verificado.']);
        } catch (DuplicateAccountIdentifier) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['nombre_usuario' => 'El nombre de usuario o el correo ya está en uso.']);
        } catch (InvalidAccountInput) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['nombre_usuario' => 'Revise los datos de la cuenta, incluida la contraseña.']);
        } catch (AccountNotFound) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['nombre_usuario' => 'No puede realizarse la operación solicitada.']);
        } catch (Throwable $exception) {
            Log::error('[PatientPortalController - updatePatientProfile] '.$exception::class);

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['nombre_usuario' => 'No fue posible guardar los cambios.']);
        }

        return redirect()->route('portal.profile')->with('status', 'El perfil quedó actualizado.');
    }

    public function verifyPatientEmail(Request $request): RedirectResponse
    {
        try {
            $this->portal->verifyPatientEmail((int) $request->attributes->get('identity')->getKey());
        } catch (PatientPortalDisabled) {
            return back()->withErrors(['correo_acceso' => 'El portal no está habilitado.']);
        } catch (PatientProfileNotAllowed) {
            return back()->withErrors(['correo_acceso' => 'Esta operación corresponde a la cuenta de paciente.']);
        } catch (InvalidAccountInput) {
            return back()->withErrors(['correo_acceso' => 'Indique un correo de acceso válido antes de verificarlo.']);
        } catch (AccountNotFound) {
            return back()->withErrors(['correo_acceso' => 'No puede realizarse la operación solicitada.']);
        } catch (Throwable $exception) {
            Log::error('[PatientPortalController - verifyPatientEmail] '.$exception::class);

            return back()->withErrors(['correo_acceso' => 'No fue posible guardar los cambios.']);
        }

        return redirect()->route('portal.profile')->with(
            'status',
            'El correo quedó marcado como verificado. Eso no habilita los recordatorios.',
        );
    }
}
