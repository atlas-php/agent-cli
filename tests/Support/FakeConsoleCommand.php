<?php

declare(strict_types=1);

namespace Atlas\Agent\Tests\Support;

use Illuminate\Console\Command;

/**
 * Class FakeConsoleCommand
 *
 * Captures console output calls for validating renderer behavior.
 */
final class FakeConsoleCommand extends Command
{
    protected $signature = 'fake';

    protected $description = 'Fake command for testing output rendering';

    /**
     * @var array<int, string>
     */
    public array $errors = [];

    /**
     * @var array<int, string>
     */
    public array $infos = [];

    /**
     * @var array<int, string>
     */
    public array $lines = [];

    public function error($string, $verbosity = null)
    {
        $this->errors[] = (string) $string;
    }

    public function info($string, $verbosity = null)
    {
        $this->infos[] = (string) $string;
    }

    public function line($string, $style = null, $verbosity = null)
    {
        $this->lines[] = (string) $string;
    }
}
