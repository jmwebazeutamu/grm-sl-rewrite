# GRM Rewrite — Data Dictionary

Generated from the authoritative SQLite schema (`database/database.sqlite`) on 2026-04-19. 57 tables grouped by domain. Foreign-key references appear as **→ table.column** in the Notes column. All tables include Laravel `created_at`/`updated_at` unless noted otherwise.

Domain index:

1. [Identity & Auth](#1-identity--auth)
2. [Organization](#2-organization)
3. [Locality](#3-locality)
4. [Reference lookups](#4-reference-lookups)
5. [Grievance core](#5-grievance-core)
6. [Notifications & Messaging](#6-notifications--messaging)
7. [Audit](#7-audit)
8. [Framework infrastructure](#8-framework-infrastructure)

Convention notes:

- `created_by_id` / `updated_by_id` are set by the `RecordsAuthorship` trait and reference `person.id`.
- `tinyint(1)` is Laravel's boolean encoding in SQLite.
- The `person` table holds both staff and (via `role`) admin users. The `User` model maps to it.

---

## 1. Identity & Auth

### `person`

Users of the system (staff and admins). The `User` model maps to this table.

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | INTEGER | NO | — | PK |
| 2 | username | varchar | NO | — | unique |
| 3 | name | varchar | NO | — | |
| 4 | email | varchar | YES | — | unique when set |
| 5 | email_verified_at | datetime | YES | — | |
| 6 | password | varchar | NO | — | bcrypt |
| 7 | remember_token | varchar | YES | — | |
| 8 | phone_number | varchar | YES | — | |
| 9 | organization_id | INTEGER | YES | — | → organization.id |
| 10 | position | varchar | YES | — | |
| 11 | office_id | INTEGER | YES | — | → office.id |
| 12 | is_active | tinyint(1) | NO | 1 | |

### `notification_preference`

Per-user channel toggles.

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | INTEGER | NO | — | PK |
| 2 | person_id | INTEGER | NO | — | → person.id (cascade) |
| 3 | email_enabled | tinyint(1) | NO | 1 | |
| 4 | sms_enabled | tinyint(1) | NO | 1 | |
| 5 | in_app_enabled | tinyint(1) | NO | 1 | |

### `roles`

Spatie `laravel-permission` role definitions.

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | INTEGER | NO | — | PK |
| 2 | name | varchar | NO | — | |
| 3 | guard_name | varchar | NO | — | |

### `permissions`

Spatie permission definitions.

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | INTEGER | NO | — | PK |
| 2 | name | varchar | NO | — | |
| 3 | guard_name | varchar | NO | — | |

### `model_has_roles`

Polymorphic pivot linking any model to roles. Composite PK `(role_id, model_id, model_type)`.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | role_id | INTEGER | NO | → roles.id (cascade) |
| 2 | model_type | varchar | NO | |
| 3 | model_id | INTEGER | NO | |

### `model_has_permissions`

Polymorphic pivot linking any model to permissions. Composite PK `(permission_id, model_id, model_type)`.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | permission_id | INTEGER | NO | → permissions.id (cascade) |
| 2 | model_type | varchar | NO | |
| 3 | model_id | INTEGER | NO | |

### `role_has_permissions`

Role ⟷ permission pivot.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | permission_id | INTEGER | NO | → permissions.id (cascade) |
| 2 | role_id | INTEGER | NO | → roles.id (cascade) |

### `personal_access_tokens`

Laravel Sanctum API tokens.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | tokenable_type | varchar | NO | polymorphic owner class |
| 3 | tokenable_id | INTEGER | NO | polymorphic owner id |
| 4 | name | varchar | NO | |
| 5 | token | varchar | NO | |
| 6 | abilities | TEXT | YES | JSON |
| 7 | last_used_at | datetime | YES | |
| 8 | expires_at | datetime | YES | |

### `password_reset_tokens`

Laravel password-reset plumbing.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | email | varchar | NO | PK |
| 2 | token | varchar | NO | |
| 3 | created_at | datetime | YES | |

### `sessions`

Database session driver store.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | varchar | NO | PK |
| 2 | user_id | INTEGER | YES | |
| 3 | ip_address | varchar | YES | |
| 4 | user_agent | TEXT | YES | |
| 5 | payload | TEXT | NO | |
| 6 | last_activity | INTEGER | NO | |

---

## 2. Organization

### `organization`

Top-level org entity (e.g., a government agency). Self-referencing for hierarchy.

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | INTEGER | NO | — | PK |
| 2 | name | varchar | NO | — | |
| 3 | acronym | varchar | YES | — | |
| 4 | description | TEXT | YES | — | |
| 5 | parent_id | INTEGER | YES | — | → organization.id |
| 6 | sla_days | INTEGER | NO | 30 | default resolution SLA |
| 7 | created_by_id | INTEGER | YES | — | → person.id |
| 8 | updated_by_id | INTEGER | YES | — | → person.id |
| 9 | deleted_at | datetime | YES | — | soft delete |

### `office`

An organizational unit / location.

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | INTEGER | NO | — | PK |
| 2 | name | varchar | NO | — | |
| 3 | acronym | varchar | YES | — | |
| 4 | address | varchar | YES | — | |
| 5 | is_headquarters | tinyint(1) | NO | 0 | |
| 6 | organization_id | INTEGER | NO | — | → organization.id (cascade) |
| 7 | region_id | INTEGER | YES | — | → region.id |
| 8 | district_id | INTEGER | YES | — | → district.id |
| 9 | created_by_id | INTEGER | YES | — | → person.id |
| 10 | updated_by_id | INTEGER | YES | — | → person.id |
| 11 | deleted_at | datetime | YES | — | soft delete |

### `office_person`

Pivot: person ⟷ office (many-to-many secondary assignments).

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | office_id | INTEGER | NO | → office.id (cascade) |
| 3 | person_id | INTEGER | NO | → person.id (cascade) |
| 4 | created_by_id | INTEGER | YES | → person.id |
| 5 | updated_by_id | INTEGER | YES | → person.id |

### `employee`

Employee records (distinct from `person` auth entity; has HR-style fields).

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | first_name | varchar | NO | |
| 3 | last_name | varchar | NO | |
| 4 | email | varchar | YES | |
| 5 | mobile_number | varchar | YES | |
| 6 | office_number | varchar | YES | |
| 7 | organization_id | INTEGER | NO | → organization.id (cascade) |
| 8 | office_id | INTEGER | YES | → office.id |
| 9 | created_by_id | INTEGER | YES | → person.id |
| 10 | updated_by_id | INTEGER | YES | → person.id |
| 11 | deleted_at | datetime | YES | soft delete |

### `programme`

A programme run by an organization (e.g., social-safety scheme).

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | INTEGER | NO | — | PK |
| 2 | name | varchar | NO | — | |
| 3 | acronym | varchar | YES | — | |
| 4 | code | varchar | YES | — | |
| 5 | organization_id | INTEGER | NO | — | → organization.id (cascade) |
| 6 | active | tinyint(1) | NO | 1 | |
| 7 | status | varchar | NO | 'active' | |
| 8 | sla_days | INTEGER | YES | — | programme-specific SLA |
| 9 | created_by_id | INTEGER | YES | — | → person.id |
| 10 | updated_by_id | INTEGER | YES | — | → person.id |
| 11 | deleted_at | datetime | YES | — | soft delete |

### `organization_grievance_type`

Pivot: organization ⟷ grievance_type (which types an org handles).

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | organization_id | INTEGER | NO | → organization.id (cascade) |
| 3 | grievance_type_id | INTEGER | NO | → grievance_type.id (cascade) |
| 4 | created_by_id | INTEGER | YES | → person.id |
| 5 | updated_by_id | INTEGER | YES | → person.id |

### `org_grievance_types`

Organization-defined classification labels (used by grievance `org_classification_id`).

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | INTEGER | NO | — | PK |
| 2 | organization_id | INTEGER | NO | — | → organization.id (cascade) |
| 3 | label | varchar | NO | — | |
| 4 | active | tinyint(1) | NO | 1 | |

### `saved_reports`

Persisted report definitions (selected fields + filter criteria).

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |
| 3 | created_by_id | INTEGER | NO | → person.id (cascade) |
| 4 | organization_id | INTEGER | YES | → organization.id |
| 5 | fields | TEXT | NO | JSON |
| 6 | filters | TEXT | YES | JSON |

---

## 3. Locality

Sierra Leone administrative hierarchy (top → bottom):
**country → region → district → chiefdom → section → locality**.

Each child has a NOT NULL FK to its parent with `ON DELETE CASCADE` / `ON UPDATE RESTRICT`.

### `country`

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |
| 3 | iso_code | varchar | YES | |

### `region`

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |
| 3 | country_id | INTEGER | NO | → country.id |

### `district`

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |
| 3 | region_id | INTEGER | NO | → region.id |

### `chiefdom`

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |
| 3 | district_id | INTEGER | NO | → district.id |

### `section`

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |
| 3 | chiefdom_id | INTEGER | NO | → chiefdom.id |

### `locality`

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |
| 3 | section_id | INTEGER | NO | → section.id |

---

## 4. Reference lookups

Named enumerations populated via seeders. All have `name` (NOT NULL), most have `created_by_id` / `updated_by_id` and timestamps; those fields are not repeated below.

### `grievance_type`

Top-level grievance categorization (e.g., "Harassment", "Corruption").

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |

### `how_reported`

Intake channel (web, SMS, in-person, etc.).

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |

### `priority`

Priority tier with SLA hints.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |
| 3 | description | TEXT | YES | |
| 4 | ranking | INTEGER | YES | sort order |
| 5 | response_time_hours | INTEGER | YES | |
| 6 | resolution_time_hours | INTEGER | YES | |

### `status`

Lookup used by several sub-workflows; `category_type` disambiguates scope.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |
| 3 | description | TEXT | YES | |
| 4 | category_type | varchar | YES | discriminator |

### `review_outcome`

Outcomes of grievance review.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |

### `case_concept`

Case-concept categorization under a grievance_type.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |
| 3 | description | TEXT | YES | |
| 4 | grievance_type_id | INTEGER | NO | → grievance_type.id (restrict) |

### `area`

Responsibility area (classification attribute).

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |

### `access`

Access-level / confidentiality tier applied to grievance + classification.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |

### `action_type`

Action-type lookup (legacy-compatible; runtime uses `ActionType` PHP enum on `grievance_action.type`).

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |
| 3 | description | TEXT | YES | |

### `satisfaction`

Satisfaction levels (legacy lookup; runtime uses `FeedbackRating` enum on `grievance_feedback.rating`).

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |

### `feedback_status`

Feedback lifecycle status.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | name | varchar | NO | |
| 3 | description | TEXT | YES | |

---

## 5. Grievance core

### `grievance`

Main case record. State-driven (via `state` enum column), soft-deletes, and carries all classification FKs denormalized from the (retained) `grievance_classification` row.

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | INTEGER | NO | — | PK |
| 2 | g_number | varchar | NO | — | unique public ref |
| 3 | summary | TEXT | NO | — | |
| 4 | description | TEXT | YES | — | |
| 5 | grievance_type_id | INTEGER | NO | — | → grievance_type.id (restrict) |
| 6 | how_reported_id | INTEGER | YES | — | → how_reported.id |
| 7 | priority_id | INTEGER | YES | — | → priority.id |
| 8 | state | varchar | NO | 'submitted' | `GrievanceState` enum; indexed |
| 9 | is_anonymous | tinyint(1) | NO | 0 | |
| 10 | review_comment | TEXT | YES | — | |
| 11 | reviewed_at | datetime | YES | — | |
| 12 | reviewed_by_id | INTEGER | YES | — | → person.id |
| 13 | region_id | INTEGER | YES | — | → region.id |
| 14 | district_id | INTEGER | YES | — | → district.id |
| 15 | chiefdom_id | INTEGER | YES | — | → chiefdom.id |
| 16 | section_id | INTEGER | YES | — | → section.id |
| 17 | locality_id | INTEGER | YES | — | → locality.id |
| 18 | received_at | datetime | NO | — | |
| 19 | resolved_at | datetime | YES | — | |
| 20 | closed_at | datetime | YES | — | |
| 21 | related_grievance_id | INTEGER | YES | — | → grievance.id (self) |
| 22 | assigned_officer_id | INTEGER | YES | — | → person.id (unified ownership) |
| 23 | classified_organization_id | INTEGER | YES | — | → organization.id |
| 24 | classified_programme_id | INTEGER | YES | — | → programme.id |
| 25 | classified_case_concept_id | INTEGER | YES | — | → case_concept.id |
| 26 | classified_area_id | INTEGER | YES | — | → area.id |
| 27 | access_id | INTEGER | YES | — | → access.id |
| 28 | category | varchar | YES | — | `GrievanceCategory` enum |
| 29 | accepted_at | datetime | YES | — | pipeline timestamp |
| 30 | categorized_at | datetime | YES | — | pipeline timestamp |
| 31 | assigned_at | datetime | YES | — | pipeline timestamp |
| 32 | org_classification_id | INTEGER | YES | — | → org_grievance_types.id |
| 33 | closure_comment | TEXT | YES | — | |
| 34 | closure_reviewed_by_id | INTEGER | YES | — | → person.id |
| 35 | closure_reviewed_at | datetime | YES | — | |
| 36 | reopened_at | datetime | YES | — | |
| 37 | implementing_organization_id | INTEGER | YES | — | → organization.id |
| 38 | programme_id | INTEGER | YES | — | → programme.id (implementing) |
| 39 | created_by_id | INTEGER | YES | — | → person.id |
| 40 | updated_by_id | INTEGER | YES | — | → person.id |
| 41 | deleted_at | datetime | YES | — | soft delete |

State values (`GrievanceState`): `submitted`, `under_review`, `accepted`, `categorized`, `assigned`, `org_classified`, `in_progress`, `resolved`, `closed`, `rejected`, `trashed`, `escalated`, `under_admin_review`, `reopened`.

### `grievance_complainer`

Grievance submitter (0..1 per grievance).

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | grievance_id | INTEGER | NO | → grievance.id (cascade) |
| 3 | first_name | varchar | YES | |
| 4 | last_name | varchar | YES | |
| 5 | gender | varchar | YES | |
| 6 | email | varchar | YES | |
| 7 | phone_number | varchar | YES | |
| 8 | address | varchar | YES | |
| 9 | organization_id | INTEGER | YES | → organization.id |
| 10 | other_organization | varchar | YES | free-text when no org FK |
| 11 | region_id | INTEGER | YES | → region.id |
| 12 | district_id | INTEGER | YES | → district.id |
| 13 | chiefdom_id | INTEGER | YES | → chiefdom.id |
| 14 | section_id | INTEGER | YES | → section.id |
| 15 | locality_id | INTEGER | YES | → locality.id |

### `grievance_suspect`

Accused / alleged party (0..N per grievance).

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | INTEGER | NO | — | PK |
| 2 | grievance_id | INTEGER | NO | — | → grievance.id (cascade) |
| 3 | first_name | varchar | YES | — | |
| 4 | last_name | varchar | YES | — | |
| 5 | title | varchar | YES | — | |
| 6 | gender | varchar | YES | — | |
| 7 | email | varchar | YES | — | |
| 8 | phone_number | varchar | YES | — | |
| 9 | address | varchar | YES | — | |
| 10 | organization_id | INTEGER | YES | — | → organization.id |
| 11 | other_organization | varchar | YES | — | |
| 12 | region_id .. locality_id | INTEGER | YES | — | location hierarchy FKs |
| 13 | is_beneficiary | tinyint(1) | NO | 0 | flag if suspect is also a programme beneficiary |
| 14 | programme_id | INTEGER | YES | — | → programme.id |
| 15 | implementing_organization_id | INTEGER | YES | — | → organization.id |
| 16 | beneficiary_id_number | varchar | YES | — | |

### `grievance_beneficiary`

Affected programme beneficiary (0..N per grievance).

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | grievance_id | INTEGER | NO | → grievance.id (cascade) |
| 3 | name | varchar | YES | |
| 4 | gender | varchar | YES | |
| 5 | phone_number | varchar | YES | |
| 6 | household_id | varchar | YES | external reference |
| 7 | implementing_agency_id | INTEGER | YES | → organization.id |
| 8 | social_programme_id | INTEGER | YES | → programme.id |

### `grievance_classification`

Retained for audit; runtime logic uses `grievance.classified_*` columns.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | grievance_id | INTEGER | NO | → grievance.id (cascade) |
| 3 | organization_id | INTEGER | YES | → organization.id |
| 4 | programme_id | INTEGER | YES | → programme.id |
| 5 | case_concept_id | INTEGER | YES | → case_concept.id |
| 6 | responsible_area_id | INTEGER | YES | → area.id |
| 7 | access_id | INTEGER | YES | → access.id |
| 8 | created_by_id | INTEGER | YES | → person.id |
| 9 | updated_by_id | INTEGER | YES | → person.id |

### `grievance_action`

Unified case-timeline entry. Replaces legacy `action_taken` + `remark` + `resolution` tables via a `type` discriminator.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | grievance_id | INTEGER | NO | → grievance.id (cascade) |
| 3 | type | varchar | NO | `ActionType` enum: `investigate`, `contact`, `update`, `resolve`, `escalate` |
| 4 | body | TEXT | NO | |
| 5 | assigned_to_id | INTEGER | YES | → person.id |
| 6 | created_by_id | INTEGER | YES | → person.id |
| 7 | updated_by_id | INTEGER | YES | → person.id |

### `grievance_status_history`

State-transition audit trail. No Laravel timestamps — `occurred_at` is the authoritative time.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | grievance_id | INTEGER | NO | → grievance.id (cascade) |
| 3 | from_state | varchar | YES | `GrievanceState` enum |
| 4 | to_state | varchar | NO | `GrievanceState` enum |
| 5 | note | TEXT | YES | |
| 6 | actor_id | INTEGER | YES | → person.id |
| 7 | occurred_at | datetime | NO | |

### `grievance_attachment`

File uploads attached to a grievance. Direct FK (not polymorphic).

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | INTEGER | NO | — | PK |
| 2 | grievance_id | INTEGER | NO | — | → grievance.id (cascade) |
| 3 | disk | varchar | NO | — | Laravel filesystem disk |
| 4 | path | varchar | NO | — | relative path on disk |
| 5 | original_name | varchar | NO | — | |
| 6 | mime_type | varchar | NO | — | |
| 7 | size_bytes | INTEGER | NO | — | |
| 8 | uploaded_by_id | INTEGER | YES | — | → person.id |
| 9 | source | varchar | NO | 'submission' | `AttachmentSource` enum: `submission`, `officer` |
| 10 | description | TEXT | YES | — | |
| 11 | stored_filename | varchar | YES | — | |

### `grievance_feedback`

Post-resolution complainer feedback (0..1 per grievance).

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | INTEGER | NO | — | PK |
| 2 | grievance_id | INTEGER | NO | — | → grievance.id (cascade) |
| 3 | rating | INTEGER | NO | — | `FeedbackRating` enum 1–5 |
| 4 | comment | TEXT | YES | — | |
| 5 | channel | varchar | NO | 'web' | `web` / `sms` / `ivr` |
| 6 | submitted_at | datetime | NO | — | |

### `grievance_feedback_token`

One-shot signed URL for public (unauthenticated) feedback submission.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | grievance_id | INTEGER | NO | → grievance.id (cascade) |
| 3 | token | varchar | NO | unique |
| 4 | expires_at | datetime | NO | |
| 5 | consumed_at | datetime | YES | |

---

## 6. Notifications & Messaging

### `notifications`

Standard Laravel notifications table (polymorphic `notifiable`).

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | varchar | NO | PK (UUID) |
| 2 | type | varchar | NO | notification class |
| 3 | notifiable_type | varchar | NO | |
| 4 | notifiable_id | INTEGER | NO | |
| 5 | data | TEXT | NO | JSON payload |
| 6 | read_at | datetime | YES | |

### `sms_inbox`

Inbound SMS messages. Parsed into a command + (optionally) matched to a grievance.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | from | varchar | NO | |
| 3 | body | TEXT | NO | |
| 4 | provider_message_id | varchar | YES | |
| 5 | parsed_command | varchar | YES | |
| 6 | matched_grievance_id | INTEGER | YES | → grievance.id |
| 7 | received_at | datetime | NO | |

### `sms_outbox`

Outbound SMS queue.

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | INTEGER | NO | — | PK |
| 2 | to | varchar | NO | — | |
| 3 | body | TEXT | NO | — | |
| 4 | provider_message_id | varchar | YES | — | |
| 5 | status | varchar | NO | 'sent' | |
| 6 | failure_reason | TEXT | YES | — | |
| 7 | dispatched_at | datetime | NO | — | |

---

## 7. Audit

### `audit_log`

Generic append-only audit trail. Polymorphic `subject`.

| # | Column | Type | Null | Notes |
|---|---|---|---|---|
| 1 | id | INTEGER | NO | PK |
| 2 | actor_id | INTEGER | YES | → person.id |
| 3 | action | varchar | NO | verb (e.g., `grievance.state_changed`) |
| 4 | subject_type | varchar | YES | model class |
| 5 | subject_id | INTEGER | YES | |
| 6 | payload | TEXT | YES | JSON |
| 7 | ip_address | varchar | YES | |
| 8 | occurred_at | datetime | NO | |

---

## 8. Framework infrastructure

Laravel plumbing — generally opaque to domain code.

| Table | Purpose |
|---|---|
| `migrations` | Applied migration log (id, migration, batch) |
| `cache` | Database cache store (key, value, expiration) |
| `cache_locks` | Cache lock coordination (key, owner, expiration) |
| `jobs` | Pending queue jobs |
| `job_batches` | Laravel batch jobs metadata |
| `failed_jobs` | Failed queue jobs (uuid, payload, exception, failed_at) |
