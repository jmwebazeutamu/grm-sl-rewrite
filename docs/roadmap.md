# Roadmap

Absolute dates from rewrite kick-off (insert date when Phase 1 starts).

## Phase 0 — Containment (done in legacy)

- [x] Remove unauthenticated `/clear-all`, `/link-storage`, `/route-cache`, `/optimize`, `/clear-cache`, `/view-clear`, `/config-clear` from legacy `routes/web.php`.
- [x] Fix 4 plain-text `v-html` bugs (Complainer/Suspect/Office address, job payload).
- [x] Verify authorization coverage — actually good (22/22 on GrmGrievance). One Media entity and the disabled Message module are the only real gaps.

## Phase 1 — Foundation (target: 3–4 weeks, Medium)

- [x] Rewrite workspace scaffolded (composer.json, package.json, Vite, TS, Tailwind, Pint, PHPStan, ESLint, Prettier).
- [x] Laravel 11 bootstrap + Inertia middleware with typed props.
- [x] Fortify configured for `username` login against `person`.
- [x] Spatie v6 role+permission seed (super-admin, grm-officer, organization-admin, complainant).
- [x] Reference module (Locality) — Controller/Action/FormRequest/Resource/Policy/Tests.
- [x] Vue 3 pages for Locality (Index/Create/Show).
- [x] `safe-html` sanitizer for rich text.
- [x] GitHub Actions CI (Pint + PHPStan + Pest + ESLint + typecheck + npm audit + composer audit).
- [ ] `composer create-project` + `npm install` run locally; fill in stock Laravel files.
- [ ] Seeder for hierarchical localities (country → region → district → chiefdom → section → locality) from existing legacy data.

## Phase 2 — Reference & Geography (2–3 weeks, Low–Medium)

- [x] Reference tables (10): `grievance_type`, `how_reported`, `priority`, `status`, `action_type`, `area`, `case_concept`, `review_outcome`, `satisfaction`, `feedback_status`. Migration, models, shared `ReferenceController` + 10 concrete controllers, shared `ReferencePolicy` + 10 concrete policies.
- [x] Organization module: `organization`, `office`, `office_person` (pivot), `employee`, `programme`, `organization_grievance_type` (pivot). Migration, models, policies, `OrganizationController` (full CRUD + grievance-type attachment), `EmployeeController` (full CRUD).
- [x] `RecordsAuthorship` trait replaces the legacy `date_created`/`last_updated`/`created_by_id`/`modified_by_id` pattern with standard Laravel timestamps + `created_by_id`/`updated_by_id`.
- [x] `RolePermissionSeeder` expanded with every Phase 2 resource and a new `reference-admin` role.
- [x] Policies registered in `AuthServiceProvider`; `reference()` route helper applies `can:` middleware on every endpoint.
- [x] Admin Vue pages: Locality, GrievanceType (full), CaseConcept (Index + Create), Organization (full), Employee (full). Shared `ReferenceTable` + `NameOnlyForm` components serve the other 8 lookups with negligible per-lookup markup.
- [x] Pest tests: `LocalityTest`, `GrievanceTypeTest` (including authorship stamping), `OrganizationTest` (including self-parent rejection and soft-delete), `EmployeeTest`.
- [ ] Remaining: copy `GrievanceType/*.vue` for the 7 simple lookups and `CaseConcept/*.vue` for Priority/Status (which have extra fields). Mechanical, estimated 2–3 hours.
- [ ] Office & Programme controllers + Vue (same template; use Locality as reference).
- [ ] Seeder to backfill `country → region → district → chiefdom → section → locality` from legacy data.

## Phase 3 — Grievance Intake (4–6 weeks, High)

- [x] Explicit `GrievanceState` backed enum replaces the legacy row-count-inference pattern. Terminal-state semantics live on the enum, not in controllers.
- [x] Migrations: `grievance`, `grievance_status_history`, `grievance_complainer`, `grievance_suspect`, `grievance_beneficiary`, `grievance_classification`, `grievance_attachment`, `access`. Soft-deletes on `grievance`.
- [x] Models with relationships + `RecordsAuthorship` on top-level entities.
- [x] `GrievanceWorkflow` service: single entry point for all state transitions, atomic history writes, `GrievanceStateChanged` event dispatch, `InvalidTransition` exception for illegal moves.
- [x] `SubmitGrievance` action: transactional intake (grievance + complainer + suspects + beneficiaries + attachments + initial history row), dispatches `GrievanceSubmitted`.
- [x] `AssignGrievanceNumber` action: `GRM-YYYY-NNNNNN` sequential numbering, collision-safe.
- [x] `PublicGrievanceController`: anonymous + identified submission, confirmation page, status lookup by `g_number`.
- [x] Admin `GrievanceController`: index with filters, show, transition endpoint.
- [x] `GrievancePolicy` with `transition`, `review`, and `view_pii` abilities beyond the standard five.
- [x] `SubmitGrievanceRequest` with reCAPTCHA rule (auto-bypasses when secret unset — dev/test friendly).
- [x] Rate limiter `grievance-submit` (5/min, 30/hour per IP) registered in `RouteRateLimiters`.
- [x] `GrievanceResource` masks complainer PII when `is_anonymous` is true and viewer lacks `grievance.view_pii`.
- [x] `RolePermissionSeeder` gains grievance-specific extras (transition/review/view_pii) and grants them to `grm-officer`.
- [x] Vue pages: public submit (single-screen with optional suspect array and file uploads), confirmation, status; admin index (filter + state chip) and show (workflow sidebar with history).
- [x] Pest tests: `PublicSubmissionTest` (6 cases), `WorkflowTest` (7 cases), `AdminControllerTest` (5 cases).
- [ ] Dusk E2E for the public submission happy path (deferred — requires Chromedriver installed locally).
- [ ] Attachments: currently go to `local` disk; swap to `s3` via config when credentials are ready.
- [ ] Complainant self-service portal (authenticated user views their own cases via a `person` FK on complainer) — deferred.

## Phase 4 — Resolution, Feedback, Reporting (4–5 weeks, Medium–High) — **simplified & modernised**

- [x] **State machine simplified** from 10 states → 8, with the middle three (Approved / Classified / Assigned) collapsed into a single `InProgress`. Happy path is now four transitions: `Submitted → UnderReview → InProgress → Resolved → Closed`. Classification moved onto the grievance as editable metadata (no more state gate). Legacy mapping documented in `data-migration.md`.
- [x] **Unified `grievance_action` table** replaces the three legacy tables (`grievance_action_taken`, `grievance_remark`, `grievance_resolution`). One `ActionType` enum (Investigate / Contact / Update / Resolve / Escalate) discriminates purpose.
- [x] `GrievanceTimeline` service merges status history + actions + feedback into one chronological stream — the admin Show page is now a single-pane timeline (Slack/GitHub style) instead of tabs across three tables.
- [x] `PostAction` action auto-advances state: posting an `Investigate` or `Contact` from `UnderReview` moves the case to `InProgress`; posting a `Resolve` moves to `Resolved` and issues a feedback token. One click replaces the legacy "click transition, then fill form" two-step.
- [x] **Tokenised public feedback** — no complainant login required. Token generated when case resolves, `GrievanceFeedbackToken` is one-shot (consumed_at) with expiry. Signed URL delivered by Phase 5 SMS/email listener.
- [x] `FeedbackRating` enum with `triggersEscalation()` — dissatisfied ratings auto-escalate via the workflow, no officer action needed.
- [x] `ReportAggregator` cached read-model (Redis, 5-min TTL) with six aggregates: state counts, submissions per day, avg resolution, SLA breaches, by region, by type. `InvalidateReportCache` listener attaches to `GrievanceStateChanged` / `GrievanceSubmitted`.
- [x] Dashboard is now widget-based (`MetricCard` + `Sparkline`) not a placeholder — active cases, SLA breaches, avg resolution, closed count, 14-day trend, state distribution. Reports page shows by-region and by-type bar breakdowns.
- [x] `ActionComposer` Vue component: single textarea + action-type dropdown at the bottom of the Show page. Inline posting via Inertia with `preserveScroll`.
- [x] Pest tests added: `ActionTest` (3 cases), `FeedbackTest` (5 cases covering token lifecycle and rating-driven transitions), `TimelineTest`, `AggregatorTest` (3 cases covering cache behavior), plus `WorkflowTest` updated to the simplified state set.
- [x] Nav updated; Dashboard route now served by `ReportsController::dashboard`.
- [ ] Queued Excel/PDF exports (deferred — infra in Phase 5 when the queue worker lands).
- [ ] `GrievancePolicy` additional PII rules for `view_pii` when viewer is not the assigned officer (deferred).

## Phase 5 — Notifications & SMS (2–3 weeks, Medium) — **simplified & modernised**

- [x] **SMS gateway abstraction** (`SmsGateway` contract) with two implementations: `AfricasTalkingGateway` (live + sandbox via `AT_USERNAME=sandbox`) and `LogSmsGateway` (default in dev/test, writes to the log channel so developers see what would have been sent). Config-driven selection via `SMS_DRIVER`, auto-falls-back to Log when credentials are missing.
- [x] **Laravel `SmsChannel`** reads a `SmsMessage` VO returned by the notification's `toSms()`. Null result → no-op (respects preferences and missing phone numbers).
- [x] Four queued notifications, all implementing `ShouldQueue`:
  - `GrievanceReceivedNotification` (submission ack — mail + SMS)
  - `GrievanceStateChangedNotification` (meaningful transitions — mail + SMS; silent on Resolved/Closed/Trashed)
  - `FeedbackInvitationNotification` (carries the tokenised signed URL — mail + SMS)
  - `GrievanceAssignedNotification` (internal; in-app + email only — officers see SMS in their preferences if they want it)
- [x] Three listeners wired in `EventServiceProvider`: `SendGrievanceReceivedNotification` → `GrievanceSubmitted`, `SendStateChangeNotification` → `GrievanceStateChanged`, `SendFeedbackInvitation` → `FeedbackRequested`. All use `Notification::route()` for complainer dispatch (complainer isn't a User).
- [x] **Preferences simplified to three boolean toggles** (in-app, email, SMS) — not a per-event matrix. Default all-on. Per-channel opt-out. `User::preferredChannels()` resolves the list for internal notifications; external (complainant) notifications use whichever contact details the case has.
- [x] Settings page: `/settings/notifications` with explanations per channel and a disabled-state for SMS when no phone is on record.
- [x] **Inbound SMS webhook** `/webhooks/sms/inbound` parses one command: `STATUS GRM-YYYY-NNNNNN`. Anything else returns a help reply. Shared-secret header (`X-Inbound-Secret`) + rate limiter (120/min per IP) + full `sms_inbox` audit log. Not an IVR — deliberate.
- [x] SMS outbox/inbox tables (`sms_outbox`, `sms_inbox`) for operator visibility and forensic traceability when delivery receipts go missing.
- [x] Tests: `GrievanceNotificationTest` (5 cases), `SmsGatewayTest` (4 cases incl. HTTP fake), `InboundSmsTest` (5 cases), `PreferencesTest` (4 cases). 18 new feature tests.
- [x] Config file `config/notifications.php` centralises SMS driver, log channel, and inbound secret.
- [ ] Localization to Krio/Mende/Temne — English only for now (Phase 6+ localisation pass).
- [ ] Queue worker deployment (Redis + Horizon) — infra work, documented in ops runbook.
- [ ] Phone-number E.164 normalisation at intake — defer; AT accepts +232 and 0### alike.

## Phase 6 — Admin, RBAC, Audit (2 weeks, Medium)

- [ ] Role/permission management UI.
- [ ] Audit log (model events → `audit_log` table).
- [ ] Team/organization admin delegation UI.

## Phase 7 — Cutover (2–3 weeks, High)

- [ ] Dual-run against legacy read replica.
- [ ] Row-count + checksum validation per table.
- [ ] UAT with GRM officers.
- [ ] Decommission legacy, archive repo.

## Phase 8 — Hardening (ongoing, Low)

- [ ] External pen test.
- [ ] Performance baseline.
- [ ] Backup restore drill.
- [ ] On-call runbook.
