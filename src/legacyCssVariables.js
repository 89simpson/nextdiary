/*
 * The Vue 3 component library (nextcloud-vue 9) targets Nextcloud 31+ and
 * styles its components with CSS variables of current servers. Nextcloud 30,
 * the oldest supported server, does not define `--color-text-error` and
 * `--color-text-success` yet (Nextcloud 31 neither; they come with a later
 * release): without the fallbacks the error/success helper texts of select
 * fields would lose their color there.
 *
 * The other entries were needed by Nextcloud 25-29 (most variables were added
 * in Nextcloud 30, the `--color-*-text` ones in Nextcloud 28) and are kept as
 * a harmless safety net: only the variables the server does not provide are
 * defined here, with the Nextcloud 30 default values. Where the server
 * already defines a variable, nothing is changed.
 */
const FALLBACKS = {
	'--border-radius-small': '4px',
	'--border-radius-element': '8px',
	'--border-radius-container': '12px',
	'--border-radius-container-large': '16px',
	'--clickable-area-small': '24px',
	'--clickable-area-large': '48px',
	'--font-size-small': '13px',
	'--border-width-input': '1px',
	'--border-width-input-focused': '2px',
	'--color-error-text': 'var(--color-error)',
	'--color-warning-text': 'var(--color-warning)',
	'--color-success-text': 'var(--color-success)',
	'--color-text-error': 'var(--color-error-text)',
	'--color-text-success': 'var(--color-success-text)',
}

/**
 * Define the missing CSS variables on <body>.
 *
 * The theme variables live on <body> ([data-theme-*]) or :root; setting the
 * fallbacks on <body> lets `var(--color-error)` & co. resolve there too.
 */
export function applyLegacyCssVariables() {
	const element = document.body || document.documentElement
	const computed = window.getComputedStyle(element)
	for (const [name, value] of Object.entries(FALLBACKS)) {
		if (computed.getPropertyValue(name).trim() === '') {
			element.style.setProperty(name, value)
		}
	}
}
