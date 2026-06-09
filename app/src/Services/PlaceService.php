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
    private const MAX_LOGO_BYTES = 2 * 1024 * 1024; // 2 Mo
    /** Public path (under public/) where place logos are served from. */
    private const LOGO_PUBLIC_DIR = '/assets/uploads/places';
    /** Real (finfo) image MIME -> stored extension. */
    private const ALLOWED_LOGO_TYPES = [
        'image/png'     => 'png',
        'image/jpeg'    => 'jpg',
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
    ];

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

    /**
     * Updates a place's branding (display name, logo, primary color).
     * - Blank display name / color clear the override (NULL = I-AMU default).
     * - A logo is only replaced when a file is actually uploaded; the old file
     *   is then deleted. Sending no file keeps the current logo.
     *
     * @param array<string, mixed>|null $logoFile a single $_FILES entry, or null
     * @return array{success: true} | array{success: false, error: string}
     */
    public function updateBranding(int $id, string $displayName, string $primaryColor, ?array $logoFile): array
    {
        $current = $this->places->findBrandingById($id);
        if ($current === null) {
            return ['success' => false, 'error' => 'Site introuvable.'];
        }

        $displayName = trim($displayName);
        if (mb_strlen($displayName) > 255) {
            return ['success' => false, 'error' => 'Le nom affiche est trop long (255 caracteres max).'];
        }

        $color = $this->normalizeColor($primaryColor);
        if ($color === false) {
            return ['success' => false, 'error' => 'La couleur primaire est invalide (format attendu : #1a73c8).'];
        }

        $logoPath = $current['logo_path'];
        if ($this->hasUpload($logoFile)) {
            $stored = $this->storeLogo($id, $logoFile);
            if ($stored === false) {
                return ['success' => false, 'error' => 'Le logo est invalide (image PNG, JPEG, SVG ou WebP, 2 Mo max).'];
            }
            $this->deleteLogoFile($current['logo_path']);
            $logoPath = $stored;
        }

        $this->places->updateBranding(
            $id,
            $displayName === '' ? null : $displayName,
            $logoPath,
            $color
        );

        return ['success' => true];
    }

    private function nullIfBlank(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** Normalizes a hex color to lowercase #rrggbb, '' to null, else false (invalid). */
    private function normalizeColor(string $color): string|null|false
    {
        $color = strtolower(trim($color));
        if ($color === '') {
            return null;
        }

        return preg_match('/^#[0-9a-f]{6}$/', $color) === 1 ? $color : false;
    }

    /** @param array<string, mixed>|null $file */
    private function hasUpload(?array $file): bool
    {
        return $file !== null
            && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            && ($file['tmp_name'] ?? '') !== '';
    }

    /**
     * Validates and moves an uploaded logo under public/assets/uploads/places.
     * Returns the public path to store, or false when the file is rejected.
     *
     * @param array<string, mixed> $file
     */
    private function storeLogo(int $placeId, array $file): string|false
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return false;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            return false;
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > self::MAX_LOGO_BYTES) {
            return false;
        }

        $ext = $this->resolveLogoExtension($tmp);
        if ($ext === null) {
            return false;
        }

        $publicDir = dirname(__DIR__, 2) . '/public' . self::LOGO_PUBLIC_DIR;
        if (!is_dir($publicDir) && !mkdir($publicDir, 0775, true) && !is_dir($publicDir)) {
            return false;
        }

        $fileName = $placeId . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $absolute = $publicDir . '/' . $fileName;
        if (!move_uploaded_file($tmp, $absolute)) {
            return false;
        }

        return self::LOGO_PUBLIC_DIR . '/' . $fileName;
    }

    /** Real (finfo) image MIME -> stored extension, or null for an unsupported type. */
    private function resolveLogoExtension(string $tmp): ?string
    {
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $detected = $finfo !== false ? (finfo_file($finfo, $tmp) ?: '') : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        return self::ALLOWED_LOGO_TYPES[$detected] ?? null;
    }

    /** Removes a previously stored logo file from disk (best effort). */
    private function deleteLogoFile(?string $publicPath): void
    {
        if ($publicPath === null || !str_starts_with($publicPath, self::LOGO_PUBLIC_DIR . '/')) {
            return;
        }
        $absolute = dirname(__DIR__, 2) . '/public' . $publicPath;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
