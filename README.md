# Garam Cap Kapal — Backend API

> RESTful API backend for the **Garam Cap Kapal** e-commerce platform, built with Laravel 13 and PHP 8.3.
> Manages product catalog, order lifecycle, payments (Midtrans), reward points, community forum, career listings, and a full admin dashboard.

---

## Table of Contents

- [Tech Stack](#tech-stack)
- [Architecture Overview](#architecture-overview)
  - [Modular Monolith](#modular-monolith)
  - [Directory Structure](#directory-structure)
  - [Module Anatomy](#module-anatomy)
  - [Request Lifecycle](#request-lifecycle)
- [Modules](#modules)
  - [Auth](#auth-module)
  - [Product](#product-module)
  - [Order](#order-module)
  - [Payment](#payment-module)
  - [Reward](#reward-module)
  - [Forum](#forum-module)
  - [Career](#career-module)
  - [CMS](#cms-module)
  - [Promo](#promo-module)
  - [Notification](#notification-module)
  - [Admin](#admin-module)
- [Database Schema](#database-schema)
  - [Entity Relationship Diagram](#entity-relationship-diagram)
  - [Key Models](#key-models)
- [API Design](#api-design)
  - [Versioning & Base URL](#versioning--base-url)
  - [Authentication](#authentication)
  - [Rate Limiting](#rate-limiting)
  - [Response Format](#response-format)
  - [Error Handling](#error-handling)
- [API Endpoints](#api-endpoints)
- [Core Patterns & Conventions](#core-patterns--conventions)
  - [Repository Pattern](#repository-pattern)
  - [Service Layer](#service-layer)
  - [Data Transfer Objects (DTOs)](#data-transfer-objects-dtos)
  - [API Resources (Transformers)](#api-resources-transformers)
  - [Form Request Validation](#form-request-validation)
  - [Enums & State Machines](#enums--state-machines)
  - [Event-Driven Architecture](#event-driven-architecture)
  - [Background Jobs](#background-jobs)
  - [Scheduled Tasks](#scheduled-tasks)
- [Infrastructure](#infrastructure)
  - [Docker Services](#docker-services)
  - [CI/CD Pipeline](#cicd-pipeline)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Local Development (Docker)](#local-development-docker)
  - [Local Development (Native)](#local-development-native)
- [Testing](#testing)
- [Environment Variables](#environment-variables)

---

## Tech Stack

| Layer              | Technology                                                 |
| ------------------ | ---------------------------------------------------------- |
| **Framework**      | Laravel 13 (PHP 8.3)                                       |
| **Database**       | PostgreSQL 16                                               |
| **Cache / Queue**  | Redis 7 (via Laravel Horizon)                               |
| **Search**         | Meilisearch (via Laravel Scout)                             |
| **Auth**           | Laravel Sanctum (token-based)                               |
| **Payment**        | Midtrans (Virtual Account, e-Wallet, QRIS, Retail Outlet)  |
| **File Storage**   | AWS S3 + Spatie Media Library                               |
| **Image Processing** | Intervention Image                                       |
| **Authorization**  | Spatie Laravel Permission (roles & permissions)             |
| **Observability**  | Laravel Telescope, Activity Log (Spatie)                    |
| **Container**      | Docker (PHP-FPM 8.3 Alpine + Nginx)                        |
| **CI/CD**          | GitHub Actions → SSH deploy to aaPanel                      |
| **Static Analysis**| PHPStan + Larastan                                          |
| **Code Style**     | Laravel Pint                                                |

---

## Architecture Overview

### Modular Monolith

The application follows a **modular monolith** architecture. Business logic is organized into self-contained modules under `app/Modules/`, each encapsulating its own controllers, services, repositories, models, events, jobs, and routes. Cross-module communication happens through service injection and Laravel's event system.

```
┌─────────────────────────────────────────────────────────────────────┐
│                         routes/api.php                              │
│   ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐             │
│   │  Public   │ │ Webhook  │ │   Auth   │ │  Admin   │             │
│   │ throttle  │ │ (no auth)│ │ throttle │ │ throttle │             │
│   └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘             │
│        │             │            │             │                    │
│   Module Routes  Module Routes  Module Routes  Module Routes        │
└────────┼─────────────┼────────────┼─────────────┼───────────────────┘
         │             │            │             │
         ▼             ▼            ▼             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        app/Modules/                                 │
│  ┌───────┐ ┌─────────┐ ┌───────┐ ┌─────────┐ ┌────────┐          │
│  │ Auth  │ │ Product │ │ Order │ │ Payment │ │ Reward │  ...      │
│  └───┬───┘ └────┬────┘ └───┬───┘ └────┬────┘ └───┬────┘          │
│      │          │          │          │           │                 │
│      ▼          ▼          ▼          ▼           ▼                 │
│  Controller → Service → Repository → Model → Database              │
└─────────────────────────────────────────────────────────────────────┘
```

### Directory Structure

```
garam-capkapal-api/
├── app/
│   ├── Core/                          # Shared kernel
│   │   ├── Contracts/                 # Base interfaces (RepositoryInterface)
│   │   ├── Enums/                     # Shared enums
│   │   ├── Exceptions/                # Global exception handler
│   │   ├── Http/                      # Shared middleware & requests
│   │   ├── Providers/                 # Core service providers
│   │   └── Traits/                    # Shared traits (ApiResponseTrait)
│   │
│   ├── Models/                        # Global models (User)
│   │
│   ├── Modules/                       # Feature modules
│   │   ├── Admin/                     # Dashboard, order mgmt, reports, inventory
│   │   ├── Auth/                      # Register, login, profile, addresses
│   │   ├── Career/                    # Job listings & applications
│   │   ├── CMS/                       # Content management (WIP)
│   │   ├── Forum/                     # Community discussions
│   │   ├── Notification/              # Push/email notifications (WIP)
│   │   ├── Order/                     # Cart, checkout, order tracking
│   │   ├── Payment/                   # Midtrans gateway, webhooks
│   │   ├── Product/                   # Catalog, categories, reviews, search
│   │   ├── Promo/                     # Promotions & coupons (WIP)
│   │   └── Reward/                    # Points, tiers, redemptions, vouchers
│   │
│   ├── Console/                       # Artisan commands
│   ├── Events/                        # Application-level events
│   ├── Jobs/                          # Cross-module queued jobs
│   ├── Listeners/                     # Application-level event listeners
│   ├── Mail/                          # Mailable classes
│   ├── Notifications/                 # Notification classes
│   └── Providers/                     # Service providers
│
├── config/                            # Framework & package configuration
├── database/
│   ├── factories/                     # Model factories
│   ├── migrations/                    # 47 migration files
│   └── seeders/                       # Database seeders
├── docker/
│   └── nginx/                         # Nginx config for Docker
├── routes/
│   ├── api.php                        # API route orchestrator
│   ├── console.php                    # Scheduled tasks
│   └── web.php                        # Web routes (minimal)
├── tests/
│   ├── Feature/                       # Feature/integration tests
│   └── Unit/                          # Unit tests
├── Dockerfile                         # PHP 8.3-FPM Alpine
├── docker-compose.yml                 # Production-like services
├── docker-compose.override.yml        # Dev overrides (ports, Vite)
└── .github/workflows/deploy.yml       # CI/CD pipeline
```

### Module Anatomy

Each module follows a consistent internal structure:

```
app/Modules/{ModuleName}/
├── Controllers/             # HTTP controllers (thin, delegates to service)
├── DataTransferObjects/     # Spatie DTOs for typed data passing
├── Enums/                   # Module-specific PHP 8.1+ backed enums
├── Events/                  # Domain events
├── Jobs/                    # Queued jobs
├── Listeners/               # Event listeners
├── Models/                  # Eloquent models (scoped to module)
├── Observers/               # Model observers
├── Policies/                # Authorization policies
├── Repositories/
│   ├── Contracts/           # Repository interfaces
│   └── Eloquent/            # Eloquent implementations
├── Requests/                # Form request validation
├── Resources/               # API resources (JSON transformers)
├── Services/                # Business logic layer
└── routes.php               # Module route definitions
```

### Request Lifecycle

```
HTTP Request
    │
    ▼
┌──────────────────────┐
│  Middleware Pipeline  │
│  • Rate limiting      │
│  • Auth (Sanctum)     │
│  • Role check         │
│  • HMAC verification  │
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│  Form Request        │  ← Input validation & authorization
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│  Controller          │  ← Thin — delegates to service
│  (uses ApiResponse)  │
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│  Service Layer       │  ← Business logic, orchestration
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│  Repository          │  ← Data access abstraction
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│  Eloquent Model      │  ← Database interaction
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│  API Resource        │  ← JSON transformation
└──────────┬───────────┘
           │
           ▼
JSON Response (ApiResponseTrait)
```

---

## Modules

### Auth Module

Handles user registration, login, email verification, password reset, profile management, and address book.

| Area       | Details                                                     |
| ---------- | ----------------------------------------------------------- |
| Models     | `User`, `UserProfile`, `UserAddress`, `Role`, `Permission`  |
| Auth       | Sanctum token-based (bearer tokens)                         |
| Roles      | `admin`, `editor`, `user`, `agen`                           |
| Features   | Register, login, logout, refresh, forgot/reset password, email verification, profile CRUD, avatar upload, address CRUD |

### Product Module

Full product catalog with variants, categories, images, reviews, and full-text search.

| Area       | Details                                                              |
| ---------- | -------------------------------------------------------------------- |
| Models     | `Product`, `ProductVariant`, `ProductImage`, `ProductReview`, `Category` |
| Search     | Meilisearch via Laravel Scout (autocomplete suggestions)             |
| Status     | `draft`, `active`, `archived` (`ProductStatus` enum)                 |
| Features   | Public listing & detail, featured products, category browse, search suggestions, review submission, admin CRUD, image management, stock management, status toggling |

### Order Module

Cart management, checkout processing, and order lifecycle tracking.

| Area       | Details                                                                  |
| ---------- | ------------------------------------------------------------------------ |
| Models     | `Cart`, `CartItem`, `Order`, `OrderItem`, `OrderStatusHistory`, `ShippingAddress`, `Coupon` |
| Status     | `pending` → `confirmed` → `processing` → `shipped` → `delivered` → `completed` (with `cancelled` / `refunded` branches) |
| Features   | Cart CRUD, coupon apply/remove, shipping options, checkout, order history, order cancellation |

**Order State Machine:**
```
pending ──→ confirmed ──→ processing ──→ shipped ──→ delivered ──→ completed
   │            │              │                          │
   └──→ cancelled ←────────────┘                          └──→ refunded
```

### Payment Module

Midtrans payment gateway integration with webhook-based payment status updates.

| Area       | Details                                                        |
| ---------- | -------------------------------------------------------------- |
| Models     | `Payment`, `PaymentLog`                                        |
| Gateway    | `PaymentGatewayInterface` → `MidtransGateway` implementation  |
| Methods    | Virtual Account, e-Wallet, QRIS, Credit Card, Retail Outlet   |
| Status     | `pending`, `waiting`, `paid`, `failed`, `expired`, `refunded`, `voided` |
| Webhook    | `POST /api/v1/webhooks/midtrans` — HMAC signature verified via `VerifyMidtransNotification` middleware |
| Features   | Create payment, check status, retry payment, webhook handling, refund, void, admin bulk sync |

### Reward Module

Loyalty points system with tiered rewards, point redemption, and voucher management.

| Area       | Details                                                                 |
| ---------- | ----------------------------------------------------------------------- |
| Models     | `RewardPoint`, `PointItem`, `PointTransaction`, `Redemption`, `UserVoucher` |
| Features   | View redeemable items, redeem points, transaction history, voucher wallet, admin item CRUD, admin verification, summary reports, user history |

### Forum Module

Community discussion board with threaded replies and like functionality.

| Area       | Details                                         |
| ---------- | ----------------------------------------------- |
| Models     | `ForumPost`, `ForumReply`, `ForumLike`          |
| Features   | List/show posts, forum stats, create posts, reply, toggle like (posts & replies) |

### Career Module

Job listing and application management system.

| Area       | Details                                                       |
| ---------- | ------------------------------------------------------------- |
| Models     | `Career`, `CareerApplication`                                 |
| Enums      | `CareerStatus` (draft/published), `EmploymentType` (full-time/part-time/contract/internship), `ApplicationStatus` |
| Features   | Public job listing with filtering, job detail, apply (with file upload), admin CRUD, application review |

### CMS Module

> 🚧 **Work in progress** — Module structure created, routes not yet defined.

Content management for static pages, banners, and promotional content.

### Promo Module

> 🚧 **Work in progress** — Module structure created, routes not yet defined.

Promotional campaigns and coupon management.

### Notification Module

> 🚧 **Work in progress** — Module structure created, routes not yet defined.

Push notifications and email notification management.

### Admin Module

Centralized back-office module for dashboard analytics, order management, reporting, inventory control, user management, and audit logging.

| Area       | Details                                                                                 |
| ---------- | --------------------------------------------------------------------------------------- |
| Controllers| `DashboardController`, `OrderManagementController`, `ReportController`, `InventoryController`, `UserManagementController`, `AuditLogController` |
| Features   | Dashboard KPIs & charts (revenue, top products, user distribution), order management with status updates & notes, revenue/product/user reports with CSV export (async), inventory summary with low/out-of-stock alerts, bulk stock import, user management (suspend/activate/role), audit log viewer |

---

## Database Schema

### Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              USER DOMAIN                                        │
│  ┌──────────┐   1:1   ┌──────────────┐   1:N   ┌───────────────┐              │
│  │  users   │────────→│ user_profiles │        │ user_addresses │              │
│  └────┬─────┘         └──────────────┘         └───────────────┘              │
│       │ M:N                                                                    │
│  ┌────┴──────┐                                                                 │
│  │   roles   │←──→ permissions                                                 │
│  └───────────┘                                                                 │
└──────┬──────────────────────────────────────────────────────────────────────────┘
       │
       │ 1:N
       ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│                            COMMERCE DOMAIN                                      │
│                                                                                 │
│  ┌────────────┐  N:1  ┌──────────┐  1:N  ┌──────────────────┐                 │
│  │ categories │←──────│ products │──────→│ product_variants  │                 │
│  └────────────┘       └────┬─────┘       └──────────────────┘                 │
│                            │ 1:N                                                │
│                    ┌───────┼────────┐                                           │
│                    ▼       ▼        ▼                                           │
│            ┌────────────┐ ┌─────────────────┐                                  │
│            │prod_images │ │ product_reviews  │                                  │
│            └────────────┘ └─────────────────┘                                  │
│                                                                                 │
│  ┌────────┐  1:N  ┌────────────┐                                               │
│  │ carts  │──────→│ cart_items  │                                               │
│  └────────┘       └────────────┘                                               │
│                                                                                 │
│  ┌────────┐  1:N  ┌─────────────┐  1:1  ┌──────────────────────┐              │
│  │ orders │──────→│ order_items  │      │ shipping_addresses    │              │
│  └───┬────┘       └─────────────┘       └──────────────────────┘              │
│      │ 1:N                                                                     │
│      ├──→ order_status_histories                                               │
│      └──→ payments ──→ payment_logs                                            │
│                                                                                 │
│  ┌─────────┐  1:N  ┌───────────────┐                                           │
│  │ coupons │──────→│ coupon_usages  │                                           │
│  └─────────┘       └───────────────┘                                           │
└─────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────┐
│                            REWARD DOMAIN                                        │
│  ┌───────────────┐  ┌────────────────────┐  ┌──────────────┐                   │
│  │ reward_points │  │ point_transactions │  │ point_items  │                   │
│  └───────────────┘  └────────────────────┘  └──────┬───────┘                   │
│                                                     │ 1:N                       │
│                     ┌──────────────┐  1:N  ┌────────┴──────┐                   │
│                     │ user_vouchers│←──────│  redemptions  │                   │
│                     └──────────────┘       └───────────────┘                   │
└─────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────┐
│                          COMMUNITY DOMAIN                                       │
│  ┌─────────────┐  1:N  ┌───────────────┐                                       │
│  │ forum_posts │──────→│ forum_replies  │                                       │
│  └──────┬──────┘       └───────────────┘                                       │
│         │ M:N                                                                   │
│  ┌──────┴──────┐                                                                │
│  │ forum_likes │ (polymorphic: posts & replies)                                 │
│  └─────────────┘                                                                │
│                                                                                 │
│  ┌──────────┐  1:N  ┌───────────────────────┐                                  │
│  │ careers  │──────→│ career_applications    │                                  │
│  └──────────┘       └───────────────────────┘                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Key Models

| Model                | Table                      | UUID | Slug | Soft Delete | Notable Traits/Features                  |
| -------------------- | -------------------------- | ---- | ---- | ----------- | ---------------------------------------- |
| `User`               | `users`                    | ✅   | ✗    | ✗           | HasRoles, HasProfile, points balance     |
| `Product`            | `products`                 | ✅   | ✅   | ✗           | Searchable (Scout), HasMedia, HasSlug    |
| `ProductVariant`     | `product_variants`         | ✅   | ✗    | ✗           | Stock tracking, weight, price            |
| `Category`           | `categories`               | ✅   | ✅   | ✗           | Nested (parent_id)                       |
| `Order`              | `orders`                   | ✅   | ✗    | ✗           | Order number, state machine, totals      |
| `Payment`            | `payments`                 | ✅   | ✗    | ✗           | Gateway ID, method, status               |
| `Career`             | `careers`                  | ✅   | ✅   | ✗           | Status, employment type, location        |
| `CareerApplication`  | `career_applications`      | ✅   | ✗    | ✗           | Applicant info, resume, status           |
| `ForumPost`          | `forum_posts`              | ✅   | ✗    | ✗           | Like count, reply count                  |

---

## API Design

### Versioning & Base URL

```
Base URL: /api/v1
```

All endpoints are prefixed with `/api/v1`. Versioning is handled via URL path prefix.

### Authentication

| Method | Description |
| ------ | ----------- |
| **Bearer Token** | Laravel Sanctum. Include `Authorization: Bearer {token}` in headers. |
| **Login** | `POST /api/v1/auth/login` → Returns token |
| **Logout** | `POST /api/v1/auth/logout` → Revokes token |

### Rate Limiting

| Tier       | Limit        | Scope               |
| ---------- | ------------ | -------------------- |
| **Public** | 60 req/min   | Per IP address       |
| **Auth**   | 120 req/min  | Per user ID          |
| **Admin**  | 300 req/min  | Per user ID          |

### Response Format

All responses follow a consistent JSON envelope:

**Success:**
```json
{
  "status": "success",
  "message": "Success",
  "data": { ... }
}
```

**Success (Created):**
```json
{
  "status": "success",
  "message": "Created",
  "data": { ... }
}
```

**No Content:** `204` with empty body.

**Error:**
```json
{
  "status": "error",
  "message": "Validation failed.",
  "errors": {
    "field": ["Error message"]
  }
}
```

### Error Handling

| Status Code | Description                           |
| ----------- | ------------------------------------- |
| `401`       | Unauthenticated (invalid/missing token) |
| `403`       | Forbidden (insufficient role)         |
| `404`       | Resource not found                    |
| `422`       | Validation error (with field details) |
| `429`       | Rate limit exceeded                   |
| `500`       | Internal server error                 |

---

## API Endpoints

### Auth (`/api/v1/auth`, `/api/v1/user`, `/api/v1/addresses`)

| Method   | Endpoint                      | Auth | Description               |
| -------- | ----------------------------- | ---- | ------------------------- |
| `POST`   | `/auth/register`              | ✗    | Register new user         |
| `POST`   | `/auth/login`                 | ✗    | Login                     |
| `POST`   | `/auth/forgot-password`       | ✗    | Send password reset email |
| `POST`   | `/auth/reset-password`        | ✗    | Reset password            |
| `GET`    | `/auth/verify-email/{token}`  | ✗    | Verify email address      |
| `GET`    | `/auth/me`                    | ✅   | Get current user          |
| `POST`   | `/auth/logout`                | ✅   | Logout                    |
| `POST`   | `/auth/refresh`               | ✅   | Refresh token             |
| `GET`    | `/user/profile`               | ✅   | Get profile               |
| `PUT`    | `/user/profile`               | ✅   | Update profile            |
| `POST`   | `/user/avatar`                | ✅   | Upload avatar             |
| `PUT`    | `/user/password`              | ✅   | Change password           |
| `GET`    | `/addresses`                  | ✅   | List addresses            |
| `POST`   | `/addresses`                  | ✅   | Create address            |
| `PUT`    | `/addresses/{address}`        | ✅   | Update address            |
| `DELETE` | `/addresses/{address}`        | ✅   | Delete address            |

### Products (`/api/v1/products`, `/api/v1/categories`)

| Method   | Endpoint                                    | Auth  | Description            |
| -------- | ------------------------------------------- | ----- | ---------------------- |
| `GET`    | `/products`                                 | ✗     | List products          |
| `GET`    | `/products/featured`                        | ✗     | Featured products      |
| `GET`    | `/products/search/suggest`                  | ✗     | Search autocomplete    |
| `GET`    | `/products/{slug}`                          | ✗     | Product detail         |
| `GET`    | `/products/{slug}/reviews`                  | ✗     | Product reviews        |
| `POST`   | `/products/{slug}/reviews`                  | ✅    | Submit a review        |
| `GET`    | `/categories`                               | ✗     | List categories        |
| `GET`    | `/categories/{slug}`                        | ✗     | Category detail        |
| `GET`    | `/admin/products`                           | Admin | Admin product list     |
| `POST`   | `/admin/products`                           | Admin | Create product         |
| `PUT`    | `/admin/products/{product}`                 | Admin | Update product         |
| `PATCH`  | `/admin/products/{product}/update-status`   | Admin | Toggle product status  |
| `POST`   | `/admin/products/{product}/images`          | Admin | Upload product image   |
| `DELETE` | `/admin/products/images/{image}`            | Admin | Delete product image   |
| `PATCH`  | `/admin/products/variants/{variant}/stock`  | Admin | Update variant stock   |
| `POST`   | `/admin/categories`                         | Admin | Create category        |
| `PUT`    | `/admin/categories/{id}`                    | Admin | Update category        |

### Orders (`/api/v1/cart`, `/api/v1/checkout`, `/api/v1/orders`)

| Method   | Endpoint                        | Auth  | Role          | Description         |
| -------- | ------------------------------- | ----- | ------------- | ------------------- |
| `GET`    | `/cart`                         | ✅    | user \| agen  | View cart           |
| `POST`   | `/cart/items`                   | ✅    | user \| agen  | Add item to cart    |
| `PUT`    | `/cart/items/{cartItem}`        | ✅    | user \| agen  | Update item qty     |
| `DELETE` | `/cart/items/{cartItem}`        | ✅    | user \| agen  | Remove item         |
| `POST`   | `/cart/coupon`                  | ✅    | user \| agen  | Apply coupon        |
| `DELETE` | `/cart/coupon`                  | ✅    | user \| agen  | Remove coupon       |
| `GET`    | `/cart/shipping-options`        | ✅    | user \| agen  | Get shipping rates  |
| `DELETE` | `/cart`                         | ✅    | user \| agen  | Clear cart          |
| `POST`   | `/checkout`                     | ✅    | user \| agen  | Process checkout    |
| `GET`    | `/orders`                       | ✅    | user \| agen  | List my orders      |
| `GET`    | `/orders/{orderNumber}`         | ✅    | user \| agen  | Order detail        |
| `POST`   | `/orders/{orderNumber}/cancel`  | ✅    | user \| agen  | Cancel order        |

### Payments (`/api/v1/payments`, `/api/v1/webhooks`)

| Method   | Endpoint                         | Auth  | Description                |
| -------- | -------------------------------- | ----- | -------------------------- |
| `POST`   | `/webhooks/midtrans`             | ✗*    | Midtrans webhook callback  |
| `GET`    | `/payments/status/{orderId}`     | ✅    | Check payment status       |
| `POST`   | `/payments/{orderNumber}/retry`  | ✅    | Retry failed payment       |
| `POST`   | `/admin/payments/sync-all`       | Admin | Bulk sync payment statuses |

> \* Webhook is verified via HMAC signature middleware, not Sanctum auth.

### Rewards (`/api/v1/api/reward`)

| Method   | Endpoint                              | Auth  | Description                |
| -------- | ------------------------------------- | ----- | -------------------------- |
| `GET`    | `/api/reward/items`                   | ✅    | List redeemable items      |
| `GET`    | `/api/reward/transactions`            | ✅    | Point transaction history  |
| `GET`    | `/api/reward/vouchers`                | ✅    | My vouchers                |
| `GET`    | `/api/reward/redemptions/{id}`        | ✅    | Redemption detail          |
| `POST`   | `/api/reward/redeem`                  | ✅    | Redeem points              |
| `GET`    | `/api/admin/reward/items`             | Admin | Admin list items           |
| `POST`   | `/api/admin/reward/items`             | Admin | Create reward item         |
| `PUT`    | `/api/admin/reward/items/{id}`        | Admin | Update reward item         |
| `DELETE` | `/api/admin/reward/items/{id}`        | Admin | Delete reward item         |
| `GET`    | `/api/admin/reward/transactions`      | Admin | All transactions           |
| `PATCH`  | `/api/admin/reward/redemption/{id}/status` | Admin | Verify redemption     |
| `GET`    | `/api/admin/reward/summary`           | Admin | Summary report             |
| `GET`    | `/api/admin/reward/users/{id}/history`| Admin | User point history         |

### Forum (`/api/v1/forum`)

| Method   | Endpoint                                | Auth | Description         |
| -------- | --------------------------------------- | ---- | ------------------- |
| `GET`    | `/forum`                                | ✗    | List posts          |
| `GET`    | `/forum/stats`                          | ✗    | Forum statistics    |
| `GET`    | `/forum/{id}`                           | ✗    | Post detail         |
| `POST`   | `/forum`                                | ✅   | Create post         |
| `POST`   | `/forum/{id}/replies`                   | ✅   | Reply to post       |
| `POST`   | `/forum/{id}/like`                      | ✅   | Toggle post like    |
| `POST`   | `/forum/{id}/replies/{replyId}/like`    | ✅   | Toggle reply like   |

### Careers (`/api/v1/careers`)

| Method   | Endpoint                                         | Auth  | Description           |
| -------- | ------------------------------------------------ | ----- | --------------------- |
| `GET`    | `/careers`                                       | ✗     | List published jobs   |
| `GET`    | `/careers/{slug}`                                | ✗     | Job detail            |
| `POST`   | `/careers/{slug}/apply`                          | ✗     | Submit application    |
| `GET`    | `/admin/careers`                                 | Admin | Admin list all        |
| `POST`   | `/admin/careers`                                 | Admin | Create job listing    |
| `GET`    | `/admin/careers/{career}`                        | Admin | Admin job detail      |
| `PUT`    | `/admin/careers/{career}`                        | Admin | Update job listing    |
| `DELETE` | `/admin/careers/{career}`                        | Admin | Delete job listing    |
| `PATCH`  | `/admin/careers/{career}/status`                 | Admin | Update job status     |
| `GET`    | `/admin/careers/{career}/applications`           | Admin | List applications     |
| `GET`    | `/admin/careers/applications/{application}`      | Admin | Application detail    |
| `PATCH`  | `/admin/careers/applications/{application}/status` | Admin | Update app status   |

### Admin Dashboard (`/api/v1/admin`)

| Method   | Endpoint                               | Auth  | Role          | Description              |
| -------- | -------------------------------------- | ----- | ------------- | ------------------------ |
| `GET`    | `/admin/dashboard`                     | ✅    | admin\|editor | Dashboard KPIs           |
| `GET`    | `/admin/dashboard/revenue-chart`       | ✅    | admin\|editor | Revenue chart data       |
| `GET`    | `/admin/dashboard/top-products`        | ✅    | admin\|editor | Top selling products     |
| `GET`    | `/admin/dashboard/user-distribution`   | ✅    | admin\|editor | User distribution stats  |
| `GET`    | `/admin/orders`                        | ✅    | admin\|editor | List all orders          |
| `GET`    | `/admin/orders/{orderNumber}`          | ✅    | admin\|editor | Order detail             |
| `PATCH`  | `/admin/orders/{orderId}/status`       | ✅    | admin\|editor | Update order status      |
| `POST`   | `/admin/orders/{orderId}/notes`        | ✅    | admin\|editor | Add admin note           |
| `GET`    | `/admin/reports/revenue`               | ✅    | admin\|editor | Revenue report           |
| `GET`    | `/admin/reports/products`              | ✅    | admin\|editor | Product report           |
| `GET`    | `/admin/reports/users`                 | ✅    | admin\|editor | User report              |
| `GET`    | `/admin/reports/revenue/export`        | ✅    | admin\|editor | Export revenue CSV       |
| `POST`   | `/admin/reports/export`                | ✅    | admin\|editor | Queue async export       |
| `GET`    | `/admin/reports/export/{exportId}`     | ✅    | admin\|editor | Check export status      |
| `GET`    | `/admin/inventory/summary`             | ✅    | admin\|editor | Inventory summary        |
| `GET`    | `/admin/inventory/low-stock`           | ✅    | admin\|editor | Low stock alerts         |
| `GET`    | `/admin/inventory/out-of-stock`        | ✅    | admin\|editor | Out of stock items       |
| `PATCH`  | `/admin/inventory/{variantId}/stock`   | ✅    | admin         | Adjust stock             |
| `POST`   | `/admin/inventory/bulk-import`         | ✅    | admin         | Bulk stock import        |
| `GET`    | `/admin/users`                         | ✅    | admin\|editor | List users               |
| `GET`    | `/admin/users/{userId}`                | ✅    | admin\|editor | User detail              |
| `POST`   | `/admin/users/{userId}/suspend`        | ✅    | admin         | Suspend user             |
| `POST`   | `/admin/users/{userId}/activate`       | ✅    | admin         | Activate user            |
| `PATCH`  | `/admin/users/{userId}/role`           | ✅    | admin         | Update user role         |
| `GET`    | `/admin/audit-logs`                    | ✅    | admin         | View audit logs          |

---

## Core Patterns & Conventions

### Repository Pattern

All data access goes through repository interfaces, enabling testability and future data source swapping.

```
app/Core/Contracts/RepositoryInterface.php          ← Base CRUD interface
app/Modules/{Module}/Repositories/Contracts/        ← Module-specific interface (extends base)
app/Modules/{Module}/Repositories/Eloquent/         ← Eloquent implementation
```

**Base interface methods:** `all()`, `paginate()`, `findById()`, `findOrFail()`, `create()`, `update()`, `delete()`

Module interfaces extend with domain-specific methods (e.g., `findBySlug()`, `listPublic()`, `incrementSoldCount()`).

### Service Layer

Business logic is encapsulated in service classes. Controllers remain thin — they validate input, delegate to the service, and return a response.

```php
// Controller (thin)
public function store(StoreCareerRequest $request): JsonResponse
{
    $career = $this->careerService->create($request->validated());
    return $this->created(new CareerResource($career));
}
```

### Data Transfer Objects (DTOs)

Typed data objects using `spatie/laravel-data` for structured data passing between layers. Located in each module's `DataTransferObjects/` directory.

### API Resources (Transformers)

Laravel API Resources in `Resources/` directories handle JSON serialization. They decouple internal model structure from the public API contract.

### Form Request Validation

Dedicated Form Request classes in `Requests/` directories encapsulate validation rules and authorization logic, keeping controllers clean.

### Enums & State Machines

PHP 8.1+ backed enums are used extensively for type-safe status management:

| Enum               | Values                                                                         |
| ------------------- | ----------------------------------------------------------------------------- |
| `OrderStatus`       | `pending`, `confirmed`, `processing`, `shipped`, `delivered`, `completed`, `cancelled`, `refunded` |
| `PaymentStatus`     | `pending`, `waiting`, `paid`, `failed`, `expired`, `refunded`, `voided`       |
| `PaymentMethod`     | `virtual_account`, `ewallet`, `qris`, `credit_card`, `retail_outlet`, `flat`  |
| `ProductStatus`     | `draft`, `active`, `archived`                                                  |
| `CareerStatus`      | `draft`, `published`                                                           |
| `EmploymentType`    | `full_time`, `part_time`, `contract`, `internship`                             |
| `ApplicationStatus` | Manages career application workflow                                            |

`OrderStatus` includes a full state machine with `allowedTransitions()`, `canTransitionTo()`, and `isFinal()` methods.

### Event-Driven Architecture

Domain events are dispatched from services and handled by listeners/jobs for side effects:
- Order confirmed → reward points calculation
- Payment received → order status update
- Product reviewed → rating recalculation

Events and listeners are organized within each module's `Events/` and `Listeners/` directories.

### Background Jobs

Queued jobs (processed by Laravel Horizon + Redis) handle async work:

| Job                         | Description                                       |
| --------------------------- | ------------------------------------------------- |
| `CheckRewardTierUpgrade`    | Evaluate user tier promotion after point changes   |
| `GenerateAdminExport`       | Async CSV/Excel report generation → uploaded to S3 |
| `RecalculateProductRating`  | Recompute average rating after new review          |
| `RefundRewardPoints`        | Return points on order cancellation/refund         |

### Scheduled Tasks

Defined in `routes/console.php`:

| Schedule              | Command / Task                       | Description                          |
| --------------------- | ------------------------------------ | ------------------------------------ |
| Every 5 min           | `payments:expire`                    | Expire stale pending payments        |
| Daily 00:05           | `rewards:expire-points`              | Expire old reward points             |
| Weekly                | `payments:prune-logs --days=90`      | Clean up payment logs > 90 days      |
| Daily 06:00           | `admin:generate-daily-report`        | Generate daily admin report          |
| Weekly Sun 02:00      | S3 export cleanup                    | Delete export files > 7 days old     |

---

## Infrastructure

### Docker Services

Defined in `docker-compose.yml`:

| Service       | Image                   | Port  | Description                          |
| ------------- | ----------------------- | ----- | ------------------------------------ |
| `app`         | PHP 8.3-FPM Alpine      | 9000  | Laravel application (PHP-FPM)        |
| `web`         | Nginx Alpine            | 8000  | Reverse proxy / static files         |
| `db`          | PostgreSQL 16 Alpine    | 5432* | Primary database                     |
| `redis`       | Redis 7 Alpine          | 6379* | Cache, sessions, queue broker        |
| `horizon`     | (same as `app`)         | —     | Queue worker (Laravel Horizon)       |
| `scheduler`   | (same as `app`)         | —     | Task scheduler (cron loop)           |
| `vite`*       | Node 20 Alpine          | 5173* | Vite dev server (dev override only)  |

> \* Ports exposed only in `docker-compose.override.yml` for local development.

### CI/CD Pipeline

**GitHub Actions** (`.github/workflows/deploy.yml`):

```
Push to `develop` branch
    │
    ▼
GitHub Actions Runner (ubuntu-latest)
    │
    ▼
SSH into production server (aaPanel)
    │
    ├── git pull origin develop
    ├── docker compose build
    ├── docker compose up -d
    ├── php artisan migrate --force
    ├── php artisan optimize:clear
    ├── php artisan config:cache
    ├── php artisan route:cache
    ├── php artisan view:cache
    └── php artisan horizon:terminate (graceful restart)
```

---

## Getting Started

### Prerequisites

- **Docker** & **Docker Compose** (recommended)
- OR: PHP 8.3+, Composer, PostgreSQL 16, Redis 7, Node.js 20+

### Local Development (Docker)

```bash
# 1. Clone the repository
git clone <repository-url>
cd garam-capkapal-api

# 2. Copy environment file
cp .env.example .env

# 3. Start all services
docker compose up -d

# 4. Install PHP dependencies
docker compose exec app composer install

# 5. Generate application key
docker compose exec app php artisan key:generate

# 6. Run database migrations
docker compose exec app php artisan migrate

# 7. Seed the database (optional)
docker compose exec app php artisan db:seed

# 8. Install Node.js dependencies (for Vite assets)
docker compose exec vite npm install
```

The API will be available at `http://localhost:8000/api/v1`.

### Local Development (Native)

```bash
# 1. Install dependencies
composer install
npm install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Run migrations
php artisan migrate

# 4. Start all development services concurrently
composer dev
```

`composer dev` starts the PHP server, queue worker, log tail (Pail), and Vite dev server in parallel.

---

## Testing

```bash
# Run all tests
composer test

# Run tests directly
php artisan test

# Run specific test suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage

# Static analysis
vendor/bin/phpstan analyse

# Code style
vendor/bin/pint
```

**Test configuration** (`phpunit.xml`):
- Uses **SQLite in-memory** database for speed
- Disables Telescope, Pulse, Nightwatch
- Uses array/sync drivers for cache, mail, queue, sessions

---

## Environment Variables

| Variable              | Description                           | Default                |
| --------------------- | ------------------------------------- | ---------------------- |
| `APP_NAME`            | Application name                      | `Laravel`              |
| `APP_ENV`             | Environment (local/production)        | `local`                |
| `APP_DEBUG`           | Debug mode                            | `true`                 |
| `APP_URL`             | Application base URL                  | `http://localhost`     |
| `DB_CONNECTION`       | Database driver                       | `pgsql`                |
| `DB_HOST`             | Database host                         | `127.0.0.1`            |
| `DB_PORT`             | Database port                         | `5432`                 |
| `DB_DATABASE`         | Database name                         | `garam_capkapal_api`   |
| `DB_USERNAME`         | Database user                         | `root`                 |
| `DB_PASSWORD`         | Database password                     | —                      |
| `REDIS_HOST`          | Redis host                            | `127.0.0.1`            |
| `REDIS_PORT`          | Redis port                            | `6379`                 |
| `QUEUE_CONNECTION`    | Queue driver                          | `database`             |
| `CACHE_STORE`         | Cache driver                          | `database`             |
| `FILESYSTEM_DISK`     | Default filesystem disk               | `local`                |
| `MAIL_MAILER`         | Mail driver                           | `log`                  |
| `AWS_ACCESS_KEY_ID`   | AWS S3 access key                     | —                      |
| `AWS_SECRET_ACCESS_KEY`| AWS S3 secret key                    | —                      |
| `AWS_BUCKET`          | S3 bucket name                        | —                      |

> See `.env.example` for the complete list of configurable variables.

---

<p align="center">
  <strong>Garam Cap Kapal API</strong> · Built with ❤️ using Laravel
</p>
