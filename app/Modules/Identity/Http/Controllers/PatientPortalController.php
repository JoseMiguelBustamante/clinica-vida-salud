<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\DuplicateAccountIdentifier;
use App\Modules\Identity\Http\Requests\RegisterPatientAccountRequest;
use App\Modules\Identity\InvalidAccountInput;
use App\Modules\Identity\PatientPortalService;
use App\Modules\Identity\PatientRegistrationDisabled;
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
}
