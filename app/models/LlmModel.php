<?php

namespace App\Models;

use App\Core\Model;
use App\Services\OllamaService;

class LlmModel extends Model
{
    protected string $table = 'model';
    protected string $primaryKey = 'model_id';

    /**
     * Retourne les modèles actifs.
     */
    public function findActive(): array
    {
        return $this->findBy(['is_active' => true], 'name ASC');
    }

    /**
     * Trouve un modèle par son nom.
     */
    public function findByName(string $name): ?array
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Synchronise la table `model` avec les modèles réellement disponibles
     * sur le serveur Ollama. Les modèles présents sur Ollama mais absents en
     * base sont créés (et activés). Les modèles présents en base mais
     * disparus d'Ollama sont désactivés (jamais supprimés, pour préserver
     * l'historique des interactions liées par FK).
     *
     * @return array{added: int, reactivated: int, disabled: int}
     */
    public function syncFromOllama(OllamaService $ollama): array
    {
        $ollamaModels = $ollama->listModels();
        $result = ['added' => 0, 'reactivated' => 0, 'disabled' => 0];

        $seenNames = [];
        foreach ($ollamaModels as $om) {
            $name = $om['name'] ?? null;
            if (!$name) continue;
            $seenNames[] = $name;

            $existing = $this->findByName($name);
            if ($existing) {
                if (!$existing['is_active']) {
                    $this->update((int) $existing['model_id'], ['is_active' => true]);
                    $result['reactivated']++;
                }
                continue;
            }

            // Nouveau modèle : extrait le tag après ':' comme version
            $version = (strpos($name, ':') !== false)
                ? substr($name, strpos($name, ':') + 1)
                : 'latest';

            $this->create([
                'name'           => $name,
                'version'        => $version,
                'provider'       => 'ollama',
                'max_tokens'     => 4096,
                'context_window' => 8192,
                'is_active'      => true,
            ]);
            $result['added']++;
        }

        // Désactive les modèles Ollama disparus du serveur
        foreach ($this->findAll() as $m) {
            if ($m['provider'] === 'ollama'
                && $m['is_active']
                && !in_array($m['name'], $seenNames, true)) {
                $this->update((int) $m['model_id'], ['is_active' => false]);
                $result['disabled']++;
            }
        }

        return $result;
    }
}
