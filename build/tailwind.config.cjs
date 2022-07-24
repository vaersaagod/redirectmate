/** @type {import('tailwindcss').Config} */

const plugin = require('tailwindcss/plugin');

// Helper function to generate numbers/classes
const generate = (start, stop, step = 1, unit = '', addUnitToKey = true) => Array.from({ length: (stop - start) / step + 1 }, (_, i) => parseFloat((start + (i * step)).toFixed(4)))
    .reduce((carry, value) => {
        carry[(unit && addUnitToKey ? `${value}${unit}` : `${value}`)] = (unit ? `${value}${unit}` : value);
        return carry;
    }, {});


module.exports = {
    content: [
        "../src/templates/**/*.{twig,html}",
        "./src/**/*.{vue,js}",
    ],
    theme: {
        /**
         * ========================
         * Screens (breakpoints)
         * https://tailwindcss.com/docs/screens
         * ========================
         */
        screens: {
            s: '440px',
            sp: '600px',
            m: '750px',
            mp: '980px',
            l: '1200px',
            lp: '1420px',
            xl: '1800px',
            pt: {
                raw: '(orientation: portrait)'
            },
            ls: {
                raw: '(orientation: landscape)'
            }
        },

        /**
         * ========================
         * Colors
         * https://tailwindcss.com/docs/customizing-colors
         * ========================
         */

        /**
         * ========================
         * Font families
         * https://tailwindcss.com/docs/font-family
         * ========================
         */

        /*
        fontFamily: {

        },
        */

        /**
         * ========================
         * Font sizes
         * https://tailwindcss.com/docs/font-size
         * ========================
         */
        fontSize: {
            base: '16px',
            ...generate(10, 150, 1, 'px', false)
        },

        /**
         * ========================
         * Line height
         * https://tailwindcss.com/docs/line-height
         * ========================
         */
        lineHeight: {
            none: '1',
            base: (22 / 16),
            1: '1',
            '1_1': '1.1',
            '1_2': '1.2',
            '1_3': '1.3',
            '1_4': '1.4',
            '1_5': '1.5',
            '1_6': '1.6',
            ...generate(10, 150, 1, 'px', false)
        },

        /**
         * ========================
         * Font weight
         * https://tailwindcss.com/docs/font-weight
         * ========================
         */
        fontWeight: {
            hairline: '100',
            thin: '200',
            light: '300',
            normal: '400',
            medium: '500',
            semibold: '600',
            bold: '700',
            extrabold: '800',
            black: '900'
        },

        /**
         * ========================
         * Spacing
         * https://tailwindcss.com/docs/customizing-spacing
         * ========================
         */
        spacing: {
            px: '1px',
            0: 0,
            ...generate(1, 300, 1, 'px', false),
            ...generate(1, 100, 1, '%', true)
        },

        /**
         * ========================
         * Width
         * https://tailwindcss.com/docs/width
         * ========================
         */
        width: {
            auto: 'auto',
            inherit: 'inherit',
            full: '100%',
            screen: '100vw',
            px: '1px',
            0: '0px',
            min: 'min-content',
            max: 'max-content',
            fit: 'fit-content',
            '1/2': `${100 * (1 / 2)}%`, // 50%
            '1/3': `${100 * (1 / 3)}%`, // 33.33333333%
            '2/3': `${100 * (2 / 3)}%`, // 66.66666667%
            '1/4': `${100 * (1 / 4)}%`, // 25%
            '2/4': `${100 * (2 / 4)}%`, // 50%
            '3/4': `${100 * (3 / 4)}%`, // 75%
            '1/5': `${100 * (1 / 5)}%`, // 20%
            '2/5': `${100 * (2 / 5)}%`, // 40%
            '3/5': `${100 * (3 / 5)}%`, // 60%
            '4/5': `${100 * (4 / 5)}%`, // 80%
            '1/6': `${100 * (1 / 6)}%`, // 16.66666667%
            '2/6': `${100 * (2 / 6)}%`, // 33.33333333%
            '3/6': `${100 * (3 / 6)}%`, // 50%,
            '4/6': `${100 * (4 / 6)}%`, // 66.66666667%
            '5/6': `${100 * (5 / 6)}%`, // 83.33333333%
            '1/12': `${100 * (1 / 12)}%`,
            '2/12': `${100 * (2 / 12)}%`,
            '3/12': `${100 * (3 / 12)}%`,
            '4/12': `${100 * (4 / 12)}%`,
            '5/12': `${100 * (5 / 12)}%`,
            '6/12': `${100 * (6 / 12)}%`,
            '7/12': `${100 * (7 / 12)}%`,
            '8/12': `${100 * (8 / 12)}%`,
            '9/12': `${100 * (9 / 12)}%`,
            '10/12': `${100 * (10 / 12)}%`,
            '11/12': `${100 * (11 / 12)}%`,
            ...generate(1, 100, 1, '%', false), // 1 - 100%
            ...generate(1, 300, 1, 'px', true), // 1 - 100px
            ...generate(1, 100, 1, 'vw', true), // 1 - 100vw
            ...generate(1, 100, 1, 'vh', true), // 1 - 100vh
            ...generate(1, 100, 1, 'vmin', true), // 1 - 100vmin
            ...generate(1, 100, 1, 'vmax', true) // 1 - 100vmax
        },

        /**
         * ========================
         * Min-width
         * https://tailwindcss.com/docs/min-width
         * ========================
         */
        minWidth: {
            full: '100%',
            screen: '100vw',
            inherit: 'inherit',
            0: 0,
            100: '100%',
            '100vw': '100vw'
        },

        /**
         * ========================
         * Max-width
         * https://tailwindcss.com/docs/max-width
         * ========================
         */
        maxWidth: (theme, { breakpoints }) => ({
            none: 'none',
            full: '100%',
            screen: '100vw',
            inherit: 'inherit',
            0: 0,
            100: '100%',
            ...breakpoints(theme('screens'))
        }),

        /**
         * ========================
         * Height
         * https://tailwindcss.com/docs/height
         * ========================
         */
        height: {
            auto: 'auto',
            inherit: 'inherit',
            full: '100%',
            screen: '100vh',
            px: '1px',
            0: '0px',
            min: 'min-content',
            max: 'max-content',
            fit: 'fit-content',
            '1/2': `${100 * (1 / 2)}%`, // 50%
            '1/3': `${100 * (1 / 3)}%`, // 33.33333333%
            '2/3': `${100 * (2 / 3)}%`, // 66.66666667%
            '1/4': `${100 * (1 / 4)}%`, // 25%
            '2/4': `${100 * (2 / 4)}%`, // 50%
            '3/4': `${100 * (3 / 4)}%`, // 75%
            '1/5': `${100 * (1 / 5)}%`, // 20%
            '2/5': `${100 * (2 / 5)}%`, // 40%
            '3/5': `${100 * (3 / 5)}%`, // 60%
            '4/5': `${100 * (4 / 5)}%`, // 80%
            '1/6': `${100 * (1 / 6)}%`, // 16.66666667%
            '2/6': `${100 * (2 / 6)}%`, // 33.33333333%
            '3/6': `${100 * (3 / 6)}%`, // 50%,
            '4/6': `${100 * (4 / 6)}%`, // 66.66666667%
            '5/6': `${100 * (5 / 6)}%`, // 83.33333333%
            ...generate(1, 100, 1, '%', false), // 1 - 100%
            ...generate(1, 200, 1, 'px', true), // 1 - 100px
            ...generate(1, 100, 1, 'vw', true), // 1 - 100vw
            ...generate(1, 100, 1, 'vh', true), // 1 - 100vh
            ...generate(1, 100, 1, 'vmin', true), // 1 - 100vmin
            ...generate(1, 100, 1, 'vmax', true) // 1 - 100vmax
        },

        /**
         * ========================
         * Min-height
         * https://tailwindcss.com/docs/min-height
         * ========================
         */
        minHeight: {
            full: '100%',
            screen: '100vh',
            inherit: 'inherit',
            0: 0,
            100: '100%',
            '100vh': '100vh'
        },

        /**
         * ========================
         * Max-height
         * https://tailwindcss.com/docs/max-height
         * ========================
         */
        maxHeight: {
            none: 'none',
            full: '100%',
            screen: '100vh',
            inherit: 'inherit',
            0: 0,
            100: '100%',
            '100vh': '100vh'
        },

        /**
         * ========================
         * Top / Right / Bottom / Left
         * https://tailwindcss.com/docs/top-right-bottom-left
         * ========================
         */
        inset: {
            0: '0',
            auto: 'auto',
            px: '1px',
            50: '50%',
            100: '100%',

            ...generate(1, 100, 1, 'px', true), // 1 - 100px
        },

        /**
         * ========================
         * Border-width
         * https://tailwindcss.com/docs/border-width
         * ========================
         */
        borderWidth: {
            DEFAULT: '1px',
            px: '1px',
            ...generate(0, 10, 1, 'px', false) // 0px - 10px
        },

        /**
         * ========================
         * Border-radius
         * https://tailwindcss.com/docs/border-radius
         * ========================
         */
        borderRadius: {
            none: '0px',
            100: '100%',
            ...generate(0, 100, 1, 'px', true) // 0px - 100px
        },

        /**
         * ========================
         * Opacity
         * https://tailwindcss.com/docs/opacity
         * ========================
         */
        opacity: {
            ...Object.keys(generate(0, 100))
                .reduce((carry, value) => {
                    carry[`${value}`] = (value / 100).toFixed(2);
                    return carry;
                }, {}) // 0 - 100
        },

        /**
         * ========================
         * Order (Flexbox)
         * https://tailwindcss.com/docs/order
         * ========================
         */
        order: {
            first: '-9999',
            last: '9999',
            none: '0',
            ...generate(0, 10)
        },

        /**
         * ========================
         * Z-index
         * https://tailwindcss.com/docs/z-index
         * ========================
         */
        zIndex: {
            auto: 'auto',
            0: '0',
            '-1': '-1',
            top: '9999',
            ...generate(1, 10), // 1-10
            ...generate(20, 100, 10), // 20-100
            ...generate(200, 1000, 100), // 200 - 1000
            ...generate(2000, 9000, 1000) // 2000 - 9000
        },

        /**
         * ========================
         * Fill
         * https://tailwindcss.com/docs/fill
         * ========================
         */
        fill: theme => theme('colors'),

        /**
         * ========================
         * Stroke
         * https://tailwindcss.com/docs/stroke
         * ========================
         */
        stroke: theme => theme('colors'),

        /**
         * ========================
         * Transition-duration
         * https://tailwindcss.com/docs/transition-duration
         * ========================
         */
        transitionDuration: {
            ...generate(50, 1000, 50, 'ms', false)
        },

        /**
         * ========================
         * Extensions
         * Use this when you want to add to/extend Tailwind's default utilities, rather than overriding them (as we do with colors, fonts and spacing)
         * https://tailwindcss.com/docs/theme#extending-the-default-theme
         * ========================
         */
        extend: {
            colors: {
                link: 'var(--link-color)',
                grey: {
                    '100': 'rgb(63, 77, 90)'
                },
            },

            // Custom easing
            transitionTimingFunction: {
                'in-cubic': 'cubic-bezier(0.550, 0.055, 0.675, 0.190)',
                'out-cubic': 'cubic-bezier(0.215, 0.610, 0.355, 1.000)',
                'in-out-cubic': 'cubic-bezier(0.645, 0.045, 0.355, 1.000)',
                'in-circ': 'cubic-bezier(0.600, 0.040, 0.980, 0.335)',
                'out-circ': 'cubic-bezier(0.075, 0.820, 0.165, 1.000)',
                'in-out-circ': 'cubic-bezier(0.785, 0.135, 0.150, 0.860)',
                'in-expo': 'cubic-bezier(0.950, 0.050, 0.795, 0.035)',
                'out-expo': 'cubic-bezier(0.190, 1.000, 0.220, 1.000)',
                'in-out-expo': 'cubic-bezier(1.000, 0.000, 0.000, 1.000)',
                'in-quad': 'cubic-bezier(0.550, 0.085, 0.680, 0.530)',
                'out-quad': 'cubic-bezier(0.250, 0.460, 0.450, 0.940)',
                'in-out-quad': 'cubic-bezier(0.455, 0.030, 0.515, 0.955)',
                'in-quart': 'cubic-bezier(0.895, 0.030, 0.685, 0.220)',
                'out-quart': 'cubic-bezier(0.165, 0.840, 0.440, 1.000)',
                'in-out-quart': 'cubic-bezier(0.770, 0.000, 0.175, 1.000)',
                'in-quint': 'cubic-bezier(0.755, 0.050, 0.855, 0.060)',
                'out-quint': 'cubic-bezier(0.230, 1.000, 0.320, 1.000)',
                'in-out-quint': 'cubic-bezier(0.860, 0.000, 0.070, 1.000)',
                'in-sine': 'cubic-bezier(0.470, 0.000, 0.745, 0.715)',
                'out-sine': 'cubic-bezier(0.390, 0.575, 0.565, 1.000)',
                'in-out-sine': 'cubic-bezier(0.445, 0.050, 0.550, 0.950)',
                'in-back': 'cubic-bezier(0.600, -0.280, 0.735, 0.045)',
                'out-back': 'cubic-bezier(0.175, 0.885, 0.320, 1.275)',
                'in-out-back': 'cubic-bezier(0.680, -0.550, 0.265, 1.550)'
            }
        }
    },
    plugins: [
        /**
         * Other utilities/helpers (this is the stuff that used to live in our helpers.scss, and later utilities.scss – it's better to have them here, because then they're auto-completed 😎)
         */
        plugin(({ addUtilities }) => {
            addUtilities({
                '.gpu': {
                    backfaceVisibility: 'hidden',
                    transforms: 'translate3d(0, 0, 0)'
                },
                '.anti': {
                    '-webkit-font-smoothing': 'antialiased',
                    '-moz-osx-font-smoothing': 'grayscale'
                },
                '.round': {
                    borderRadius: '100%'
                },
                '.full': {
                    width: '100%',
                    height: '100%',
                    top: '0',
                    left: '0'
                },
                '.center': {
                    left: '50%',
                    top: '50%',
                    transform: 'translate(-50%, -50%)'
                },
                '.center-x': {
                    left: '50%',
                    transform: 'translateX(-50%)'
                },
                '.center-y': {
                    top: '50%',
                    transform: 'translateY(-50%)'
                },
                '.scrollable': {
                    overflow: 'hidden',
                    overflowY: 'auto',
                    '-ms-overflow-style': '-ms-autohiding-scrollbar',
                    '-webkit-overflow-scrolling': 'touch'
                },
                '.scrollable-x': {
                    overflow: 'hidden',
                    overflowX: 'auto',
                    '-ms-overflow-style': '-ms-autohiding-scrollbar',
                    '-webkit-overflow-scrolling': 'touch'
                },
                '.scrollbar-hidden': {
                    '-ms-overflow-style': 'none', /* IE and Edge */
                    'scrollbar-width': 'none' /* Firefox */
                },
                '.scrollbar-hidden::-webkit-scrollbar': {
                    display: 'none'
                },
                '.clear::before': {
                    content: "''",
                    display: 'table'
                },
                '.clear::after': {
                    content: "''",
                    display: 'table',
                    clear: 'both'
                }
            });
        }),
    ],
    corePlugins: {
        preflight: false,
    }
}
