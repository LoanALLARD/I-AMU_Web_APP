<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\HttpException;
use Throwable;

/**
 * Central error page. Renders pages/error for any HTTP status code, with a
 * friendly per-code message - or the explicit message of an HttpException -
 * plus technical details (type, location, trace) when APP_DEBUG is on.
 */
class ErrorController extends Controller
{
    public function show(int $code, ?Throwable $e = null): void
    {
        if (!headers_sent()) {
            http_response_code($code);
        }

        [$title, $defaultMessage] = $this->describe($code);

        // An explicitly thrown HttpException carries a tailored message that
        // overrides the generic one; an unexpected error keeps the generic one.
        $message = ($e instanceof HttpException && $e->getMessage() !== '')
            ? $e->getMessage()
            : $defaultMessage;

        $config = require __DIR__ . '/../Config/config.php';
        $debug  = (bool) ($config['debug'] ?? false);

        $exception = ($debug && $e !== null)
            ? [
                'type'  => $e::class,
                'msg'   => $e->getMessage(),
                'where' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]
            : null;

        $this->render('pages/error', [
            'code'      => $code,
            'title'     => $title,
            'message'   => $message,
            'exception' => $exception,
        ], 'main');
    }

    /**
     * @return array{0: string, 1: string} [title, default message]
     */
    private function describe(int $code): array
    {
        return match ($code) {
            400     => ['Requête invalide', 'La requête est mal formée.'],
            401     => ['Non authentifié', 'Vous devez être connecté pour accéder à cette page.'],
            403     => ['Accès refusé', "Vous n'avez pas les droits nécessaires pour accéder à cette page."],
            404     => ['Page introuvable', "La page que vous cherchez n'existe pas ou a été déplacée."],
            419     => ['Session expirée', 'Votre session a expiré. Merci de réessayer.'],
            500     => ['Erreur serveur', 'Une erreur inattendue est survenue de notre côté.'],
            default => ['Erreur', 'Une erreur est survenue.'],
        };
    }
}
