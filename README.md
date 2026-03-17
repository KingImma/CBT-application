# CBT Application

A modern Laravel application featuring a Vue 3 frontend with Tailwind CSS and shadcn-vue.

## 🚀 Tech Stack

- **Backend**: Laravel 12.x
- **Frontend**: Vue 3 (Composition API), Vite 7.x
- **UI & Styling**: Tailwind CSS v4, shadcn-vue
- **Database**: PostgreSQL
- **Environment**: Laravel Sail / Docker

## 🛠 Project Structure

- `app/`: Core application logic (Laravel).
- `resources/js/`: Vue source components and application logic.
- `resources/css/`: Styling with Tailwind CSS.
- `docs/`: In-depth documentation for various domains.

## 📖 Documentation

- **[Setup Guide (Docker)](./docs/setup-docker.md)**: Getting started with Laravel Sail / Docker.
- **[Manual Setup Guide](./docs/setup-manual.md)**: Setup instructions for local PHP/Node.
- **[Development Standards](./docs/development-standards.md)**: Coding practices, branching, and PR process.
- **[Contributing Information](./docs/contributing.md)**: Guidelines for making contributions.

## 🚦 Getting Started

Fastest way to start (for Docker users):
```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

Visit `http://localhost` to view the application.

## 📄 License

This project is licensed under the [MIT License](./LICENSE).
