import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Nunito', ...defaultTheme.fontFamily.sans],
                display: ['"Baloo 2"', 'Nunito', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                ink: { DEFAULT: '#1c2541', soft: '#4a5580' },
                mint: { DEFAULT: '#dff3ea', deep: '#cbeadb' },
                sun: '#ffd23f',
                tang: '#ff8a3d',
                berry: '#e8547a',
                leaf: '#3fae73',
                danger: '#ff5a5f',
            },
        },
    },

    plugins: [forms],
};
