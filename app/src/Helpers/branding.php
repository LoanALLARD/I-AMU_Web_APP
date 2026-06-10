<?php

declare(strict_types=1);

use Data\Database;
use Models\PlaceRepository;

if (!function_exists('placeBranding')) {
    /**
     * Branding to apply for the current request, resolved from the logged-in
     * user's place (their department -> place). Falls back to the default I-AMU
     * identity when the user has no place or it carries no custom branding.
     * Resolved once and cached for the request.
     *
     * @return array{name: string, logo: string, favicon: string, color: ?string}
     */
    function placeBranding(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $default = ['name' => 'I-AMU', 'logo' => '/assets/img/logo.png', 'favicon' => '/assets/favicon.ico', 'color' => null];

        $departmentId = isset($_SESSION['user_department_id']) && $_SESSION['user_department_id'] !== null
            ? (int) $_SESSION['user_department_id']
            : 0;
        if ($departmentId <= 0) {
            return $cache = $default;
        }

        try {
            $branding = (new PlaceRepository(Database::getConnection()))->findBrandingByDepartment($departmentId);
        } catch (\Throwable) {
            return $cache = $default;
        }
        if ($branding === null) {
            return $cache = $default;
        }

        return $cache = [
            'name'    => ($branding['display_name'] ?? '') !== '' ? (string) $branding['display_name'] : $default['name'],
            'logo'    => ($branding['logo_path'] ?? '') !== '' ? (string) $branding['logo_path'] : $default['logo'],
            'favicon' => ($branding['favicon_path'] ?? '') !== '' ? (string) $branding['favicon_path'] : $default['favicon'],
            'color'   => ($branding['primary_color'] ?? '') !== '' ? (string) $branding['primary_color'] : null,
        ];
    }
}
