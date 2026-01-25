/** @type {import('tailwindcss').Config} */
const config = {
  darkMode: 'class',
  content: ['./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}'],
  safelist: [
    'glass-card',
    'hero-glow',
    'scanline',
  ],
  theme: {
    extend: {
      colors: {
        primary: '#06f9f9',
        'accent-violet': '#8b5cf6',
        'background-light': '#f5f8f8',
        'background-dark': '#0a0a0a',
        obsidian: '#050505',
        'glass-border': '#214a4a',
      },
      fontFamily: {
        display: ['Space Grotesk', 'sans-serif'],
        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', 'Liberation Mono', 'Courier New', 'monospace'],
      },
      borderRadius: {
        DEFAULT: '0.25rem',
        lg: '0.5rem',
        xl: '0.75rem',
        full: '9999px',
      },
      animation: {
        pulse: 'pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
      },
    },
  },
  plugins: [
    function({ addUtilities }) {
      addUtilities({
        '.glass-morphism': {
          background: 'rgba(16, 35, 35, 0.4)',
          backdropFilter: 'blur(12px)',
          border: '1px solid rgba(33, 74, 74, 0.5)',
        },
        '.glass-card': {
          background: 'rgba(16, 35, 35, 0.4)',
          backdropFilter: 'blur(12px)',
          border: '1px solid rgba(33, 74, 74, 0.5)',
        },
      });
    },
  ],
};

export default config;