/*
 * URLs of the NextDiary API.
 *
 * Route parameters (deep links) end up in API paths. They are validated and
 * URL-encoded here, so a crafted value like `..\..\remote.php\dav\…` can never
 * point a request (with the CSRF token) at another endpoint of the server:
 * ids must be safe non-negative integers, dates must be YYYY-MM-DD (digits
 * only), and every placeholder value is encoded with encodeURIComponent (by
 * generateUrl).
 */
import { generateUrl } from '@nextcloud/router'

const ID_PATTERN = /^\d+$/
// Any decimal digits: with some user locales (Arabic, Persian, Hindi, ...)
// moment formats dates with that script's digits, and such dates are in use
const DATE_PATTERN = /^\p{Nd}{4}-\p{Nd}{2}-\p{Nd}{2}$/u

/**
 * @param {*} value id from a route parameter or an API response
 * @return {boolean} whether the value is a safe non-negative integer (or its decimal string)
 */
export function isValidId(value) {
	if (typeof value === 'number') {
		return Number.isSafeInteger(value) && value >= 0
	}
	return typeof value === 'string'
		&& ID_PATTERN.test(value)
		&& Number.isSafeInteger(Number.parseInt(value, 10))
}

/**
 * @param {*} value date from a route parameter
 * @return {boolean} whether the value is a YYYY-MM-DD string (digits of any script)
 */
export function isValidDate(value) {
	return typeof value === 'string' && DATE_PATTERN.test(value)
}

/**
 * @param {*} value id from a route parameter or an API response
 * @return {number} the id as a number
 * @throws {TypeError} if the value is not a valid id
 */
export function validId(value) {
	if (!isValidId(value)) {
		throw new TypeError('[NextDiary] Invalid id: ' + String(value))
	}
	return Number.parseInt(value, 10)
}

/**
 * @param {*} value date from a route parameter
 * @return {string} the date (YYYY-MM-DD)
 * @throws {TypeError} if the value is not a valid date
 */
export function validDate(value) {
	if (!isValidDate(value)) {
		throw new TypeError('[NextDiary] Invalid date: ' + String(value))
	}
	return value
}

/**
 * Build the URL of an app API endpoint.
 *
 * @param {string} path path below `/apps/nextdiary/api`, e.g. `/entry/{id}`
 * @param {{[key: string]: string|number}} [params] values of the `{placeholders}`, URL-encoded
 * @return {string}
 * @throws {TypeError} if a value is empty or a dot segment (encodeURIComponent keeps dots)
 */
export function apiUrl(path, params = {}) {
	for (const value of Object.values(params)) {
		const text = String(value)
		if (text === '' || text === '.' || text === '..') {
			throw new TypeError('[NextDiary] Invalid URL parameter: ' + text)
		}
	}
	return generateUrl('/apps/nextdiary/api' + path, params)
}
