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
- Browse available room types with amenities and pricing
- View 360° virtual tours of rooms (Panoee integration)
- Submit reservation requests online
- Track reservation status by reference number

### Admin Dashboard
- Real-time occupancy rate statistics
- Pending reservation count
- Active reservations with today's check-ins/check-outs
- Currently checked-in guest count
- Room status doughnut chart
- Recent reservations table widget

### Reservation Management
- Auto-generated reference numbers (RES-XXXXXXXX)
- Reservation workflow: Pending → Approved → Checked In → Checked Out
- Quick actions: Approve, Decline, Assign Room, Check-in, Check-out, Cancel
- Guest information tracking (name, email, phone, organization, address)
- Purpose categorization (academic, official, personal, event)
- Admin notes and review tracking

### Room Management
- **Room Types** — Define categories with capacity, base rates, and virtual tour URLs
- **Amenities** — Manage amenities (WiFi, AC, TV, etc.) with many-to-many assignment to room types
- **Floors** — Manage building floors/levels
- **Rooms** — Track rooms with status (Available, Occupied, Maintenance, Inactive)

### Stay Logs
- Check-in/check-out recording with timestamps
- Staff attribution (who checked in/out the guest)
- Remarks tracking per stay

### Reports
- Reservation summary (by status, purpose, room type)
- Occupancy report with daily trend visualization
- Room-by-room utilization analysis
- Stay log history with filtering

### User Management
- Role-based access: Admin and Staff
- Admin-only user management panel

## Installation

### Prerequisites
- PHP 8.2+, Composer, MySQL, Node.js & npm

### Setup

```bash
cd lodging-system
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

**Admin Panel:** http://localhost:8000/admin
**Guest Website:** http://localhost:8000/

## Sample Data

The seeder includes: 3 users (1 admin, 2 staff), 5 room types, 10 amenities, 3 floors, 16 rooms, 10 sample reservations (across all statuses), room assignments, and stay logs.

## License

MIT
