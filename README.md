# Filakit Installer

Filakit Installer is a command-line tool to quickly create new Filakit applications. It provides an interactive and user-friendly experience for bootstrapping your Filakit projects, supporting multiple versions and options for advanced users.

## Features

- Installs the latest or specific versions of Filakit
- Interactive prompts for configuration
- Supports Laravel Herd and Valet environments
- Checks for required PHP extensions
- Force installation even if the directory exists

## Requirements

- PHP >= 8.2
- Composer
- Required PHP extensions: tokenizer

## Installation

Install Filakit Installer globally using Composer:

```bash
composer global require filakitphp/installer
```

Make sure Composer's global bin directory is in your PATH.

## Usage

To create a new Filakit application, run:

```bash
filakit new <project-name>
```

### Options

- `--v4`      Install Filakit v4
- `--force`   Force installation even if the directory already exists

Example:

```bash
filakit new my-app --v4 --force
```

## Development

Clone the repository and install dependencies:

```bash
composer install
```

Run the installer locally:

```bash
php filakit new test-app
```

## Testing

Run tests with PHPUnit:

```bash
composer test
```

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## Author

Jefferson Simão Gonçalves

---

For more information, see the source code or open an issue on the repository.
