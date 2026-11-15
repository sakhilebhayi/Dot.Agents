import dotPreset from './dot-ui/tailwind/tailwind-preset.cjs';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    presets: [dotPreset],
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            colors: {
                // Brand palette
                brand: {
                    yellow: '#f5be1c',
                    'yellow-light': '#fde989',
                    'yellow-pale': '#fef9e7',
                    'yellow-dark': '#c49614',
                    purple: '#3d2ea0',
                    'purple-mid': '#5b48c8',
                    'purple-light': '#7c6de0',
                    'purple-pale': '#ece9f8',
                    'purple-dark': '#2d2178',
                    'purple-deeper': '#1e1660',
                },
                // Semantic surface colors
                surface: {
                    DEFAULT: '#ffffff',
                    subtle: '#f9f9f7',
                    muted:  '#f3f3ef',
                    border: '#e8e8e2',
                    'border-strong': '#d0d0c8',
                },
                // DA status palette
                status: {
                    active:   '#16a34a',
                    inactive: '#6b7280',
                    paused:   '#d97706',
                    failed:   '#dc2626',
                    pending:  '#7c3aed',
                },
            },
            spacing: {
                '18': '4.5rem',
                '68': '17rem',
                '76': '19rem',
                '88': '22rem',
            },
            borderRadius: {
                'da': '0.375rem',
            },
            boxShadow: {
                'da-sm': '0 1px 2px 0 rgb(0 0 0 / 0.05)',
                'da':    '0 1px 3px 0 rgb(0 0 0 / 0.08), 0 1px 2px -1px rgb(0 0 0 / 0.06)',
                'da-md': '0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.05)',
                'da-lg': '0 10px 15px -3px rgb(0 0 0 / 0.06), 0 4px 6px -4px rgb(0 0 0 / 0.04)',
            },
            fontSize: {
                '2xs': ['0.65rem', { lineHeight: '1rem' }],
            },
            animation: {
                'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            },
        },
    },

    plugins: [forms, typography],
};
