import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{ts,vue}',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter var', 'system-ui', 'sans-serif'],
            },
            colors: {
                // Sierra Leone flag: green – white – blue
                sl: {
                    green: {
                        50:  '#e8fbee',
                        100: '#c5f5d4',
                        200: '#8eeaaa',
                        300: '#4fdb7a',
                        400: '#26cd56',
                        500: '#1EB53A', // flag green
                        600: '#179530',
                        700: '#127526',
                        800: '#0e5a1d',
                        900: '#094a17',
                        950: '#042e0d',
                    },
                    blue: {
                        50:  '#e6f2fb',
                        100: '#bfe0f5',
                        200: '#80c1ea',
                        300: '#409fdf',
                        400: '#1a87d4',
                        500: '#0072C6', // flag blue
                        600: '#005da2',
                        700: '#00497f',
                        800: '#00365d',
                        900: '#002440',
                        950: '#001526',
                    },
                },
            },
        },
    },
    plugins: [forms, typography],
};
