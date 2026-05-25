# Manual Setup Guide (Non-Docker)

Follow these steps if you prefer to run the application using local tools instead of Docker.

## Prerequisites

- **PHP** >= 8.2
- **Composer**
- **Node.js** >= 18.x & **npm**
- **PostgreSQL** installed and running.

## Getting Started

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd CBT-application
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   ```
   Edit `.env` and update the `DB_*` variables with your local PostgreSQL credentials.

4. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

5. **Run migrations:**
   ```bash
   php artisan migrate
   ```

6. **Install Frontend dependencies:**
   ```bash
   npm install
   ```

7. **Start Development Servers:**
   We use `concurrently` to run both the Laravel server and Vite dev server:
   ```bash
   npm run dev
   ```
   Alternatively, run them in separate terminals:
   - `php artisan serve`
   - `npm run dev` (for Vite)

The application will be available at `http://localhost:8000`.
