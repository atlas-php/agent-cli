<?php

declare(strict_types=1);

namespace Atlas\Agent\Services\SessionTranscripts;

/**
 * Interface SessionTranscriptParser
 *
 * Defines provider-specific parsing for session transcript events.
 */
interface SessionTranscriptParser
{
    public function provider(): string;

    /**
     * @param  array<int, mixed>  $events
     * @return array<int, array<string, mixed>>
     */
    public function parseEvents(array $events): array;

    /**
     * @param  array<int, mixed>  $events
     * @return array<int, array<string, mixed>>
     */
    public function parseTodos(array $events): array;

    /**
     * @param  array<int, mixed>  $events
     * @return array<int, array<string, mixed>>
     */
    public function parseTurns(array $events): array;

    /**
     * @param  array<int, mixed>  $events
     * @return array<string, int>
     */
    public function parseUsageTotals(array $events): array;
}
