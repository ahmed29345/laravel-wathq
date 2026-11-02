<?php

namespace Ahmed29345\Wathq\Console;

use Ahmed29345\Wathq\Exceptions\InvalidCrNumber;
use Ahmed29345\Wathq\Exceptions\WathqUnavailable;
use Ahmed29345\Wathq\WathqClient;
use Illuminate\Console\Command;

final class CheckCommand extends Command
{
    protected $signature = 'wathq:check
        {cr : The 10-digit commercial registration number}
        {--json : Print the full result, including the raw response, as JSON}';

    protected $description = 'Look up a commercial registration number on Wathq';

    public function handle(WathqClient $wathq): int
    {
        try {
            $result = $wathq->verifyCr((string) $this->argument('cr'));
        } catch (InvalidCrNumber $e) {
            $this->components->error($e->getMessage());

            return self::INVALID;
        } catch (WathqUnavailable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Field', 'Value'], [
                ['CR number', $result->crNumber],
                ['Found', $result->found ? 'yes' : 'no'],
                ['Active', $result->active ? 'yes' : 'no'],
                ['Status ID', $result->statusId ?? '-'],
                ['Status', $result->statusName ?? '-'],
                ['HTTP status', $result->httpStatus ?? ($result->simulated ? 'test CR (no request)' : '-')],
            ]);
        }

        return $result->active ? self::SUCCESS : self::FAILURE;
    }
}
