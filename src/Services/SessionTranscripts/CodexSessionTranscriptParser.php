<?php

declare(strict_types=1);

namespace Atlas\Agent\Services\SessionTranscripts;

/**
 * Class CodexSessionTranscriptParser
 *
 * Normalizes Codex JSONL transcript events into actionable summaries.
 */
class CodexSessionTranscriptParser implements SessionTranscriptParser
{
    private const TODO_ITEM_TYPES = [
        'todo',
        'todo_list',
        'task',
        'task_list',
        'plan',
    ];

    public function provider(): string
    {
        return 'codex';
    }

    /**
     * @param  array<int, mixed>  $events
     * @return array<int, array<string, mixed>>
     */
    public function parseEvents(array $events): array
    {
        return array_values(array_filter($events, 'is_array'));
    }

    /**
     * @param  array<int, mixed>  $events
     * @return array<int, array<string, mixed>>
     */
    public function parseTodos(array $events): array
    {
        $todos = [];
        $idIndex = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $eventType = (string) ($event['type'] ?? '');
            $item = $event['item'] ?? null;
            if (! is_array($item)) {
                continue;
            }

            $itemType = (string) ($item['type'] ?? '');
            if ($itemType === '' || ! in_array($itemType, self::TODO_ITEM_TYPES, true)) {
                continue;
            }

            $id = $this->normalizeId($item['id'] ?? null);
            $status = $this->resolveTodoStatus($eventType, $item);
            $title = $this->resolveTitle($item);

            $normalized = [
                'id' => $id,
                'title' => $title,
                'status' => $status,
                'raw' => $item,
            ];

            if ($id !== null && array_key_exists($id, $idIndex)) {
                $existingIndex = $idIndex[$id];
                $todos[$existingIndex] = $this->mergeTodo($todos[$existingIndex], $normalized);

                continue;
            }

            $todos[] = $normalized;

            if ($id !== null) {
                $idIndex[$id] = array_key_last($todos);
            }
        }

        return $todos;
    }

    /**
     * @param  array<int, mixed>  $events
     * @return array<int, array<string, mixed>>
     */
    public function parseTurns(array $events): array
    {
        $turns = [];
        $activeIndex = null;
        $nextTurn = 1;

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = (string) ($event['type'] ?? '');
            if ($type === 'turn.started') {
                $activeIndex = $nextTurn;
                $turns[$activeIndex] = $this->newTurn($activeIndex);
                $nextTurn++;

                continue;
            }

            if ($type === 'turn.completed') {
                if ($activeIndex === null) {
                    $activeIndex = $nextTurn;
                    $turns[$activeIndex] = $this->newTurn($activeIndex);
                    $nextTurn++;
                }

                $turns[$activeIndex]['usage'] = $this->normalizeUsage($event['usage'] ?? null);
                $activeIndex = null;

                continue;
            }

            if (! in_array($type, ['item.started', 'item.updated', 'item.completed'], true)) {
                continue;
            }

            if ($activeIndex === null) {
                $activeIndex = $nextTurn;
                $turns[$activeIndex] = $this->newTurn($activeIndex);
                $nextTurn++;
            }

            $turns[$activeIndex]['actions'][] = $this->normalizeAction($type, $event);
        }

        ksort($turns);

        return array_values($turns);
    }

    /**
     * @param  array<int, mixed>  $events
     * @return array<string, int>
     */
    public function parseUsageTotals(array $events): array
    {
        $totals = [
            'input_tokens' => 0,
            'cached_input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
        ];

        foreach ($events as $event) {
            if (! is_array($event) || (string) ($event['type'] ?? '') !== 'turn.completed') {
                continue;
            }

            $usage = $event['usage'] ?? null;
            if (! is_array($usage)) {
                continue;
            }

            $totals['input_tokens'] += $this->asInt($usage['input_tokens'] ?? null);
            $totals['cached_input_tokens'] += $this->asInt($usage['cached_input_tokens'] ?? null);
            $totals['output_tokens'] += $this->asInt($usage['output_tokens'] ?? null);
        }

        $totals['total_tokens'] = $totals['input_tokens'] + $totals['output_tokens'];

        return $totals;
    }

    private function normalizeId(mixed $id): ?string
    {
        if (is_string($id) && trim($id) !== '') {
            return trim($id);
        }

        if (is_int($id)) {
            return (string) $id;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveTitle(array $item): ?string
    {
        $candidates = [
            $item['title'] ?? null,
            $item['text'] ?? null,
            $item['summary'] ?? null,
            $item['task'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $trimmed = trim($candidate);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveTodoStatus(string $eventType, array $item): string
    {
        $explicit = $this->normalizeStatus((string) ($item['status'] ?? ''));

        if ($explicit !== null) {
            return $explicit;
        }

        return match ($eventType) {
            'item.completed' => 'completed',
            'item.started', 'item.updated' => 'in_progress',
            default => 'unknown',
        };
    }

    private function normalizeStatus(string $status): ?string
    {
        $normalized = strtolower(trim($status));

        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'complete', 'completed', 'done' => 'completed',
            'in-progress', 'in_progress', 'in progress', 'in processing', 'processing' => 'in_progress',
            'pending', 'todo', 'not started' => 'pending',
            default => $normalized,
        };
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeTodo(array $existing, array $incoming): array
    {
        $existingStatus = is_string($existing['status'] ?? null) ? $existing['status'] : 'unknown';
        $incomingStatus = is_string($incoming['status'] ?? null) ? $incoming['status'] : 'unknown';

        $status = $this->preferStatus($existingStatus, $incomingStatus);

        $existing['status'] = $status;
        if ($existing['title'] === null && $incoming['title'] !== null) {
            $existing['title'] = $incoming['title'];
        }
        $existing['raw'] = $incoming['raw'];

        return $existing;
    }

    private function preferStatus(string $current, string $incoming): string
    {
        $priority = [
            'completed' => 3,
            'in_progress' => 2,
            'pending' => 1,
            'unknown' => 0,
        ];

        $currentKey = $priority[$current] ?? 0;
        $incomingKey = $priority[$incoming] ?? 0;

        if ($incomingKey >= $currentKey) {
            return $incoming;
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    private function normalizeArray(mixed $data): ?array
    {
        return is_array($data) ? $data : null;
    }

    private function asInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '' && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>|null  $usage
     * @return array<string, int>|null
     */
    private function normalizeUsage(mixed $usage): ?array
    {
        if (! is_array($usage)) {
            return null;
        }

        return [
            'input_tokens' => $this->asInt($usage['input_tokens'] ?? null),
            'cached_input_tokens' => $this->asInt($usage['cached_input_tokens'] ?? null),
            'output_tokens' => $this->asInt($usage['output_tokens'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function normalizeAction(string $type, array $event): array
    {
        $item = $event['item'] ?? [];

        return [
            'type' => $type,
            'item_type' => is_array($item) ? (string) ($item['type'] ?? '') : '',
            'item' => $this->normalizeArray($item) ?? [],
        ];
    }

    /**
     * @return array{index: int, actions: array<int, array<string, mixed>>, usage: array<string, mixed>|null}
     */
    private function newTurn(int $index): array
    {
        return [
            'index' => $index,
            'actions' => [],
            'usage' => null,
        ];
    }
}
