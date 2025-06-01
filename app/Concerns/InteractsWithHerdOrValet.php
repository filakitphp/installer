<?php

declare(strict_types = 1);

namespace Filakit\Concerns;

use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

trait InteractsWithHerdOrValet
{
    /**
     * Determine if the given directory is parked using Herd or Valet.
     */
    public function isParkedOnHerdOrValet(string $directory): bool
    {
        $output = $this->runOnValetOrHerd('paths');

        $decodedOutput = json_decode($output);

        return is_array($decodedOutput) && in_array(dirname($directory), $decodedOutput);
    }

    /**
     * Runs the given command on the "herd" or "valet" CLI.
     *
     * @return string|false
     */
    protected function runOnValetOrHerd(string $command): false | string
    {
        foreach (['herd', 'valet'] as $tool) {
            $process = new Process([$tool, $command, '-v']);

            try {
                $process->run();

                if ($process->isSuccessful()) {
                    return trim($process->getOutput());
                }
            } catch (ProcessStartFailedException) {
            }
        }

        return false;
    }
}
