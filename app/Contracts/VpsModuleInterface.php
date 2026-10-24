<?php

namespace App\Contracts;

use App\Models\Service;

/**
 * What a server module offers so the virtual server panel (status, power,
 * graphs, root password, reinstall, snapshots, backups) can run on it.
 *
 * The panel, the customer's and the admin's VPS controllers and the service
 * pages depend on this and on nothing hypervisor-specific, so a module for
 * another platform (OpenNebula, LXD, CloudStack...) gets the same screens by
 * implementing it. vpsFeatures() decides which parts of the panel appear: a
 * module that cannot reinstall or back up simply leaves those names out and
 * the panel hides them.
 *
 * A module also declares two class constants the controllers read through the
 * module's class:
 *   CLIENT_POWER_ACTIONS  the power actions a customer may send
 *   SNAPSHOT_NAME         a regex a snapshot name must match
 *
 * Methods that act return the usual ['success' => bool, 'message' => string,
 * 'data' => array]. Methods that list return an array, or an object with a
 * public string $error when the list cannot be read.
 */
interface VpsModuleInterface extends ServerModuleInterface
{
    /** Parts of the panel this service has: status, power, graphs, password, reinstall, snapshots, backups. */
    public function vpsFeatures(Service $service): array;

    /** @return array{available: bool, error?: string, busy?: bool, message?: string}&array<string, mixed> */
    public function vmStatus(Service $service): array;

    /** @return array{available: bool, points?: array, error?: string} */
    public function graphs(Service $service, string $timeframe = 'hour'): array;

    public function power(Service $service, string $action): array;

    /** @return array<int, array{id: string, name: string}> */
    public function reinstallChoices(Service $service): array;

    /** Link a guest that already exists on the server to this service (staff, "Link to an existing account"). */
    public function claim(Service $service, int $vmid): array;

    public function imageName(string $image): string;

    public function reinstall(Service $service, string $image, string $password): array;

    public static function normaliseSshKeys(?string $text): ?string;

    public function setSshKeys(Service $service, string $keys): void;

    public function storedSshKeys(Service $service): string;

    public function snapshotLimit(Service $service): int;

    /** @return array<int, array{name: string, description: string, time: int}>|object */
    public function snapshots(Service $service): array|object;

    public function snapshotCreate(Service $service, string $name, string $description = ''): array;

    public function snapshotRollback(Service $service, string $name): array;

    public function snapshotDelete(Service $service, string $name): array;

    public function backupLimit(Service $service): int;

    /** @return array<int, array{volid: string, size: int, time: int, notes: string, protected: bool}>|object */
    public function backups(Service $service): array|object;

    public function backupCreate(Service $service): array;

    public function backupRestore(Service $service, string $volid): array;

    public function backupDelete(Service $service, string $volid): array;
}
