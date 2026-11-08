# Entity Relationship Diagram (ERD) - Marketplace Laundry

Dokumentasi rancangan basis data relational untuk aplikasi Marketplace Laundry.

## 📐 Mermaid ERD Diagram

```mermaid
erDiagram
    users ||--o{ customer_addresses : "owns (1:N)"
    users ||--o{ stores : "owns (1:N)"
    users ||--o| drivers : "profile (1:1)"
    users ||--o{ laundry_orders : "places as customer"
    users ||--o{ order_status_histories : "updates status"
    users ||--o{ notifications : "receives"

    stores ||--o{ services : "offers (1:N)"
    stores ||--o{ laundry_orders : "processes (1:N)"

    customer_addresses ||--o{ laundry_orders : "delivery location"

    drivers ||--o{ laundry_orders : "assigned pickup/delivery"

    laundry_orders ||--o{ order_items : "contains (1:N)"
    laundry_orders ||--o| payments : "has payment (1:1)"
    laundry_orders ||--o{ order_status_histories : "tracks history (1:N)"

    users {
        bigint id PK
        string google_id UK
        string name
        string email UK
        string password
        string phone
        enum role "customer, owner, driver"
        boolean is_active
        timestamp created_at
    }

    customer_addresses {
        bigint id PK
        bigint user_id FK
        string label
        string recipient_name
        string recipient_phone
        text full_address
        string rt
        string rw
        string district
        string city
        string postal_code
        decimal latitude
        decimal longitude
        boolean is_primary
    }

    stores {
        bigint id PK
        bigint owner_id FK
        string store_name
        string phone
        text full_address
        decimal latitude
        decimal longitude
        boolean is_open
    }

    services {
        bigint id PK
        bigint store_id FK
        string service_name
        enum unit "kg, pcs, meter"
        decimal price_per_unit
        int estimated_duration_hours
        text description
        boolean is_active
    }

    drivers {
        bigint id PK
        bigint user_id FK,UK
        string vehicle_type
        string license_plate
        boolean is_available
        decimal current_latitude
        decimal current_longitude
    }

    laundry_orders {
        bigint id PK
        string order_number UK
        bigint customer_id FK
        bigint store_id FK
        bigint address_id FK
        bigint pickup_driver_id FK
        bigint delivery_driver_id FK
        decimal weight_kg
        decimal subtotal_amount
        decimal shipping_fee
        decimal additional_fee
        decimal discount_amount
        decimal total_amount
        decimal distance_km
        enum order_status
        text notes
    }

    order_items {
        bigint id PK
        bigint order_id FK
        bigint service_id FK
        decimal qty
        decimal price_per_unit
        decimal subtotal
        string notes
    }

    payments {
        bigint id PK
        bigint order_id FK,UK
        enum payment_method "qris, cash, bank_transfer"
        enum payment_status "pending, paid, expired, failed, refunded"
        decimal amount
        datetime expired_at
        datetime paid_at
        string transaction_reference
    }

    order_status_histories {
        bigint id PK
        bigint order_id FK
        string previous_status
        string new_status
        text notes
        bigint updated_by FK
        timestamp created_at
    }

    notifications {
        bigint id PK
        bigint user_id FK
        string title
        text message
        string type
        bigint reference_id
        boolean is_read
        timestamp created_at
    }
```

---

## 📋 Penjelasan Entitas & Relasi Database

### 1. `users`
- **Deskripsi**: Entitas utama pengguna sistem.
- **Role**: `customer` (Pelanggan), `owner` (Pemilik Laundry), dan `driver` (Kurir).
- **Indeks**: `idx_users_role`, `idx_users_email`.

### 2. `customer_addresses`
- **Deskripsi**: Menyimpan daftar alamat pengiriman pelanggan. Pelanggan dapat memiliki lebih dari satu alamat (`is_primary` menandai alamat utama).
- **Relasi**: Foreign key `user_id` merujuk ke `users(id)` dengan `ON DELETE CASCADE`.

### 3. `stores`
- **Deskripsi**: Profil toko laundry yang dimiliki oleh pengguna berperan `owner`.
- **Relasi**: Foreign key `owner_id` merujuk ke `users(id)` dengan `ON DELETE CASCADE`.

### 4. `services`
- **Deskripsi**: Katalog layanan laundry yang ditawarkan oleh masing-masing toko (misal: Cuci Kiloan, Dry Clean).
- **Satuan (Unit)**: `kg`, `pcs`, `meter`.
- **Relasi**: Foreign key `store_id` merujuk ke `stores(id)` dengan `ON DELETE CASCADE`.

### 5. `drivers`
- **Deskripsi**: Profil kurir (Driver) untuk melakukan jemput/antar laundry.
- **Relasi**: Foreign key `user_id` merujuk ke `users(id)` secara unique (`1:1`).

### 6. `laundry_orders`
- **Deskripsi**: Entitas utama transaksi pemesanan laundry.
- **Nomor Order Unik**: Kolom `order_number` bertipe unique string (contoh: `ORD-20260902-XXXX`).
- **Alur Status (`order_status`)**:
  - `pending_pickup`
  - `pickup_assigned`
  - `picked_up`
  - `arrived_at_store`
  - `weighed`
  - `in_process`
  - `ready_for_delivery`
  - `delivery_assigned`
  - `delivered`
  - `completed`
  - `cancelled`

### 7. `order_items`
- **Deskripsi**: Rincian item/layanan yang dibeli dalam satu order.
- **Relasi**: Foreign key `order_id` ke `laundry_orders(id)` (`ON DELETE CASCADE`) dan `service_id` ke `services(id)`.

### 8. `payments`
- **Deskripsi**: Menyimpan status dan rincian transaksi pembayaran.
- **Batas Waktu Expiration**: Kolom `expired_at` (DATETIME) yang mencatat batas waktu pembayaran 1 jam.
- **Status Pembayaran**: `pending`, `paid`, `expired`, `failed`, `refunded`.

### 9. `order_status_histories`
- **Deskripsi**: Rekam jejak (*audit trail*) seluruh perubahan status order dari awal hingga selesai beserta catatan dan user yang melakukan pengubahan (`updated_by`).

### 10. `notifications`
- **Deskripsi**: Pesan notifikasi in-app untuk pengguna terkait pembaruan order.
