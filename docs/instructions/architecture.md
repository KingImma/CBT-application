# Application Architecture

Domain-first modular monolith for the CBT Laravel API. Goal: anyone (junior or senior) can open a domain folder and understand that product area without hunting across technical layers.

Inspiration: capability-oriented layout (similar to Midday's domain packages), adapted to a **single Laravel app** — not a multi-package monorepo, not full hexagonal architecture.

## Mental model

```
HTTP (routes + controllers)
        ↓
  Domain Action (use-case)
        ↓
  Models / Jobs / Events
        ↓
  Data (DTO in / out)
```

- Controllers stay thin: authorize, call one action or query, map to Data, return `ApiResponse`.
- Business writes and multi-model workflows live in **Actions**.
- Shared code only when used by **two or more** domains with no natural owner.

## Top-level layout (target)

```
app/
  Domains/
    Academic/       # sessions, terms, class levels, arms, subjects
    Auth/           # login, password, OTP
    Exams/          # lifecycle, attempts, grading, reports, session state
    Import/         # CSV import pipeline
    Questions/      # question bank + options
    Students/       # roster, profiles, queries
    Teachers/       # roster, assignments, reports
    Tenancy/        # tenants, super-admin, plans, provisioning
    Settings/       # school settings, grading scales
  Modules/          # shared dependency services (time, scheduling, persistence, tenancy context)
  Shared/           # cross-cutting only (ApiResponse, enums, global traits)
  Http/             # Controllers, Middleware, Requests
  Console/Commands/ # all Artisan commands
  Jobs/ Mail/ Notifications/ Providers/
  Exceptions/       # Handler + shared base exception only
```

### Light shape inside each domain

Create a subfolder only when it has at least one file:

```
Domains/{Name}/
  Actions/      # one class = one use-case
  Data/         # Spatie Data input/output DTOs
  Models/       # Eloquent models owned by this domain
  Policies/
  Queries/
  Events/
  Exceptions/
  Support/      # pure rules, transitions, domain helpers
  Jobs/         # optional
```

**Migration note:** Legacy code still lives under `app/Actions/`, `app/Data/`, `app/Models/Tenant/`, etc. New work goes into `app/Domains/{Name}/`. Existing code moves domain-by-domain (Exams first). See the architecture plan / phase checklist below.

## Domains (ownership)

| Domain | Owns |
|--------|------|
| **Exams** | Exam, attempts, answers, results, session state, activate/publish, sit-exam, class reports |
| **Questions** | Question bank, options, clone (exam-attached questions stay under Exams) |
| **Students** | Student profiles, roster CRUD, student queries |
| **Teachers** | Teacher profiles, assignments, teacher reports |
| **Academic** | AcademicSession, Term, ClassLevel, ClassArm, Subject, pivots |
| **Settings** | SchoolSetting, GradingScale |
| **Auth** | Password/OTP/change password, authenticate flows |
| **Tenancy** | Tenant, SuperAdmin, SubscriptionPlan, provisioning, plan limits |
| **Import** | CSV import, import jobs/schemas (calls Students/Teachers actions) |

Cross-domain calls are allowed: call the other domain's **Action**, not private Support.

## Naming conventions

| Kind | Pattern | Good | Avoid |
|------|---------|------|--------|
| Action | `{Verb}{Noun}` | `CreateExam`, `StartExamAttempt` | `CloneQuestionAction`, `Student` service |
| Rules helper | `{Noun}Rules` / `{Noun}Transitions` | `ExamLifecycleRules` | Dual `*Guards` + `*Guard` |
| Query | `{Noun}Query` | `StudentQuery` | Ad-hoc fat controller queries for lists |
| DTO | `{Noun}Data` / `{Verb}{Noun}Data` | `ExamData`, `CreateExamData` | Mixing FormRequest + Data on same endpoint |
| Exception | clear event name | `ExamCannotBeActivated` | Deep unused taxonomy |
| Job | `{Verb}{Noun}Job` | `GradeExamAttemptJob` | |
| Command class | `{Verb}{Noun}Command` (optional Command suffix ok) | under `app/Console/Commands` | under `Data/` |

- Prefer **singular domain product names** that match speech: `Exams`, `Students`, `Tenancy` (not mixed `Tenant`/`Tenants`).
- Drop the `Action` class suffix — the folder already says Actions.
- Prefer **one action per use-case** over multi-method entity services (`CreateStudent` + `UpdateStudent`, not `Student`).

## Complexity rules (what not to add)

1. **No base CRUD action wrappers** (`CreateAction` / `UpdateAction` / `DeleteAction` style). Use plain `DB::transaction` and linear code in the action.
2. **No ports/adapters, no repository interface per model**, no Application/Domain/Infrastructure layers.
3. **No empty folders** "for later."
4. **One validation path per endpoint** — Spatie Data is the long-term standard for tenant APIs; do not double-validate with FormRequest and Data on the same body.
5. Prefer model methods for simple state checks (`$exam->canActivate()`); Support classes for pure reusable rules.
6. Do not add new files under legacy `app/Actions/Tenants/...` — use `app/Domains/{Name}/`.

## Domains vs Modules vs Shared

The three layers have one direction of dependency and must not be blurred:

```
Domains  →  Modules  →  Shared
```

- **Domains** own business capability: use-cases, models, domain exceptions, policies. Domains may depend on modules and other domains' Actions/Queries.
- **Modules** are the shared *dependency services* that actions need in order to work — time/date ranges, schedule windows, persistence helpers, tenant context. A module is infrastructure: it must **not** import anything from `App\Domains` and must **not** know about a specific product model. If a helper needs a domain model, it belongs in that domain (or as a cross-domain Action/Query), not in a module.
- **Shared** holds inert primitives with no behaviour beyond formatting: `ApiResponse`, string/CSV helpers, enums, global traits.

When two or more domains need the same behaviour and it is not tied to a single domain's model, extract it into a module instead of copying it or forcing a domain to own it.

### Current modules

| Module | Provides | Typical consumers |
|--------|----------|-------------------|
| `Modules/Time` | `DateRange` — ordered range value object with overlap/containment/duration | sessions, exam slots, any window math |
| `Modules/Schedule` | `ScheduleWindow` — optional-bound window with open/closed predicates | assessment schedules, exam attempts |
| `Modules/Persistence` | `UniqueConstraint::isViolation()` — driver-agnostic duplicate-key detection | any write action that maps a race to a domain exception |
| `Modules/Tenancy` | `TenantContext` — tenant id/name, central check, tenant-scoped cache keys | cache access, jobs, reports |

**Shared primitives:** `ApiResponse`, generic string/CSV helpers, small stable enums, truly global traits.

**Domain:** exam session state, question grading, import schemas, domain exceptions, domain policies.

## Commands

All Artisan commands live in `app/Console/Commands` (`App\Console\Commands`). Registered via `bootstrap/app.php` `withCommands`.

## HTTP surface

- Routes remain domain-split under `routes/tenant/*.php` (already good).
- Controllers under `app/Http/Controllers/Api/...` stay thin; optional later rename to match domains.
- Middleware stays under `app/Http/Middleware`.

## Known debt (tracked for later phases)

| Item | Status |
|------|--------|
| ~~Move commands to `Console/`~~ | ✅ Done (Phase 0) |
| ~~Delete empty `Contracts/`~~ | N/A — never existed |
| ~~Remove `lorisleiva/laravel-actions`~~ | ✅ Done (Phase 0) |
| ~~Delete dead code (`ExamAttemptGuard`, `ExamAttemptStatusTransition`, example jobs)~~ | ✅ Done (Phase 0) |
| ~~Move `MonitorException` to `Shared/Support`~~ | ✅ Done (Phase 0) |
| ~~Inline `app/Concerns/` traits into `CreateTenant`~~ | ✅ Done (Phase 0) |
| ~~Rename `CloneQuestionAction` → `CloneQuestion`~~ | ✅ Done (Phase 0) |
| Move Exams actions/data/support into `Domains/Exams` | Phase 1 |
| Inline/remove Base Create/Update/Delete actions | Phase 1 |
| Split `Student` / `Teacher` services into verb actions | Phase 2 |
| Move Academic, Settings, Questions | Phase 3 |
| Move Auth, Tenancy; slim exception tree | Phase 4 |
| Default scaffold `App\Models\User` vs `Tenant\User` / `SuperAdmin` clarity | Phase 4 |
| Fat controllers without actions | Extract as domains land |

## Where do I change X?

| I need to… | Look in… |
|------------|----------|
| Activate an exam | `Domains/Exams/Actions` |
| Create a student | `Domains/Students/Actions` |
| Change API response envelope | `Shared/Support` |
| Date/range math or overlap checks | `Modules/Time` |
| Open/closed window rules | `Modules/Schedule` |
| Detect a duplicate-key DB error | `Modules/Persistence` |
| Tenant id / tenant-scoped cache key | `Modules/Tenancy` |
| Add an Artisan command | `app/Console/Commands` |
| Tenant provisioning | `Domains/Tenancy/Actions` |
| Sit an exam / submit answers | `Domains/Exams/Actions/Attempts` |
| Reset password / OTP | `Domains/Auth/Actions` |
| Manage sessions & terms | `Domains/Academic/Actions` |
| Clone a question | `Domains/Questions/Actions` |
| Grading scale | `Domains/Settings/Data` |

## Phase checklist

- [x] **Phase 0** — Dead code cleanup; `CloneQuestionAction` renamed; `laravel-actions` removed; Concerns inlined; `MonitorException` relocated; conventions for new code
- [x] **Phase 1** — Exams domain (gold path)
- [x] **Phase 2** — Students, Teachers, Import
- [x] **Phase 3** — Academic, Settings, Questions
- [x] **Phase 4** — Auth, Tenancy, shared cleanup
- [x] **Phase 5** — Namespace audit, empty directory cleanup, broken import fixes, pint verified
- [x] **Phase 6** — Shared dependency services extracted into `app/Modules` (Time, Schedule, Persistence, Tenancy context)
