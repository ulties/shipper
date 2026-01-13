<?php

declare(strict_types=1);

\test('validate command runs successfully with valid config', function (): void {
    $this->artisan('validate', ['--config' => 'shipper.yml'])
        ->expectsOutput('Validating configuration: shipper.yml')
        ->assertExitCode(0);
});

\test('validate command shows error for missing config', function (): void {
    $this->artisan('validate', ['--config' => 'nonexistent.yml'])
        ->assertExitCode(1);
});
