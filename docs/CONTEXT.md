# GRM Sierra Leone — rewrite context

Paste this file at the start of any new chat to brief Claude on the project
before asking for feature improvements.

---

## 1. What this is

A Laravel 11 + Vue 3 + Inertia 2 rewrite of the Sierra Leone Grievance
Response Management (GRM) system. The legacy app was Laravel 8 + Vue 2, both
EOL. This rewrite lives in `/home/jmwebaze/grm_sl/rewrite/`; the legacy app
lives in `/home/jmwebaze/grm_sl/`.

The rewrite is deployed via Docker on this host and accessible at:

- **URL:** http://104.225.218.102:8081/
- **Container:** `grm-sl`
- **Image:** `grm-sl-rewrite:dev`
- **Database:** SQLite at `/app/database/database.sqlite` inside the container
- **Port map:** `0.0.0.0:8081` → container `:8000` (`php artisan serve`)

---

## 2. Tech stack

| Layer | Version | Notes |
|---|---|---|
| PHP | 8.3 | docker-php-alpine |
| Laravel | 11.51 | bootstrap/app.php style, bootstrap/providers.php |
| Vue | 3.4 | Composition API, `<script setup>` |
| Inertia | 2.x | `@inertiajs/vue3`, ZiggyVue for `route()` |
| Vite | 5 | builds to `public/build/` |
| Tailwind | 3.4 | Inter font |
| Fortify + Sanctum | latest | username-based login against `person` table |
| Spatie Permission | 6 | RBAC, roles + permissions |
| Spatie QueryBuilder | 5 | admin index filtering |

No AdminLTE / BootstrapVue / Laravel Mix — those were legacy-only.

---

## 3. Architecture — domain layout

Code is organized by bounded context under `app/Domain/`:

```
app/Domain/
├── Audit/            AuditEntry + AuditLogger service. Event-driven; listeners
│                     in Listeners/ capture GrievanceSubmitted, StateChanged,
│                     UserRoleAssigned.
│
├── Grievance/        The core. State machine, timeline, actions, feedback.
│   ├── Enums/        GrievanceState (8 states), ActionType, FeedbackRating
│   ├── Models/       Grievance, Complainer, Suspect, Beneficiary, Action,
│   │                 StatusHistory, Attachment, Feedback, FeedbackToken, Access
│   ├── Services/     GrievanceWorkflow (state machine — single owner of all
│   │                 transitions), GrievanceTimeline (merged stream)
│   ├── Actions/      Single-purpose invokables: SubmitGrievance,
│   │                 AssignGrievanceNumber, PostAction, IssueFeedbackToken,
│   │                 RecordFeedback, ClassifyGrievance, AssignGrievance
│   ├── Events/       GrievanceSubmitted, GrievanceStateChanged, FeedbackRequested
│   ├── Listeners/    SendGrievanceReceivedNotification, SendStateChange…,
│   │                 SendFeedbackInvitation
│   ├── Notifications/  GrievanceReceived, StateChanged, FeedbackInvitation,
│   │                   GrievanceAssigned (all implement ShouldQueue)
│   ├── Policies/     GrievancePolicy — the authorization centrepiece
│   └── Http/Controllers/  Admin/{Grievance,Action,Classification,Assignment}Controller,
│                          PublicGrievanceController, FeedbackController
│
├── Identity/         Users, roles, user admin. User maps to `person` table.
│   ├── Models/User.php   (with HasRoles, Notifiable, phone_number,
│   │                      primary_organization_id)
│   ├── Actions/InviteUser  (never set password directly; emails reset link)
│   ├── Events/UserRoleAssigned
│   └── Policies/{User,Role}Policy (super-admin lock protections)
│
├── Locality/         Sierra Leone geography hierarchy:
│                     Country → Region → District → Chiefdom → Section → Locality
│
├── Notification/     SMS gateway abstraction + preferences + inbound webhook
│   ├── Contracts/SmsGateway, SmsDispatchResult
│   ├── Gateways/LogSmsGateway (dev/test default), AfricasTalkingGateway
│   ├── Channels/SmsChannel + SmsMessage
│   ├── Services/InboundSmsHandler (STATUS <g-number> command)
│   └── Models/NotificationPreference, SmsOutbox, SmsInbox
│
├── Organization/     Organization, Office, Employee, Programme + their policies.
│                     OrganizationPolicy + EmployeePolicy enforce org-admin scoping
│                     via user's primary_organization_id.
│
├── Reference/        10 lookup tables: grievance_type, how_reported, priority,
│                     status, action_type, area, case_concept, review_outcome,
│                     satisfaction, feedback_status.
│                     Shared abstract ReferenceController + 10 concrete subclasses,
│                     shared abstract ReferencePolicy.
│
└── Reporting/        ReportAggregator (cached read-model, 5-min Redis/file TTL,
                      invalidated on GrievanceStateChanged). ReportsController
                      serves /dashboard + /admin/reports.
```

---

## 4. Workflow — the business rule chain

This is what the application's authorization model defends:

1. **Registration** — public submits at `/submit-grievance` (anonymous or
   identified). reCAPTCHA v3 + rate-limit (5/min, 30/hour per IP).
   State: `submitted`. An ack notification (email + SMS) goes out via the
   `SendGrievanceReceivedNotification` listener.

2. **ACC review + classification** — Anti-Corruption Commission staff pick
   up the case from the intake queue. They review (approve / reject /
   trash) and classify (route to responsible org). Only ACC (or super-
   admin / cross-org supervisor) can see the `submitted` / `under_review`
   queue.

3. **Restricted types stay at ACC.** `Corruption` and `Gender-Based
   Violence` grievances are always routed to ACC and cannot be reassigned.
   `ClassifyGrievance` silently forces `classified_organization_id = ACC`
   for these types; `GrievancePolicy` refuses non-ACC access even if the
   FK were manipulated.

4. **Routing to relevant organization** — for other types, GRM Officer /
   ACC reviewer sets `classified_organization_id`. Once set, state
   transitions via `UnderReview → InProgress` (this transition auto-fires
   when an ACC reviewer posts an `Investigate` or `Contact` action).

5. **Org-scoped visibility** — after classification, only employees of the
   owning org see the case. `GrievancePolicy::view` checks
   `user.primary_organization_id === grievance.classified_organization_id`.

6. **Intra-org hierarchy** — within an org:
   - **GRM Officer** (role `grm-officer`) has admin rights over ALL org
     cases: view, assign, reassign, classify, transition, edit any case.
   - **Organization Officer** (role `organization-officer`) can view any
     org case but can only edit/transition/upload for cases they're
     assigned to. Enforced via `grievance.assign` permission —
     grm-officer has it, organization-officer does not.
   - The distinction is enforced via `canAdminister($user)` (has
     `grievance.assign` perm) OR `isAssignedTo($user, $case)`
     (`$user->id === $case->assigned_officer_id`) in
     `GrievancePolicy::update` and `::transition`.

7. **Processing** — assigned officer (or GRM Officer) posts actions
   (investigate / contact / update / resolve). Posting a `resolve` action
   auto-transitions to `Resolved` and issues a tokenised signed URL to
   the complainant.

8. **Feedback + closure** — complainant clicks the URL (no login — token
   is the credential, one-shot, 30-day expiry), rates 1–5. Rating ≤ 2
   auto-escalates back to `InProgress` via `FeedbackRating::triggersEscalation`;
   rating ≥ 3 closes the case.

---

## 5. Authorization model (cheat sheet)

| Ability | Who passes |
|---|---|
| `view` | super-admin OR cross-org supervisor OR employee of owning org (ACC for intake/restricted types) |
| `update`, `transition` | above AND (has `grievance.assign` OR is the assigned officer) |
| `assign`, `classify` | above AND has `grievance.assign` (GRM Officer or ACC reviewer) |
| `review` | ACC member OR cross-org supervisor |
| `delete` | super-admin only (via `Gate::before`) |

Super-admin bypass is in `app/Providers/AuthServiceProvider::boot()` via
`Gate::before(fn ($u) => $u->hasRole('super-admin') ? true : null)`.

Cross-org supervisor = user with permissions but no `primary_organization_id`
(treat as head-office oversight).

---

## 6. State machine — `GrievanceState`

```
submitted ──► under_review ──► in_progress ──► resolved ──► closed
                │                   │              │
                ├─► rejected        └─► trashed    └─► escalated ──┐
                │                                                   │
                └─► trashed                                          └─► in_progress
```

`GrievanceWorkflow::transition()` in `app/Domain/Grievance/Services/` is the
ONLY legal way to change state. It:
- Rejects illegal transitions (`InvalidTransition` exception)
- Writes a `GrievanceStatusHistory` row
- Stamps `reviewed_at`/`resolved_at`/`closed_at`
- Dispatches `GrievanceStateChanged`

Never set `$grievance->state = …` directly anywhere.

Terminal: `rejected`, `trashed`, `closed`.

---

## 7. Demo personas — all password `ChangeMe123!`

| Username | Org | Role | Use for |
|---|---|---|---|
| `admin` | — | super-admin | Everything; bypasses policies |
| `acc_reviewer` | ACC | acc-reviewer | Intake queue + ACC-retained cases |
| `mohs_grm` | MoHS | grm-officer | Demo GRM Officer admin powers |
| `mohs_officer` | MoHS | organization-officer | Demo assigned-only edit |
| `mohs_jalloh` | MoHS | organization-officer | Demo "same org, not assigned" |
| `mbsse_grm` | MBSSE | grm-officer | |
| `mbsse_officer` | MBSSE | organization-officer | |
| `fcc_grm` | FCC | grm-officer | |
| `fcc_officer` | FCC | organization-officer | |
| `fcc_fofana` | FCC | organization-officer | |
| `mwr_grm` | MWR | grm-officer | |

Seeded: 20 grievances routed to these orgs (Corruption/GBV → ACC, Health →
MoHS, Education → MBSSE, Environmental → MWR, Service Delivery /
Infrastructure / Employment / Land Dispute → FCC). Cases in
`in_progress`/`resolved`/`closed`/`escalated` states have a random
organization-officer assigned.

---

## 8. Running / debugging

```bash
# Logs
docker logs -f grm-sl
docker exec grm-sl tail -f /app/storage/logs/laravel.log

# Shell inside
docker exec -it grm-sl sh

# Artisan
docker exec grm-sl php artisan route:list
docker exec grm-sl php artisan tinker
docker exec grm-sl php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder --force
docker exec grm-sl php artisan permission:cache-reset

# Rebuild frontend without rebuilding image
docker cp rewrite/resources/js/Pages/Whatever.vue grm-sl:/app/resources/js/Pages/Whatever.vue
docker exec grm-sl npx vite build
docker restart grm-sl

# Rebuild image (full, ~3 min)
cd rewrite && docker build -t grm-sl-rewrite:dev .
docker rm -f grm-sl && docker run -d --name grm-sl -p 8081:8000 grm-sl-rewrite:dev

# Restart to reset PHP opcache after in-container edits
docker restart grm-sl
```

---

## 9. Development conventions

When adding features, match the existing patterns:

1. **Single-purpose actions** in `app/Domain/<Context>/Actions/` — invokable
   classes (`public function __invoke`). Transactional when they touch
   multiple models.
2. **FormRequest** per mutating endpoint. No `$request->all()`.
3. **Resource** per JSON output. Never return Eloquent models directly.
4. **Policy** for every mutating route — middleware is
   `can:<ability>,<param-or-class>`. Routes are policy-gated, not controller-
   gated.
5. **Events + listeners** for side effects (notifications, audit,
   cache invalidation). Wire in `EventServiceProvider`.
6. **`RecordsAuthorship` trait** on models with `created_by_id`/`updated_by_id`
   columns. Don't set those columns manually — the trait does it from
   `auth()->id()`.
7. **Enum-based status, priority, rating** — no stringly-typed state.
8. **Tests** (Pest) in `tests/Feature/<Context>/`. Authorize + happy-path +
   edge-case minimum.
9. **Inertia page** = `resources/js/Pages/<Context>/<Action>.vue`.
   `<script setup>` style, no TypeScript strict mode (for now — scaffold
   has known strict-mode warnings we skip with `npx vite build` not
   `npm run build`).
10. **Route model binding** works because we have explicit `Route::bind()`
    for custom-named lookups and Laravel-default for grievances, users, etc.
11. **Super-admin bypass** is global. Don't hand-code "if super-admin" in
    policies — `Gate::before` handles it.

---

## 10. Known gaps / deferred

Things that are noted but not yet built:

- **TypeScript strict mode warnings** in several Vue pages (Ziggy global,
  DOMPurify namespace types). Frontend builds with `npx vite build` skipping
  `vue-tsc`. Would need cleanup for `npm run build` to pass.
- **No employee → user FK** — every User has `primary_organization_id`, but
  Employee model is a separate contact directory. Proper merge pending.
- **Officer roster on an employee record** — no UI to promote an Employee
  to a User account.
- **File uploads on grievances** after submission — model exists
  (`GrievanceAttachment`), public submission form handles uploads, but no
  admin UI for post-submission document uploads yet.
- **Password reset email flow** — Fortify is configured but SMTP is set to
  `log`; in dev, reset emails land in `/app/storage/logs/laravel.log`.
- **Queue worker** — `QUEUE_CONNECTION=sync` in the demo container. Queued
  notifications run inline. Production needs Redis + Horizon.
- **MySQL migration** — demo uses SQLite. `ReportAggregator` has been
  patched to use `julianday()` for SQLite; needs portable query for MySQL
  prod deployment.
- **Multi-org membership** — users have a single `primary_organization_id`.
  Multi-org would use the existing `office_person` pivot; not done.
- **Reclassification audit** — if a GRM Officer re-routes a case to another
  org, there's no explicit "reclassified" entry in the timeline beyond the
  `ClassifyGrievance` action's update stamp.
- **Reports CSV/PDF export** — the service is ready; queued job + button
  not yet.
- **Localization** — English only. Krio, Mende, Temne are targets for
  later.

---

## 11. Files most useful for feature work

- `app/Domain/Grievance/Policies/GrievancePolicy.php` — the big authorization surface
- `app/Domain/Grievance/Services/GrievanceWorkflow.php` — transition rules
- `app/Domain/Grievance/Services/GrievanceTimeline.php` — merged event stream
- `app/Domain/Grievance/Actions/` — the "verbs" of the system
- `app/Providers/EventServiceProvider.php` — event → listener map
- `app/Providers/AuthServiceProvider.php` — policy registration + Gate::before
- `config/grm.php` — ACC acronym + restricted-type list
- `config/notifications.php` — SMS driver config
- `database/seeders/RolePermissionSeeder.php` — role definitions
- `database/seeders/DemoDataSeeder.php` — demo personas + case classification map
- `routes/web.php` — route definitions with policy middleware
- `resources/js/Pages/Grievance/Admin/Show.vue` — the case detail UX
- `resources/js/Components/Timeline.vue` / `ActionComposer.vue` — timeline UX
- `docs/roadmap.md` — phase-by-phase build log

---

## 12. How to prompt for feature improvements

Effective prompts against this codebase:

- **Reference exact files and paths** (the ones listed in §11 are stable).
- **Name the policy ability** you're changing (`view`, `update`, `assign`…).
- **Name the state** you're touching (`under_review`, `in_progress`…).
- **Call out the workflow rule** from §4 it relates to. E.g. "per the rule
  in §4.6, I want …" — lets Claude skip to the right concept.
- **Specify whether you want**:
  - source changes + rebuild (durable but slow)
  - in-container patches (fast but lost on rebuild)
  - both (source + container for immediate testing)
- **Say what you don't want** — e.g. "don't change the state machine,"
  "don't add new permissions," "keep the existing UI, just add X."
- **Ask for tests** explicitly if you want them. The policy has 100%
  coverage of the happy path; edge cases are worth asking about.

Example good prompt:

> Following the context in the pasted file: the GRM Officer (§4.6) should
> be able to add an *internal note* to a case that only officers in the
> same org can see — never exposed to the complainant. Add an
> `ActionType::InternalNote`, hide it from the public status page and the
> feedback timeline, but show it in the admin Show timeline. Update source
> + container, add a Pest test for the visibility rule. Don't modify
> existing action types or the state machine.

---

## 13. Metadata for this file

- Last updated: 2026-04-15 (state of code: intra-org RBAC pass complete).
- If the URL, personas, or workflow rules change materially, regenerate
  this file with `./vendor/bin/pest` + a fresh seed to ensure accuracy.
- This is context for feature work; it is NOT a replacement for reading
  the specific files mentioned in §11.
