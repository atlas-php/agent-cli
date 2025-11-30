<?php

declare(strict_types=1);

namespace Atlas\Agent\Services\CommandRenderers;

use InvalidArgumentException;

/**
 * Class SessionCommandOutputRendererRegistry
 *
 * Resolves command output renderers per provider to keep commands provider-agnostic.
 */
class SessionCommandOutputRendererRegistry
{
    /**
     * @var array<string, SessionCommandOutputRenderer>
     */
    private array $renderers;

    /**
     * @param  array<int, SessionCommandOutputRenderer>|null  $renderers
     */
    public function __construct(?array $renderers = null)
    {
        $this->renderers = [];
        $renderers = $renderers ?? [new CodexCommandOutputRenderer];

        foreach ($renderers as $renderer) {
            $provider = $this->normalizeProvider($renderer->provider());
            $this->renderers[$provider] = $renderer;
        }
    }

    public function forProvider(string $provider): SessionCommandOutputRenderer
    {
        $normalized = $this->normalizeProvider($provider);

        if (! array_key_exists($normalized, $this->renderers)) {
            throw new InvalidArgumentException('No renderer registered for provider: '.$provider);
        }

        return $this->renderers[$normalized];
    }

    private function normalizeProvider(string $provider): string
    {
        $normalized = strtolower(trim($provider));

        if ($normalized === '') {
            throw new InvalidArgumentException('Provider name cannot be empty.');
        }

        return $normalized;
    }
}
