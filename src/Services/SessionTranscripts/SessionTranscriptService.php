<?php

declare(strict_types=1);

namespace Atlas\Agent\Services\SessionTranscripts;

use InvalidArgumentException;
use RuntimeException;

/**
 * Class SessionTranscriptService
 *
 * Loads provider transcripts and exposes normalized session insights.
 */
class SessionTranscriptService
{
    private string $sessionsBasePath;

    /**
     * @var array<string, SessionTranscriptParser>
     */
    private array $parsers;

    /**
     * @param  array<int, SessionTranscriptParser>|null  $parsers
     */
    public function __construct(?string $sessionsBasePath = null, ?array $parsers = null)
    {
        $this->sessionsBasePath = $this->normalizeDirectory($sessionsBasePath);

        $providedParsers = $parsers ?? [new CodexSessionTranscriptParser];
        $this->parsers = [];

        foreach ($providedParsers as $parser) {
            $provider = $this->normalizeProvider($parser->provider());
            $this->parsers[$provider] = $parser;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fullTranscript(string $provider, string $sessionId): array
    {
        $normalizedProvider = $this->normalizeProvider($provider);
        $parser = $this->resolveParser($normalizedProvider);
        $events = $this->readEvents($normalizedProvider, $sessionId);

        return $parser->parseEvents($events);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function todoList(string $provider, string $sessionId): array
    {
        $normalizedProvider = $this->normalizeProvider($provider);
        $parser = $this->resolveParser($normalizedProvider);
        $events = $this->readEvents($normalizedProvider, $sessionId);

        return $parser->parseTodos($events);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function turns(string $provider, string $sessionId): array
    {
        $normalizedProvider = $this->normalizeProvider($provider);
        $parser = $this->resolveParser($normalizedProvider);
        $events = $this->readEvents($normalizedProvider, $sessionId);

        return $parser->parseTurns($events);
    }

    /**
     * @return array<string, int>
     */
    public function usageTotals(string $provider, string $sessionId): array
    {
        $normalizedProvider = $this->normalizeProvider($provider);
        $parser = $this->resolveParser($normalizedProvider);
        $events = $this->readEvents($normalizedProvider, $sessionId);

        return $parser->parseUsageTotals($events);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readEvents(string $provider, string $sessionId): array
    {
        $path = $this->sessionLogPath($provider, $sessionId);

        if (! is_file($path)) {
            throw new RuntimeException('Session log not found for provider '.$provider.' and session '.$sessionId);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException('Failed to read session log at '.$path);
        }

        $events = [];

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $events[] = $decoded;
            }
        }

        return $events;
    }

    private function sessionLogPath(string $provider, string $sessionId): string
    {
        $normalizedSessionId = $this->normalizeSessionId($sessionId);

        return $this->sessionsBasePath.DIRECTORY_SEPARATOR.$provider.DIRECTORY_SEPARATOR.$normalizedSessionId.'.jsonl';
    }

    private function resolveParser(string $provider): SessionTranscriptParser
    {
        if (! isset($this->parsers[$provider])) {
            throw new InvalidArgumentException('Unsupported provider: '.$provider);
        }

        return $this->parsers[$provider];
    }

    private function normalizeProvider(string $provider): string
    {
        $trimmed = strtolower(trim($provider, DIRECTORY_SEPARATOR));

        if ($trimmed === '') {
            throw new InvalidArgumentException('Provider is required for transcript parsing.');
        }

        if (str_contains($trimmed, '..') || str_contains($trimmed, DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Invalid provider supplied for transcript parsing.');
        }

        return $trimmed;
    }

    private function normalizeSessionId(string $sessionId): string
    {
        $trimmed = trim($sessionId);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Session ID is required for transcript parsing.');
        }

        if (str_contains($trimmed, DIRECTORY_SEPARATOR) || str_contains($trimmed, '..')) {
            throw new InvalidArgumentException('Invalid session ID supplied for transcript parsing.');
        }

        return $trimmed;
    }

    private function normalizeDirectory(?string $path): string
    {
        $resolved = $path !== null ? $path : storage_path('app/sessions');

        return rtrim($resolved, DIRECTORY_SEPARATOR);
    }
}
