# Shipper

CLI deployment tool for Ploi.io with plan/apply workflow.

## Project Structure

```
shipper/
├── app/          # Laravel Zero CLI application (PHP 8.3+)
├── cli/          # CLI entry points, scripts
├── docs/         # Technical documentation
├── examples/     # Example project configurations
├── specs/        # Feature specifications (Spec-Kit format)
├── website/      # Marketing + docs website (planned)
├── tests/        # Test suite (Pest/PHPUnit)
├── shipper.yml   # Example configuration
└── shipper       # CLI entry point script
```

## Quick Start

```bash
php shipper --help
php shipper validate --config shipper.yml
php shipper plan --project api --profile staging
php shipper apply --project api --profile staging
```

## Key Commands

- `validate` - Validate shipper.yml configuration
- `plan` - Show deployment plan (dry run)
- `apply` - Execute deployment
- `destroy` - Tear down deployment

## Development

```bash
composer install
composer test        # Run tests
composer format      # Fix code style
composer analyse     # PHPStan level 9
```

## Configuration

See `docs/CONFIGURATION.md` for full configuration reference.

## Stack

- PHP 8.3+ with Laravel Zero
- Pest for testing
- PHPStan for static analysis
- Box for PHAR compilation