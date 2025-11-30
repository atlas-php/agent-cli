<?php

declare(strict_types=1);

namespace Atlas\Agent\Tests\Feature;

use Atlas\Agent\Services\CommandRenderers\CodexCommandOutputRenderer;
use Atlas\Agent\Services\CommandRenderers\SessionCommandOutputRendererRegistry;
use Atlas\Agent\Tests\TestCase;
use InvalidArgumentException;

/**
 * Class SessionCommandOutputRendererRegistryTest
 *
 * Validates provider resolution and error handling for command renderers.
 */
final class SessionCommandOutputRendererRegistryTest extends TestCase
{
    public function test_registry_resolves_renderer_by_provider(): void
    {
        $registry = new SessionCommandOutputRendererRegistry([
            new CodexCommandOutputRenderer,
        ]);

        $renderer = $registry->forProvider('CODEX');

        $this->assertInstanceOf(CodexCommandOutputRenderer::class, $renderer);
    }

    public function test_registry_rejects_unknown_provider(): void
    {
        $registry = new SessionCommandOutputRendererRegistry([
            new CodexCommandOutputRenderer,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $registry->forProvider('other');
    }
}
