# 🏛️ RAM;LOP — Documentación Técnica y Comercial

> **Proyecto:** Tienda online de calzado artesanal para damas
> **Marca:** Ram;Lop — "Architectural Minimalism in Footwear"
> **Estado:** MVP funcional v1.0
> **Stack:** Astro 6 (static) · PHP 8+ · TypeScript strict · Tailwind 4 · Stripe · ImageKit · PHPMailer

---

## 📋 Índice

1. [Resumen Ejecutivo](#1-resumen-ejecutivo)
2. [Funcionalidades MVP](#2-funcionalidades-mvp)
3. [Arquitectura del Sistema](#3-arquitectura-del-sistema)
4. [Panel Administrador](#4-panel-administrador)
5. [Seguridad](#5-seguridad)
6. [Integraciones Externas](#6-integraciones-externas)
7. [Inventario y Productos](#7-inventario-y-productos)
8. [Diseño y Marca](#8-diseño-y-marca)
9. [Roadmap — Fases Futuras](#9-roadmap--fases-futuras)
10. [Estructura del Proyecto](#10-estructura-del-proyecto)
11. [Comandos y Verificación](#11-comandos-y-verificación)
12. [Guía para Desarrolladores](#12-guía-para-desarrolladores)
13. [Base de Datos MySQL](#13-base-de-datos-mysql)

---

## 1. Resumen Ejecutivo

Ram;Lop es una tienda online de **calzado artesanal femenino** con estética minimalista arquitectónica. El proyecto usa una arquitectura híbrida:

- **Frontend**: Astro 6 con `output: "static"` — genera HTML/CSS/JS estático
- **Backend**: PHP 8+ para APIs (CRUD productos/pedidos, Stripe, ImageKit, email)
- **Admin**: Páginas Astro estáticas con JS cliente que consume APIs PHP
- **Hosting**: Compatible con cualquier hosting PHP compartido (cPanel, DirectAdmin) — no requiere Node.js en producción

### ¿Por qué Astro + PHP?

Astro genera páginas estáticas ultrarrápidas para el catálogo público, manteniendo la riqueza de componentes con Tailwind 4. PHP maneja toda la lógica de negocio (pagos, sesiones admin, notificaciones) y puede ejecutarse en cualquier hosting compartido sin necesidad de Node.js en producción. El desarrollo local usa dos servidores en paralelo (Astro HMR para frontend, PHP para APIs) con un `fetch` override automático que redirige las llamadas `/api/*` al puerto PHP.

---

## 2. Funcionalidades MVP

### 🛍️ Tienda Pública

| Página | Ruta | Funcionalidad |
|--------|------|---------------|
| Home | `/` | Hero slider con 4 imágenes editoriales, bento grid de nuevos lanzamientos, sección tendencias, selección curada de productos |
| Catálogo | `/catalogo` | Grid 3 cols desktop / 2 mobile, filtros por talla/color/estilo, breadcrumbs, efecto vista rápida, botón "Cargar más" |
| Detalle Producto | `/producto/[slug]` | Galería de 3 imágenes, selector de color/talle, acordeones (descripción, cuidados, envíos), botón añadir al carrito, productos relacionados |
| Carrito | `/carrito` | Items desde localStorage, ajuste de cantidad, eliminación, resumen de orden, botón a checkout |
| Checkout | `/checkout` | Formulario de envío, Stripe Elements (confirmCardPayment) o transferencia bancaria, resumen final |

### 🔐 Panel Administrador

| Módulo | Ruta | Funcionalidad |
|--------|------|---------------|
| Login | `/admin/login` | Autenticación con sesión PHP + cookie HttpOnly |
| Dashboard | `/admin` | Estadísticas: total productos, pedidos, ingresos del mes, pedidos recientes |
| Productos | `/admin/productos` | Lista con CRUD completo (crear, editar, eliminar) |
| Nuevo Producto | `/admin/productos/nuevo` | Formulario con nombre, slug, descripción, precio, categoría, talles, colores |
| Editar Producto | `/admin/productos/editar` | Edición de producto existente (carga datos vía API) |
| Categorías | `/admin/categorias` | CRUD de categorías (crear, editar, eliminar) |
| Pedidos | `/admin/pedidos` | Lista con estados, métodos de pago, fechas, enlace a detalle |
| Detalle Pedido | `/admin/pedidos/detalle` | Vista completa con datos del cliente, items, cambio de estado |

### 💳 Pagos

- **Stripe**: Payment Intents con Stripe Elements (`stripe.confirmCardPayment`) + webhooks para confirmación
- **Transferencia bancaria**: Subida de comprobante + notificación por email al admin

### 📧 Notificaciones

- Email al admin cuando se realiza un pedido por transferencia (PHPMailer)
- Email de confirmación al cliente al completar pago

---

## 3. Arquitectura del Sistema

```
┌──────────────────────────────────────────────────────────────────┐
│                         Cliente (Browser)                         │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────────────────┐ │
│  │  Home    │ │ Catálogo │ │ Carrito  │ │  Admin (Astro SSG)  │ │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┬──────────┘ │
│              │ localStorage (carrito)                │            │
│              │ fetch() a /api/*.php                  │            │
└──────────────────────────────────────────────────────┼────────────┘
                                                       │
┌──────────────────────────────────────────────────────▼────────────┐
│              PHP 8+ (Built-in server / Apache / Nginx)             │
│                                                                    │
│  ┌──────────────┐  ┌──────────────┐  ┌─────────────────────────┐  │
│  │  Static HTML  │  │  PHP APIs    │  │  Sesiones Admin         │  │
│  │  (dist/*.html)│  │  /api/*.php  │  │  (auth.php)             │  │
│  └──────────────┘  └──────┬───────┘  └─────────────────────────┘  │
│                           │                                        │
│  ┌────────────────────────▼────────────────────────────────────┐  │
│  │              Capa de Servicios (php/includes/)                │  │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────────────┐  │  │
│  │  │ Stripe   │ │ ImageKit │ │ PHPMailer│ │ Database (PDO) │  │  │
│  │  └──────────┘ └──────────┘ └──────────┘ └────────────────┘  │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                           │                                        │
│  ┌────────────────────────▼────────────────────────────────────┐  │
│  │              MySQL 8.0 · vuno_ramlop_ecommerce                │  │
│  │  40 tablas normalizadas · InnoDB · utf8mb4                    │  │
│  └─────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
```

### Stack Técnico

| Capa | Tecnología | Versión |
|------|-----------|---------|
| Framework Frontend | Astro | 6.x (static) |
| Backend APIs | PHP | 8+ |
| Lenguaje | TypeScript / PHP 8 | strict |
| Estilos | Tailwind CSS | 4.x con @theme |
| Iconos | Material Symbols Outlined | — |
| Fuentes | Playfair Display + Hanken Grotesk | Google Fonts |
| Base de datos | MySQL 8.0+ · PDO | `vuno_ramlop_ecommerce` · 40 tablas normalizadas |
| Pagos | Stripe (stripe/stripe-php) | — |
| Email | PHPMailer (phpmailer/phpmailer) | — |
| Imágenes | ImageKit API (cURL) | — |

---

## 4. Panel Administrador

### 4.1 Autenticación

- **Login**: `POST /api/admin/login.php` — valida credenciales contra `ADMIN_EMAIL`/`ADMIN_PASSWORD` del `.env`, inicia sesión PHP
- **Verificación**: `GET /api/admin/verify.php` — cada página admin verifica la sesión vía fetch cliente; si no hay sesión, redirige a `/admin/login`
- **Logout**: `GET /api/admin/logout.php` — destruye la sesión y redirige al login
- **Sesión PHP**: Cookie `PHPSESSID` con `HttpOnly`, `SameSite=None` (localhost) / `Strict` (producción), `Secure` en localhost y HTTPS

### 4.2 Módulos Actuales

#### Dashboard (`/admin`)
- Resumen: total productos, total pedidos, ingresos del mes, pedidos recientes
- Cards con datos calculados desde `products.json` y `orders.json`

#### Productos (`/admin/productos`)
- Tabla con nombre, precio, categoría, stock total, acciones (editar/eliminar)
- CRUD completo: crear (`/admin/productos/nuevo`), editar (`/admin/productos/editar`), eliminar (vía API)
- Formulario con 4 tabs: Información, Precio & Categoría, Imágenes, Variantes
- **Matriz stock color × talle** en la pestaña Variantes: grid con 4 colores × 6 talles, cada celda con input numérico de stock
- Solo los colores con al menos un stock > 0 se persisten en DB
- API PHP conecta `stocks` del frontend a `product_variants` en MySQL

#### Categorías (`/admin/categorias`)
- Tabla para gestionar categorías (nombre, slug)
- CRUD completo via `php/api/categorias/*.php`

#### Pedidos (`/admin/pedidos`)
- Lista con: ID, cliente, fecha, total, estado, método de pago
- Badges de estado: pending (ocre), paid (negro), shipped (negro 80%), delivered (negro), cancelled (rojo)
- Detalle con items del pedido, datos del cliente, cambio de estado
- Vista de comprobante de transferencia (link de ImageKit) cuando el método de pago es transferencia

### 4.3 Layout Admin

El panel usa un layout común `_layout.astro` con:
- **Sidebar** fijo (250px) con iconos Material Symbols + navegación: Dashboard, Productos, Categorías, Cupones, Reseñas, Blog, Pedidos, Configuración, Seguridad, Usuarios
- **Header** superior con título de la página + botón de logout
- **Auth guard** cliente: cada página verifica sesión vía `fetch(/api/admin/verify.php)`
- Responsive: sidebar colapsa a top nav en mobile

---

## 5. Seguridad

### 5.1 Implementación Actual

| Aspecto | Implementación |
|---------|---------------|
| **Autenticación admin** | Sesiones PHP nativas. Cookie `PHPSESSID` con flags: `HttpOnly`, `Path=/`, `SameSite=Strict` (producción) / `None` (localhost), `Secure` en HTTPS/localhost |
| **Verificación** | Cada página admin hace `fetch(/api/admin/verify.php)` desde JS cliente al cargar. Si no hay sesión, redirige a `/admin/login` |
| **Protección XSS** | Cookie HttpOnly — el session ID NO es accesible desde JavaScript del cliente |
| **Protección CSRF** | SameSite=Strict en producción — la cookie no se envía en requests cross-site |
| **Cierre de sesión** | Endpoint dedicado que destruye la sesión y elimina la cookie |
| **Credenciales** | Almacenadas en variables de entorno (`.env`), no en código |
| **API Keys** | Stripe, ImageKit, SMTP — todas via `env()` desde `.env` |

### 5.2 Mejoras Implementadas

| Mejora | Estado |
|--------|--------|
| Rate limiting en login (prevenir brute force) | ✅ |
| Headers de seguridad (CSP, HSTS, X-Frame-Options, X-Content-Type-Options) | ✅ |
| Logging y auditoría de acciones admin | ✅ |
| Roles de usuario (admin, editor, viewer) | ✅ |
| 2FA (TOTP) para acceso admin | ✅ |

### 5.3 Mejoras Planeadas (Phase 3)

*(None — 2FA completed in Phase 2 consolidation)*

---

## 6. Integraciones Externas

### 6.1 Stripe — Pagos con Tarjeta

| Endpoint PHP | Propósito |
|--------------|-----------|
| `POST /api/stripe/create-payment-intent.php` | Crea un PaymentIntent desde el frontend |
| `POST /api/stripe/webhook.php` | Recibe confirmación de pago asíncrona |

**Estado actual**: Implementado vía `stripe/stripe-php`. Paso de pago completo con Stripe Elements (`confirmCardPayment`). Requiere `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `PUBLIC_STRIPE_KEY`, `STRIPE_WEBHOOK_SECRET` en `.env`.

### 6.2 ImageKit — Gestión de Imágenes

| Función PHP | Propósito |
|-------------|-----------|
| `uploadImage($file, $folder?)` | Subir imagen individual vía cURL |
| `getImages($options?)` | Listar imágenes con paginación |
| `deleteImage($fileId)` | Eliminar imagen |

**Estado actual**: Servicio implementado en `php/includes/imagekit.php` vía cURL a REST API de ImageKit. El formulario admin de productos (nuevo/editar) ya sube imágenes a ImageKit via `POST /api/imagekit/upload.php`. El checkout también sube comprobantes de transferencia a la carpeta `receipts`. Requiere `IMAGEKIT_PRIVATE_KEY`, `IMAGEKIT_PUBLIC_KEY`, `IMAGEKIT_URL_ENDPOINT` en `.env`.

### 6.3 PHPMailer — Notificaciones Email

| Endpoint PHP | Propósito |
|--------------|-----------|
| `POST /api/email/send.php` | Enviar email genérico |
| Server-side | `sendOrderConfirmation()` / `sendNewOrderNotification()` se llaman desde `confirm-payment.php`, `webhook.php` y `create.php` |

**Estado actual**: Implementado con PHPMailer y transporte SMTP configurable. Se activa al recibir un pedido (Stripe o transferencia). Los emails usan **templates HTML** con `{{variable}}` placeholders desde archivos en `php/email-templates/`, renderizados por `renderTemplate()`. Las variables (items, totales, datos bancarios, etc.) se reemplazan en servidor — no más envío desde JS cliente.

---

## 7. Inventario y Productos

### 7.1 Modelo de Datos (MySQL)

Ver [sección 13 — Base de Datos MySQL](#13-base-de-datos-mysql) para el esquema completo normalizado con 38 tablas.

### 7.2 Estructura de Inventario

- **Stock numérico**: Cada variante (color × talle) tiene stock entero en `product_variants.stock`
- **Matriz color × talle**: La tabla `product_variants` cruza `product_colors` y `product_sizes` con stock individual
- **Gestión admin**: El formulario de producto (nuevo/editar) tiene una pestaña "Variantes" con inputs numéricos de stock en grid 4 colores × 6 talles. El backend persiste `stocks` en `product_variants` vía `saveProduct()`
- **Solo colores con stock**: Si un color tiene 0 stock en todos los talles, no se persiste en `product_colors`, limpiando la matriz automáticamente
- **Movimientos**: Cada cambio de stock por orden se registra en `stock_movements` con referencia a la orden
- **Categorías**: Teselas en `categories` con soporte de jerarquía (parent_id)

### 7.3 Datos de Semilla

- **7 productos** en `src/data/products.json` (para migrar a DB)
- **2 pedidos** de muestra en `src/data/orders.json` (para migrar a DB)
- **5 categorías** iniciales: Heels, Sandals, Mules, Boots, Flats

---

## 8. Diseño y Marca

### 8.1 Identidad

| Elemento | Descripción |
|----------|-------------|
| **Nombre** | Ram;Lop |
| **Eslogan** | "Architectural Minimalism in Footwear" |
| **Producto** | Calzado 100% artesanal para damas |
| **Estilo** | Minimalismo arquitectónico — editorial gallery aesthetic |

### 8.2 Paleta de Colores

Ver `html-designs/DESIGN.md` para la paleta completa. Tokens principales:

| Token | Hex | Uso |
|-------|-----|-----|
| `monolith-black` | `#1A1A1A` | Tipografía, botones primarios, sidebar admin |
| `sand-nude` | `#E6DED5` | Fondos de secciones |
| `clay-accent` | `#C18C7E` | Acentos, CTAs secundarios |
| `background` | `#faf9f8` | Fondo general |
| `off-white` | `#F5F3F0` | Tarjetas, tablas admin |

### 8.3 Tipografía

- **Playfair Display** — Headlines (títulos, colecciones)
- **Hanken Grotesk** — Body, labels, precios, navegación, admin
- **label-caps**: 12px, 700, letter-spacing 0.1em — categorías

### 8.4 Diseño Admin

- **Sidebar**: 250px, fondo `monolith-black`, texto `off-white`, iconos Material Symbols
- **Content area**: fondo `background`, padding 8
- **Tablas**: sin bordes, filas con hover, tipografía clean
- **Botones**: `monolith-black` con `off-white`, sin border-radius
- **Formularios**: inputs con borde inferior (`border-b`) estilo minimalista

---

## 9. Roadmap — Fases Futuras

### Phase 2 — Consolidación

| Prioridad | Feature | Estado |
|-----------|---------|--------|
| Alta | CRUD completo de productos (crear, editar, eliminar) | ✅ |
| Alta | Vista detalle de pedido `/admin/pedidos/detalle` | ✅ |
| Alta | Cambio de estado de pedidos desde admin | ✅ |
| Alta | Dashboard admin con estadísticas | ✅ |
| Alta | CRUD de categorías | ✅ |
| Alta | Sidebar de navegación admin | ✅ |
| Alta | Migración a MySQL: esquema 40 tablas normalizadas | ✅ |
| Alta | Wishlist / lista de deseos | ✅ |
| Alta | Reseñas y valoraciones desde admin | ✅ |
| Alta | Roles de usuario (admin, editor, viewer) | ✅ |
| Alta | Headers de seguridad (CSP, HSTS, X-Frame-Options) | ✅ |
| Alta | Rate limiting en login | ✅ |
| Alta | Logging y auditoría de acciones admin | ✅ |
| Media | Cupones y descuentos | ✅ |
| Media | Conectar ImageKit upload desde formulario admin | ✅ |
| Media | Stock numérico por talle × color | ✅ |
| Media | Paginación en listas admin | ✅ |
| Media | Visualización de comprobantes de transferencia | ✅ |
| Media | Búsqueda y filtros en admin | ✅ |

### Phase 3 — Escalabilidad

| Prioridad | Feature | Estado |
|-----------|---------|--------|
| Alta | Migrar APIs JSON a MySQL | ✅ |
| Media | Email transaccional con templates HTML | ✅ |

### Phase 4 — Crecimiento

| Prioridad | Feature | Estado |
|-----------|---------|--------|
| Media | Blog / editorial content | ✅ |
| Media | Multi-idioma (i18n) | Pendiente |

### Phase 5 — Futuro

| Prioridad | Feature | Estado |
|-----------|---------|--------|
| Baja | Notificaciones WhatsApp/Twilio | Pendiente |
| Baja | PWA / soporte offline | Pendiente |

---

## 10. Estructura del Proyecto

```
/
├── html-designs/                   # Diseños HTML originales de referencia
│   └── DESIGN.md                   # Design tokens completos
├── public/
│   ├── js/
│   │   ├── cart.js                 # Carrito cliente (localStorage + eventos)
│   │   └── api.js                  # Fetch override: redirect /api/* a PHP en HMR mode
│   └── favicon.svg
├── src/
│   ├── components/
│   │   ├── atoms/                  # Button, Input, Tag, Icon
│   │   ├── molecules/              # ProductCard, CartItem, FilterGroup
│   │   └── organisms/              # Navbar, Footer, ProductGrid, HeroSection
│   ├── layouts/
│   │   └── BaseLayout.astro        # Layout principal con SEO, fonts, api.js
│   ├── pages/
│   │   ├── index.astro             # Home con hero slider
│   │   ├── catalogo.astro          # Catálogo con filtros
│   │   ├── producto/[slug].astro   # Detalle de producto (getStaticPaths)
│   │   ├── carrito.astro           # Carrito de compras
│   │   ├── checkout.astro          # Checkout (Stripe Elements + Transferencia)
│   │   └── admin/
│   │       ├── _layout.astro       # Shell admin con sidebar + auth guard
│   │       ├── login.astro         # Login page
│   │       ├── index.astro         # Dashboard con estadísticas
│   │       ├── productos.astro     # Lista de productos
│   │       ├── productos/nuevo.astro # Formulario nuevo producto
│   │       ├── productos/editar.astro # Formulario editar producto
│   │       ├── categorias.astro    # CRUD de categorías
│   │       ├── blog.astro          # Lista de posts del blog
│   │       ├── blog/nuevo.astro    # Nuevo post
│   │       ├── blog/editar.astro   # Editar post
│   │       ├── pedidos.astro       # Lista de pedidos
│   │       └── pedidos/detalle.astro # Detalle de pedido
│   ├── data/
│   │   ├── products.json           # 7 productos de semilla
│   │   └── orders.json             # 2 pedidos de muestra
│   ├── styles/
│   │   └── global.css              # Tailwind @theme + design tokens
│   └── env.d.ts                    # Tipo global Window.RamLopCart
├── php/                            # 🆕 PHP Backend
│   ├── config.php                  # .env loader, CORS, session config, DB, helpers
│   ├── composer.json               # Dependencias PHP (stripe, phpmailer)
│   ├── email-templates/            # Plantillas HTML con {{variable}} placeholders
│   │   ├── order_confirmation.html
│   │   └── new_order_notification.html
│   ├── database/
│   │   └── schema.sql              # Esquema MySQL (40 tablas normalizadas)
│   ├── includes/
│   │   ├── auth.php                # Sesiones admin (login, logout, verify)
│   │   ├── storage.php             # CRUD MySQL (products, orders, categories)
│   │   ├── stripe.php              # Stripe SDK wrapper
│   │   ├── email.php               # PHPMailer wrapper + template renderer
│   │   ├── imagekit.php            # ImageKit REST API via cURL
│   │   └── helpers.php             # Utilidades (slug, validación)
│   ├── blog/                        # Blog público (PHP dinámico)
│   │   └── index.php               # Lista y detalle de posts
│   ├── dev-router.php               # Router para desarrollo (blog URLs limpias)
│   └── api/
│       ├── admin/
│       │   ├── login.php           # POST — autenticar
│       │   ├── verify.php          # GET — verificar sesión
│       │   └── logout.php          # GET — cerrar sesión
│       ├── productos/
│       │   ├── list.php            # GET — listar
│       │   ├── get.php             # GET — obtener por ID
│       │   ├── create.php          # POST — crear
│       │   ├── update.php          # POST — actualizar
│       │   └── delete.php          # POST — eliminar
│       ├── categorias/
│       │   ├── list.php            # GET — listar
│       │   ├── create.php          # POST — crear
│       │   ├── update.php          # POST — actualizar
│       │   └── delete.php          # POST — eliminar
│       ├── pedidos/
│       │   ├── list.php            # GET — listar
│       │   ├── get.php             # GET — obtener por ID
│       │   └── update-status.php   # POST — cambiar estado
│       ├── dashboard/
│       │   └── stats.php           # GET — estadísticas del dashboard
│       ├── stripe/
│       │   ├── create-payment-intent.php
│       │   └── webhook.php
│       ├── email/
│       │   └── send.php
│       ├── imagekit/
│       │   └── upload.php
│       └── blog/
│           ├── list.php            # GET — listar posts
│           ├── get.php             # GET — obtener post
│           ├── create.php          # POST — crear post
│           ├── update.php          # POST — actualizar post
│           ├── delete.php          # POST — eliminar post
│           └── categories.php      # GET — listar categorías
├── dist/                           # Build output (generado por deploy.sh)
│   ├── index.html
│   ├── admin/...                   # Páginas admin estáticas
│   ├── api/...                     # PHP endpoints copiados
│   ├── includes/...                # PHP includes copiados
│   ├── config.php                  # Config PHP copiado
│   ├── data/...                    # JSON files copiados
│   └── vendor/...                  # Composer dependencies
├── .env                            # Variables de entorno (no versionado)
├── .env.example                    # Template de variables requeridas
├── astro.config.mjs                # Config Astro (output: "static")
├── tsconfig.json                   # TypeScript strict
├── package.json                    # Dependencias Node.js
├── composer.json                   # Dependencias PHP (stripe, phpmailer)
├── deploy.sh                       # Script de build + deploy
├── dev.sh                          # Script de desarrollo (single o dual server)
└── AGENTS.md                       # Este archivo
```

---

## 11. Comandos y Verificación

### Desarrollo

```bash
# Opción 1: Servidor único (recomendado, sin HMR)
./dev.sh                # Build + PHP en http://localhost:4321

# Opción 2: Dos servidores (HMR para frontend)
# Terminal 1:
./dev.sh api            # PHP APIs en http://localhost:8000
# Terminal 2:
./dev.sh hmr            # Astro HMR en http://localhost:4321 (fetch override a :8000)

# Solo frontend (sin APIs)
pnpm dev                # Astro dev en :4321 (HMR, solo páginas estáticas)
```

### Verificación

```bash
pnpm astro check        # TypeScript check (0 errors = saludable)
pnpm build              # Build frontend estático
bash deploy.sh          # Build + copia PHP + composer install → dist/
php -l php/config.php   # Syntax check PHP
php -l php/api/*/*.php  # Syntax check endpoints
mysql -u dail -p vuno_ramlop_ecommerce < php/database/schema.sql  # Migrate DB
php -r "require 'php/config.php'; getDb(); echo 'DB OK';"  # Verify DB connection
```

### Producción

```bash
bash deploy.sh          # Genera dist/ completo
# Subir dist/ al hosting via FTP/rsync/git
```

### Acceso Admin

```
URL:      http://localhost:4321/admin/login
Email:    (definido en .env → ADMIN_EMAIL)
Password: (definido en .env → ADMIN_PASSWORD)
```

### Variables de Entorno Requeridas (`.env`)

```
# Stripe
STRIPE_SECRET_KEY=
STRIPE_PUBLISHABLE_KEY=
PUBLIC_STRIPE_KEY=
STRIPE_WEBHOOK_SECRET=

# ImageKit
IMAGEKIT_PRIVATE_KEY=
IMAGEKIT_PUBLIC_KEY=
IMAGEKIT_URL_ENDPOINT=

# Email (SMTP)
SMTP_HOST=
SMTP_PORT=
SMTP_USER=
SMTP_PASS=
FROM_EMAIL=

# Admin
ADMIN_EMAIL=
ADMIN_PASSWORD=
```

---

## 12. Guía para Desarrolladores

### Modos de Operación

**MODO A — Desarrollo de Interfaces (Astro Static)**
- Contexto: Frontend público, páginas `.astro`, componentes
- Foco: Tailwind 4, diseño responsive, HMR con `pnpm dev`
- APIs no disponibles (mock data o build + PHP)
- Salud: `pnpm astro check`

**MODO B — Lógica de Negocio (PHP Backend)**
- Contexto: API endpoints en `php/api/`, servicios en `php/includes/`
- Foco: Validación, seguridad, integración con servicios externos
- Lenguaje: PHP 8+ con tipado estricto
- Prueba: `./dev.sh api` y curl contra `localhost:8000/api/*.php`

**MODO C — Full Stack (Recomendado)**
- Contexto: Desarrollo completo que requiere frontend + APIs
- Flujo: `./dev.sh api` + `./dev.sh hmr` en terminales separadas
- `public/js/api.js` redirige automáticamente `/api/*` a PHP en modo HMR
- Alternativa: `./dev.sh` (todo en un puerto, sin HMR pero más simple)

### Convenciones de Código

- **Astro/TS**: TypeScript strict, imports absolutos desde `src/`
- **PHP 8**: Tipado estricto (`declare(strict_types=1)`), camelCase funciones
- **Componentes**: Atómicos en `src/components/atoms/`
- **Sin librerías UI externas**: Tailwind + vanilla JS
- **Iconos**: Material Symbols Outlined via Google Fonts
- **Imágenes**: URLs de ImageKit o Unsplash (placeholder)
- **CSS Admin**: Clases Tailwind inline, sin estilos separados
- **Base de datos**: MySQL 8.0+ con PDO. Sin columnas JSON. Todo normalizado con tablas intermedias. Claves foráneas con InnoDB. Nombres de tablas en `snake_case` plural. Nombres de columnas en `snake_case`. Timestamps `created_at`/`updated_at` en cada tabla.

### Flujo de Trabajo Admin (PHP API)

1. Las páginas admin (`/admin/*.astro`) son **estáticas** (generadas en build)
2. El JS cliente hace `fetch()` a endpoints PHP (`/api/*.php`)
3. Los endpoints usan `getDb()` (PDO) para consultar MySQL — `php/includes/storage.php` maneja la lógica de negocio
4. `auth.php` maneja sesiones con cookies `HttpOnly`
5. El auth guard se ejecuta en el navegador: `fetch(/api/admin/verify.php)`

### Arquitectura de API PHP

Cada endpoint en `php/api/` sigue el patrón:
```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);

// ... lógica del endpoint
jsonResponse($data);
```

---

---

## 13. Base de Datos MySQL

### 13.1 Esquema — `vuno_ramlop_ecommerce`

40 tablas normalizadas con InnoDB, utf8mb4, cero columnas JSON.  
Archivo de migración: `php/database/schema.sql`

#### Admin y Seguridad

| Tabla | Descripción |
|-------|-------------|
| `admin_roles` | Roles del panel (superadmin, editor, viewer) |
| `admin_users` | Usuarios admin con password_hash y FK a rol |
| `admin_activity_log` | Auditoría de acciones en el panel |
| `admin_activity_details` | Metadatos clave-valor de cada acción (antes/detpués de cambios) |

#### Catálogo

| Tabla | Descripción |
|-------|-------------|
| `categories` | Jerarquía de categorías con parent_id (autorreferencia) |
| `products` | Productos con SKU, slug único, precio, dimensiones físicas, SEO |
| `product_details` | Lista de cuidados/materiales (ítems ordenados) |
| `product_tags` | Tags por producto (relación N:M) |
| `product_categories` | Asignación producto → categoría (N:M) |
| `product_colors` | Colores disponibles por producto |
| `product_sizes` | Talles disponibles por producto |
| `product_variants` | **Matriz color × talle** con stock numérico y SKU propio |
| `product_images` | Galería de imágenes (general o por color) |
| `product_reviews` | Reseñas con rating 1-5, vinculadas a orden y cliente |
| `stock_movements` | **Log de inventario**: cada entrada/salida con referencia a orden o ajuste |

#### Clientes

| Tabla | Descripción |
|-------|-------------|
| `customers` | Cuentas de cliente (email único, password_hash opcional para guest) |
| `customer_addresses` | Libreta de direcciones (envío/facturación, múltiples por cliente) |
| `customer_sessions` | Sesiones de login de clientes (token, expiración) |
| `wishlist_items` | Productos guardados por cliente |

#### Órdenes

| Tabla | Descripción |
|-------|-------------|
| `order_statuses` | Catálogo de estados (pending, paid, shipped, delivered, cancelled) |
| `payment_statuses` | Catálogo de estados de pago (pending, completed, failed, refunded) |
| `payment_methods` | Métodos de pago activos (stripe, transfer) |
| `orders` | Órdenes completas con **snapshots denormalizados** de direcciones de envío/facturación |
| `order_items` | Líneas de orden con snapshot del producto (nombre, slug, precio, SKU al momento de compra) |
| `order_status_history` | **Log de cambios de estado**: quién cambió, desde/hacia qué estado |
| `coupon_usage` | Registro de uso de cupones por orden |

#### Envíos

| Tabla | Descripción |
|-------|-------------|
| `shipping_zones` | Zonas geográficas |
| `shipping_zone_countries` | Países por zona (relación 1:N) |
| `shipping_methods` | Métodos de envío (standard, express) |
| `shipping_rates` | Tarifas por zona × método (base, por item, por kg, free_above) |

#### Impuestos y Cupones

| Tabla | Descripción |
|-------|-------------|
| `tax_rates` | Tasas de impuesto por país/estado |
| `coupons` | Códigos de descuento (% o fijo) con fechas de vigencia y límites |

#### Pagos

| Tabla | Descripción |
|-------|-------------|
| `bank_accounts` | Cuentas bancarias para transferencia (múltiples, configurables desde admin) |

#### Notificaciones

| Tabla | Descripción |
|-------|-------------|
| `email_templates` | Plantillas reutilizables (subject, body HTML/text) |
| `email_queue` | Cola de correos pendientes/enviados/fallidos con reintentos |
| `notification_log` | Historial de notificaciones enviadas (email, SMS, WhatsApp) |

#### CMS y Configuración

| Tabla | Descripción |
|-------|-------------|
| `pages` | Páginas institucionales (about, terms, contacto) con SEO |
| `settings` | Configuración clave-valor por sección (store, receipt, imagekit, smtp) |

### 13.2 Convenciones de Base de Datos

| Aspecto | Regla |
|---------|-------|
| **Motor** | InnoDB (claves foráneas, transacciones) |
| **Charset** | utf8mb4 · utf8mb4_unicode_ci |
| **Nombres de tablas** | `snake_case` plural (ej. `order_items`, `product_variants`) |
| **Nombres de columnas** | `snake_case` (ej. `payment_intent_id`, `is_active`) |
| **LLaves primarias** | `INT UNSIGNED AUTO_INCREMENT` llamadas `id` |
| **LLaves foráneas** | Nombre de tabla referenciada + `_id` (ej. `customer_id`, `product_id`) |
| **Timestamps** | `created_at` y `updated_at` con `DEFAULT CURRENT_TIMESTAMP` y `ON UPDATE` |
| **Soft delete** | Columna `deleted_at TIMESTAMP NULL DEFAULT NULL` (solo en `products`) |
| **Cero JSON** | Todo normalizado con tablas intermedias. Sin columnas de tipo `JSON`. |
| **Snapshots** | Datos denormalizados en órdenes (direcciones, producto) para preservar histórico aunque los originales cambien |

### 13.3 Conexión desde PHP

```php
// config.php define constantes DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
// getDb() retorna PDO singleton

$db = getDb();
$stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();
```

Todas las consultas usan **prepared statements** (PDO). No hay concatenación de SQL.

### 13.4 Migración

```bash
# Crear/actualizar base de datos desde schema.sql
mysql -u dail -p vuno_ramlop_ecommerce < php/database/schema.sql

# Verificar conexión
php -r "require 'php/config.php'; \$db = getDb(); echo 'DB OK';"
```

El archivo `schema.sql` es **idempotente** — incluye `CREATE DATABASE IF NOT EXISTS` y puede ejecutarse múltiples veces.

---

## 14. Plan de Pruebas — Panel Administrador

### 14.1 Fase 1 — Preparación y Autenticación (✓ Completada)

| # | Prueba | Resultado |
|---|--------|-----------|
| 1 | Verificar credenciales admin en DB (`admin_users.password_hash`) | ✅ |
| 2 | `validateCredentials()` usa solo DB con `password_verify()` (sin fallback `.env`) | ✅ |
| 3 | Migrar columnas TOTP a `admin_users` (`totp_secret`, `totp_enabled`, `backup_codes`) | ✅ |
| 4 | Corregir seed `schema.sql` (email + hash correctos) | ✅ |
| 5 | Login `admin@vunotek.com` / `admin123` funciona con validación DB | ✅ |
| 6 | Login con credenciales inválidas → 401 | ✅ |
| 7 | Eliminar `getOrCreateAdminUserId()` — ahora solo `getAdminUserId()` sin auto-creación desde `.env` | ✅ |
| 8 | Login real en navegador, confirmar sesión y Dashboard | ✅ |
| 9 | Login con usuario eliminado de DB → 401 (sin fallback a `.env`) | ✅ |
| 10 | Login con usuario creado directamente en DB → funciona | ✅ |

### 14.2 Fase 2 — Dashboard

| # | Prueba | Resultado |
|---|--------|-----------|
| 1 | Carga de 4 cards de estadísticas (total productos, pedidos, ingresos mes, pedidos recientes) | ⬜ |
| 2 | Verificar pedidos recientes en tabla del dashboard | ⬜ |
| 3 | Probar con DB vacía (sin seed) — manejo de error graceful | ⬜ |

### 14.3 Fase 3 — Gestión de Productos

| # | Prueba | Resultado |
|---|--------|-----------|
| 1 | Lista de productos con paginación, búsqueda, filtro por categoría | ⬜ |
| 2 | Crear producto nuevo: llenar 4 tabs (Información, Precio/Categoría, Imágenes, Variantes) | ⬜ |
| 3 | Subir imagen desde formulario | ⬜ |
| 4 | Matriz stock color × talle en pestaña Variantes | ⬜ |
| 5 | Editar producto existente (carga datos vía API) | ⬜ |
| 6 | Eliminar producto con modal de confirmación | ⬜ |
| 7 | Verificar slug auto-generado al escribir título | ⬜ |
| 8 | Probar CRUD con rol `editor` (debería funcionar) | ⬜ |
| 9 | Probar CRUD con rol `viewer` (debería denegar escritura) | ⬜ |

### 14.4 Fase 4 — Categorías

| # | Prueba | Resultado |
|---|--------|-----------|
| 1 | Lista de categorías paginada | ⬜ |
| 2 | Crear categoría (nombre + slug auto) | ⬜ |
| 3 | Editar categoría existente | ⬜ |
| 4 | Eliminar categoría | ⬜ |

### 14.5 Fase 5 — Cupones

| # | Prueba | Resultado |
|---|--------|-----------|
| 1 | Lista de cupones con paginación y búsqueda | ⬜ |
| 2 | Crear cupón: descuento porcentaje | ⬜ |
| 3 | Crear cupón: descuento fijo | ⬜ |
| 4 | Crear cupón con fechas de vigencia | ⬜ |
| 5 | Crear cupón con límite de usos | ⬜ |
| 6 | Editar cupón | ⬜ |
| 7 | Eliminar cupón | ⬜ |
| 8 | Verificar que cupón vencido no se aplica en checkout | ⬜ |
| 9 | Verificar que cupón agotado no se aplica | ⬜ |

### 14.6 Fase 6 — Reseñas

| # | Prueba | Resultado |
|---|--------|-----------|
| 1 | Lista de reseñas con filtro por estado (pending/approved) | ⬜ |
| 2 | Aprobar reseña pendiente | ⬜ |
| 3 | Eliminar reseña | ⬜ |

### 14.7 Fase 7 — Blog

| # | Prueba | Resultado |
|---|--------|-----------|
| 1 | Lista de posts con búsqueda y filtro status (draft/published) | ⬜ |
| 2 | Crear post: título, slug auto, extracto, contenido HTML, imagen, autor, categoría | ⬜ |
| 3 | Editar post con carga de datos existentes | ⬜ |
| 4 | Eliminar post (soft delete) | ⬜ |
| 5 | Verificar blog público en `/blog/` y `/blog/{slug}` | ⬜ |

### 14.8 Fase 8 — Pedidos

| # | Prueba | Resultado |
|---|--------|-----------|
| 1 | Lista de pedidos con filtro por estado y búsqueda | ⬜ |
| 2 | Ver badges de estado: pending (ocre), paid (negro), shipped (negro 80%), delivered (negro), cancelled (rojo) | ⬜ |
| 3 | Detalle de pedido: datos cliente, items, totales | ⬜ |
| 4 | Visualización de comprobante de transferencia (link ImageKit) | ⬜ |
| 5 | Cambio de estado (pending → paid → shipped → delivered) | ⬜ |
| 6 | Cancelar pedido | ⬜ |
| 7 | Verificar registro en `admin_activity_log` al cambiar estado | ⬜ |

### 14.9 Fase 9 — Seguridad (2FA)

| # | Prueba | Resultado |
|---|--------|-----------|
| 1 | Ir a `/admin/seguridad` — ver estado 2FA (disabled inicialmente) | ⬜ |
| 2 | Setup: ingresar password, escanear QR con app TOTP | ⬜ |
| 3 | Verificar código TOTP, recibir 8 backup codes | ⬜ |
| 4 | Cerrar sesión y login de nuevo — debe pedir TOTP después del password | ⬜ |
| 5 | Login con código TOTP válido → Dashboard | ⬜ |
| 6 | Login con código TOTP inválido → error | ⬜ |
| 7 | Login con backup code → Dashboard | ⬜ |
| 8 | Deshabilitar 2FA (password + código TOTP) | ⬜ |
| 9 | Verificar que backup code usado no funciona de nuevo | ⬜ |

### 14.10 Fase 10 — Usuarios (solo superadmin)

| # | Prueba | Resultado |
|---|--------|-----------|
| 1 | Lista de usuarios con roles | ⬜ |
| 2 | Crear usuario via modal (email, nombre, password, rol) | ⬜ |
| 3 | Editar usuario (cambiar email, nombre, password, rol) | ⬜ |
| 4 | Eliminar usuario con confirmación | ⬜ |
| 5 | Login como `editor` — sidebar oculta "Usuarios" y "Configuración" | ⬜ |
| 6 | Login como `viewer` — botones de crear/editar/eliminar deben ocultarse | ⬜ |

### 14.11 Fase 11 — Configuración

| # | Prueba | Resultado |
|---|--------|-----------|
| 1 | Abrir `/admin/configuracion` — carga todas las secciones (Store, Receipt, ImageKit, Stripe, Transfer, SMTP) | ⬜ |
| 2 | Modificar valor en sección Store y guardar | ⬜ |
| 3 | Verificar cambio persiste al recargar | ⬜ |

### 14.12 Fase 12 — Issues de Seguridad (Hallazgos)

| # | Issue | Archivo | Riesgo | Estado |
|---|-------|---------|--------|--------|
| 1 | ImageKit upload sin auth | `php/api/imagekit/upload.php` | ⚠️ Alto | ⬜ |
| 2 | Pedidos API sin auth (`list`, `get`) | `php/api/pedidos/list.php`, `get.php` | ⚠️ Alto | ⬜ |
| 3 | Rate-limit sin try/catch en DB connection | `php/includes/auth.php` | 🟡 Medio | ⬜ |
| 4 | Dashboard sin fallback si DB vacía | `php/api/dashboard/stats.php` | 🟢 Bajo | ⬜ |

---

> **Documentación generada:** 02/06/2026
> **Última actualización:** 04/06/2026 — Plan de pruebas Panel Admin (Phase 1)
