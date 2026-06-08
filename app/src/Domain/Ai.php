<?php

namespace Domain;

/*
 *  This class represent the IA and how  
 *  data are processing 
 */

use Domain\LlmAdaptaterInterface;

class Ai {


    private int $id;
    private ?int $department_id;
    private ?int $resource_id;
    private string $name;                   // name of the model
    private string $size;
    private string $provider;           // compagny who delivery the model
    private int $max_tokens;
    private string $context_window;   // size of the context window of the model
    private bool $is_active;
    private bool $is_shareable;
    private string $url;                    // address of the api
    private LlmAdaptaterInterface $adaptater;   // type of adaptator

    public function __construct(int $id, ?int $department_id, ?int $resource_id,string $name, string $size, string $provider, int $max_tokens, string $context_window, bool $is_active, bool $is_shareable, string $url, LlmAdaptaterInterface $adaptater) {
        $this->id = $id;
        $this->department_id = $department_id;
        $this->resource_id = $resource_id;
        $this->name = $name;
        $this->size = $size;
        $this->provider = $provider;
        $this->max_tokens = $max_tokens;
        $this->context_window = $context_window;
        $this->is_active = $is_active;
        $this->is_shareable = $is_shareable;
        $this->url = $url;
        $this->adaptater = $adaptater;
    }

    /**
     * @param array<int, int> $context
     */
    public function ask(string $message, array $context, ?string $postprompt, ?string $preprompt): string {
        return $this->adaptater->generate($message, $context, $postprompt, $preprompt,null);
    }


    // Getters & Setters
    public function getId(): int
    {
        return $this->id;
    }

    public function getDepartmentId(): ?int
    {
        return $this->department_id;
    }

    public function getResourceId(): ?int
    {
        return $this->resource_id;
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
        return $this->context_window;
    }

    public function getInfoCompagny(): string
    {
        return $this->provider;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isShareable(): bool
    {
        return $this->is_shareable;
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