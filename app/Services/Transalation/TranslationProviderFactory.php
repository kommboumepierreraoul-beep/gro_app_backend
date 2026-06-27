<?php

namespace App\Services\Translation;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class TranslationProviderFactory
{
    public function __construct(protected Container $container) {}

    public function make(string $name): TranslationProviderInterface
    {
        $config = config("translation.providers.{$name}");

        if (!$config || !isset($config['driver'])) {
            throw new InvalidArgumentException(
                "Provider de traduction inconnu : « {$name} ». Vérifiez config/translation.php."
            );
        }

        // Le container Laravel résout automatiquement les autres dépendances
        // typées du constructeur (ex: DeepSeekService) en plus de $config.
        $provider = $this->container->make($config['driver'], ['config' => $config]);

        if (!$provider instanceof TranslationProviderInterface) {
            throw new InvalidArgumentException(
                "Le driver « {$config['driver']} » doit implémenter TranslationProviderInterface."
            );
        }

        return $provider;
    }
}
