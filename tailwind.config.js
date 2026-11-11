/** @type {import('tailwindcss').Config} */
export default {
  content: ['./resources/views/**/*.blade.php', './public/js/**/*.js', './app/Support/**/*.php', './app/Enums/**/*.php'],
  theme: {
    extend: {
      colors: {
        laut: { 50: '#EEF6F8', 100: '#D5E8EE', 200: '#A9D0DC', 300: '#74B1C4', 400: '#3F8EA8', 500: '#1C6F8C', 600: '#0E4D64', 700: '#0B3D50', 800: '#082E3D', 900: '#05202B' },
        lamun: { 50: '#EEF7F2', 100: '#D3EBDD', 500: '#3E9670', 600: '#2F7D5B', 700: '#24624A' },
        karang: { 50: '#FEF1F0', 100: '#FDDCD8', 500: '#D92D20', 600: '#B42318', 700: '#912018' },
        surya: { 50: '#FDF6E7', 100: '#FAE8BF', 500: '#D69E2E', 600: '#B7791F', 700: '#975A16' },
        kabut: { 50: '#F7F9FA', 100: '#F3F6F7', 200: '#E3E9EC', 300: '#CBD5DA', 400: '#9AA9B1', 500: '#6B7D87', 600: '#4D5E68' },
        tinta: '#13232B',
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'Roboto', 'sans-serif'],
      },
      fontSize: {
        '2xs': ['0.6875rem', { lineHeight: '1rem' }],
      },
      maxWidth: { prose: '68ch' },
    },
  },
  plugins: [],
};
