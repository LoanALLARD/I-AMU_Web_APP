<?php

declare(strict_types=1);

namespace Domain;

/*
 *  This class represent the IA and how  
 *  data are processing 
 */

use Domain\LlmAdaptaterInterface;

class Ai {


    private int $id;
    private ?int $departmentId;
    private ?int $resourceId;
    private string $name;                   // name of the model
    private string $size;
    private string $provider;           // compagny who delivery the model
    private ?int $maxTokens;
    private string $contextWindow;   // size of the context window of the model
    private bool $isActive;
    private bool $isShareable;
    private string $url;                    // address of the api
    private LlmAdaptaterInterface $adaptater;   // type of adaptator

    public function __construct(int $id, ?int $departmentId, ?int $resourceId,string $name, string $size, string $provider, ?int $maxTokens, string $contextWindow, bool $isActive, bool $isShareable, string $url, LlmAdaptaterInterface $adaptater) {
        $this->id = $id;
        $this->departmentId = $departmentId;
        $this->resourceId = $resourceId;
        $this->name = $name;
        $this->size = $size;
        $this->provider = $provider;
        $this->maxTokens = $maxTokens;
        $this->contextWindow = $contextWindow;
        $this->isActive = $isActive;
        $this->isShareable = $isShareable;
        $this->url = $url;
        $this->adaptater = $adaptater;
    }

    /**
     * @param array<int, int> $context
     */
    public function ask(string $message, array $context, ?string $preprompt, ?string $postprompt): string {
        return $this->adaptater->generate($message, $context, $preprompt, $postprompt,null);
    }


    // Getters & Setters
    public function getId(): int
    {
        return $this->id;
    }

    public function getDepartmentId(): ?int
    {
        return $this->departmentId;
    }

    public function getResourceId(): ?int
    {
        return $this->resourceId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    public function getInfoContextWindow(): string
    {
        return $this->contextWindow;
    }

    public function getInfoCompagny(): string
    {
        return $this->provider;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isShareable(): bool
    {
        return $this->isShareable;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getFormatRequest(): string
    {
        return '';
    }

    public function setName(string $name): void
    {
    }

    public function setInfoContextWindow(string $infoContextWindow): void
    {
    }

    public function setInfoCompagny(string $infoCompagny): void
    {
    }

    public function setUrl(string $url): void
    {
    }

    public function setFormatRequest(string $formatRequest): void
    {
    }
}