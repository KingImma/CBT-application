# Development Standards

To maintain code quality and consistency, all contributors are expected to follow these standards.

## Coding Style

### Backend (PHP/Laravel)
- Follow **PSR-12** coding standards.
- Use `laravel/pint` for automated styling: `vendor/bin/pint`.
- Use type hints and return types wherever possible.
- Keep controllers lean; use Services or Actions for business logic.

#### Model Conventions
- Default: all Eloquent models must set `protected $guarded = ['id']` for mass-assignment protection.
- Exception: models that assign their primary key manually (`$incrementing = false` AND no auto-generating trait like `HasUuids`) must NOT guard `id`, or `Model::create(['id' => ...])` silently drops the key.

#### Refactoring Checklist
When deleting or renaming a class:
1. Run `rg "OldClassName" app/ --type php` (or equivalent) to find all remaining references.
2. Update or remove each reference before committing the deletion.

### Frontend (Vue/Vue)
- Use **Vue 3 Composition API** with `<script setup>`.
- Use **Tailwind CSS** for styling.
- Use **shadcn-vue** for UI components.
- Follow the component structure in `resources/js/components`.

## Branching Strategy

- **main**: Production-ready code.
- **develop**: Integration branch for features.
- **Feature Branches**: `feature/short-description`
- **Bug Fixes**: `fix/short-description`
- **Hotfixes**: `hotfix/short-description`

## Pull Request Process

1. Create a branch from `develop`.
2. Implement your changes and add tests if applicable.
3. Ensure the project builds successfully: `npm run build`.
4. Open a PR against `develop`.
5. Fill out the PR template completely.
6. Await review from at least one peer.

## Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):
- `feat: add user authentication`
- `fix: resolve login redirect loop`
- `docs: update setup instructions`
- `style: linting fixes`
- `refactor: simplify database query`
