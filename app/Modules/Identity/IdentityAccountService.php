<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

final class IdentityAccountService
{
    public function __construct(private IdentityRepository $identities) {}

    public function getAllAccounts(?string $fragment): Collection
    {
        return $this->identities->getAllAccounts($fragment === '' ? null : $fragment);
    }

    public function getAllRoles(): Collection
    {
        return $this->identities->getAllRoles();
    }

    public function getAccountById(int $id): object
    {
        $account = $this->identities->getAccountById($id);
        if ($account === null) {
            throw new AccountNotFound;
        }

        return $account;
    }

    /**
     * @param  array{nombre_usuario: string, correo_acceso?: ?string, password: string, id_rol: int, activo?: bool}  $input
     */
    public function createAccount(array $input): int
    {
        $username = $input['nombre_usuario'];
        $email = $this->normalizedEmail($input['correo_acceso'] ?? null);
        $password = $input['password'];
        $this->assertIdentifier($username, 100, false);
        $this->assertIdentifier($email, 255, true);
        $this->assertPassword($password);
        $this->assertUnique($username, $email, null);
        $roleId = (int) $input['id_rol'];
        $this->assertRole($roleId);

        return $this->identities->createAccount(
            $username,
            $email,
            Hash::make($password),
            array_key_exists('activo', $input) ? (bool) $input['activo'] : true,
            $roleId,
        );
    }

    /**
     * @param  array{nombre_usuario: string, correo_acceso?: ?string, password?: ?string, id_rol: int, activo: bool}  $input
     */
    public function updateAccount(int $id, array $input, int $actorId): void
    {
        $account = $this->getAccountById($id);
        $username = $input['nombre_usuario'];
        $email = $this->normalizedEmail($input['correo_acceso'] ?? null);
        $this->assertIdentifier($username, 100, false);
        $this->assertIdentifier($email, 255, true);
        $this->assertUnique($username, $email, $id);
        $roleId = (int) $input['id_rol'];
        $this->assertRole($roleId);
        $active = (bool) $input['activo'];
        if ($id === $actorId && ((int) $account->id_rol !== $roleId || (bool) $account->activo !== $active)) {
            throw new ProtectedAccountChange;
        }

        $values = [
            'nombre_usuario' => $username,
            'correo_acceso' => $email,
            'activo' => $active,
            'id_rol' => $roleId,
        ];
        $password = $input['password'] ?? null;
        if ($password !== null && $password !== '') {
            $this->assertPassword($password);
            $values['contrasena_hash'] = Hash::make($password);
        }

        $this->identities->updateAccount($id, $values);
    }

    public function deactivateAccount(int $id, int $actorId): void
    {
        $this->getAccountById($id);
        if ($id === $actorId) {
            throw new ProtectedAccountChange;
        }

        $this->identities->setAccountActive($id, false);
    }

    public function activateAccount(int $id): void
    {
        $this->getAccountById($id);
        $this->identities->setAccountActive($id, true);
    }

    private function normalizedEmail(?string $email): ?string
    {
        return $email === null || $email === '' ? null : $email;
    }

    private function assertIdentifier(?string $value, int $maximum, bool $optional): void
    {
        if ($value === null || $value === '') {
            if (! $optional) {
                throw new InvalidAccountInput;
            }

            return;
        }

        if (! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $maximum
            || preg_match('/\p{C}|\A\s|\s\z/u', $value) !== 0) {
            throw new InvalidAccountInput;
        }

        if ($optional && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidAccountInput;
        }
    }

    private function assertPassword(string $password): void
    {
        if (! mb_check_encoding($password, 'UTF-8') || mb_strlen($password) < 12 || strlen($password) > 72
            || preg_match('/\p{C}/u', $password) !== 0) {
            throw new InvalidAccountInput;
        }
    }

    private function assertUnique(string $username, ?string $email, ?int $exceptId): void
    {
        if ($this->identities->identifierTaken($username, $exceptId)
            || ($email !== null && $this->identities->identifierTaken($email, $exceptId))) {
            throw new DuplicateAccountIdentifier;
        }
    }

    private function assertRole(int $roleId): void
    {
        if ($this->identities->getRoleById($roleId) === null) {
            throw new RoleNotFound;
        }
    }
}
