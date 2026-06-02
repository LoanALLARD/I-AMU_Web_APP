<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Exceptions\UnsupportedModelException;
use App\Application\Services\GenerateReplyService;

/**
 * JSON endpoint behind POST /chat.
 *
 * Parses the {model, message, context} payload, delegates generation to
 * {@see GenerateReplyService} and returns a {response} envelope. Keeps
 * the exact request/response contract of the legacy ServeurFolder
 * controller it replaces, so the existing chat front-end is unaffected.
 */
final class LLMController
{
    public function __construct(
        private readonly GenerateReplyService $generateReply,
    ) {
    }

    public function handleChat(): void
    {
        header('Content-Type: application/json');

        $data = json_decode((string) file_get_contents('php://input'), true);

        if (!is_array($data) || !isset($data['model'], $data['message'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données invalides. « model » et « message » sont requis.']);
            return;
        }

        $context = $data['context'] ?? [];
        if (!is_array($context)) {
            $context = [];
        }

        try {
            $response = $this->generateReply->execute(
                (string) $data['model'],
                (string) $data['message'],
                $context,
            );
        } catch (UnsupportedModelException $e) {
            http_response_code(404);
            echo json_encode(['error' => $e->getMessage()]);
            return;
        } catch (\Throwable $e) {
            http_response_code(502);
            echo json_encode(['error' => 'Le service LLM est indisponible.']);
            return;
        }

        echo json_encode(['response' => $response]);
    }
}
