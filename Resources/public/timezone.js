export function syncBrowserTimezone({endpoint, csrfToken, csrfHeader = 'X-CSRF-Token', storedBrowserTimezone}) {
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    if (!timezone || timezone === storedBrowserTimezone) {
        return Promise.resolve(false);
    }

    const headers = {'Content-Type': 'application/json'};
    if (csrfToken) {
        headers[csrfHeader] = csrfToken;
    }

    return fetch(endpoint, {
        method: 'POST',
        headers,
        body: JSON.stringify({timezone}),
        credentials: 'same-origin',
    }).then((response) => {
        if (!response.ok) {
            return false;
        }
        window.dispatchEvent(new CustomEvent('lunetics:timezone-synced', {detail: {timezone}}));
        return true;
    }).catch(() => false);
}
