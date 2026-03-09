# Lodging Management System with Virtual Tour Integration

A Lodging Management System with Virtual Tour Integration for University Homestay, Central Mindanao University. Built as a Capstone Project for MIS by Romer Ian O. Pasoc. This system manages room reservations, guest check-in/out tracking, amenity management, and virtual tour integration using a modern web-based admin panel and a public guest-facing website.

## Tech Stack

- **Backend:** Laravel 11 (PHP 8.2)
- **Admin Panel:** Filament v3
- **Database:** MySQL
- **Frontend:** Vite, Tailwind CSS
- **Virtual Tours:** Panoee (iframe embeds)

## Features

### Guest-Facing Website
- **Room Catalog** — Browse all active room types with:
  - Amenities and pricing information
  - Available bed count (for dormitories)
  - Occupancy rate display
- **Virtual Tours** — 360° virtual tours per room type (Panoee integration)
- **Online Reservations** — Submit booking requests with:
  - Guest information (name, contact, gender, organization)
  - Occupancy breakdown (male/female/total)
  - Check-in/out date selection
  - Special requests and purpose of stay documentation
- **Reservation Tracking** — Check status anytime using reference number
- **Guest Messaging** — Two-tier communication system:
  - General inquiries (no reservation required)
  - Reservation-specific messages (with reference number)
- **Live Chat** — Message staff and receive real-time replies

### Admin Dashboard & Widgets
- **StatsOverview Widget** — Real-time metrics:
  - Total/occupied rooms and occupancy rate
  - Pending/approved/checked-in reservation counts
  - Today's check-ins and check-outs
  - Near-due reservations (checkout in next 24 hours)
  - Overdue reservations
  - Current checked-in guest count
- **ReservationCalendar Widget** — Monthly calendar view of all reservations
- **RoomStatusChart Widget** — Visual distribution of room statuses
- **RecentBookings & RecentNotifications** — Activity feed widgets
- **Site Settings Page** — Configure site branding and system parameters

### Admin Management Modules (11 Resources)
- **Reservations** — Full lifecycle management with status tracking (Pending → Approved → Checked In → Checked Out, plus Declined/Cancelled)
  - Auto-generated reference numbers (YYYY-#### format, e.g., 2026-0001)
  - Guest demographics and contact info
  - Check-in hold mechanism (temporary reservation state)
  - Reviewer audit trail and notes
- **Rooms** — Physical room management with:
  - Gender restrictions (male, female, any)
  - Capacity and bed tracking
  - Status monitoring (Available, Occupied, Maintenance, Inactive)
- **Room Types** — Define room categories with:
  - Pricing options (per-room or per-person)
  - Sharing types (private or public/dormitory)
  - Virtual tour integration
- **Amenities** — Manage room features with many-to-many assignment to room types
- **Floors** — Building structure and level organization
- **Beds** — Individual dormitory bed management (dormitory mode support)
- **Guests** — Comprehensive guest profiles with:
  - ID verification (type and number)
  - Special demographic flags (senior, PWD status)
  - Contact and address information
- **Services** — Add-on services with pricing and categorization
- **Messages/Conversations** — Guest communication tracking with:
  - General inquiry management
  - Reservation-linked conversations
  - Read/unread status
- **Users** — Role-based staff and admin accounts
- **Notifications** — System notification tracking and management
- **Settings** — Site configuration with caching strategy

### Advanced Occupancy System
- **Dormitory Mode** — Multiple guests per room (each assigned to individual bed)
- **Private Room Mode** — Single guest/reservation per room
- **Capacity Management**:
  - Gender-based room restrictions
  - Per-person and per-room pricing support
  - Bed availability validation
  - Gender-aware occupancy breakdown

### Check-in/Check-out System
- **Check-in Hold** — Temporary reservation state with validation
- **Multi-room Check-in** — Assign single reservation to multiple rooms
- **Guest Details Capture** — Per-assignment information:
  - ID verification
  - Special demographic flags
  - Payment recording (mode, amount, reference)
  - Audit trail (staff attribution, timestamps)
- **Atomic Transactions** — Consistent state management

### Reports
- **Reservation Summary** — By status, purpose, room type (date range filtered)
- **Occupancy Report** — Historical trends and analytics
- **Room Utilization** — Per-room performance analysis
- **Stay Log History** — Guest check-in/out records with filtering

### Automated Processes & Real-time Notifications
- **Reservation Events** — Staff notified on:
  - New reservations submitted
  - Status changes (approved, checked-in, etc.)
  - Name changes (syncs to all assignments)
  - Cancellations/declines (closes assignments)
- **Room Events** — Staff notified on room creation, deletion, status/activity changes
- **Assignment Events** — Notifications on guest assignments and check-ins
- **Message Events** — Staff notified of guest messages and inquiries
- **WebSocket Broadcasting** — Real-time notifications with channel-based delivery
- **Scheduled Tasks** — Automatic reminders for near-due reservations (checkout in next 24 hours)

### User Management & Permissions
- **Three-Tier Role System**:
  - **Super Administrator** — Exclusive ability to manage user roles and permissions; inherits all admin privileges
  - **Administrator** — Full system access and resource management
  - **Staff** — Limited to assigned operations with no user management access
- **Granular Permission Control** — Customizable permissions per user for:
  - Resource operations (view/create/edit/delete)
  - Role assignments (super admin only)
  - System configuration access
- **User Caching** — Optimized staff directory lookups

### Performance & System Features
- **Strategic Caching**:
  - Dashboard stats cached 60 seconds
  - Calendar data cached 5 minutes
  - Settings cached 1 hour
  - Staff directory cached 15 minutes
- **Database Optimization** — Production indexes for frequently queried data
- **Command-Line Tools**:
  - Data synchronization (room/bed status sync)
  - Test data generation and cleanup
  - Duplicate detection and removal utilities
  - Notification system testing

## Installation

### Prerequisites
- PHP 8.2+, Composer, MySQL, Node.js & npm

### Setup


```bash
cd uhlms
composer install
npm install
cp .env.example .env
php artisan key:generate
# Configure .env with your DB credentials
php artisan migrate:fresh --seed
npm run build
php artisan storage:link
php artisan serve
```

## Default Login Credentials

| Role  | Email              | Password |
|-------|--------------------|----------|
| Admin | admin@cmu.edu.ph   | password |
| Staff | maria@cmu.edu.ph   | password |
| Staff | juan@cmu.edu.ph    | password |

**Note:** Super Administrator accounts can be created manually or promoted from existing admins via direct database update (set `role = 'super_admin'`). Super Admins inherit all admin privileges plus the exclusive ability to manage user roles and permissions.

**Admin Panel:** http://localhost:8000/admin
**Guest Website:** http://localhost:8000/

## Sample Data

The seeder includes: 3 users (1 admin, 2 staff), 5 room types, 10 amenities, 3 floors, 16 rooms, 10 sample reservations (across all statuses), room assignments, and stay logs.

## License

MIT
