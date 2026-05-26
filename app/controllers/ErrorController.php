<?php

namespace App\Controllers;

use App\Core\Controller;

class ErrorController extends Controller
{
    public function show(int $code = 404, string $message = 'Page introuvable'): void
    {
        http_response_code($code);
        $this->render('pages/error', [
            'title'   => "Erreur $code",
            'code'    => $code,
            'message' => $message,
        ]);
    }
}
