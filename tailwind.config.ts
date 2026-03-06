import type { Config } from 'tailwindcss';

const config: Config = {
  darkMode: 'class',
  content: [
    './src/pages/**/*.{js,ts,jsx,tsx,mdx}',
    './src/components/**/*.{js,ts,jsx,tsx,mdx}',
    './src/app/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      colors: {
        /* ── Palette 1: Deep Medical Blue ── */
        primary: {
          50:  '#E8F1FA',
          100: '#C5DCF3',
          200: '#9DC5EC',
          300: '#73ADE4',
          400: '#4D97DD',
          500: '#004A99',   /* Primary brand */
          600: '#003D80',
          700: '#002F6C',   /* Dark Blue accent */
          800: '#002254',
          900: '#00163C',
        },
        /* ── Accent Teal (Palette 1) ── */
        teal: {
          50:  '#E0F2F1',
          100: '#B2DFDB',
          200: '#80CBC4',
          300: '#4DB6AC',   /* Accent Teal */
          400: '#26A69A',
          500: '#009688',
          600: '#00897B',
          700: '#00796B',
          800: '#00695C',
          900: '#004D40',
        },
        /* ── Palette 2: Soft Charcoal Wellness ── */
        charcoal: {
          50:  '#ECEFF1',
          100: '#CFD8DC',
          200: '#B0BEC5',
          300: '#90A4AE',
          400: '#78909C',   /* Slate Gray */
          500: '#607D8B',
          600: '#546E7A',
          700: '#455A64',
          800: '#37474F',   /* Charcoal */
          900: '#263238',
        },
        /* ── Muted greens / wellness ── */
        wellness: {
          300: '#81C784',   /* Muted Green */
          400: '#66BB6A',
          500: '#4CAF50',
          600: '#43A047',
        },
        accent: {
          50: '#FEF9E7',
          100: '#FCF3CF',
          200: '#F9E79F',
          300: '#F7DC6F',
          400: '#F4D03F',
          500: '#D4AC0D',
          600: '#B7950B',
        },
        /* ── Pale Aqua (Palette 3) ── */
        aqua: {
          100: '#E0F7FA',
          200: '#B2EBF2',   /* Pale Aqua */
          300: '#80DEEA',
          400: '#4DD0E1',
        },
        navy: '#0A1628',
        dark: {
          bg:      '#0F1723',     /* Deep gradient charcoal base */
          surface: '#161F2E',     /* Slightly lighter surface */
          card:    '#1C2738',     /* Card background */
          border:  '#2A3A50',     /* Subtle border */
          accent:  '#4DB6AC',     /* Teal accent in dark mode */
          'accent-hover': '#26A69A',
          muted:   '#8B9BB4',     /* Muted text */
        },
      },
      fontFamily: {
        sans:    ['var(--font-opensans)', 'Open Sans', 'system-ui', '-apple-system', 'sans-serif'],
        heading: ['var(--font-montserrat)', 'Montserrat', 'system-ui', '-apple-system', 'sans-serif'],
        gujarati: ['Hind Vadodara', 'Noto Sans Gujarati', 'sans-serif'],
      },
      borderRadius: {
        '2xl': '1rem',
        '3xl': '1.5rem',
        '4xl': '2rem',
      },
      boxShadow: {
        'soft':     '0 2px 15px -3px rgba(0, 0, 0, 0.06), 0 10px 20px -2px rgba(0, 0, 0, 0.03)',
        'soft-lg':  '0 10px 40px -10px rgba(0, 0, 0, 0.09), 0 2px 10px -2px rgba(0, 0, 0, 0.03)',
        'soft-xl':  '0 20px 60px -15px rgba(0, 0, 0, 0.08)',
        'glow':     '0 0 25px rgba(0, 74, 153, 0.15)',
        'glow-lg':  '0 0 40px rgba(0, 74, 153, 0.2)',
        'glow-accent': '0 0 25px rgba(77, 182, 172, 0.2)',
        'glass':    '0 8px 32px rgba(0, 0, 0, 0.06)',
        'card-hover': '0 14px 44px -8px rgba(0, 74, 153, 0.12), 0 4px 14px -2px rgba(0, 0, 0, 0.05)',
      },
      animation: {
        'fade-in':        'fadeIn 0.6s ease-out forwards',
        'fade-in-up':     'fadeInUp 0.6s ease-out forwards',
        'fade-in-down':   'fadeInDown 0.5s ease-out forwards',
        'slide-in-right': 'slideInRight 0.3s ease-out forwards',
        'slide-in-left':  'slideInLeft  0.3s ease-out forwards',
        'float':          'float 6s ease-in-out infinite',
        'pulse-soft':     'pulseSoft 3s ease-in-out infinite',
        'shimmer':        'shimmer 2.5s ease-in-out infinite',
        'gradient-shift': 'gradientShift 8s ease-in-out infinite',
      },
      keyframes: {
        fadeIn: {
          '0%':   { opacity: '0' },
          '100%': { opacity: '1' },
        },
        fadeInUp: {
          '0%':   { opacity: '0', transform: 'translateY(24px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        fadeInDown: {
          '0%':   { opacity: '0', transform: 'translateY(-16px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        slideInRight: {
          '0%':   { transform: 'translateX(100%)' },
          '100%': { transform: 'translateX(0)' },
        },
        slideInLeft: {
          '0%':   { transform: 'translateX(-100%)' },
          '100%': { transform: 'translateX(0)' },
        },
        float: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%':      { transform: 'translateY(-12px)' },
        },
        pulseSoft: {
          '0%, 100%': { opacity: '1' },
          '50%':      { opacity: '0.7' },
        },
        shimmer: {
          '0%':   { backgroundPosition: '-200% 0' },
          '100%': { backgroundPosition: '200% 0' },
        },
        gradientShift: {
          '0%, 100%': { backgroundPosition: '0% 50%' },
          '50%':      { backgroundPosition: '100% 50%' },
        },
      },
      backgroundImage: {
        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
        'hero-pattern': 'linear-gradient(135deg, var(--primary) 0%, var(--secondary) 50%, var(--accent) 100%)',
      },
      spacing: {
        '18': '4.5rem',
        '22': '5.5rem',
        '30': '7.5rem',
      },
    },
  },
  plugins: [],
};
export default config;
