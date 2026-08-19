import assert from 'node:assert/strict';
import {afterEach, test} from 'node:test';
import {syncBrowserTimezone} from '../../Resources/public/timezone.js';

const originalFetch = globalThis.fetch;
const originalIntl = globalThis.Intl;
const originalWindow = globalThis.window;

afterEach(() => {
    globalThis.fetch = originalFetch;
    globalThis.Intl = originalIntl;
    globalThis.window = originalWindow;
});

function installBrowser(timezone) {
    globalThis.Intl = {DateTimeFormat: () => ({resolvedOptions: () => ({timeZone: timezone})})};
    const events = [];
    globalThis.window = {dispatchEvent: (event) => events.push(event)};
    globalThis.CustomEvent = class CustomEvent {
        constructor(type, options) {
            this.type = type;
            this.detail = options.detail;
        }
    };
    return events;
}

test('missing and unchanged browser zones avoid fetch', async () => {
    let calls = 0;
    globalThis.fetch = async () => { ++calls; };

    installBrowser(undefined);
    assert.equal(await syncBrowserTimezone({endpoint: '/timezone'}), false);
    installBrowser('Europe/Berlin');
    assert.equal(await syncBrowserTimezone({endpoint: '/timezone', storedBrowserTimezone: 'Europe/Berlin'}), false);
    assert.equal(calls, 0);
});

test('successful sync sends the exact post and dispatches the event', async () => {
    const events = installBrowser('Europe/Berlin');
    const calls = [];
    globalThis.fetch = async (...args) => {
        calls.push(args);
        return {ok: true};
    };

    assert.equal(await syncBrowserTimezone({endpoint: '/_lunetics/timezone/browser', csrfToken: 'csrf-value'}), true);
    assert.deepEqual(calls, [[
        '/_lunetics/timezone/browser',
        {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': 'csrf-value'},
            body: '{"timezone":"Europe/Berlin"}',
            credentials: 'same-origin',
        },
    ]]);
    assert.equal(events.length, 1);
    assert.equal(events[0].type, 'lunetics:timezone-synced');
    assert.deepEqual(events[0].detail, {timezone: 'Europe/Berlin'});
});

test('custom CSRF header is used when configured', async () => {
    installBrowser('Europe/Berlin');
    let options;
    globalThis.fetch = async (_endpoint, requestOptions) => {
        options = requestOptions;
        return {ok: true};
    };

    assert.equal(await syncBrowserTimezone({endpoint: '/timezone', csrfToken: 'csrf-value', csrfHeader: 'X-Timezone-CSRF'}), true);
    assert.deepEqual(options.headers, {'Content-Type': 'application/json', 'X-Timezone-CSRF': 'csrf-value'});
});

test('non-success response returns false without an event', async () => {
    const events = installBrowser('Europe/Berlin');
    globalThis.fetch = async () => ({ok: false});

    assert.equal(await syncBrowserTimezone({endpoint: '/timezone'}), false);
    assert.deepEqual(events, []);
});

test('fetch rejection returns false without an event', async () => {
    const events = installBrowser('Europe/Berlin');
    globalThis.fetch = async () => { throw new Error('offline'); };

    assert.equal(await syncBrowserTimezone({endpoint: '/timezone'}), false);
    assert.deepEqual(events, []);
});
