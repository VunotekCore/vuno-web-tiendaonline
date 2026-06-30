# Vunotek — Architectural Minimalism in Footwear

Tienda online de calzado artesanal para damas con carrito, pagos (Stripe + transferencia), panel administrador completo y blog.

## Stack

| Capa | Tecnología |
|------|-----------|
| Frontend | Astro 6 (static) · Tailwind 4 · TypeScript strict |
| Backend | PHP 8+ · PDO MySQL |
| Base de datos | MySQL 8.0 · 40 tablas normalizadas · InnoDB |
| Pagos | Stripe (stripe/stripe-php) |
| Imágenes | ImageKit API |
| Email | PHPMailer con templates HTML |
| Admin | Vue 3 · Pinia · Composition API |

## Features

### 🛍️ Tienda Pública
Home con hero slider y bento grid · Catálogo con filtros (talla/color/estilo) · Detalle de producto con galería y matriz stock color×talle · Carrito (localStorage) · Checkout con Stripe Elements y transferencia bancaria · Blog · Wishlist

### 🔐 Panel Administrador
Dashboard con estadísticas · CRUD productos (4 tabs, matriz stock) · Pedidos con cambio de estado y comprobantes · Categorías · Cupones · Reseñas · Blog · Clientes · Usuarios con roles (superadmin/editor/viewer) · Configuración (14 secciones) · 2FA (TOTP) · Logs de auditoría · POS

## Requisitos

- **PHP** 8.1+
- **MySQL** 8.0+
- **Node.js** 22 + **pnpm** 10
- **Composer** (solo para instalar vendor/ inicial)

## Quick Start

```bash
cp .env.example .env                  # editar credenciales
composer install --working-dir=backend
pnpm install
bash dev.sh                           # http://localhost:4321
```

## Development Modes

| Comando | Descripción |
|---------|------------|
| `bash dev.sh` | Build + PHP server, todo en :4321 |
| `bash dev.sh api` | Solo PHP APIs en :8000 |
| `bash dev.sh hmr` | Astro HMR en :4321 + PHP en :8000 |
| `pnpm dev` | Solo frontend Astro (sin APIs) |

## Deploy

```bash
# Full build (frontend + backend, local) — usa .env
bash deploy.sh

# Solo backend (sobre dist/ existente) — usa .env.production
bash deploy.sh --prod

# CI/CD (GitHub Actions): push a main → pnpm build → FTP a Hostinger
```

## Estructura

```
/
├── src/                    # Astro pages, components, layouts
│   ├── components/         # atoms, molecules, organisms, admin, seo
│   ├── layouts/            # BaseLayout, admin _layout
│   ├── pages/              # rutas públicas y admin
│   ├── stores/             # Pinia stores (auth)
│   └── config/             # tenant.ts (SEO/brand estático)
├── backend/                # PHP SOA (Service-Oriented Architecture)
│   ├── Controllers/        # 21 controladores
│   ├── Models/             # 17 modelos (solo SQL prepared statements)
│   ├── Services/           # Stripe, Email, ImageKit, Auth
│   ├── api/                # 99 entry points HTTP
│   ├── Config/             # Database PDO singleton
│   └── database/           # schema.sql, seed.sql, migraciones
├── public/js/              # format-price, cart, api, etc.
├── .github/workflows/      # CI deploy
└── dist/                   # Build output (gitignored)
```

## Admin

```
URL:      /admin/login
Usuario:  admin@vunotek.com (seed)
Password: definido en .env → ADMIN_PASSWORD

Roles:    superadmin · editor · viewer
```

## Base de Datos

```bash
# Migración
mysql -u <user> -p <dbname> < backend/database/schema.sql

# Datos semilla
mysql -u <user> -p <dbname> < backend/database/seed.sql

# Verificar conexión
php -r "require 'backend/config.php'; \$db = getDb(); echo 'DB OK';"
```

40 tablas normalizadas · InnoDB · utf8mb4 · cero columnas JSON

## Variables de Entorno

Ver `.env.example` para la lista completa. Variables principales:

| Variable | Descripción |
|----------|------------|
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` | Conexión MySQL |
| `STRIPE_SECRET_KEY` / `PUBLIC_STRIPE_KEY` | Stripe |
| `IMAGEKIT_PRIVATE_KEY` / `IMAGEKIT_PUBLIC_KEY` / `IMAGEKIT_URL_ENDPOINT` | ImageKit |
| `ADMIN_PASSWORD` | Password para seed de admin |

SMTP y email del store se configuran desde Admin → Configuración.

## Documentación Detallada

Ver `AGENTS.md` para documentación técnica completa: arquitectura, convenciones, plan de pruebas, roadmap, esquema DB detallado.
