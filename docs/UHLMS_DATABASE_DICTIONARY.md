# UHLMS database dictionary

This dictionary accompanies [uhlms-database-erd.png](./uhlms-database-erd.png). It documents the operational tables shown in the ERD, including field purpose, nullability, defaults, keys, relationship behavior, and sample values. The samples follow the current migrations, model casts, validation options, and seeded data formats; they are examples only and are not production data.

## Conventions

- `PK` — primary key.
- `FK` — foreign key.
- `UK` — unique key.
- `nullable` — the database permits `NULL`.
- JSON fields are application-managed documents or arrays; they are not relational joins unless explicitly marked `FK`.
- `created_at` and `updated_at` are Laravel timestamps unless noted otherwise.
- The ERD displays the relationship-driving subset of columns for readability; the table sections below list every column in the current migrated schema.

## Relationship summary

| Parent table | Child table | Relationship | Meaning |
|---|---|---:|---|
| `users` | `reservations` | 1-to-many | A user may review many reservations; `reviewed_by` is nullable. |
| `users` | `reservation_alternative_offers` | 1-to-many | A user may propose many offers; `proposed_by` is nullable. |
| `users` | `room_assignments` | 1-to-many | A user is required to assign rooms and may optionally record check-in or checkout. |
| `users` | `check_in_snapshots` | 1-to-many | A user may capture many snapshots; `captured_by` is nullable. |
| `users` | `reservation_charges` | 1-to-many | A user may create many charges; `created_by` is nullable. |
| `users` | `reservation_payments` | 1-to-many | A user may receive many payments; `received_by` is nullable. |
| `users` | `reservation_feedback` | 1-to-many | A user may review many feedback records; `reviewed_by` is nullable. |
| `guest_accounts` | `reservations` | 1-to-many | An account may own many reservations; `guest_account_id` is nullable. |
| `guest_accounts` | `reservation_feedback` | 1-to-many | A feedback record must identify its submitting guest account. |
| `room_types` | `rooms` | 1-to-many | A room belongs to one room type. |
| `floors` | `rooms` | 1-to-many | A room belongs to one floor. |
| `room_types` | `amenity_room_type` | 1-to-many | A room type can have many amenity links. |
| `amenities` | `amenity_room_type` | 1-to-many | An amenity can be assigned to many room types. |
| `room_types` | `reservations` | 1-to-many | A reservation may retain an optional preferred room type. |
| `room_types` | `reservation_room_requests` | 1-to-many | A request line must identify its requested room type. |
| `room_types` | `reservation_alternative_offers` | 1-to-many | An alternative offer must identify its offered room type. |
| `reservations` | `guests` | 1-to-many | A reservation contains its guest records. |
| `reservations` | `reservation_room_requests` | 1-to-many | A reservation can request multiple room-type/capacity lines. |
| `reservations` | `reservation_alternative_offers` | 1-to-many | A reservation can receive multiple alternative offers. |
| `reservations` | `room_holds` | 1-to-many | A reservation can hold one or more rooms for a date range. |
| `reservations` | `room_assignments` | 1-to-many | A reservation can have one or more room assignments. |
| `reservations` | `check_in_snapshots` | 1-to-many | A reservation can have multiple historical check-in snapshots. |
| `reservations` | `reservation_payments` | 1-to-many | A reservation can receive multiple payments. |
| `reservations` | `reservation_charges` | 1-to-many | A reservation can accrue multiple charges. |
| `reservations` | `reservation_feedback` | 1-to-zero/one | A reservation can have at most one feedback record. |
| `reservations` | `reservation_logs` | 1-to-many | A reservation can have many audit-log entries. |
| `guests` | `reservations` | 1-to-many | A reservation may optionally reference one guest row for billing. |
| `guests` | `room_assignments` | 1-to-many | An assignment may optionally link to a guest row. |
| `guests` | `check_in_snapshots` | 1-to-many | A snapshot may optionally link to a guest row. |
| `reservation_room_requests` | `reservation_alternative_offers` | 1-to-many | An offer may optionally answer one request line. |
| `rooms` | `room_holds` | 1-to-many | A room can have many date-range holds. |
| `rooms` | `room_assignments` | 1-to-many | A room can appear in many historical assignments. |

## Table dictionary

### `users`

Administrative and staff authentication accounts. Some operational tables reference users for review, assignment, payment, or audit actions.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | User identifier. | `1001` |
| `name` | `varchar`, required |  | Display name. | `Maria Santos` |
| `email` | `varchar`, required | UK | Login email address. | `maria.santos@example.com` |
| `email_verified_at` | `timestamp`, nullable |  | Email verification time. | `2026-08-01 09:15:00` |
| `password` | `varchar`, required |  | Hashed password. | `$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCwX3oC5q0cN1zF1oJpK` |
| `two_factor_secret` | `text`, nullable |  | Encrypted two-factor authentication secret. | `NULL` |
| `two_factor_recovery_codes` | `text`, nullable |  | Encrypted JSON recovery-code list. | `NULL` |
| `two_factor_confirmed_at` | `timestamp`, nullable |  | Time two-factor authentication was confirmed. | `NULL` |
| `role` | `varchar`, default `guest` |  | Application role, such as administrator, staff, or guest. | `staff` |
| `permissions` | `json`, nullable |  | Optional associative permission overrides keyed by application permission name. | `{"reservations_create":true}` |
| `remember_token` | `varchar`, nullable |  | Laravel remember-me token. | `NULL` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-08-14 10:30:00 / 2026-08-14 10:30:00` |

### `guest_accounts`

Guest-facing accounts used for account access, reservation ownership, and feedback submission.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Guest account identifier. | `501` |
| `last_name`, `first_name` | `varchar`, nullable |  | Guest name components. | `Santos / Alex` |
| `middle_initial` | `varchar(10)`, nullable |  | Middle initial. | `M` |
| `name` | `varchar`, required |  | Display name. | `Alex M. Santos` |
| `email` | `varchar`, required | UK | Account email address. | `alex.santos@example.com` |
| `email_verified_at` | `timestamp`, nullable |  | Email verification time. | `2026-08-01 09:20:00` |
| `password` | `varchar`, required |  | Hashed account password. | `$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCwX3oC5q0cN1zF1oJpK` |
| `phone` | `varchar(30)`, nullable |  | Contact number. | `+63 917 555 0101` |
| `gender` | `varchar(20)`, nullable |  | Guest gender value. | `Female` |
| `age` | `unsigned smallint`, nullable |  | Guest age. | `28` |
| `address` | `text`, nullable |  | Guest address. | `123 Mabini St., Manila` |
| `last_login_at` | `timestamp`, nullable |  | Most recent successful login. | `2026-08-13 18:45:00` |
| `disabled_at` | `timestamp`, nullable |  | Account disablement time; `NULL` means enabled. | `NULL` |
| `remember_token` | `varchar`, nullable |  | Laravel remember-me token. | `NULL` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-08-01 09:00:00 / 2026-08-13 18:45:00` |

### `floors`

Physical floors or building levels used to organize rooms.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Floor identifier. | `3` |
| `name` | `varchar`, required |  | Human-readable floor name. | `Ground Floor` |
| `level` | `int`, default `0` |  | Numeric ordering or floor level. | `0` |
| `description` | `text`, nullable |  | Floor notes or description. | `Main guest floor` |
| `is_active` | `boolean`, default `true` |  | Whether the floor is available for use. | `true` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-07-01 08:00:00 / 2026-08-14 10:30:00` |

### `room_types`

Sellable room categories and their pricing/sharing rules.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Room-type identifier. | `10` |
| `name` | `varchar`, required |  | Room-type name shown to guests and staff. | `Dormitory` |
| `description` | `text`, nullable |  | Room-type description. | `Shared room with bunk beds` |
| `base_rate` | `decimal(10,2)`, required |  | Base nightly rate used for pricing. | `850.00` |
| `pricing_type` | `enum`, default `flat_rate` |  | `flat_rate` or `per_person`. | `flat_rate` |
| `room_sharing_type` | `enum`, default `public` |  | `public` for shared/dorm-style inventory or `private` for private rooms. | `public` |
| `images` | `json`, nullable |  | Ordered room-type image metadata. | `["rooms/dormitory-1.jpg"]` |
| `is_active` | `boolean`, default `true` |  | Whether the room type can be selected for new requests. | `true` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-07-01 08:00:00 / 2026-08-14 10:30:00` |

### `rooms`

Physical room inventory. Availability and capacity checks operate on this table together with holds and assignments.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Physical room identifier. | `101` |
| `room_number` | `varchar`, required | UK | Room label or number. | `D-101` |
| `room_type_id` | `bigint`, required | FK → `room_types.id` | Room category. Cascade-deletes with its room type. | `10` |
| `floor_id` | `bigint`, required | FK → `floors.id` | Physical floor. Cascade-deletes with its floor. | `3` |
| `capacity` | `int`, default `1` |  | Maximum capacity used for room matching. | `20` |
| `status` | `enum`, default `available` |  | `available`, `occupied`, `maintenance`, `inactive`, or `reserved`. | `available` |
| `description` | `text`, nullable |  | Room description. | `20-bed dormitory` |
| `notes` | `text`, nullable |  | Internal room notes. | `Near shared bathroom` |
| `is_active` | `boolean`, default `true` |  | Soft availability flag for inventory management. | `true` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-07-01 08:00:00 / 2026-08-14 10:30:00` |

### `amenities`

Reusable amenity catalog entries.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Amenity identifier. | `21` |
| `name` | `varchar`, required |  | Amenity name. | `Wi-Fi` |
| `description` | `text`, nullable |  | Amenity description. | `Complimentary wireless internet` |
| `is_active` | `boolean`, default `true` |  | Whether the amenity is currently offered. | `true` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-07-01 08:00:00 / 2026-08-14 10:30:00` |

### `amenity_room_type`

Many-to-many pivot between room types and amenities.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Pivot row identifier. | `301` |
| `amenity_id` | `bigint`, required | FK → `amenities.id` | Linked amenity. | `21` |
| `room_type_id` | `bigint`, required | FK → `room_types.id` | Linked room type. | `10` |
| `created_at`, `updated_at` | `timestamp` |  | Pivot lifecycle timestamps. | `2026-07-01 08:00:00 / 2026-08-14 10:30:00` |
| `(amenity_id, room_type_id)` | composite unique | UK | Prevents duplicate amenity assignments. | `21, 10` |

### `reservations`

The central booking record. It stores the guest-facing request, dates, review state, payment summary, and workflow metadata.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Reservation identifier. | `7001` |
| `reference_number` | `varchar`, required | UK | Human-readable reservation reference generated as `YYYY-NNNN`. | `2026-0001` |
| `guest_account_id` | `bigint`, nullable | FK → `guest_accounts.id` | Account that owns the reservation; set to `NULL` if the account is deleted. | `501` |
| `guest_name` | `varchar`, required |  | Primary guest display name captured at booking time. | `Alex M. Santos` |
| `guest_last_name`, `guest_first_name` | `varchar`, nullable |  | Primary guest name components. | `Santos / Alex` |
| `guest_middle_initial` | `varchar(10)`, nullable |  | Primary guest middle initial. | `M` |
| `guest_email` | `varchar`, required |  | Contact email captured for the reservation. | `alex.santos@example.com` |
| `guest_phone` | `varchar`, nullable |  | Contact mobile number. | `+63 917 555 0101` |
| `guest_address` | `text`, nullable |  | Guest address. | `123 Mabini St., Manila` |
| `guest_gender` | `varchar(20)`, nullable |  | Primary guest gender. | `Female` |
| `guest_age` | `unsigned smallint`, nullable |  | Primary guest age. | `28` |
| `num_male_guests`, `num_female_guests` | `int`, default `0` |  | Guest-count summary fields. | `0 / 1` |
| `preferred_room_type_id` | `bigint`, nullable | FK → `room_types.id` | Primary requested room type. Set to `NULL` if the room type is deleted. Detailed requests live in `reservation_room_requests`. | `10` |
| `billing_guest_id` | `bigint`, nullable | FK → `guests.id` | Optional guest record used for billing. Set to `NULL` if deleted. | `9001` |
| `check_in_date` | `date`, required |  | First stay date. | `2026-08-14` |
| `check_out_date` | `date`, required |  | Checkout date; normally after check-in. | `2026-08-15` |
| `number_of_occupants` | `int`, default `1` |  | Total occupants declared for the reservation. | `1` |
| `purpose` | `varchar`, nullable |  | Purpose of stay. | `Leisure` |
| `special_requests` | `text`, nullable |  | Guest-requested notes or accommodations. | `Late arrival` |
| `status` | `enum`, default `pending` |  | Workflow state: `pending`, `awaiting_alternative_confirmation`, `approved`, `confirmed`, `declined`, `cancelled`, `checked_in`, or `checked_out`. | `pending` |
| `approved_at` | `timestamp`, nullable |  | Time approval was recorded. | `NULL` |
| `admin_notes` | `text`, nullable |  | Internal staff notes. | `Verify ID at check-in` |
| `reviewed_by` | `bigint`, nullable | FK → `users.id` | Staff member who reviewed the reservation. Set to `NULL` if deleted. | `1001` |
| `reviewed_at` | `timestamp`, nullable |  | Review completion time. | `2026-08-14 10:30:00` |
| `addons_total` | `decimal(10,2)`, default `0` |  | Add-on subtotal summary. | `0.00` |
| `payments_total` | `decimal(10,2)`, default `0` |  | Total posted payments summary. | `0.00` |
| `balance_due` | `decimal(10,2)`, default `0` |  | Current amount due. | `850.00` |
| `payment_status` | `varchar`, default `pending` |  | Payment workflow summary. | `pending` |
| `payment_link_token` | `uuid`, nullable | UK | Secure token for a guest payment link. | `550e8400-e29b-41d4-a716-446655440000` |
| `payment_link_expires_at` | `timestamp`, nullable |  | Payment-link expiry. | `2026-08-15 10:30:00` |
| `deposit_percentage` | `decimal(5,2)`, nullable |  | Reservation-specific deposit override. | `50.00` |
| `discount_declared` | `boolean`, default `false` |  | Whether a guest declared a discount eligibility. | `false` |
| `discount_declared_type` | `enum`, nullable |  | `senior_citizen`, `pwd`, or `student`. | `NULL` |
| `discount_verified` | `boolean`, default `false` |  | Whether staff verified the discount declaration. | `false` |
| `discount_verification_notes` | `text`, nullable |  | Verification notes. | `NULL` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-08-14 10:00:00 / 2026-08-14 10:30:00` |

### `guests`

Individual guest records belonging to a reservation. This table stores companion/occupant details separately from the primary reservation contact fields.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Guest-record identifier. | `9001` |
| `reservation_id` | `bigint`, required | FK → `reservations.id` | Reservation containing the guest. Cascade-deletes with the reservation. | `7001` |
| `first_name`, `last_name` | `varchar`, nullable |  | Guest name components. | `Jordan / Santos` |
| `middle_initial` | `varchar`, nullable |  | Middle initial. | `R` |
| `relationship_to_primary` | `varchar`, nullable |  | Relationship to the primary guest. | `Friend` |
| `age` | `int`, nullable |  | Guest age. | `29` |
| `gender` | `varchar`, nullable |  | Guest gender. | `Female` |
| `contact_number` | `varchar`, nullable |  | Guest contact number. | `+63 918 555 0102` |
| `id_type` | `varchar`, nullable |  | Identification document type. | `Passport` |
| `id_number` | `varchar`, nullable |  | Identification document number. | `P1234567` |
| `notes` | `text`, nullable |  | Guest-specific notes. | `Vegetarian meal` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-08-14 10:05:00 / 2026-08-14 10:05:00` |

> `full_name` was removed by a later migration. The current schema derives display names from the component fields.

### `reservation_room_requests`

Normalized room-request lines. A single reservation can contain multiple room types, capacities, and room counts.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Request-line identifier. | `8001` |
| `reservation_id` | `bigint`, required | FK → `reservations.id` | Parent reservation. Cascade-deletes with the reservation. | `7001` |
| `room_type_id` | `bigint`, required | FK → `room_types.id` | Requested room type. Restricts deletion of a referenced room type. | `10` |
| `requested_capacity` | `unsigned smallint`, nullable |  | Requested capacity per selected room. | `20` |
| `requested_room_count` | `unsigned smallint`, default `1` |  | Number of rooms requested for this line. | `1` |
| `requested_room_ids` | `json`, nullable |  | Specific room IDs selected during an admin-created reservation. This is a preference snapshot, not a foreign-key join or room hold. | `[101]` |
| `occupant_count` | `unsigned smallint`, default `1` |  | Occupants assigned to this request line. | `1` |
| `sort_order` | `unsigned smallint`, default `0` |  | Display and processing order within the reservation. | `1` |
| `notes` | `text`, nullable |  | Notes attached to this request line. | `Admin-preselected room 101` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-08-14 10:00:00 / 2026-08-14 10:00:00` |

### `reservation_alternative_offers`

Alternative room proposals created when the originally requested room type or inventory cannot be fulfilled.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Alternative-offer identifier. | `8101` |
| `reservation_id` | `bigint`, required | FK → `reservations.id` | Parent reservation; cascade-deletes with it. | `7001` |
| `reservation_room_request_id` | `bigint`, nullable | FK → `reservation_room_requests.id` | Request line being answered; set to `NULL` if deleted. | `8001` |
| `offered_room_type_id` | `bigint`, required | FK → `room_types.id` | Alternative room type being offered. Restricts room-type deletion. | `10` |
| `room_ids` | `json`, required |  | Specific rooms temporarily offered/held. JSON array, not an FK join. | `[101]` |
| `original_total` | `decimal(10,2)`, required |  | Original quoted total. | `850.00` |
| `quoted_total` | `decimal(10,2)`, required |  | Total for the alternative offer. | `850.00` |
| `message` | `text`, nullable |  | Message shown to the guest. | `A dormitory room is available.` |
| `status` | `varchar`, default `pending` |  | Offer state: `pending`, `accepted`, `declined`, or `expired`. | `pending` |
| `expires_at` | `timestamp`, required |  | Offer expiration time. | `2026-08-14 12:00:00` |
| `responded_at` | `timestamp`, nullable |  | Guest response time. | `NULL` |
| `proposed_by` | `bigint`, nullable | FK → `users.id` | Staff proposer; set to `NULL` if deleted. | `1001` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-08-14 10:30:00 / 2026-08-14 10:30:00` |

### `room_holds`

Date-range inventory holds used to secure rooms during approval, payment, or short-term alternative offers.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Hold identifier. | `8201` |
| `room_id` | `bigint`, required | FK → `rooms.id` | Room being held. Cascade-deletes with the room. | `101` |
| `reservation_id` | `bigint`, required | FK → `reservations.id` | Reservation owning the hold. Cascade-deletes with the reservation. | `7001` |
| `hold_from` | `date`, required |  | Hold start date, normally check-in. | `2026-08-14` |
| `hold_to` | `date`, required |  | Hold end date, normally check-out. | `2026-08-15` |
| `hold_type` | `varchar`, default `advance` |  | Hold category, commonly `advance` or `short_term`. | `advance` |
| `held_guest_count` | `unsigned smallint`, nullable |  | Number of guest slots secured by the hold. | `1` |
| `expires_at` | `timestamp`, nullable |  | Expiration for temporary holds; advance holds normally use `NULL`. | `NULL` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-08-14 10:30:00 / 2026-08-14 10:30:00` |

### `room_assignments`

Actual guest-to-room allocations used during check-in and the stay. This is distinct from a requested room preference or a room hold.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Assignment identifier. | `8301` |
| `reservation_id` | `bigint`, required | FK → `reservations.id` | Parent reservation. Cascade-deletes with it. | `7001` |
| `guest_id` | `bigint`, nullable | FK → `guests.id` | Assigned guest, if linked. Set to `NULL` if deleted. | `9001` |
| `room_id` | `bigint`, required | FK → `rooms.id` | Assigned physical room. Restricts room deletion. | `101` |
| `assigned_by` | `bigint`, required | FK → `users.id` | Staff user who created the assignment. Restricts user deletion. | `1001` |
| `assigned_at` | `timestamp`, default current time |  | Assignment creation time. | `2026-08-14 10:35:00` |
| `checked_in_at` | `timestamp`, nullable |  | Official check-in time for this assignment. | `NULL` |
| `checked_in_by` | `bigint`, nullable | FK → `users.id` | Staff user who recorded check-in. | `NULL` |
| `checked_out_at` | `timestamp`, nullable |  | Actual checkout time. | `NULL` |
| `checked_out_by` | `bigint`, nullable | FK → `users.id` | Staff user who recorded checkout. | `NULL` |
| `status` | `enum`, default `checked_in` |  | `checked_in` or `checked_out`. | `checked_in` |
| `notes`, `remarks` | `text`, nullable |  | Assignment and stay notes. | `Near window / Guest requested lower bunk` |
| `guest_last_name`, `guest_first_name` | `varchar`, nullable |  | Snapshot guest identity fields. | `Santos / Jordan` |
| `guest_middle_initial` | `varchar(10)`, nullable |  | Snapshot middle initial. | `R` |
| `guest_gender` | `varchar`, nullable |  | Snapshot guest gender. | `Female` |
| `guest_age` | `unsigned smallint`, nullable |  | Snapshot guest age. | `29` |
| `guest_full_address` | `text`, nullable |  | Snapshot guest address. | `123 Mabini St., Manila` |
| `guest_contact_number` | `varchar(20)`, nullable |  | Snapshot guest contact number. | `+63 918 555 0102` |
| `id_type`, `id_number` | `varchar`, nullable |  | Identification details captured at check-in. | `Passport / P1234567` |
| `is_student`, `is_senior_citizen`, `is_pwd` | `boolean`, default `false` |  | Eligibility flags used for discounts or reporting. | `false / false / false` |
| `purpose_of_stay` | `varchar`, nullable |  | Purpose recorded during check-in. | `Leisure` |
| `nationality` | `varchar`, default `Filipino` |  | Guest nationality. | `Filipino` |
| `num_male_guests`, `num_female_guests` | `int`, default `0` |  | Occupancy summary for the assignment. | `0 / 1` |
| `detailed_checkin_datetime`, `detailed_checkout_datetime` | `datetime`, nullable |  | Detailed stay timestamps. | `2026-08-14 14:00:00 / NULL` |
| `additional_requests` | `json`, nullable |  | Add-on or additional-request codes stored as a JSON array. | `[]` |
| `payment_mode`, `payment_mode_other` | `varchar`, nullable |  | Lowercase payment mode (`cash`, `bank_transfer`, `gcash`, `check`, or `others`) and optional custom description. | `cash / NULL` |
| `payment_amount` | `decimal(10,2)`, nullable |  | Amount recorded at check-in/payment capture. | `850.00` |
| `payment_or_number` | `varchar`, nullable |  | Official receipt number. | `OR-2026-000123` |
| `or_date` | `date`, nullable |  | Official receipt date. | `2026-08-14` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-08-14 10:35:00 / 2026-08-14 10:35:00` |

### `check_in_snapshots`

Immutable-style historical snapshots of check-in information at a point in time.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Snapshot identifier. | `8401` |
| `reservation_id` | `bigint`, required | FK → `reservations.id` | Reservation being captured. Cascade-deletes with it. | `7001` |
| `guest_id` | `bigint`, nullable | FK → `guests.id` | Related guest, if available. Set to `NULL` if deleted. | `9001` |
| `id_type`, `id_number` | `varchar`, nullable |  | Identification details at capture time. | `Passport / P1234567` |
| `nationality` | `varchar`, nullable |  | Nationality at capture time. | `Filipino` |
| `purpose_of_stay` | `varchar`, nullable |  | Purpose at capture time. | `Leisure` |
| `detailed_checkin_datetime`, `detailed_checkout_datetime` | `datetime`, nullable |  | Detailed stay timestamps. | `2026-08-14 14:00:00 / NULL` |
| `payment_mode` | `varchar`, nullable |  | Payment method recorded in the snapshot. | `cash` |
| `payment_amount` | `decimal(10,2)`, nullable |  | Payment amount recorded in the snapshot. | `850.00` |
| `payment_or_number` | `varchar`, nullable |  | Receipt number. | `OR-2026-000123` |
| `or_date` | `date`, nullable |  | Receipt date. | `2026-08-14` |
| `additional_requests` | `json`, nullable |  | Add-on or special-request codes stored as a JSON array. | `[]` |
| `remarks` | `text`, nullable |  | Snapshot remarks. | `ID verified` |
| `captured_by` | `bigint`, nullable | FK → `users.id` | User who captured the snapshot. | `1001` |
| `captured_at` | `timestamp`, nullable |  | Snapshot capture time. | `2026-08-14 14:05:00` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-08-14 14:05:00 / 2026-08-14 14:05:00` |

### `reservation_charges`

Line-item financial charges associated with a reservation.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Charge identifier. | `8501` |
| `reservation_id` | `bigint`, required | FK → `reservations.id` | Parent reservation. Cascade-deletes with it. | `7001` |
| `charge_type` | `varchar`, required |  | Charge category. | `room` |
| `scope_type` | `varchar`, default `reservation` |  | Billing scope discriminator. | `reservation` |
| `scope_id` | `unsigned bigint`, nullable |  | Optional scoped entity ID; application-managed, not an FK. | `7001` |
| `description` | `varchar`, required |  | Charge description. | `Dormitory room - 1 night` |
| `qty` | `decimal(10,2)`, default `1` |  | Quantity charged. | `1.00` |
| `unit_price` | `decimal(10,2)`, default `0` |  | Price per unit. | `850.00` |
| `amount` | `decimal(10,2)`, required |  | Extended charge amount. | `850.00` |
| `currency` | `varchar(3)`, default `PHP` |  | Currency code. | `PHP` |
| `meta` | `json`, nullable |  | Additional charge metadata. | `{"nights":1}` |
| `created_by` | `bigint`, nullable | FK → `users.id` | User who created the charge. | `1001` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-08-14 10:00:00 / 2026-08-14 10:00:00` |

### `reservation_payments`

Payment transactions posted against a reservation.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Payment identifier. | `8601` |
| `reservation_id` | `bigint`, required | FK → `reservations.id` | Parent reservation. Cascade-deletes with it. | `7001` |
| `amount` | `decimal(10,2)`, required |  | Payment amount. | `850.00` |
| `payment_mode` | `varchar`, nullable |  | Payment method. | `cash` |
| `gateway` | `varchar`, nullable |  | Payment gateway, such as `paymongo` or `manual`; `NULL` is used for legacy manual payments. | `NULL` |
| `gateway_payment_id` | `varchar`, nullable, unique | UK | Gateway payment or payment-intent identifier. | `NULL` |
| `gateway_source_id` | `varchar`, nullable |  | Gateway source or checkout-session identifier. | `NULL` |
| `gateway_status` | `varchar`, nullable |  | Gateway state, such as `pending`, `paid`, `failed`, `cancelled`, or `refunded`. | `NULL` |
| `gateway_metadata` | `json`, nullable |  | Sanitized gateway payload and checkout metadata. | `NULL` |
| `is_deposit` | `boolean`, default `false` |  | Whether the payment is a partial deposit. | `false` |
| `reference_no` | `varchar`, nullable |  | External or internal payment reference. | `PAY-2026-000123` |
| `or_date` | `date`, nullable |  | Receipt date. | `2026-08-14` |
| `status` | `varchar`, default `posted` |  | Payment posting state. | `posted` |
| `received_by` | `bigint`, nullable | FK → `users.id` | Staff user who recorded the payment. | `1001` |
| `received_at` | `timestamp`, nullable |  | Payment receipt time. | `2026-08-14 14:10:00` |
| `remarks` | `text`, nullable |  | Payment notes. | `Paid in full` |
| `meta` | `json`, nullable |  | Gateway or payment metadata. | `{"channel":"admin"}` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-08-14 14:10:00 / 2026-08-14 14:10:00` |

### `reservation_feedback`

Guest feedback attached to a completed reservation. The unique reservation constraint makes this one-to-zero/one.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Feedback identifier. | `8701` |
| `reservation_id` | `bigint`, required | UK/FK → `reservations.id` | Reservation being reviewed; unique. | `7001` |
| `guest_account_id` | `bigint`, required | FK → `guest_accounts.id` | Account submitting the feedback. Cascade-deletes with the account. | `501` |
| `overall_rating` | `unsigned tinyint`, required |  | Overall rating. | `5` |
| `cleanliness_rating`, `comfort_rating`, `service_rating`, `value_rating`, `booking_experience_rating` | `unsigned tinyint`, nullable |  | Category ratings. | `5 / 5 / 5 / 4 / 5` |
| `would_stay_again` | `boolean`, nullable |  | Whether the guest would stay again. | `true` |
| `comments` | `text`, nullable |  | Public or internal guest comments. | `Clean and convenient.` |
| `admin_notes` | `text`, nullable |  | Staff-only notes. | `NULL` |
| `status` | `varchar(30)`, default `new` |  | Feedback processing state: `new`, `reviewed`, or `archived`. | `reviewed` |
| `visibility_status` | `varchar(30)`, default `internal` |  | Publication/visibility state. | `public` |
| `public_display_consent` | `boolean`, default `false` |  | Whether the guest consented to public display. | `true` |
| `public_display_room_type` | `boolean`, default `false` |  | Whether the room type may be shown with public feedback. | `true` |
| `submitted_at` | `timestamp`, nullable |  | Submission time. | `2026-08-16 09:00:00` |
| `reviewed_by` | `bigint`, nullable | FK → `users.id` | Staff reviewer. | `1001` |
| `reviewed_at` | `timestamp`, nullable |  | Review time. | `2026-08-16 10:00:00` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-08-16 09:00:00 / 2026-08-16 10:00:00` |

### `reservation_logs`

Audit/event history for reservation workflow changes.

| Field | Type / constraints | Key | Description | Sample value |
| --- | --- | --- | --- | --- |
| `id` | `bigint`, auto-increment | PK | Log identifier. | `8801` |
| `reservation_id` | `bigint`, required | FK → `reservations.id` | Reservation being logged. Cascade-deletes with it. | `7001` |
| `event` | `varchar(60)`, required |  | Machine-readable event name, such as `reservation_created` or `reservation_approved`. | `reservation_created` |
| `description` | `varchar`, required |  | Human-readable event description. | `Reservation #2026-0001 created.` |
| `actor_id` | `unsigned bigint`, nullable | App reference | Actor identifier; intentionally has no FK because events can outlive user records. | `1001` |
| `actor_name` | `varchar(150)`, nullable |  | Actor-name snapshot for audit readability. | `Maria Santos` |
| `meta` | `json`, nullable |  | Event-specific context. | `{"room_ids":[101]}` |
| `logged_at` | `timestamp`, default current time |  | Event time. | `2026-08-14 10:35:00` |
| `created_at`, `updated_at` | `timestamp` |  | Record lifecycle timestamps. | `2026-08-14 10:35:00 / 2026-08-14 10:35:00` |

## Related tables not shown in this ERD image

The application also contains support, virtual-tour, settings, service-catalog, payment-webhook, notification, and Laravel infrastructure tables. They are intentionally outside this operational reservation ERD. The main related application tables are:

- `services`, `settings`, `reservation_sequences`, `support_inquiries`, `support_inquiry_replies`, `tour_waypoints`, `tour_hotspots`, `payment_webhook_events`, `force_deletion_logs`, framework notification tables, and Laravel infrastructure tables such as `jobs`, `failed_jobs`, `sessions`, `cache`, and `migrations`.
