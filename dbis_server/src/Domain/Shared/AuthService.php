<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Shared\Entities\User as User;
use App\Domain\Shared\Entities\PrivilegeType;
use App\Domain\Shared\Entities\PrivilegeAddon;

/**
 * AuthService
 *
 * Service for managing authentication and account data.
 *
 */
interface AuthService
{
    /**
     *
     * @param string $login E-Mail or Nickname
     * @param string $password
     * @return bool
     */
    public function login(string $login, string $password): bool;

    public function logout(): void;
    /**
     * @return bool Whether the session user is successfully authenticated
     */
    public function isSessionAuthenticated(): bool;
    public function getAuthenticatedUser(): ?User;
    public function setAuthenticatedUser(User $user): void;
    public function hasAuthenticatedUserRole(string $roleKey): bool;
    /**
     *
     * @param array $query
     *  - q: query string
    * @return User[]
     */
    public function getUsers(array $query = []): array;
    public function getUserById(string $id): ?User;
    public function persistUser(User $user): void;

    /**
     *
     * @return PrivilegeType[]
     */
    public function getPrivilegeTypes(): array;
    public function getPrivilegeTypeById(int $id): ?PrivilegeType;

    /**
     *
     * @return PrivilegeAddon[]
     */
    public function getPrivilegeAddons(): array;
    public function getPrivilegeAddonById(int $id): ?PrivilegeAddon;
}
