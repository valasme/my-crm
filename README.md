# My CRM

A production-style CRM application built with **Laravel 13**, **Livewire 4 (Volt-style single-file components)**, and **Flux UI 2**.

This project is designed to demonstrate strong full-stack execution for hiring: domain modeling, secure multi-tenant boundaries, reactive UX, database indexing strategy, and deep automated testing.

---

## Recruiter Snapshot

- **Domain breadth:** Companies, Contacts, Activities, Tasks, Deals, Global Search, Settings, and Auth/Security.
- **Architecture depth:** Policies, Form Requests, model observers, queued jobs, transactional updates, and targeted rate limiting.
- **Data integrity:** Composite foreign-key constraints for `primary_contact_id`, ownership isolation, and controlled relationship derivation.
- **UX quality:** Livewire/Flux UI with filter-preserving navigation, paginated tables, and drag-and-drop Kanban stage updates.
- **Testing maturity:** **286 Pest tests** (`203 feature` + `83 unit`) across auth, CRUD, policies, validation, indexing, timeline sync, and Kanban behavior.

---

## Product Overview

### Core CRM Modules

- **Companies**
  - Lifecycle status (`lead`, `prospect`, `customer`, `churned`)
  - Primary contact support with ownership/company integrity constraints
  - Follow-up tracking and account-level activity signals
- **Contacts**
  - Company-linked or standalone contact management
  - Contact method preferences, status lifecycle, and follow-up tracking
- **Activities**
  - Logged interactions (`call`, `email`, `meeting`, `note`)
  - Planned/completed/canceled status workflow
- **Tasks**
  - Follow-up work linked to companies, contacts, or activities
  - Active/inactive state and next follow-up scheduling
- **Deals**
  - Pipeline stages (`lead`, `qualified`, `proposal`, `negotiation`, `won`, `lost`)
  - Amount, currency, probability, expected close date, and `sort_order` sequencing
  - **Kanban board** with stage-to-stage drag/drop updates and in-column reordering

### Cross-Cutting Features

- **Global Search Modal** across all CRM entities (top N results per group)
- **Timeline Sync Automation** for `last_contacted_at` and `next_follow_up_at`
- **Per-user data isolation** via ownership checks + policies
- **Read/write request throttling** per domain resource
- **Fortify auth stack:** registration, login, password reset, email verification, and 2FA

---

## Architecture Highlights

### Backend

- Laravel 13 with conventional domain layering:
  - `app/Http/Requests` for validation + sanitization
  - `app/Http/Controllers` for write operations
  - Eloquent models + policies for domain and authorization rules
  - Observers + queued jobs for asynchronous timeline recalculation
- Defensive persistence patterns:
  - Ownership-scoped model resolution for updates/deletes
  - Transactional deal stage movement and sort-order compaction
  - Strict whitelisting/sanitization for search/filter query params

### Frontend

- Livewire Volt-style page components under `resources/views/pages/**/⚡*.blade.php`
- Flux UI component system for layout, forms, table views, badges, modal search, and navigation
- Filter-preserving navigation patterns for list/detail/edit flows

### Data & Performance

- Core tables: `companies`, `contacts`, `activities`, `tasks`, `deals`
- Focused indexing for listing, filtering, follow-up windows, and timeline sync queries
- Optional full-text indexing on MySQL/MariaDB for selected searchable fields
- Composite foreign keys enforcing valid `primary_contact_id` ownership and company membership

---

## Security & Reliability

- Authorization policies for every CRM aggregate (`Company`, `Contact`, `Activity`, `Task`, `Deal`)
- Route-level auth/verification gates for protected areas
- Dedicated rate-limit buckets for high-frequency read/write endpoints and global search
- Immutable date handling (`CarbonImmutable`) and production protections for destructive DB commands
- Password defaults hardened in production

---

## Tech Stack

| Layer | Details |
|---|---|
| Language | PHP `^8.3` |
| Framework | Laravel `^13.7` |
| Reactive UI | Livewire `^4.1` |
| UI Components | Flux UI `^2.13.1` |
| Auth | Laravel Fortify `^1.34` |
| Styling | Tailwind CSS 4 |
| Build | Vite 8 |
| Testing | Pest 4 + PHPUnit 12 |
| Formatting | Laravel Pint |

---

## Project Structure

```text
app/
  Actions/                # Fortify actions + timeline sync action
  Concerns/               # Validation rule concerns
  Http/
    Controllers/          # Store/update/destroy resource endpoints
    Requests/             # Store/Update form request validation
  Jobs/                   # Timeline sync queue job
  Models/                 # User, Company, Contact, Activity, Task, Deal
  Observers/              # Activity/Task timeline observers
  Policies/               # Resource authorization policies
  Providers/              # Policy registration, rate limiting, Fortify setup

database/
  migrations/             # Schema + constraints + indexes
  factories/              # Test data generation
  seeders/                # Non-production bulk data seeders

resources/views/
  pages/                  # Livewire Volt-style page components
  components/             # Shared UI components incl. global search modal
  layouts/                # Application shell/sidebar

tests/
  Feature/                # End-to-end behavior and HTTP/UI interaction tests
  Unit/                   # Model, policy, validation, and index behavior tests
```

---

## Local Development

### 1) Install and bootstrap

```bash
git clone <your-repo-url> my-crm
cd my-crm
composer run setup
```

### 2) Run the app

```bash
composer run dev
```

### 3) Run tests

```bash
php artisan test --compact
```

### 4) Format code

```bash
vendor/bin/pint --dirty --format agent
```

---

## Database Seeding

`DatabaseSeeder` creates a baseline test user and seeds realistic CRM records (non-production only):

- 50 companies
- 50 contacts
- 50 activities
- 50 tasks
- 40 deals

Default seeded user:
- Email: `test@example.com`
- Password: `password`

---

## Code of Conduct

This repository uses a professional and respectful collaboration standard. See:

- [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md)

---

## License

This project is open-source and licensed under the [MIT License](LICENSE).

---

## Hiring Note

If you are evaluating this repository for hiring, focus on:

- model and schema design decisions
- authorization and tenant isolation strategy
- form request validation rigor
- observer + queue workflow for timeline synchronization
- transaction-safe deal pipeline ordering
- test coverage depth and scenario quality

This codebase is intentionally structured as a portfolio-grade demonstration of practical Laravel engineering.

---

## Contributing

Contributions are welcome for educational improvement under the license terms.
Please open an issue first for major changes.