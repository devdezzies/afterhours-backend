How to setup this project 

1. Install PHP Dependencies
```bash
composer install 
```

2. Create environment file 
```bash
cp .env.example .env
```

3. Start development server 
```bash
php artisan serve --port 8000
```

Made by:
1. Abdullah (@devdezzies)
2. Hilmi Musyafa (@hilmimusyafa)
3. Muhammad Febrian Hafiz (@swagenougk)
4. Muhammad Ardiansyah Pratama (@davindthomassingson)
5. Abiyoso Danar Panji Yudhanto (@mr_fahrenheit)
# AfterHours API

Laravel API for the AfterHours Flutter storefront.

## Local setup

1. Copy `.env.example` to `.env` and generate an application key.
2. Use SQLite for local development, or configure PostgreSQL and ensure the
   PHP `pdo_pgsql` extension is installed.
3. Run `php artisan migrate --seed`.
4. Start the API with `php artisan serve --host=0.0.0.0`.

The health endpoint is `GET /up`; mobile endpoints are under `/api`.
Protected endpoints use a Sanctum bearer token.

Key customer flows:

- `POST /api/auth/register`, `/api/auth/login`, and `/api/auth/logout`
- `GET|PUT /api/profile`
- `GET /api/products` and `GET /api/products/{id}`
- `POST /api/cart/validate`
- `GET|POST /api/orders` and `GET /api/orders/{id}`

Order creation requires an `Idempotency-Key` header. Prices are integer IDR
values at the API boundary. Configure browser origins through
`CORS_ALLOWED_ORIGINS`.

Run verification with:

```bash
php artisan test
```
