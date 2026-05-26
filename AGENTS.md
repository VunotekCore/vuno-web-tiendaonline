# 🏛️ RAM;LOP — Documentación Técnica y Comercial

> **Proyecto:** Tienda online de calzado artesanal para damas
> **Marca:** Ram;Lop — "Architectural Minimalism in Footwear"
> **Estado:** MVP funcional v1.0
> **Stack:** Astro 6 · Node.js · TypeScript strict · Tailwind 4 · Stripe · ImageKit · Nodemailer

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

---

## 1. Resumen Ejecutivo

Ram;Lop es una tienda online de **calzado artesanal femenino** con estética minimalista arquitectónica. El proyecto está construido con tecnologías web modernas (Astro 6 + Node.js) y cuenta con:

- **Tienda pública** con catálogo, carrito y checkout
- **Panel administrador** para gestión de productos, pedidos e inventario
- **Dos métodos de pago**: Stripe (tarjetas) y transferencia bancaria
- **Gestión de imágenes** externa via ImageKit
- **Notificaciones** por email
- **Autenticación segura** con cookies HttpOnly

### ¿Por qué Node.js?

Node.js es el runtime que ejecuta el servidor. Astro en modo `output: "server"` lo requiere para:
- API endpoints (Stripe webhooks, ImageKit, email)
- Verificación server-side de cookies en el panel admin
- Persistencia de datos (lectura/escritura de archivos)

**Para el cliente final es transparente** — el sitio se percibe como una web rápida y moderna. Node.js no es visible ni requiere intervención del cliente.

---

## 2. Funcionalidades MVP

### 🛍️ Tienda Pública

| Página | Ruta | Funcionalidad |
|--------|------|---------------|
| Home | `/` | Hero slider con 4 imágenes editoriales, bento grid de nuevos lanzamientos, sección tendencias, selección curada de productos |
| Catálogo | `/catalogo` | Grid 3 cols desktop / 2 mobile, filtros por talla/color/estilo, breadcrumbs, efecto vista rápida, botón "Cargar más" |
| Detalle Producto | `/producto/[slug]` | Galería de 3 imágenes, selector de color/talle, acordeones (descripción, cuidados, envíos), botón añadir al carrito, productos relacionados |
| Carrito | `/carrito` | Items desde localStorage, ajuste de cantidad, eliminación, resumen de orden, botón a checkout |
| Checkout | `/checkout` | Formulario de envío, selección de pago (Stripe o transferencia), resumen final |

### 🔐 Panel Administrador

| Módulo | Ruta | Funcionalidad |
|--------|------|---------------|
| Login | `/admin/login` | Autenticación con cookie HttpOnly + JWT |
| Productos | `/admin/productos` | Lista dinámica de productos con datos desde JSON |
| Nuevo Producto | `/admin/productos/nuevo` | Formulario de creación (preparado) |
| Pedidos | `/admin/pedidos` | Lista con estados, métodos de pago, fechas |

### 💳 Pagos

- **Stripe**: Payment Intents con webhooks para confirmación
- **Transferencia bancaria**: Subida de comprobante + notificación por email al admin

### 📧 Notificaciones

- Email al admin cuando se realiza un pedido por transferencia (Nodemailer)

---

## 3. Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────┐
│                     Cliente (Browser)                    │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │
│  │  Home    │ │ Catálogo │ │ Carrito  │ │  Admin   │  │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘  │
│              │ localStorage (carrito)                   │
│              │ Cookie HttpOnly (token admin)            │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│                   Astro 6 (Server)                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Páginas    │  │  API Routes  │  │  Middleware   │  │
│  │   SSR        │  │  /api/*      │  │  Auth Guard   │  │
│  └──────────────┘  └──────┬───────┘  └──────────────┘  │
│                           │                              │
│  ┌────────────────────────▼──────────────────────────┐  │
│  │              Servicios (src/lib/)                   │  │
│  │  ┌────────┐ ┌────────┐ ┌────────┐ ┌─────────────┐ │  │
│  │  │ Stripe │ │ImageKit│ │ Email  │ │ Cookie Auth  │ │  │
│  │  └────────┘ └────────┘ └────────┘ └─────────────┘ │  │
│  │  ┌─────────────┐ ┌──────────────┐                  │  │
│  │  │  Storage    │ │   Admin      │                  │  │
│  │  │ (JSON files)│ │ (JWT verify) │                  │  │
│  │  └─────────────┘ └──────────────┘                  │  │
│  └────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────┘
```

### Stack Técnico

| Capa | Tecnología | Versión |
|------|-----------|---------|
| Framework | Astro | 6.x |
| Runtime | Node.js | 18+ |
| Lenguaje | TypeScript | strict |
| Estilos | Tailwind CSS | 4.x con @theme |
| Iconos | Material Symbols Outlined | — |
| Fuentes | Playfair Display + Hanken Grotesk | Google Fonts |
| Base de datos | JSON files (src/data/) | Migración futura a SQLite/PostgreSQL |
| Adaptador | @astrojs/node | standalone |

---

## 4. Panel Administrador

### 4.1 Autenticación

- **Login**: `POST /api/admin/login` — valida credenciales, genera token JWT, lo envía como cookie
- **Verificación**: Cada página bajo `/admin/*` verifica el token server-side desde la cookie
- **Logout**: `GET /api/admin/logout` — invalida la cookie y redirige
- **Verificación API**: `GET /api/admin/verify` — endpoint para validar sesión vigente

### 4.2 Módulos Actuales

#### Productos (`/admin/productos`)
- Tabla con nombre, precio, categoría, colores
- Datos dinámicos desde `src/data/products.json`
- Preparado para CRUD completo

#### Nuevo Producto (`/admin/productos/nuevo`)
- Formulario con campos: nombre, slug, descripción, precio, categoría
- Upload de imágenes (preparado para ImageKit)
- Gestión de talles y colores con stock

#### Pedidos (`/admin/pedidos`)
- Lista con: ID, cliente, fecha, total, estado, método de pago
- Badges de estado con colores: pendiente (amarillo), pagado (verde), enviado (azul), entregado (gris)

### 4.3 Mejoras Pendientes (Phase 2)

- [ ] Endpoints PUT/DELETE para productos
- [ ] Vista detalle de pedido `/admin/pedidos/[id]`
- [ ] Cambio de estado de pedidos
- [ ] Visualización de comprobantes de transferencia
- [ ] Paginación y búsqueda
- [ ] Dashboard con estadísticas

---

## 5. Seguridad

### 5.1 Implementación Actual

| Aspecto | Implementación |
|---------|---------------|
| **Autenticación admin** | Cookie `ramlop_admin_token` con flags: `HttpOnly`, `Path=/`, `SameSite=Strict`, `Max-Age=86400` |
| **Token JWT** | Generado con `base64( email + ":" + timestamp + ":" + secret )` + hash SHA-256 |
| **Verificación server-side** | Cada request a página admin lee la cookie del header, verifica el token, redirige si es inválido |
| **Protección XSS** | Cookie HttpOnly — el token NO es accesible desde JavaScript del cliente |
| **Protección CSRF** | SameSite=Strict — la cookie no se envía en requests cross-site |
| **Cierre de sesión** | Endpoint dedicado que setea `Max-Age=0` en la cookie |
| **Credenciales** | Almacenadas en variables de entorno (`.env`), no en código |
| **API Keys** | Stripe, ImageKit, SMTP — todas via `process.env` |

### 5.2 Mejoras Planeadas (Phase 2-3)

- [ ] JWT con expiración y renovación automática
- [ ] Rate limiting en login (prevenir brute force)
- [ ] CSRF token adicional para formularios admin
- [ ] Headers de seguridad (CSP, HSTS, X-Frame-Options)
- [ ] Logging de intentos de acceso
- [ ] Roles de usuario (admin, editor, viewer)
- [ ] 2FA para acceso admin
- [ ] Auditoría de cambios en productos/pedidos

---

## 6. Integraciones Externas

### 6.1 Stripe — Pagos con Tarjeta

| Endpoint | Propósito |
|----------|-----------|
| `POST /api/stripe/create-payment-intent` | Crea un PaymentIntent desde el frontend |
| `POST /api/stripe/webhook` | Recibe confirmación de pago asíncrona |

**Estado actual**: Implementado. Requiere `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET` en `.env`.

### 6.2 ImageKit — Gestión de Imágenes

| Función | Propósito |
|---------|-----------|
| `uploadImage(file, folder?)` | Subir imagen individual |
| `uploadBatch(files, folder?)` | Subir lote de imágenes |
| `getImage(fileId)` | Obtener metadata de imagen |
| `getImages(options?)` | Listar imágenes con paginación |
| `deleteImage(fileId)` | Eliminar imagen |

**Estado actual**: Servicio implementado en `src/lib/imagekit.ts`. Falta conectar el formulario de admin productos al upload real.

### 6.3 Nodemailer — Notificaciones Email

| Endpoint | Propósito |
|----------|-----------|
| `POST /api/email/send` | Enviar email de notificación de pedido |

**Estado actual**: Implementado con transporte SMTP configurable. Se activa al recibir un pedido por transferencia bancaria.

### 6.4 Mejoras Planeadas (Phase 2-3)

- [ ] Webhooks de ImageKit para sincronización
- [ ] Email transaccional con templates HTML
- [ ] Cola de emails con reintentos
- [ ] Integración con servicio de tracking de envíos
- [ ] Notificaciones WhatsApp/Twilio (fase 3)

---

## 7. Inventario y Productos

### 7.1 Modelo de Datos

```typescript
interface Product {
  id: string;
  name: string;
  slug: string;
  description: string;
  details?: string[];
  price: number;
  currency: string;
  images: string[];
  category: string;
  colors: { name: string; hex: string }[];
  sizes: { label: string; value: string; inStock: boolean }[];
}
```

### 7.2 Estructura de Inventario

- **Stock por talle**: Cada producto tiene un array de talles con flag `inStock`
- **Stock por color**: Cada producto tiene un array de colores disponibles
- **Combinación**: La intersección talle × color determina la disponibilidad real

### 7.3 Datos de Semilla

- **7 productos** en `src/data/products.json` con imágenes, talles, colores, precios
- **2 pedidos** de muestra en `src/data/orders.json` con diferentes estados

### 7.4 Mejoras Planeadas (Phase 2)

- [ ] Stock por combinación específica (talle + color + cantidad numérica)
- [ ] Alertas de stock bajo
- [ ] Historial de precios
- [ ] Categorías y subcategorías gestionables desde admin
- [ ] Variantes de producto (color, material, edición)
- [ ] Importación/exportación CSV
- [ ] Migración a base de datos relacional (SQLite → PostgreSQL)

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

Ver `html-designs/DESIGN.md` para la paleta completa (Material Design 3). Tokens principales:

| Token | Hex | Uso |
|-------|-----|-----|
| `monolith-black` | `#1A1A1A` | Tipografía, botones primarios |
| `sand-nude` | `#E6DED5` | Fondos de secciones |
| `clay-accent` | `#C18C7E` | Acentos, CTAs secundarios |
| `background` | `#faf9f8` | Fondo general |

### 8.3 Tipografía

- **Playfair Display** — Headlines (títulos, colecciones)
- **Hanken Grotesk** — Body, labels, precios, navegación
- **label-caps**: 12px, 700, letter-spacing 0.1em — categorías

### 8.4 Diseño Responsive

- Container max: 1440px
- Desktop: 12 columnas, 80px márgenes
- Mobile: 2 columnas, 20px márgenes
- Sin sombras — tonal layering + low-contrast outlines
- Border radius: DEFAULT 0.125rem

---

## 9. Roadmap — Fases Futuras

### Phase 2 — Consolidación (Siguiente)

| Prioridad | Feature | Estado |
|-----------|---------|--------|
| Alta | CRUD completo de productos (crear, editar, eliminar) | Pendiente |
| Alta | PUT/DELETE endpoints para productos | Pendiente |
| Alta | Vista detalle de pedido `/admin/pedidos/[id]` | Pendiente |
| Alta | Cambio de estado de pedidos desde admin | Pendiente |
| Alta | Conectar ImageKit upload desde formulario admin | Pendiente |
| Media | Stock numérico por talle × color | Pendiente |
| Media | Paginación en listas admin | Pendiente |
| Media | Dashboard admin con estadísticas | Pendiente |
| Media | Visualización de comprobantes de transferencia | Pendiente |
| Baja | Búsqueda y filtros en admin | Pendiente |

### Phase 3 — Escalabilidad

| Prioridad | Feature | Estado |
|-----------|---------|--------|
| Alta | Migración a base de datos (SQLite → PostgreSQL) | Pendiente |
| Alta | Autenticación JWT con expiración y refresh | Pendiente |
| Alta | Headers de seguridad (CSP, HSTS) | Pendiente |
| Alta | Rate limiting en login | Pendiente |
| Media | Email transaccional con templates HTML | Pendiente |
| Media | Roles de usuario (admin, editor, viewer) | Pendiente |
| Media | Cache de catálogo (ISR o CDN) | Pendiente |
| Baja | 2FA para acceso admin | Pendiente |
| Baja | Logging y auditoría | Pendiente |

### Phase 4 — Crecimiento

| Prioridad | Feature | Estado |
|-----------|---------|--------|
| Media | Cupones y descuentos | Pendiente |
| Media | Wishlist / lista de deseos | Pendiente |
| Media | Reseñas y valoraciones | Pendiente |
| Media | Notificaciones WhatsApp/Twilio | Pendiente |
| Baja | WordPress headless como CMS | Pospuesto |
| Baja | Multi-idioma (i18n) | Pendiente |
| Baja | PWA / soporte offline | Pendiente |
| Baja | Blog / editorial content | Pendiente |

---

## 10. Estructura del Proyecto

```
/
├── html-designs/                   # Diseños HTML originales de referencia
│   ├── DESIGN.md                   # Design tokens completos (Material Design 3)
│   ├── inicio_ram_lop/             # Homepage
│   ├── cat_logo_ram_lop/           # Catálogo
│   ├── detalle_de_producto_ram_lop/ # Detalle de producto
│   └── carrito_de_compras_ram_lop/ # Carrito
├── public/
│   ├── js/cart.js                  # Carrito cliente (localStorage + eventos)
│   └── favicon.svg
├── src/
│   ├── components/
│   │   ├── atoms/                  # Button, Input, Tag, Icon
│   │   ├── molecules/              # ProductCard, CartItem, FilterGroup
│   │   └── organisms/              # Navbar, Footer, ProductGrid, HeroSection
│   ├── layouts/
│   │   └── BaseLayout.astro        # Layout principal con SEO, fonts, Material Symbols
│   ├── pages/
│   │   ├── index.astro             # Home con hero slider
│   │   ├── catalogo.astro          # Catálogo con filtros
│   │   ├── producto/[slug].astro   # Detalle de producto
│   │   ├── carrito.astro           # Carrito de compras
│   │   ├── checkout.astro          # Checkout (Stripe + Transferencia)
│   │   ├── api/
│   │   │   ├── admin/login.ts      # Login → setea cookie HttpOnly
│   │   │   ├── admin/verify.ts     # Verifica token desde cookie
│   │   │   ├── admin/logout.ts     # Invalida cookie
│   │   │   ├── stripe/create-payment-intent.ts
│   │   │   ├── stripe/webhook.ts
│   │   │   ├── imagekit/upload.ts
│   │   │   └── email/send.ts
│   │   └── admin/
│   │       ├── login.astro         # Login page
│   │       ├── layout.astro        # Shell admin con auth guard
│   │       ├── productos.astro     # Lista de productos
│   │       ├── productos/nuevo.astro # Formulario nuevo producto
│   │       └── pedidos.astro       # Lista de pedidos
│   ├── lib/
│   │   ├── storage.ts              # JSON file CRUD (products, orders)
│   │   ├── imagekit.ts             # ImageKit service
│   │   ├── stripe.ts               # Stripe service
│   │   ├── email.ts                # Nodemailer service
│   │   ├── admin.ts                # Auth (validate, generate, verify token)
│   │   ├── cookie.ts               # Cookie helpers (set, clear, get)
│   │   ├── cart.ts                 # Cart calculations
│   │   ├── types.ts                # Interfaces globales
│   │   └── utils.ts                # Helpers
│   ├── data/
│   │   ├── products.json           # 7 productos de semilla
│   │   └── orders.json             # 2 pedidos de muestra
│   ├── styles/
│   │   └── global.css              # Tailwind @theme + design tokens
│   └── env.d.ts                    # Tipo global Window.RamLopCart
├── .env.example                    # Variables de entorno requeridas
├── astro.config.mjs                # Config Astro (server, node adapter)
├── tsconfig.json                   # TypeScript strict
├── package.json                    # Dependencias y scripts
└── AGENTS.md                       # Este archivo
```

---

## 11. Comandos y Verificación

```bash
pnpm dev              # Servidor de desarrollo (http://localhost:4321)
pnpm build            # Build producción (output en dist/)
pnpm preview          # Preview del build
pnpm astro check      # TypeScript check (0 errors = saludable)
pnpm lint             # ESLint
pnpm format           # Prettier
pnpm test             # Vitest (a implementar)
```

### Acceso Admin

```
URL:      http://localhost:4321/admin/login
Email:    admin@ramlop.com
Password: ramlop2024
```

### Variables de Entorno Requeridas (`.env`)

```
STRIPE_SECRET_KEY=
STRIPE_PUBLISHABLE_KEY=
STRIPE_WEBHOOK_SECRET=
IMAGEKIT_PRIVATE_KEY=
IMAGEKIT_PUBLIC_KEY=
IMAGEKIT_URL_ENDPOINT=
SMTP_HOST=
SMTP_PORT=
SMTP_USER=
SMTP_PASS=
FROM_EMAIL=
ADMIN_EMAIL=
ADMIN_PASSWORD=
TOKEN_SECRET=
```

---

## 12. Guía para Desarrolladores

### Modos de Operación

**MODO A — Desarrollo de Interfaces (Astro Ecosystem)**
- Contexto: Ecommerce frontend, páginas públicas, catálogo, carrito
- Detección: `astro.config.mjs` o archivos `.astro`
- Foco: Componentes `.astro`, Tailwind 4, diseño responsive
- Salud: `pnpm astro check`

**MODO B — Lógica de Negocio (Node.js Core / API Routes)**
- Contexto: API endpoints, servicios de pago, notificaciones, admin
- Detección: Archivos en `src/pages/api/`, `src/lib/`
- Foco: Validación, seguridad, integración con servicios externos
- Lenguaje: TypeScript estricto siempre

### Convenciones de Código

- TypeScript strict — `strict: true` en `tsconfig.json`
- Importaciones con path absoluto desde `src/`
- Componentes atómicos en `src/components/atoms/`
- Sin librerías externas de UI — Tailwind + vanilla JS
- Iconos: Material Symbols Outlined via Google Fonts
- Imágenes: URLs de ImageKit o AIDA (placeholder)
- Commits con prefijo `[MODO-A|MODO-B] Ram;Lop - descripción`

### Flujo de Trabajo

1. **Diagnóstico** — Identificar Modo y validar salud del proyecto
2. **Plan** — Lista de pasos (requiere aprobación para cambios estructurales)
3. **Ejecución** — Código limpio, tipado, validado
4. **Cierre** — Estado, archivos modificados, siguiente paso

---

> **Documentación generada:** 26/05/2026
> **Última actualización:** MVP v1.0
