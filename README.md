# PHP Workbench

A personal PHP workbench for building and experimenting with server-rendered applications, reusable infrastructure, and modular features.

## Overview

`php-workbench` is a PHP application that combines a shared base layer with independent application modules. It serves as a working environment for exploring implementation approaches, developing reusable components, and testing ideas through practical use.

The project uses server-side rendering with Twig, with application logic organised around routing, middleware, models, configuration, and modules.

## Structure

- `app/` — Application controllers.
- `base/` — Shared infrastructure, including request handling, routing-related components, models, sessions, and responses.
- `configs/` — Application configuration.
- `database/` — Database-related files.
- `helpers/` — Shared helper functions.
- `init/` — Application initialisation.
- `middlewares/` — Request middleware.
- `modules/` — Feature-specific application code.
- `public/` — Web server document root and public assets.
- `routes/` — Route definitions.
- `storage/` — Application storage and runtime files.
- `views/` — Twig templates.

## Technologies

- PHP 8.2+
- Composer
- Twig 3
- Bootstrap 5
- PHP dotenv
- PHPUnit for testing

## Setup

1. Clone the repository.
2. Install the Composer dependencies:

   ```bash
   composer install
   ```

3. Create a `.env` file using the configuration expected by the application.
4. Configure the database and other required environment variables.
5. Configure your local web server to serve the `public/` directory as the document root.
6. Run the application and verify that the required services and database are configured correctly.

Refer to the configuration files and application initialisation code for the environment variables and database settings required by your local setup.

## Project Status

This is an evolving personal project, not a standalone framework or a production-ready distribution. Its structure and components may change as new requirements and experiments emerge.

## Licence

Released under the MIT License. See [LICENSE](LICENSE) for details.
