/**
 * Security helpers for safely rendering untrusted, request-derived values
 * (tracked 404 source URLs, referrers etc.) in the control panel.
 */

/**
 * Returns the given URL if it is safe to use as a link target, or null if it
 * carries a potentially dangerous scheme (e.g. "javascript:" or "data:").
 *
 * Relative references (paths, query strings, fragments), protocol-relative URLs
 * and absolute http(s) URLs are considered safe. Anything else is rejected.
 *
 * @param {*} url
 * @returns {string|null}
 */
export function sanitizeUrl(url) {
    if (url === null || url === undefined) {
        return null;
    }

    const value = String(url).trim();

    if (value === '') {
        return null;
    }

    // Relative references and protocol-relative URLs are safe
    if (/^(\/|#|\?)/.test(value)) {
        return value;
    }

    // Absolute URLs: only allow http(s)
    if (/^https?:\/\//i.test(value)) {
        return value;
    }

    // Any other explicit scheme (javascript:, data:, vbscript:, …) is unsafe
    if (/^[a-z][a-z0-9+.-]*:/i.test(value)) {
        return null;
    }

    // No scheme => treat as a relative reference
    return value;
}

/**
 * Escapes a value for safe interpolation into an HTML string, in both element
 * text and double-quoted attribute contexts.
 *
 * @param {*} value
 * @returns {string}
 */
export function escapeHtml(value) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    };

    return String(value ?? '').replace(/[&<>"']/g, (char) => map[char]);
}
