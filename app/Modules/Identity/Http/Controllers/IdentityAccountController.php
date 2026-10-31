<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\User;
use App\Modules\Identity\AccountNotFound;
use App\Modules\Identity\DuplicateAccountIdentifier;
use App\Modules\Identity\Http\Requests\SearchAccountsRequest;
use App\Modules\Identity\Http\Requests\StoreAccountRequest;
use App\Modules\Identity\Http\Requests\UpdateAccountRequest;
use App\Modules\Identity\IdentityAccountService;
use App\Modules\Identity\InvalidAccountInput;
use App\Modules\Identity\ProtectedAccountChange;
use App\Modules\Identity\RoleNotFound;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class IdentityAccountController
{
    public function __construct(private IdentityAccountService $accounts) {}

    public function getAllAccounts(SearchAccountsRequest $request): View
    {
        $input = $request->validated();

        return view('identity.accounts.index', [
            'accounts' => $this->accounts->getAllAccounts($input['q'] ?? null),
            'q' => $input['q'] ?? '',
        ]);
    }

    public function showCreateAccountForm(): View
    {
        return view('identity.accounts.create', [
            'roles' => $this->accounts->getAllRoles(),
        ]);
    }

    public function createAccount(StoreAccountRequest $request): RedirectResponse
    {
        try {
            $this->accounts->createAccount($this->accountInput($request->validated()));
        } catch (DuplicateAccountIdentifier) {
            return back()->withInput($request->except('password'))
                ->withErrors(['nombre_usuario' => 'El nombre de usuario o el correo ya está en uso.']);
        } catch (RoleNotFound) {
            return back()->withInput($request->except('password'))
                ->withErrors(['id_rol' => 'El rol seleccionado no es válido.']);
        } catch (InvalidAccountInput) {
            return back()->withInput($request->except('password'))
                ->withErrors(['nombre_usuario' => 'Revise los datos de la cuenta, incluida la contraseña.']);
        } catch (Throwable $exception) {
            Log::error('[IdentityAccountController - createAccount] '.$exception::class);

            return back()->withInput($request->except('password'))
                ->withErrors(['nombre_usuario' => 'No fue posible guardar la cuenta.']);
        }

        return redirect()->route('accounts.index')->with('status', 'La cuenta quedó registrada.');
    }

    public function getAccountById(int $usuario): View|RedirectResponse
    {
        try {
            return view('identity.accounts.show', [
                'account' => $this->accounts->getAccountById($usuario),
            ]);
        } catch (AccountNotFound) {
            return redirect()->route('accounts.index')
                ->withErrors(['nombre_usuario' => 'No puede realizarse la operación solicitada.']);
        }
    }

    public function showEditAccountForm(int $usuario): View|RedirectResponse
    {
        try {
            return view('identity.accounts.edit', [
                'account' => $this->accounts->getAccountById($usuario),
                'roles' => $this->accounts->getAllRoles(),
            ]);
        } catch (AccountNotFound) {
            return redirect()->route('accounts.index')
                ->withErrors(['nombre_usuario' => 'No puede realizarse la operación solicitada.']);
        }
    }

    public function updateAccount(UpdateAccountRequest $request, int $usuario): RedirectResponse
    {
        try {
            $this->accounts->updateAccount($usuario, $this->accountInput($request->validated()), $this->actorId($request));
        } catch (AccountNotFound) {
            return redirect()->route('accounts.index')->withErrors(['nombre_usuario' => 'No puede realizarse la operación solicitada.']);
        } catch (DuplicateAccountIdentifier) {
            return back()->withInput($request->except('password'))
                ->withErrors(['nombre_usuario' => 'El nombre de usuario o el correo ya está en uso.']);
        } catch (RoleNotFound) {
            return back()->withInput($request->except('password'))
                ->withErrors(['id_rol' => 'El rol seleccionado no es válido.']);
        } catch (ProtectedAccountChange) {
            return back()->withInput($request->except('password'))
                ->withErrors(['id_rol' => 'No puede cambiar el rol ni el estado de su propia cuenta.']);
        } catch (InvalidAccountInput) {
            return back()->withInput($request->except('password'))
                ->withErrors(['nombre_usuario' => 'Revise los datos de la cuenta, incluida la contraseña.']);
        } catch (Throwable $exception) {
            Log::error('[IdentityAccountController - updateAccount] '.$exception::class);

            return back()->withInput($request->except('password'))
                ->withErrors(['nombre_usuario' => 'No fue posible guardar los cambios.']);
        }

        return redirect()->route('accounts.show', $usuario)->with('status', 'La cuenta quedó actualizada.');
    }

    public function deactivateAccount(Request $request, int $usuario): RedirectResponse
    {
        try {
            $this->accounts->deactivateAccount($usuario, $this->actorId($request));
        } catch (AccountNotFound) {
            return redirect()->route('accounts.index')->withErrors(['nombre_usuario' => 'No puede realizarse la operación solicitada.']);
        } catch (ProtectedAccountChange) {
            return back()->withErrors(['nombre_usuario' => 'No puede desactivar su propia cuenta.']);
        } catch (Throwable $exception) {
            Log::error('[IdentityAccountController - deactivateAccount] '.$exception::class);

            return back()->withErrors(['nombre_usuario' => 'No fue posible cambiar el estado.']);
        }

        return redirect()->route('accounts.show', $usuario)->with('status', 'La cuenta quedó inactiva.');
    }

    public function activateAccount(int $usuario): RedirectResponse
    {
        try {
            $this->accounts->activateAccount($usuario);
        } catch (AccountNotFound) {
            return redirect()->route('accounts.index')->withErrors(['nombre_usuario' => 'No puede realizarse la operación solicitada.']);
        } catch (Throwable $exception) {
            Log::error('[IdentityAccountController - activateAccount] '.$exception::class);

            return back()->withErrors(['nombre_usuario' => 'No fue posible cambiar el estado.']);
        }

        return redirect()->route('accounts.show', $usuario)->with('status', 'La cuenta quedó activa.');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{nombre_usuario: string, correo_acceso: ?string, password?: string, id_rol: int, activo: bool}
     */
    private function accountInput(array $input): array
    {
        $payload = [
            'nombre_usuario' => $input['nombre_usuario'],
            'correo_acceso' => $input['correo_acceso'] ?? null,
            'id_rol' => (int) $input['id_rol'],
            'activo' => (bool) (int) $input['activo'],
        ];
        if (array_key_exists('password', $input)) {
            $payload['password'] = $input['password'];
        }

        return $payload;
    }

    private function actorId(Request $request): int
    {
        /** @var User $actor */
        $actor = $request->attributes->get('identity');

        return (int) $actor->getKey();
    }
}
