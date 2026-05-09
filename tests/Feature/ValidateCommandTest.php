<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

final class ValidateCommandTest extends Tests\TestCase
{
    public function test_validate_shows_error_for_missing_config(): void
    {
        $exitCode = Artisan::call('validate', ['--config' => 'nonexistent.yml']);
        $this->assertEquals(1, $exitCode);
    }

    public function test_validate_runs_successfully_with_valid_config(): void
    {
        $exitCode = Artisan::call('validate', ['--config' => 'shipper.yml']);
        $this->assertEquals(0, $exitCode);
    }
}
