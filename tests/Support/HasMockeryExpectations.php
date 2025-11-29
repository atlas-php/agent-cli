<?php

declare(strict_types=1);

namespace Atlas\Agent\Tests\Support;

interface HasMockeryExpectations
{
    public function once(): self;

    /**
     * @param  mixed  ...$args
     */
    public function with(...$args): self;

    public function andReturnSelf(): self;

    /**
     * @param  mixed  ...$args
     */
    public function andReturn(...$args): self;

    public function andReturnUsing(callable $callback): self;

    /**
     * @param  class-string<\Throwable>|\Throwable  $exception
     * @param  mixed  ...$arguments
     */
    public function andThrow($exception, ...$arguments): self;
}
