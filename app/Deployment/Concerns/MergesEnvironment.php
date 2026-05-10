<?php

declare(strict_types=1);

namespace App\Deployment\Concerns;

trait MergesEnvironment
{
    /**
     * Merge environment variables into existing .env content.
     *
     * @param array<string, string> $variables
     */
    public function mergeEnvContent(string $existing, array $variables): string
    {
        if ($variables === []) {
            return $existing;
        }

        $lines = \explode("\n", $existing);
        $resultLines = [];
        $hasNonEmpty = false;

        foreach ($lines as $line) {
            $trimmed = \trim($line);

            if ($trimmed === '' || $trimmed[0] === '#') {
                $resultLines[] = $line;
                if ($trimmed !== '') {
                    $hasNonEmpty = true;
                }
                continue;
            }

            $equalPos = \strpos($trimmed, '=');
            if ($equalPos !== false) {
                $key = \substr($trimmed, 0, $equalPos);
                $hasNonEmpty = true;

                if (isset($variables[$key])) {
                    $resultLines[] = $key.'='.$variables[$key];
                    unset($variables[$key]);
                } else {
                    $resultLines[] = $line;
                }
            } else {
                $resultLines[] = $line;
            }
        }

        if ($variables !== []) {
            if ($hasNonEmpty) {
                $resultLines[] = '';
            }
            foreach ($variables as $key => $value) {
                $resultLines[] = $key.'='.$value;
            }
        }

        return \implode("\n", $resultLines);
    }
}
