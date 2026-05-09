<?php

declare(strict_types=1);

class ValidateCommandTest extends Tests\TestCase
{
    public function testValidateShowsErrorForMissingConfig(): void
    {
        $command = $this->artisan('validate', ['--config' => 'nonexistent.yml']);
        /** @phpstan-ignore-next-line */
        $command->assertExitCode(1);
    }

    public function testValidateRunsSuccessfullyWithValidConfig(): void
    {
        $command = $this->artisan('validate', ['--config' => 'shipper.yml']);
        /** @phpstan-ignore-next-line */
        $command->assertExitCode(0);
    }
}
