# Docker Setup Guide

This project uses [Laravel Sail](https://laravel.com/docs/sail), a light-weight command-line interface for interacting with Laravel's default Docker development environment.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop) installed and running.
- [Node.js & npm](https://nodejs.org/en/download/) (optional, if you want to run npm commands locally).

## Getting Started

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd CBT-application
   ```

2. **Install Composer dependencies:**
   Since you don't have PHP installed locally, you can use a small Docker container to install the dependencies:
   ```bash
   docker run --rm \
       -u "$(id -u):$(id -g)" \
       -v "$(pwd):/var/www/html" \
       -w /var/www/html \
       laravelsail/php8.3-composer:latest \
       composer install --ignore-platform-reqs
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   ```

4. **Start the environment:**
   ```bash
   ./vendor/bin/sail up -d
   ```

5. **Generate application key:**
   ```bash
   ./vendor/bin/sail artisan key:generate
   ```

6. **Run migrations:**
   ```bash
   ./vendor/bin/sail artisan migrate
   ```

7. **Install Frontend dependencies:**
   ```bash
   ./vendor/bin/sail npm install
   ```

8. **Start Development Servers:**
   ```bash
   ./vendor/bin/sail npm run dev
   ```

The application will be available at `http://localhost`.

## Useful Sail Commands

- **Stop containers:** `./vendor/bin/sail stop`
- **Run artisan commands:** `./vendor/bin/sail artisan <command>`
- **Run php commands:** `./vendor/bin/sail php <command>`
- **Run npm commands:** `./vendor/bin/sail npm <command>`
- **Run composer commands:** `./vendor/bin/sail composer <command>`
- **Enter shell:** `./vendor/bin/sail shell`
