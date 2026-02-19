# Contributing to LearnKit

Thank you for your interest in contributing to LearnKit! This document provides guidelines and workflows for development.

## Development Setup

### Requirements

- PHP 7.4 or higher
- Composer
- WordPress 5.8 or higher
- Lando (for local development)

### Installation

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```

3. Start Lando environment:
   ```bash
   lando start
   ```

## Code Quality Standards

This project follows WordPress Coding Standards and uses automated tools to ensure code quality.

### Running Code Quality Checks

```bash
# Run PHP Code Sniffer
composer phpcs

# Auto-fix PHP Code Sniffer issues
composer phpcbf

# Run PHP Mess Detector
composer phpmd

# Run all linting tools
composer lint
```

### Pre-commit Hook

A pre-commit hook is automatically installed that runs PHPCS on staged files. This ensures code quality before commits.

To bypass the hook (not recommended):
```bash
git commit --no-verify
```

## Testing

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage report
composer test-coverage
```

### Writing Tests

Tests are located in the `tests/` directory and use PHPUnit with Yoast PHPUnit Polyfills.

Test file naming convention:
- `test-*.php` for test files
- `bootstrap.php` for test bootstrap

Example test:
```php
<?php
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class Test_My_Feature extends TestCase {
    public function test_something() {
        $this->assertTrue( true );
    }
}
```

## Workflow

### Creating a Feature Branch

```bash
git checkout -b feature/your-feature-name
```

### Making Changes

1. Write your code
2. Write tests for your code
3. Run code quality checks: `composer lint`
4. Run tests: `composer test`
5. Commit your changes with clear messages

### Commit Messages

Use clear, descriptive commit messages:

```
Add enrollment API endpoint

- Implement POST /learnkit/v1/enrollments
- Add input validation
- Include unit tests
```

### Submitting Changes

1. Push your branch to GitHub
2. Create a Pull Request
3. Ensure all CI checks pass
4. Request review from maintainers

## CI/CD

GitHub Actions automatically runs tests on:
- Push to main, develop, and feature branches
- Pull requests to main and develop

Tests run against:
- PHP 7.4, 8.0, 8.1, 8.2
- WordPress latest and latest-1

## Code Style

- Follow WordPress Coding Standards
- Use meaningful variable and function names
- Document all functions with PHPDoc blocks
- Keep functions small and focused
- Write defensive code with proper error handling

## Questions?

If you have questions or need help, please open an issue on GitHub.
