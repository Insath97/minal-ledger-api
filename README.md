# Minal Ledger API

Point-of-Sale & Ledger Management System — RESTful API built with Laravel 12.

## Tech Stack

- **Framework:** Laravel 12
- **Language:** PHP 8.2+
- **Database:** MySQL
- **Authentication:** JWT (via `php-open-source-saver/jwt-auth`)
- **Authorization:** Role-Based Access Control (via `spatie/laravel-permission`)
- **Social Login:** Google OAuth (via `laravel/socialite`)
- **Testing:** Pest PHP
- **Queue/Cache/Session:** Database driver

## Features

### Authentication
- JWT-based login/logout with token refresh
- Authenticated user profile (`GET /me`, `PUT /profile`)
- Email verification flow with token expiry
- Google OAuth configured (via Socialite)
- Hidden password-less "User Scope" login support

### Role-Based Access Control
- 4 roles: Super Admin, System Admin, Manager, Cashier
- ~40 permissions across 11 modules
- Super Admin automatically receives all permissions
- Permission-based middleware on every controller

### Modules

| Module | Description |
|--------|-------------|
| **Users** | CRUD, active list, toggle status, profile image upload, welcome email |
| **Roles & Permissions** | Full CRUD with permission assignment |
| **Banks** | CRUD, active list, toggle status |
| **Customers** | CRUD, active list, toggle status, auto code generation (CUST-XXXXX), NIC & profile image upload |
| **Sales** | CRUD, active list, auto invoice numbering (INV-YYYYMMDD-XXXXX), bill image upload, multi-status tracking (unpaid/partial/paid) |
| **Cheques** | CRUD, active list, status updates (pending/cleared/bounced), cheque image upload |
| **Payments** | Bulk FIFO settlement across multiple sales, multiple payment methods, proof of payment upload |
| **Expenses** | CRUD, summary endpoints, category breakdown, receipt image upload |
| **Finance & Reports** | Dashboard, P&L statement, income breakdown by payment method, expense breakdown by category, customer dues aging (0-30/31-60/61-90/90+) |
| **Activity Logs** | Read-only audit trail for all CRUD operations with payload sanitization |

### File Uploads
All uploads stored in `public/uploads/{module}/` via `FileUploadTrait`. Supports:
- Single & multiple file uploads
- Automatic old file deletion on update
- Graceful directory creation

### Activity Logging
Every create, update, delete, and toggle-status action is logged with:
- User, action, module, description
- Request payload (passwords/tokens redacted)
- IP address, user agent, URL, HTTP method
- Fallback to file logging on DB failure

## Installation

### Requirements
- PHP 8.2+
- Composer
- MySQL 8.0+
- Node.js (for frontend, optional)

### Setup

```bash
# Clone the repository
git clone <repo-url> minal-ledger-api
cd minal-ledger-api

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# Generate JWT secret
php artisan jwt:secret

# Configure your database in .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=minal_ledger_api
DB_USERNAME=root
DB_PASSWORD=

# Run migrations and seeders
php artisan migrate --seed

# Start development server
php artisan serve
```

### Default Credentials
After seeding:
- **Email:** dev@localhost.com
- **Username:** devadmin
- **Password:** password123
- **Role:** Super Admin

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_NAME` | Application name | Minal Ledger API |
| `APP_ENV` | Environment | local |
| `APP_DEBUG` | Debug mode | true |
| `APP_URL` | App URL | http://localhost |
| `FRONTEND_URL` | Frontend URL | http://localhost:5173 |
| `DB_DATABASE` | Database name | minal_ledger_api |
| `JWT_SECRET` | JWT signing key | (generated) |
| `JWT_TTL` | JWT token TTL (minutes) | 60 |
| `JWT_REFRESH_TTL` | Refresh TTL (minutes) | 20160 (14 days) |
| `CORS_ALLOWED_ORIGINS` | CORS origins | (set as needed) |
| `GOOGLE_CLIENT_ID` | Google OAuth client ID | — |
| `GOOGLE_CLIENT_SECRET` | Google OAuth secret | — |
| `GOOGLE_REDIRECT` | Google OAuth redirect URL | — |
| `MAIL_MAILER` | Mail driver | log |

## API Endpoints

All endpoints are prefixed with `/api/v1`. Protected endpoints require `Authorization: Bearer {token}`.

### Authentication

| Method | URI | Permission | Description |
|--------|-----|------------|-------------|
| POST | `/login` | Public | Login with username or email |
| POST | `/logout` | Authenticated | Logout & invalidate token |
| GET | `/me` | Authenticated | Get current user with roles & permissions |
| PUT | `/profile` | Authenticated | Update own profile (name, phone, email, password, profile_image) |

### Users

| Method | URI | Permission | Description |
|--------|-----|------------|-------------|
| GET | `/users` | User Index | Paginated list (search, is_active, role filters) |
| POST | `/users` | User Create | Create user (JSON or multipart with profile_image) |
| GET | `/users/{id}` | User Index | Get user by ID |
| PUT | `/users/{id}` | User Update | Update user |
| DELETE | `/users/{id}` | User Delete | Delete user (cannot delete self) |
| GET | `/users/list` | User List | Active users for dropdowns |
| PATCH | `/users/{id}/toggle-status` | User Toggle Status | Toggle active/inactive |

### Roles

| Method | URI | Permission | Description |
|--------|-----|------------|-------------|
| GET | `/roles` | Role Index | Paginated list with permissions |
| POST | `/roles` | Role Create | Create role with permissions |
| GET | `/roles/{id}` | Role Index | Get role by ID with permissions |
| PUT | `/roles/{id}` | Role Update | Update role name & permissions |
| DELETE | `/roles/{id}` | Role Delete | Delete role |
| GET | `/roles/list` | Role List | All roles for dropdowns |

### Permissions

| Method | URI | Permission | Description |
|--------|-----|------------|-------------|
| GET | `/permissions` | Permission Index | Paginated list with assigned roles count |
| POST | `/permissions` | Permission Create | Create permission |
| GET | `/permissions/{id}` | Permission Index | Get permission by ID |
| PUT | `/permissions/{id}` | Permission Update | Update permission |
| DELETE | `/permissions/{id}` | Permission Delete | Delete permission (blocked if assigned to role) |
| GET | `/permissions/list` | — | All permissions grouped for UI |

### Banks

| Method | URI | Permission | Description |
|--------|-----|------------|-------------|
| GET | `/banks` | Bank Index | Paginated list (search, is_active filters) |
| POST | `/banks` | Bank Create | Create bank |
| GET | `/banks/{id}` | Bank Index | Get bank by ID |
| PUT | `/banks/{id}` | Bank Update | Update bank |
| DELETE | `/banks/{id}` | Bank Delete | Delete bank |
| GET | `/banks/list` | Bank List | Active banks for dropdowns |
| PATCH | `/banks/{id}/toggle-status` | Bank Toggle Status | Toggle active/inactive |

### Customers

| Method | URI | Permission | Description |
|--------|-----|------------|-------------|
| GET | `/customers` | Customer Index | Paginated list (search, is_active filters) |
| POST | `/customers` | Customer Create | Create customer (JSON or multipart with images) |
| GET | `/customers/{id}` | Customer Index | Get customer by ID |
| PUT | `/customers/{id}` | Customer Update | Update customer (multipart with images) |
| DELETE | `/customers/{id}` | Customer Delete | Delete customer |
| GET | `/customers/list` | Customer List | Active customers for dropdowns |
| PATCH | `/customers/{id}/toggle-status` | Customer Toggle Status | Toggle active/inactive |

### Sales

| Method | URI | Permission | Description |
|--------|-----|------------|-------------|
| GET | `/sales` | Sale Index | Paginated list (search, customer, payment status, business type, date range filters) |
| POST | `/sales` | Sale Create | Create sale (supports bill_image upload) |
| GET | `/sales/{id}` | Sale Index | Get sale by ID with customer & payment allocation |
| PUT | `/sales/{id}` | Sale Update | Update sale |
| DELETE | `/sales/{id}` | Sale Delete | Delete sale |
| GET | `/sales/list` | Sale List | Active sales for dropdowns |

### Cheques

| Method | URI | Permission | Description |
|--------|-----|------------|-------------|
| GET | `/cheques` | Cheque Index | Paginated list (search, status, customer, date range filters) |
| POST | `/cheques` | Cheque Create | Create cheque (supports cheque_image upload) |
| GET | `/cheques/{id}` | Cheque Index | Get cheque by ID |
| PUT | `/cheques/{id}` | Cheque Update | Update cheque |
| DELETE | `/cheques/{id}` | Cheque Delete | Delete cheque |
| GET | `/cheques/list` | Cheque List | All cheques for dropdowns |
| PATCH | `/cheques/{id}/status` | Cheque Update Status | Update status (pending/cleared/bounced) |

### Payments

| Method | URI | Permission | Description |
|--------|-----|------------|-------------|
| GET | `/payments` | Payment Index | Paginated list (search, customer, payment method, date range filters) |
| POST | `/payments` | Payment Create | Create payment with FIFO allocation to sales (supports proof_image upload) |
| GET | `/payments/{id}` | Payment Index | Get payment by ID with allocated sales |
| PUT | `/payments/{id}` | Payment Update | Update payment |
| DELETE | `/payments/{id}` | Payment Delete | Delete payment & reverse allocations |

### Expenses

| Method | URI | Permission | Description |
|--------|-----|------------|-------------|
| GET | `/expenses` | Expense Index | Paginated list (search, category, date range filters) |
| POST | `/expenses` | Expense Create | Create expense (supports receipt_image upload) |
| GET | `/expenses/{id}` | Expense Index | Get expense by ID |
| PUT | `/expenses/{id}` | Expense Update | Update expense |
| DELETE | `/expenses/{id}` | Expense Delete | Delete expense |
| GET | `/expenses/summary` | Expense Index | Total & breakdown by category for a date range |

### Finance & Reports

| Method | URI | Permission | Description |
|--------|-----|------------|-------------|
| GET | `/finance/dashboard` | Finance Dashboard | Dashboard with totals, top customers, recent sales/payments/cheques/expenses |
| GET | `/finance/pnl` | Finance Dashboard | Profit & Loss statement for a date range |
| GET | `/finance/income-breakdown` | Finance Dashboard | Income grouped by payment method |
| GET | `/finance/expense-breakdown` | Finance Dashboard | Expenses grouped by category |
| GET | `/finance/dues-aging` | Finance Dashboard | Customer outstanding balance aging (0-30, 31-60, 61-90, 90+ days) |

### Activity Logs

| Method | URI | Permission | Description |
|--------|-----|------------|-------------|
| GET | `/activity-logs` | ActivityLog Index | Paginated list (search, module, action, level, user, date range filters) |
| GET | `/activity-logs/{id}` | ActivityLog Show | Get log entry by ID |

### System

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/` | Root info (app name, version, endpoints) |
| GET | `/health` | Health check with DB status |
| GET | `/api/health-check` | Simple health check |

## Response Format

All endpoints return a consistent JSON structure:

```json
{
  "status": "success" | "error",
  "message": "Human-readable message",
  "data": { ... } | [ ... ],
  "error": "Detailed error message (debug mode only)"
}
```

Paginated responses include Laravel pagination metadata (`current_page`, `last_page`, `per_page`, `total`, etc.) nested inside `data`.

## Error Codes

| Status | Description |
|--------|-------------|
| 200 | Success |
| 201 | Created |
| 401 | Unauthenticated / Invalid token |
| 403 | Forbidden (missing permission) |
| 404 | Resource not found |
| 422 | Validation error |
| 500 | Server error (debug message in dev mode) |

## Database Schema

| Table | Key Relationships |
|-------|-----------------|
| `users` | roles (many-to-many via Spatie) |
| `roles` | permissions (many-to-many via Spatie) |
| `permissions` | roles (many-to-many via Spatie) |
| `customers` | sales, cheques, payments |
| `sales` | customer, creator, updater, payment_sales, cheques |
| `cheques` | customer, sale, creator, updater |
| `payments` | customer, cheque, creator, updater, payment_sales |
| `payment_sales` | payment, sale (FIFO allocation pivot) |
| `expenses` | creator, updater |
| `finance_records` | polymorphic reference to sales & payments |
| `activity_logs` | user (morphTo) |

## Testing

```bash
./vendor/bin/pest
```

## License

MIT
