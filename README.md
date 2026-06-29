# Vunotek — Architectural Minimalism in Footwear

Tienda online de calzado artesanal para damas con carrito de compra, pasarela de pago (Stripe + transferencia bancaria) y panel administrador.

## Stack Tecnológico

- **Framework**: Astro 5.x
- **Estilos**: Tailwind 4 con design tokens personalizados
- **Lenguaje**: TypeScript (strict)
- **Gestor de paquetes**: pnpm
- **Imágenes**: ImageKit (subir/obtener individual y batch)
- **Pagos**: Stripe + Transferencia bancaria con upload de comprobante
- **Notificaciones**: Email (Nodemailer)

## Páginas

| Ruta | Descripción |
|------|-------------|
| `/` | Homepage — Hero, New Arrivals (bento grid), Seasonal Trends, Curated Selection |
| `/catalogo` | Catálogo con filtros (talla, color, estilo), grid 3 cols |
| `/producto/[slug]` | Detalle de producto — galería, selector color/talle, accordion |
| `/carrito` | Carrito de compras + order summary |
| `/checkout` | Checkout con Stripe y transferencia bancaria |
| `/admin/login` | Login del panel administrador |
| `/admin/productos` | CRUD de productos |
| `/admin/pedidos` | Gestión de pedidos |

## API Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/email/send` | Enviar notificaciones por email |
| POST | `/api/stripe/create-payment-intent` | Crear Payment Intent de Stripe |
| POST | `/api/stripe/webhook` | Webhook de Stripe |
| POST | `/api/imagekit/upload` | Subir imagen a ImageKit |

## Comandos

```bash
pnpm dev              # Servidor de desarrollo
pnpm build            # Build producción
pnpm preview          # Preview del build
pnpm astro check      # TypeScript check
pnpm lint             # ESLint
pnpm format           # Prettier
```

## Variables de Entorno

```bash
# Stripe
STRIPE_SECRET_KEY=
STRIPE_PUBLISHABLE_KEY=
STRIPE_WEBHOOK_SECRET=

# ImageKit
IMAGEKIT_PRIVATE_KEY=
IMAGEKIT_PUBLIC_KEY=
IMAGEKIT_URL_ENDPOINT=

# Email (SMTP)
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=
FROM_EMAIL=noreply@vunotek.com
```
