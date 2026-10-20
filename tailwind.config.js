import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/**/*.php',
    ],

    theme: {
        screens: {
            'xs': '420px',
            ...defaultTheme.screens,
        },
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            spacing: {
                '4.5': '1.125rem', // 18px
                '5.5': '1.375rem', // 22px
                '6.5': '1.625rem', // 26px
                '7.5': '1.875rem', // 30px
                '8.5': '2.125rem', // 34px
                '9.5': '2.375rem', // 38px
            },
            colors: {
                gray: {
                    750: '#283548',
                    850: '#161f30',
                },
                atoma: {
                    ice: '#ECFCFF',       // rgb(236, 252, 255)
                    aqua: '#B2FCFF',      // rgb(178, 252, 255)
                    cyan: '#5EDFFF',      // rgb(94, 223, 255)
                    blue: '#3E64FF',      // rgb(62, 100, 255) - Primary Brand
                    primary: '#3E64FF',   // Alias for primary
                    dark: '#0b1329',      // Deep dark background complement
                    card: '#121d36',      // Dark card surface complement
                },
            },
            zIndex: {
                '60': '60',
                '70': '70',
                '80': '80',
                '90': '90',
                '100': '100',
            },
        },
    },

    plugins: [forms],
};
