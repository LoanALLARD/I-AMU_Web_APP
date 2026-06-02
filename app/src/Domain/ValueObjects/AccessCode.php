<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * 6-character access code students type to join a session.
 *
 * Phase 1 decision: 6 uppercase alphanumeric characters give a ~2.17 billion
 * code space, which is more than enough for the expected scale (a few
 * hundred concurrent sessions). The dash in the mock-up (`A7K-29B`) is a
 * UI-only formatting choice; the dash is not stored.
 *
 * Validation matches the DB CHECK constraint `^[A-Z0-9]{6}$`.
 */
final readonly class AccessCode
{
    public string $value;

    public function __construct(string $value)
    {
        $value = strtoupper($value);
        if (!preg_match('/^[A-Z0-9]{6}$/', $value)) {
            throw new InvalidArgumentException(
                "Invalid access code: must be exactly 6 chars [A-Z0-9], got \"$value\"."
            );
        }
        $this->value = $value;
    }

    /**
     * Returns the value with a dash inserted between groups of 3
     * (e.g. "A7K29B" -> "A7K-29B"). Used by views only.
     */
    public function formatted(): string
    {
        return substr($this->value, 0, 3) . '-' . substr($this->value, 3, 3);
    }

    /**
     * Parses a possibly-formatted code typed by a student.
     * Accepts "A7K-29B", "a7k29b", " A7K 29B ", etc.
     *
     * @throws InvalidArgumentException if the cleaned code is not 6 chars [A-Z0-9].
     */
    public static function fromUserInput(string $raw): self
    {
        $cleaned = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $raw) ?? '');
        return new self($cleaned);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
