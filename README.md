# Hotel Room Reservation System (REST API)

SEN4303 Advanced Database Systems, Assignment 3. Backend: Laravel 13 (PHP 8.3), database: MySQL/MariaDB.

## How to run
1. Install dependencies: `composer install`
2. Create the env file: `copy .env.example .env`, then change the database lines in `.env` to:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=hotel_db
   DB_USERNAME=root
   DB_PASSWORD=
   ```
3. Generate the app key: `php artisan key:generate`
4. Start MySQL (XAMPP) and import `database/hotel_db.sql` (phpMyAdmin Import, or `mysql -u root < database/hotel_db.sql`)
5. Start the server: `php artisan serve`
6. API base URL: `http://127.0.0.1:8000/api` (send header `Accept: application/json`)

## API endpoints
| Method | URL | Description |
|---|---|---|
| GET | /api/rooms | View all rooms |
| GET | /api/rooms/available?check_in=&check_out= | View available rooms for a date range |
| POST | /api/rooms | Add a room |
| PUT | /api/rooms/{id} | Update a room |
| DELETE | /api/rooms/{id} | Delete a room |
| GET | /api/guests | View all guests |
| POST | /api/guests | Register a guest |
| PUT | /api/guests/{id} | Update a guest |
| DELETE | /api/guests/{id} | Delete a guest |
| GET | /api/reservations | View all reservations |
| GET | /api/reservations/{id} | View one reservation |
| POST | /api/reservations | Create a reservation (transaction + row lock) |
| PUT | /api/reservations/{id} | Change reservation dates (transaction + row lock) |
| PATCH | /api/reservations/{id}/cancel | Cancel a reservation (transaction + row lock) |

## Transactions and concurrency
Create, update and cancel reservation run inside `DB::transaction()` and lock the room row with `lockForUpdate()` to prevent double booking.
