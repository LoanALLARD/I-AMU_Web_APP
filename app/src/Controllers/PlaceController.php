<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Models\PlaceRepository;

/**
 * Serves the place / department data the registration form needs.
 *
 * The registration page has two cascading selects: picking a place fetches
 * that place's departments from here, over AJAX, as JSON.
 */
class PlaceController extends Controller
{
    private PlaceRepository $places;

    public function __construct()
    {
        $this->places = new PlaceRepository(Database::getConnection());
    }

    /**
     * GET /places/{id}/departments — returns the active departments of a
     * place as JSON, for the dependent select on the registration form.
     */
    public function departments(string $id): never
    {
        $placeId = (int) $id;

        $departments = array_map(
            static fn (array $row): array => [
                'id'   => (int) $row['id'],
                'name' => (string) $row['name'],
            ],
            $this->places->departmentsByPlace($placeId)
        );

        $this->json($departments);
    }
}
