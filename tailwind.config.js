/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.{html,js,th.html}",
    "./resources/views/**/*.{html,th.html}",
    "./public/**/*.html"
  ],
  theme: {
    extend: {
      colors: {
        'treehouse': {
          50: '#f0f9f3',
          100: '#dcf2e3',
          200: '#bce5ca',
          300: '#8dd1a7',
          400: '#57b67c',
          500: '#349c5c',
          600: '#277d47',
          700: '#20623a',
          800: '#1d4e31',
          900: '#194129',
          950: '#0c2315',
        }
      },
      fontFamily: {
        'sans': ['-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif']
      }
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
}