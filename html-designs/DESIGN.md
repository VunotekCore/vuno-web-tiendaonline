---
name: L'Essence de Ram;Lop
colors:
  surface: '#faf9f8'
  surface-dim: '#dadad9'
  surface-bright: '#faf9f8'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f4f3f2'
  surface-container: '#eeeeed'
  surface-container-high: '#e9e8e7'
  surface-container-highest: '#e3e2e1'
  on-surface: '#1a1c1c'
  on-surface-variant: '#444748'
  inverse-surface: '#2f3130'
  inverse-on-surface: '#f1f0ef'
  outline: '#747878'
  outline-variant: '#c4c7c7'
  surface-tint: '#5f5e5e'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#1c1b1b'
  on-primary-container: '#858383'
  inverse-primary: '#c8c6c5'
  secondary: '#635e57'
  on-secondary: '#ffffff'
  secondary-container: '#e6ded5'
  on-secondary-container: '#67625b'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#32120a'
  on-tertiary-container: '#aa786a'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#e5e2e1'
  primary-fixed-dim: '#c8c6c5'
  on-primary-fixed: '#1c1b1b'
  on-primary-fixed-variant: '#474746'
  secondary-fixed: '#e9e1d8'
  secondary-fixed-dim: '#cdc5bd'
  on-secondary-fixed: '#1e1b16'
  on-secondary-fixed-variant: '#4b4640'
  tertiary-fixed: '#ffdbd1'
  tertiary-fixed-dim: '#f3b9aa'
  on-tertiary-fixed: '#32120a'
  on-tertiary-fixed-variant: '#653c31'
  background: '#faf9f8'
  on-background: '#1a1c1c'
  surface-variant: '#e3e2e1'
  monolith-black: '#1A1A1A'
  sand-nude: '#E6DED5'
  clay-accent: '#C18C7E'
  off-white: '#FFFFFF'
  stone-gray: '#9A9A9A'
typography:
  headline-xl:
    fontFamily: Playfair Display
    fontSize: 64px
    fontWeight: '700'
    lineHeight: 72px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Playfair Display
    fontSize: 40px
    fontWeight: '600'
    lineHeight: 48px
  headline-lg-mobile:
    fontFamily: Playfair Display
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
  headline-md:
    fontFamily: Playfair Display
    fontSize: 24px
    fontWeight: '500'
    lineHeight: 32px
  body-lg:
    fontFamily: Hanken Grotesk
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Hanken Grotesk
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-caps:
    fontFamily: Hanken Grotesk
    fontSize: 12px
    fontWeight: '700'
    lineHeight: 16px
    letterSpacing: 0.1em
  price-display:
    fontFamily: Hanken Grotesk
    fontSize: 20px
    fontWeight: '500'
    lineHeight: 24px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  unit: 8px
  container-max: 1440px
  gutter: 24px
  margin-desktop: 80px
  margin-mobile: 20px
  section-gap: 120px
---

## Brand & Style
The design system is built on the philosophy of **Architectural Minimalism**. It targets a sophisticated, fashion-forward female demographic that values both trend and timeless quality. The visual narrative avoids the clutter of traditional e-commerce, instead adopting an "editorial gallery" aesthetic.

The style is **Modern Minimalist** with a focus on high-quality white space and structured layouts. By stripping away non-essential decorations, the footwear becomes the singular focus. The emotional response should be one of calm, luxury, and effortless confidence—mimicking the experience of walking into a high-end boutique.

## Colors
This design system utilizes a palette of **Modern Earth Tones**. 
- **Monolith Black** is used for high-contrast typography and primary actions, providing a grounded, authoritative feel. 
- **Sand Nude** serves as the primary background for product sections to mimic skin tones and natural leather, creating a soft, organic warmth. 
- **Clay Accent** is used sparingly for highlights, sales tags, or subtle call-to-outs.
- **Neutral** surfaces use a mix of pure white and off-white to define different content zones without needing heavy borders.

## Typography
The typography strategy pairings high-fashion editorial flair with modern Swiss-style legibility. 
- **Playfair Display** (Headlines): Used for major headings and collection names to evoke luxury and timelessness. 
- **Hanken Grotesk** (Body & Labels): A sharp, contemporary sans-serif used for product descriptions, prices, and navigation. Its high legibility ensures a "clean" look even at small sizes.
- **Micro-copy**: Use `label-caps` for category tags and technical specifications to maintain a structured, professional appearance.

## Layout & Spacing
The layout follows a **Strict Fixed Grid** for the main content container to ensure an organized, boutique-like presentation.
- **Desktop**: A 12-column grid with wide 80px margins to allow the product photography to breathe.
- **Mobile**: A 2-column grid for product listings to maximize image size, with a reduction to 20px margins.
- **Rhythm**: Generous vertical spacing (`section-gap`) is encouraged between different shoe collections to prevent visual fatigue and maintain the minimalist aesthetic.

## Elevation & Depth
In keeping with the minimalist-modern approach, this design system rejects heavy shadows in favor of **Tonal Layering** and **Low-Contrast Outlines**.
- **Surface Depth**: Use subtle shifts between `Off-White` and `Sand Nude` to differentiate sections (e.g., a nude header over a white hero section).
- **Interactive States**: Hover states on product cards should not use shadows; instead, use a subtle 10% scale-up of the image or a slight opacity shift.
- **Modals**: For "Quick View" features, use a semi-transparent `Monolith Black` overlay (40% opacity) with a centered white container to maintain focus.

## Shapes
Shapes are defined by **Modern Softness**. Elements use a very slight radius (Soft/0.25rem) to move away from the harshness of brutalism while remaining more structured than casual "bubbly" designs.
- **Product Images**: Should maintain sharp corners (0px) to feel like professional photography prints.
- **UI Elements**: Buttons and input fields use the `Soft` radius to feel approachable yet precise.

## Components
- **Buttons**: Primary buttons are solid `Monolith Black` with white `label-caps` text. They should be wide and tall (minimum 56px height) to feel significant. Secondary buttons are "Ghost" style with a 1px black border.
- **Product Cards**: Minimalist design with no external borders. The price is placed directly below the title in `price-display`. Color swatches should be small, clean circles.
- **Chips/Filters**: Use `Sand Nude` backgrounds with `Monolith Black` text. Selected states should invert the colors.
- **Input Fields**: Underlined style (bottom-border only) rather than fully enclosed boxes, keeping the forms feeling light and "sketched."
- **Navigation**: A centered, minimalist header. Use `label-caps` for top-level categories like "SANDALS," "HEELS," and "NEW ARRIVALS."
- **Checkboxes**: Square and sharp, using a simple checkmark icon without a fill color when active to maintain the line-art aesthetic.