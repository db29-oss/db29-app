import defaultTheme from 'tailwindcss/defaultTheme';
const plugin = require('tailwindcss/plugin');

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [
      plugin(function ({ addBase }) {
        addBase({
          '*': { fontSize: '1.125rem' }, // Matches `text-lg` (18px by default)
        });
      }),
    ],
    corePlugins: {
      preflight: false, // Disable the CSS reset
    },
};
