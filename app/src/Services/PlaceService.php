<?php

declare(strict_types=1);

namespace Services;

use Models\PlaceRepository;
use PDO;

/**
 * Super admin management of sites (`places`) and their `departments`.
 * A place is hard-deleted, but only while it has no department (the FK is
 * NOT NULL). A department is soft-disabled (is_active = FALSE), like the
 * email domains, to keep its history and code intact.
 */
final class PlaceService
{
    private PlaceRepository $places;

    /** `$places` is injectable for testing; production passes only the PDO. */
    public function __construct(PDO $pdo, ?PlaceRepository $places = null)
    {
        $this->places = $places ?? new PlaceRepository($pdo);
    }

    /**
     * @return list<array{id:int, name:string, address:?string, city:?string, zip_code:?string}>
     */
    public function listPlaces(): array
    {
        return $this->places->findAllPlaces();
    }

    /**
     * @return list<array{id:int, place_id:int, place_name:string, name:string, description:?string, is_active:bool}>
     */
    public function listDepartments(): array
    {
        return $this->places->findAllDepartments();
    }

    /**
     * Adds a site. Address fields are optional (kept null when blank).
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function addPlace(string $name, string $address, string $city, string $zipCode): array
    {
        $name = trim($name);

        if ($name === '') {
            return ['success' => false, 'error' => 'Le nom du site est requis.'];
        }
        if (mb_strlen($name) > 255) {
            return ['success' => false, 'error' => 'Le nom du site est trop long (255 caracteres max).'];
        }

        $this->places->addPlace(
            $name,
            $this->nullIfBlank($address),
            $this->nullIfBlank($city),
            $this->nullIfBlank($zipCode)
        );

        return ['success' => true];
    }

    /**
     * Deletes a site. Refused if any department still hangs off it, since
     * departments.place_id is NOT NULL (the FK would reject the DELETE anyway).
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function deletePlace(int $id): array
    {
        if (!$this->places->placeExists($id)) {
            return ['success' => false, 'error' => 'Site introuvable.'];
        }
        if ($this->places->countDepartmentsOfPlace($id) > 0) {
            return ['success' => false, 'error' => 'Ce site possede des departements : supprimez-les ou desactivez-les d\'abord.'];
        }

        $this->places->deletePlace($id);

        return ['success' => true];
    }

    /**
     * Adds a department to a place. Description is optional.
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function addDepartment(int $placeId, string $name, string $description): array
    {
        $name = trim($name);

        if (!$this->places->placeExists($placeId)) {
            return ['success' => false, 'error' => 'Site introuvable.'];
        }
        if ($name === '') {
            return ['success' => false, 'error' => 'Le nom du departement est requis.'];
        }
        if (mb_strlen($name) > 50) {
            return ['success' => false, 'error' => 'Le nom du departement est trop long (50 caracteres max).'];
        }
        if ($this->places->departmentNameExistsInPlace($placeId, $name)) {
            return ['success' => false, 'error' => 'Un departement de ce nom existe deja sur ce site.'];
        }

        $this->places->addDepartment($placeId, $name, $this->nullIfBlank($description));

        return ['success' => true];
    }

    /**
     * Enables or disables a department.
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function setDepartmentActive(int $id, bool $isActive): array
    {
        if (!$this->places->departmentExists($id)) {
            return ['success' => false, 'error' => 'Departement introuvable.'];
        }

        $this->places->setDepartmentActive($id, $isActive);

        return ['success' => true];
    }

    private function nullIfBlank(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
