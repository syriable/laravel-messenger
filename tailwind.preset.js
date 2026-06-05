/**
 * Tailwind preset for Laravel Messenger.
 *
 * Optional: include this in the host app's tailwind.config.js to expose the
 * messenger design tokens as Tailwind utilities (e.g. `text-msgr-accent`,
 * `bg-msgr-surface`). The shipped messenger.css already styles the UI via the
 * same `--msgr-*` custom properties, so this preset is only needed if you want
 * to compose the tokens with your own Tailwind classes.
 *
 *   // tailwind.config.js
 *   module.exports = {
 *     presets: [require('./vendor/syriable/laravel-messenger/tailwind.preset.js')],
 *   };
 */
module.exports = {
    theme: {
        extend: {
            colors: {
                'msgr-accent': 'var(--msgr-accent)',
                'msgr-link': 'var(--msgr-link)',
                'msgr-surface': 'var(--msgr-surface)',
                'msgr-surface-muted': 'var(--msgr-surface-muted)',
                'msgr-surface-hover': 'var(--msgr-surface-hover)',
                'msgr-surface-active': 'var(--msgr-surface-active)',
                'msgr-text': 'var(--msgr-text)',
                'msgr-text-muted': 'var(--msgr-text-muted)',
                'msgr-border': 'var(--msgr-border)',
                'msgr-unread': 'var(--msgr-unread)',
                'msgr-online': 'var(--msgr-online)',
                'msgr-away': 'var(--msgr-away)',
            },
            borderRadius: {
                msgr: 'var(--msgr-radius)',
                'msgr-lg': 'var(--msgr-radius-lg)',
            },
            fontFamily: {
                msgr: 'var(--msgr-font)',
            },
        },
    },
};
