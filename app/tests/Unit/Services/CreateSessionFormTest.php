<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use DateTimeImmutable;
use Domain\SessionType;
use PHPUnit\Framework\TestCase;
use Services\CreateSessionForm;

/**
 * Unit tests for the create / edit session form validator. Covers every
 * validation branch and the normalisation of the returned `data` payload.
 * Pure logic, no DB.
 */
final class CreateSessionFormTest extends TestCase
{
    /**
     * A minimal POST that passes create validation, overridable per test.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validCreatePost(array $overrides = []): array
    {
        return array_merge([
            'name'        => 'Algo TD',
            'type'        => 'TUTORIAL',
            'resource_id' => 7,
            'models'      => [1, 2],
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // Happy path + data normalisation
    // ----------------------------------------------------------------

    public function testValidCreateHasNoErrors(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost());

        self::assertSame([], $result['errors']);
    }

    public function testValidCreateNormalisesData(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost([
            'access_code' => 'ABC123',
        ]));
        $data = $result['data'];

        self::assertSame('Algo TD', $data['name']);
        self::assertSame(SessionType::Tutorial, $data['type']);
        self::assertSame(7, $data['resourceId']);
        self::assertSame(90, $data['durationMinutes']);
        self::assertSame([1, 2], $data['modelIds']);
        self::assertNull($data['startsAt']);
        self::assertNull($data['maxInputSize']);
        self::assertSame('ABC123', $data['accessCode']);
    }

    // ----------------------------------------------------------------
    // Name
    // ----------------------------------------------------------------

    public function testNameIsRequired(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost(['name' => '   ']));

        self::assertContains('Le libellé est obligatoire.', $result['errors']);
    }

    public function testNameTooLongIsRejected(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost(['name' => str_repeat('a', 256)]));

        self::assertContains('Le libellé doit faire au plus 255 caractères.', $result['errors']);
    }

    // ----------------------------------------------------------------
    // Type + resource (create only)
    // ----------------------------------------------------------------

    public function testInvalidTypeIsRejectedOnCreate(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost(['type' => 'INVALID']));

        self::assertContains('Type de session invalide.', $result['errors']);
    }

    public function testResourceIsRequiredOnCreate(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost(['resource_id' => 0]));

        self::assertContains('Une ressource est obligatoire.', $result['errors']);
    }

    public function testUpdateIgnoresTypeAndResource(): void
    {
        $result = CreateSessionForm::fromPostForUpdate(['name' => 'Renommée', 'models' => [1]]);

        self::assertSame([], $result['errors']);
        self::assertNull($result['data']['type']);
        self::assertSame(0, $result['data']['resourceId']);
    }

    // ----------------------------------------------------------------
    // Start date
    // ----------------------------------------------------------------

    public function testInvalidStartDateIsRejected(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost(['starts_at' => 'not-a-date']));

        self::assertContains('Date de démarrage invalide.', $result['errors']);
    }

    public function testValidStartDateIsParsed(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost([
            'starts_at' => '2026-06-10T10:00:00+02:00',
        ]));

        self::assertInstanceOf(DateTimeImmutable::class, $result['data']['startsAt']);
    }

    public function testEmptyStartDateStaysNull(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost(['starts_at' => '']));

        self::assertNull($result['data']['startsAt']);
    }

    // ----------------------------------------------------------------
    // Duration
    // ----------------------------------------------------------------

    public function testDurationBelowRangeIsRejected(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost(['duration_min' => 0]));

        self::assertContains('La durée doit être entre 1 et 480 minutes.', $result['errors']);
    }

    public function testDurationAboveRangeIsRejected(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost(['duration_min' => 481]));

        self::assertContains('La durée doit être entre 1 et 480 minutes.', $result['errors']);
    }

    // ----------------------------------------------------------------
    // Models
    // ----------------------------------------------------------------

    public function testAtLeastOneModelIsRequired(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost(['models' => []]));

        self::assertContains('Sélectionnez au moins un modèle.', $result['errors']);
    }

    public function testModelIdsAreFilteredAndDeduplicated(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost([
            'models' => [3, 3, 0, '5'],
        ]));

        self::assertSame([3, 5], $result['data']['modelIds']);
    }

    // ----------------------------------------------------------------
    // Max input size
    // ----------------------------------------------------------------

    public function testMaxInputSizeOutOfRangeIsRejected(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost(['max_input_size' => '200001']));

        self::assertContains("Plafond d'entrée invalide (1 à 200 000).", $result['errors']);
    }

    public function testMaxInputSizeEmptyStaysNull(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost(['max_input_size' => '']));

        self::assertNull($result['data']['maxInputSize']);
    }

    public function testValidMaxInputSizeIsStored(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost(['max_input_size' => '1500']));

        self::assertSame(1500, $result['data']['maxInputSize']);
    }

    // ----------------------------------------------------------------
    // Optional free-text fields + access code
    // ----------------------------------------------------------------

    public function testBlankOptionalFieldsBecomeNull(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost([
            'pre_prompt'   => '   ',
            'post_prompt'  => '',
            'instructions' => '   ',
        ]));
        $data = $result['data'];

        self::assertNull($data['prePrompt']);
        self::assertNull($data['postPrompt']);
        self::assertNull($data['instructions']);
    }

    public function testOptionalFieldsAreTrimmedAndKept(): void
    {
        $result = CreateSessionForm::fromPost($this->validCreatePost([
            'instructions' => '  Soyez concis.  ',
        ]));

        self::assertSame('Soyez concis.', $result['data']['instructions']);
    }

    public function testAccessCodeIsNullOnUpdate(): void
    {
        $result = CreateSessionForm::fromPostForUpdate([
            'name'        => 'Renommée',
            'models'      => [1],
            'access_code' => 'ABC123',
        ]);

        self::assertNull($result['data']['accessCode']);
    }
}
