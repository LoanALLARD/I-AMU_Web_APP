<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Domain\OllamaAdaptater;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OllamaAdaptater.
 */
final class OllamaAdaptaterTest extends TestCase
{
    private OllamaAdaptater $adaptater;

    protected function setUp(): void
    {
        // Initialisation de l'adaptateur avec des valeurs de test
        $this->adaptater = new OllamaAdaptater('http://localhost:11434/api/generate', 'llama3');
    }

    public function testReadContextFromMetadataReturnsArrayOfInts(): void
    {
        // Création d'une structure de métadonnées valides (selon la logique de double décodage)
        $innerJson = json_encode(['context' => [128006, 9125, 128007]]);
        $metaDataRaw = ['api_metadata' => $innerJson];

        $result = $this->adaptater->readContextFromMetadata($metaDataRaw);

        self::assertSame([128006, 9125, 128007], $result);
    }

    public function testReadContextFromMetadataHandlesInvalidOrMissingData(): void
    {
        // Cas 1 : La clé 'context' est absente
        $metaDataRawMissing = ['api_metadata' => json_encode(['other_key' => 'value'])];
        self::assertSame([], $this->adaptater->readContextFromMetadata($metaDataRawMissing));

        // Cas 2 : Le JSON est invalide
        $metaDataRawBroken = ['api_metadata' => '{broken_json: true,'];
        self::assertSame([], $this->adaptater->readContextFromMetadata($metaDataRawBroken));
    }

    /**
     * @runInSeparateProcess
     */
    public function testFormatMetadataReturnsDoubleEncodedJson(): void
    {
        // @runInSeparateProcess est obligatoire ici pour éviter l'erreur "headers already sent"
        // causée par la fonction header() dans formatMetadata.
        
        $response = new \stdClass();
        $response->context = [1, 2, 3];
        $response->total_duration = 50000;
        $response->done_reason = 'stop';

        $result = $this->adaptater->formatMetadata($response);

        // Reproduction de la logique actuelle de ta méthode (le double encodage)
        $expectedContext = json_encode([
            'context' => [1, 2, 3],
            'total_duration' => 50000,
            'done_reason' => 'stop'
        ]);
        
        $expectedResult = json_encode($expectedContext);

        self::assertSame($expectedResult, $result);
    }

    public function testGenerateReturnsCorrectPayloadInTestingMode(): void
    {
        $message = "Bonjour, comment ça va ?";
        $context = [128006, 9125];
        $preprompt = "Tu es un assistant utile.";
        $posprompt = " (Réponds en français)";

        // Appel de generate avec $isTesting à true
        $resultJson = $this->adaptater->generate($message, $context, $preprompt, $posprompt, true);

        // Décoder le JSON retourné pour vérifier son contenu
        $payloadData = json_decode($resultJson, true);

        // Assertions sur la structure du payload
        self::assertIsArray($payloadData);
        self::assertSame('llama3', $payloadData['model']);
        self::assertSame([128006, 9125], $payloadData['context']);
        self::assertSame('Tu es un assistant utile.', $payloadData['system']);
        self::assertFalse($payloadData['stream']);
        
        // Vérification de la concaténation du message avec le posprompt
        self::assertSame("Bonjour, comment ça va ? (Réponds en français)", $payloadData['prompt']);
    }
}