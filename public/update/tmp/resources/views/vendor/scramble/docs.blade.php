<!doctype html>
<html lang="en" data-theme="{{ $config->get('ui.theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="color-scheme" content="{{ $config->get('ui.theme', 'light') }}">
    <title>{{ $config->get('ui.title') ?? config('app.name') . ' - API Docs' }}</title>

    <script src="https://unpkg.com/@stoplight/elements@8.4.2/web-components.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements@8.4.2/styles.min.css">

    <script>
        const originalFetch = window.fetch;

        // intercept TryIt requests and add the XSRF-TOKEN header,
        // which is necessary for Sanctum cookie-based authentication to work correctly
        window.fetch = (url, options) => {
            const CSRF_TOKEN_COOKIE_KEY = "XSRF-TOKEN";
            const CSRF_TOKEN_HEADER_KEY = "X-XSRF-TOKEN";
            const getCookieValue = (key) => {
                const cookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith(key));
                return cookie?.split("=")[1];
            };

            const updateFetchHeaders = (
                headers,
                headerKey,
                headerValue,
            ) => {
                if (headers instanceof Headers) {
                    headers.set(headerKey, headerValue);
                } else if (Array.isArray(headers)) {
                    headers.push([headerKey, headerValue]);
                } else if (headers) {
                    headers[headerKey] = headerValue;
                }
            };
            const csrfToken = getCookieValue(CSRF_TOKEN_COOKIE_KEY);
            if (csrfToken) {
                const { headers = new Headers() } = options || {};
                updateFetchHeaders(headers, CSRF_TOKEN_HEADER_KEY, decodeURIComponent(csrfToken));
                return originalFetch(url, {
                    ...options,
                    headers,
                });
            }

            return originalFetch(url, options);
        };
    </script>

    <style>
        html, body { margin:0; height:100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background-color: var(--color-canvas); display: flex; flex-direction: column; }
        
        /* Custom Header Bar */
        .scramble-header {
            display: flex;
            align-items: center;
            justify-content: end;
            padding: 0 20px;
            height: 50px;
            background-color: #1a1a1a;
            color: #ffffff;
            border-bottom: 1px solid #333;
            z-index: 1000;
        }
        
        .scramble-header h1 {
            font-size: 16px;
            margin: 0;
            font-weight: 600;
        }

        .back-to-panel {
            text-decoration: none;
            color: #ffffff;
            background-color: #3b82f6; /* Blue-500 equivalent */
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .back-to-panel:hover {
            background-color: #2563eb; /* Blue-600 */
        }

        .back-to-panel svg {
            width: 16px;
            height: 16px;
        }

        elements-api {
            flex: 1;
            height: calc(100vh - 50px);
        }

        /* issues about the dark theme of stoplight/mosaic-code-viewer using web component:
         * https://github.com/stoplightio/elements/issues/2188#issuecomment-1485461965
         */
        [data-theme="dark"] .token.property {
            color: rgb(128, 203, 196) !important;
        }
        [data-theme="dark"] .token.operator {
            color: rgb(255, 123, 114) !important;
        }
        [data-theme="dark"] .token.number {
            color: rgb(247, 140, 108) !important;
        }
        [data-theme="dark"] .token.string {
            color: rgb(165, 214, 255) !important;
        }
        [data-theme="dark"] .token.boolean {
            color: rgb(121, 192, 255) !important;
        }
        [data-theme="dark"] .token.punctuation {
            color: #dbdbdb !important;
        }
        
        /* Ensure responsive layout on small screens */
        @media (max-width: 640px) {
            .scramble-header {
                padding: 0 12px;
            }
            .scramble-header h1 {
                font-size: 14px;
            }
            .back-to-panel span {
                display: none;
            }
        }
    </style>
</head>
<body style="height: 100vh; overflow-y: hidden">

<elements-api
    id="docs"
    tryItCredentialsPolicy="{{ $config->get('ui.try_it_credentials_policy', 'include') }}"
    router="hash"
    @if($config->get('ui.hide_try_it')) hideTryIt="true" @endif
    @if($config->get('ui.hide_schemas')) hideSchemas="true" @endif
    @if($config->get('ui.logo')) logo="{{ $config->get('ui.logo') }}" @endif
    @if($config->get('ui.layout')) layout="{{ $config->get('ui.layout') }}" @endif
/>
<script>
    (async () => {
        const docs = document.getElementById('docs');
        docs.apiDescriptionDocument = @json($spec);
    })();
</script>

@if($config->get('ui.theme', 'light') === 'system')
    <script>
        var mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

        function updateTheme(e) {
            if (e.matches) {
                window.document.documentElement.setAttribute('data-theme', 'dark');
                window.document.getElementsByName('color-scheme')[0].setAttribute('content', 'dark');
            } else {
                window.document.documentElement.setAttribute('data-theme', 'light');
                window.document.getElementsByName('color-scheme')[0].setAttribute('content', 'light');
            }
        }

        mediaQuery.addEventListener('change', updateTheme);
        updateTheme(mediaQuery);
    </script>
@endif

{{-- Drift — standalone tracker widget, styling not tied to any host theme --}}
<style>
    #drift-pill, #drift-panel {
        --drift-font:      -apple-system, BlinkMacSystemFont, 'Segoe UI', Inter, sans-serif;
        --drift-font-code: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace;

        --drift-bg:     #171923;
        --drift-bg-2:   #252a37;
        --drift-bg-3:   #343b4c;
        --drift-border: rgba(255, 255, 255, 0.1);

        --drift-text-1: #e7e8f2;
        --drift-text-2: #b5b5bd;
        --drift-text-3: #8e8e97;

        --drift-accent:      #000000ff;
        --drift-accent-text: #ffffffff;

        --drift-red:      #93c5fd80;
        --drift-red-text: #93c5fd80;
        --drift-orange:   #ffcb8b;
        --drift-green:    #c3e88d;
        --drift-blue:     #82aaff;
        --drift-purple:   #b191f9;
    }

    #drift-pill {
        position: fixed; bottom: 24px; right: 24px;
        background: var(--drift-accent);
        color: var(--drift-accent-text);
        font-family: var(--drift-font);
        font-size: 12px; font-weight: 600;
        padding: 8px 16px; border-radius: 999px; cursor: pointer;
        z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,.4);
        user-select: none; display: flex; align-items: center; gap: 8px;
        transition: transform .15s, box-shadow .15s;
    }
    #drift-pill:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,0,0,.5); }
    #drift-pill-count { opacity: .75; }
    #drift-pill-upd {
        background: rgba(0,0,0,.18); border-radius: 999px;
        padding: 1px 8px; font-size: 11px;
    }
    #drift-pill-retry {
        background: rgba(0,0,0,.18); border: none; border-radius: 999px;
        color: var(--drift-accent-text); padding: 1px 8px; font-size: 11px; font-weight: 600;
        cursor: pointer; font-family: inherit;
    }
    #drift-pill-retry:hover { background: rgba(0,0,0,.3); }

    #drift-panel {
        position: fixed; top: 0; right: 0; bottom: 0; width: min(380px, 100vw);
        background: var(--drift-bg);
        color: var(--drift-text-1);
        font-family: var(--drift-font);
        font-size: 12px;
        z-index: 9998;
        box-shadow: -1px 0 0 0 var(--drift-border), -8px 0 32px rgba(0,0,0,.4);
        display: flex; flex-direction: column;
        transform: translateX(100%);
        transition: transform .25s cubic-bezier(.4,0,.2,1);
    }
    #drift-panel.open { transform: translateX(0); }

    @media (prefers-reduced-motion: reduce) {
        #drift-panel { transition: none; }
    }

    #drift-header {
        padding: 16px;
        border-bottom: 1px solid var(--drift-border);
        display: flex; align-items: center; justify-content: space-between;
        flex-shrink: 0;
    }
    #drift-header-title { font-size: 13px; font-weight: 600; }
    #drift-header-actions { display: flex; align-items: center; gap: 4px; }
    #drift-close, #drift-refresh {
        display: flex; align-items: center; justify-content: center;
        background: none; border: none; cursor: pointer;
        width: 28px; height: 28px;
        color: var(--drift-text-3);
        font-size: 18px; line-height: 1; border-radius: 6px;
        transition: color .15s, background .15s;
    }
    #drift-refresh { font-size: 15px; }
    #drift-close:hover, #drift-refresh:hover {
        color: var(--drift-text-1);
        background: var(--drift-bg-2);
    }

    #drift-filters {
        margin: 8px 16px 12px;
        display: flex; flex-shrink: 0;
        border: 1px solid var(--drift-border);
        border-radius: 6px; overflow: hidden;
    }
    .drift-tf {
        flex: 1;
        background: none; border: none;
        border-right: 1px solid var(--drift-border);
        color: var(--drift-text-2);
        padding: 4px 8px;
        font-size: 12px; cursor: pointer;
        font-family: inherit;
        transition: color .15s, background .15s;
    }
    .drift-tf:last-child { border-right: none; }
    .drift-tf:hover {
        color: var(--drift-text-1);
        background: var(--drift-bg-2);
    }
    .drift-tf.active {
        background: var(--drift-accent);
        color: var(--drift-accent-text);
    }
    .drift-tf-count { opacity: .7; }

    #drift-list { flex: 1; overflow-y: auto; padding-bottom: 80px; }
    #drift-list::-webkit-scrollbar { width: 4px; }
    #drift-list::-webkit-scrollbar-track { background: transparent; }
    #drift-list::-webkit-scrollbar-thumb {
        background: var(--drift-bg-3);
        border-radius: 2px;
    }

    .drift-tag {
        position: sticky; top: 0; z-index: 1;
        padding: 12px 16px 4px;
        font-size: 11px; font-weight: 700;
        text-transform: uppercase; letter-spacing: .1em;
        color: var(--drift-text-3);
        margin-top: 4px;
        background: var(--drift-bg);
    }

    .drift-row {
        display: flex; flex-direction: column;
        padding: 8px 16px;
        border-bottom: 1px solid var(--drift-border);
    }
    .drift-row.updated {
        background: color-mix(in srgb, var(--drift-orange) 6%, transparent);
    }
    .drift-main { display: flex; align-items: center; gap: 8px; }

    .drift-cb {
        cursor: pointer;
        accent-color: var(--drift-accent);
        flex-shrink: 0; width: 14px; height: 14px;
    }

    .drift-cb-hit {
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; width: 44px; height: 44px; margin: -15px;
        cursor: pointer;
    }

    .drift-badge {
        font-size: 11px; font-weight: 700;
        padding: 2px 8px; border-radius: 4px;
        font-family: var(--drift-font-code);
        flex-shrink: 0; width: fit-content; text-align: center;
    }

    .drift-path {
        font-size: 12px;
        font-family: var(--drift-font-code);
        color: var(--drift-text-1);
        flex: 1; word-break: normal; overflow-wrap: anywhere;
    }
    .drift-path.done {
        text-decoration: line-through;
        color: var(--drift-text-3);
    }

    .drift-upd-badge {
        font-size: 11px; font-weight: 600;
        color: var(--drift-orange);
        background: color-mix(in srgb, var(--drift-orange) 12%, transparent);
        padding: 2px 8px; border-radius: 4px;
        flex-shrink: 0;
    }
    .drift-upd-badge.breaking {
        color: var(--drift-red-text);
        background: color-mix(in srgb, var(--drift-red) 15%, transparent);
    }

    .drift-chevron {
        background: none; border: none; cursor: pointer;
        color: var(--drift-text-3);
        font-size: 12px; line-height: 1; padding: 4px;
        flex-shrink: 0; border-radius: 4px;
        transition: color .15s, background .15s;
    }
    .drift-chevron:hover {
        color: var(--drift-text-1);
        background: var(--drift-bg-2);
    }

    .drift-diff {
        margin-top: 8px; padding: 12px;
        background: var(--drift-bg);
        border: 1px solid var(--drift-border);
        border-radius: 6px;
        font-size: 11px;
        font-family: var(--drift-font-code);
        line-height: 1.9;
    }
    .drift-diff-add { color: var(--drift-green); }
    .drift-diff-rem { color: var(--drift-red-text); }
    .drift-diff-neu { color: var(--drift-text-2); }

    .drift-bulk-ack {
        display: block; width: 100%;
        background: var(--drift-accent);
        color: var(--drift-accent-text); border: none;
        padding: 8px 16px; font-size: 12px; font-weight: 600; cursor: pointer;
        font-family: inherit;
        transition: opacity .15s;
    }
    .drift-bulk-ack:hover { opacity: .85; }

    .drift-select-all {
        display: flex; align-items: center; gap: 8px;
        width: 100%; padding: 10px 16px;
        background: var(--drift-bg-2);
        border-bottom: 1px solid var(--drift-border);
        font-size: 12px; font-weight: 600;
        cursor: pointer;
    }
    .drift-select-all-cb {
        cursor: pointer;
        accent-color: var(--drift-accent);
        flex-shrink: 0; width: 14px; height: 14px;
    }

    .drift-ack {
        margin-top: 8px;
        background: var(--drift-accent);
        color: var(--drift-accent-text); border: none; border-radius: 6px;
        padding: 4px 12px; font-size: 11px; cursor: pointer;
        font-weight: 600; font-family: inherit;
        transition: opacity .15s;
    }
    .drift-ack:hover { opacity: .85; }

    .drift-removed-banner {
        margin: 0 16px 12px; padding: 8px 12px;
        background: color-mix(in srgb, var(--drift-red) 8%, transparent);
        border: 1px solid color-mix(in srgb, var(--drift-red) 25%, transparent);
        border-radius: 6px;
    }
    .drift-removed-title {
        font-size: 11px; font-weight: 600;
        color: var(--drift-red-text);
        margin-bottom: 8px;
    }
    .drift-removed-row {
        display: flex; align-items: center; justify-content: space-between;
        gap: 8px; padding: 4px 0;
        font-size: 11px; font-family: var(--drift-font-code);
        color: var(--drift-text-2);
    }
    .drift-removed-dismiss {
        background: none; border: none; cursor: pointer;
        color: var(--drift-text-3);
        font-size: 14px; line-height: 1; padding: 4px; flex-shrink: 0;
        border-radius: 4px;
    }
    .drift-removed-dismiss:hover {
        color: var(--drift-text-1);
        background: var(--drift-bg-2);
    }
    .drift-removed-clear {
        margin-top: 8px;
        background: none; border: none; cursor: pointer;
        color: var(--drift-red-text);
        font-size: 11px; font-weight: 600; font-family: inherit;
        padding: 4px 0;
    }
    .drift-removed-clear:hover { text-decoration: underline; }

    .drift-empty {
        text-align: center; padding: 40px 24px;
        color: var(--drift-text-3);
        font-size: 13px;
    }

    #drift-footer {
        flex-shrink: 0;
        padding: 8px 16px;
        border-top: 1px solid var(--drift-border);
        text-align: center;
    }
    #drift-footer a {
        color: var(--drift-text-3);
        font-size: 11px;
        text-decoration: none;
    }
    #drift-footer a:hover { color: var(--drift-text-1); text-decoration: underline; }
    #drift-footer a:focus-visible {
        outline: 2px solid var(--drift-accent);
        outline-offset: 2px;
        border-radius: 2px;
    }

    .drift-tf:focus-visible {
        outline: 2px solid var(--drift-accent);
        outline-offset: -2px;
    }

    #drift-pill:focus-visible,
    #drift-close:focus-visible,
    #drift-refresh:focus-visible,
    #drift-pill-retry:focus-visible,
    .drift-cb:focus-visible,
    .drift-chevron:focus-visible,
    .drift-upd-badge:focus-visible,
    .drift-ack:focus-visible,
    .drift-bulk-ack:focus-visible,
    .drift-removed-dismiss:focus-visible,
    .drift-removed-clear:focus-visible {
        outline: 2px solid var(--drift-accent);
        outline-offset: 2px;
    }
</style>

<script>
(function () {
    'use strict';

    const STORAGE_KEY = 'drift-v1';
    const REMOVED_STORAGE_KEY = 'drift-removed-v1';
    const LEGACY_STORAGE_KEY = 'no-api-tracker-v1';
    const LEGACY_REMOVED_STORAGE_KEY = 'no-api-tracker-removed-v1';
    const METHODS = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];

    const METHOD_VAR = {
        GET:     '--drift-green',
        POST:    '--drift-blue',
        PUT:     '--drift-orange',
        PATCH:   '--drift-orange',
        DELETE:  '--drift-red-text',
        HEAD:    '--drift-purple',
        OPTIONS: '--drift-purple',
    };

    let spec         = null;
    let specFetched  = false;
    let state        = { version: 1, endpoints: {} };
    let removed      = [];
    let filter       = 'all';
    let lastFilter   = filter;
    let expandedDiffs = new Set();
    let panelOpen    = false;

    function migrateLegacyStorage(oldKey, newKey) {
        try {
            if (localStorage.getItem(newKey) !== null) return;
            const legacy = localStorage.getItem(oldKey);
            if (legacy === null) return;
            localStorage.setItem(newKey, legacy);
            localStorage.removeItem(oldKey);
        } catch (_) {}
    }

    function loadState() {
        migrateLegacyStorage(LEGACY_STORAGE_KEY, STORAGE_KEY);
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (raw) {
                const parsed = JSON.parse(raw);
                if (parsed && parsed.version === 1 && parsed.endpoints) state = parsed;
            }
        } catch (_) {}
    }

    function saveState() {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch (_) {}
    }

    function loadRemoved() {
        migrateLegacyStorage(LEGACY_REMOVED_STORAGE_KEY, REMOVED_STORAGE_KEY);
        try {
            const raw = localStorage.getItem(REMOVED_STORAGE_KEY);
            if (raw) removed = JSON.parse(raw) || [];
        } catch (_) {}
    }

    function saveRemoved() {
        try { localStorage.setItem(REMOVED_STORAGE_KEY, JSON.stringify(removed)); } catch (_) {}
    }

    function djb2(str) {
        let h = 5381;
        for (let i = 0; i < str.length; i++) h = ((h << 5) + h) ^ str.charCodeAt(i);
        return (h >>> 0).toString(16);
    }

    function bodySchemaOf(op) {
        const content = (op.requestBody || {}).content || {};
        const media   = content['application/json'] || content['multipart/form-data'] || content['application/x-www-form-urlencoded'];
        return (media || {}).schema || null;
    }

    function bodyFieldsOf(op) {
        const schema     = bodySchemaOf(op);
        const properties = (schema || {}).properties || {};
        const required   = new Set((schema || {}).required || []);
        return Object.keys(properties).map(function (name) {
            return {
                name:     name,
                type:     [].concat(properties[name].type || 'any').join('|'),
                required: required.has(name),
            };
        });
    }

    function snapshotEndpoint(op) {
        return {
            summary:     op.summary || '',
            description: op.description || '',
            params:      (op.parameters || []).map(function (p) {
                             return { name: p.name, in: p.in, required: !!p.required };
                         }),
            hasBody:     !!op.requestBody,
            bodyFields:  bodyFieldsOf(op),
            responses:   Object.keys(op.responses || {}).sort(),
        };
    }

    const hashCache = new WeakMap();

    function hashEndpoint(op) {
        if (hashCache.has(op)) return hashCache.get(op);
        const hash = djb2(JSON.stringify(snapshotEndpoint(op)));
        hashCache.set(op, hash);
        return hash;
    }

    function extractEndpoints() {
        const out = [];
        for (const [path, pathObj] of Object.entries(spec.paths || {})) {
            for (const method of METHODS) {
                const op = pathObj[method];
                if (!op) continue;
                out.push({
                    key:    method.toUpperCase() + ' ' + path,
                    method: method.toUpperCase(),
                    path,
                    tag:    (op.tags && op.tags[0]) || 'Other',
                    op,
                });
            }
        }
        return out;
    }

    function getStatus(key, op) {
        const stored = state.endpoints[key];
        if (!stored || stored.status === 'todo') return 'todo';
        if (stored.specHash !== hashEndpoint(op)) return 'updated';
        return 'done';
    }

    function computeDiff(key, op) {
        const stored = state.endpoints[key];
        if (!stored || !stored.snapshot) return [{ sign: ' ', text: 'spec changed', breaking: false }];

        const prev    = stored.snapshot;
        const curr    = snapshotEndpoint(op);
        const changes = [];

        const prevP = new Map((prev.params || []).map(function (p) { return [p.in + ':' + p.name, p]; }));
        const currP = new Map((curr.params || []).map(function (p) { return [p.in + ':' + p.name, p]; }));
        for (const [k, p] of currP) {
            if (!prevP.has(k)) changes.push({ sign: '+', text: 'param: ' + k.split(':')[1], breaking: !!p.required });
        }
        for (const [k] of prevP) {
            if (!currP.has(k)) changes.push({ sign: '-', text: 'param: ' + k.split(':')[1], breaking: true });
        }
        for (const [k, currParam] of currP) {
            const prevParam = prevP.get(k);
            if (prevParam && !!prevParam.required !== !!currParam.required) {
                changes.push({
                    sign: currParam.required ? '+' : '-',
                    text: 'param: ' + k.split(':')[1] + ' is now ' + (currParam.required ? 'required' : 'optional'),
                    breaking: !!currParam.required,
                });
            }
        }

        if (prev.hasBody !== curr.hasBody) {
            changes.push(curr.hasBody
                ? { sign: '+', text: 'request body added', breaking: true }
                : { sign: '-', text: 'request body removed', breaking: false });
        }

        const prevB = new Map((prev.bodyFields || []).map(function (f) { return [f.name, f]; }));
        const currB = new Map((curr.bodyFields || []).map(function (f) { return [f.name, f]; }));
        for (const [name, f] of currB) {
            if (!prevB.has(name)) changes.push({ sign: '+', text: 'body param: ' + name, breaking: !!f.required });
        }
        for (const [name] of prevB) {
            if (!currB.has(name)) changes.push({ sign: '-', text: 'body param: ' + name, breaking: true });
        }
        for (const [name, currField] of currB) {
            const prevField = prevB.get(name);
            if (!prevField) continue;
            if (!!prevField.required !== !!currField.required) {
                changes.push({
                    sign: currField.required ? '+' : '-',
                    text: 'body param: ' + name + ' is now ' + (currField.required ? 'required' : 'optional'),
                    breaking: !!currField.required,
                });
            }
            if (prevField.type !== currField.type) {
                changes.push({
                    sign: ' ',
                    text: 'body param: ' + name + ' type changed (' + prevField.type + ' -> ' + currField.type + ')',
                    breaking: true,
                });
            }
        }

        const prevR = new Set(prev.responses || []);
        const currR = new Set(curr.responses || []);
        for (const r of currR) if (!prevR.has(r)) changes.push({ sign: '+', text: 'response ' + r, breaking: false });
        for (const r of prevR) if (!currR.has(r)) changes.push({ sign: '-', text: 'response ' + r, breaking: true });

        if (prev.summary !== curr.summary) changes.push({ sign: ' ', text: 'summary changed', breaking: false });
        if (prev.description !== curr.description) changes.push({ sign: ' ', text: 'description changed', breaking: false });

        return changes.length ? changes : [{ sign: ' ', text: 'spec changed', breaking: false }];
    }

    function markDone(key, op) {
        state.endpoints[key] = {
            status:    'done',
            specHash:  hashEndpoint(op),
            snapshot:  snapshotEndpoint(op),
            markedAt:  new Date().toISOString(),
        };
        saveState();
    }

    function markTodo(key, op) {
        state.endpoints[key] = {
            status:   'todo',
            specHash: hashEndpoint(op),
            snapshot: snapshotEndpoint(op),
        };
        saveState();
    }

    function pruneStaleState() {
        const currentKeys = new Set(extractEndpoints().map(function (e) { return e.key; }));
        let changed = false;
        let removedChanged = false;
        const existingRemoved = new Set(removed.map(function (r) { return r.key; }));
        for (const key of Object.keys(state.endpoints)) {
            if (!currentKeys.has(key)) {
                if (!existingRemoved.has(key)) {
                    removed.push({ key: key, status: state.endpoints[key].status });
                    removedChanged = true;
                }
                delete state.endpoints[key];
                changed = true;
            }
        }
        if (changed) saveState();
        if (removedChanged) saveRemoved();
    }

    function el(tag, className) {
        const e = document.createElement(tag);
        if (className) e.className = className;
        return e;
    }

    function txt(tag, text, className) {
        const e = el(tag, className);
        e.textContent = text;
        return e;
    }

    function buildPathEl(path, className) {
        const e = el('span', className);
        const parts = path.split('/');
        parts.forEach(function (part, i) {
            if (i > 0) e.appendChild(document.createTextNode('/'));
            e.appendChild(document.createTextNode(part));
            if (i < parts.length - 1) e.appendChild(document.createElement('wbr'));
        });
        return e;
    }

    function renderPill(endpoints) {
        let done = 0, upd = 0;
        for (const e of endpoints) {
            const s = getStatus(e.key, e.op);
            if (s === 'done') done++;
            else if (s === 'updated') upd++;
        }

        document.getElementById('drift-pill-count').textContent = done + '/' + endpoints.length;

        const updEl = document.getElementById('drift-pill-upd');
        if (upd) { updEl.textContent = upd + ' updated'; updEl.hidden = false; }
        else { updEl.hidden = true; }
    }

    function renderFilterCounts(endpoints) {
        let todo = 0, done = 0, updated = 0;
        for (const e of endpoints) {
            const s = getStatus(e.key, e.op);
            if (s === 'todo') todo++;
            else if (s === 'done') done++;
            else if (s === 'updated') updated++;
        }
        const counts = { all: endpoints.length, todo: todo, done: done, updated: updated };
        for (const f of ['all', 'todo', 'done', 'updated']) {
            const countEl = document.getElementById('drift-tf-count-' + f);
            if (countEl) countEl.textContent = counts[f];
        }
    }

    function buildRow(ep) {
        const status = getStatus(ep.key, ep.op);
        const isDone = status === 'done';
        const isUpd  = status === 'updated';

        const row  = el('div', 'drift-row' + (isUpd ? ' updated' : ''));
        const main = el('div', 'drift-main');

        const cb = el('input', 'drift-cb');
        cb.type          = 'checkbox';
        cb.checked       = isDone;
        cb.indeterminate = isUpd;
        cb.title         = isUpd ? 'Spec changed since marked done — click to re-mark as done' : isDone ? 'Mark as todo' : 'Mark as done';

        const cbHit = el('span', 'drift-cb-hit');
        cbHit.appendChild(cb);
        cbHit.addEventListener('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            if (isUpd)       { expandedDiffs.delete(ep.key); markDone(ep.key, ep.op); }
            else if (isDone) { markTodo(ep.key, ep.op); }
            else             { markDone(ep.key, ep.op); }
            render();
        });

        const cssVar  = METHOD_VAR[ep.method] || '--drift-text-2';
        const badge   = txt('span', ep.method, 'drift-badge');
        badge.style.cssText = 'color:var(' + cssVar + ');background:color-mix(in srgb,var(' + cssVar + ') 12%,transparent)';

        const path = buildPathEl(ep.path, 'drift-path' + (isDone ? ' done' : ''));
        path.title = ep.path;

        main.appendChild(cbHit);
        main.appendChild(badge);
        main.appendChild(path);

        if (isUpd) {
            const diff = computeDiff(ep.key, ep.op);
            const isBreaking = diff.some(function (c) { return c.breaking; });
            if (isBreaking || filter !== 'updated') {
                const updBadge = txt('span', isBreaking ? '⚠ breaking' : '⚠ updated', 'drift-upd-badge' + (isBreaking ? ' breaking' : ''));
                main.appendChild(updBadge);
            }

            const isExpanded = expandedDiffs.has(ep.key);
            const chevron = txt('button', isExpanded ? '▾' : '▸', 'drift-chevron');
            chevron.setAttribute('aria-label', isExpanded ? 'Collapse diff' : 'Expand diff');
            chevron.addEventListener('click', function (e) {
                e.stopPropagation();
                if (isExpanded) expandedDiffs.delete(ep.key);
                else expandedDiffs.add(ep.key);
                render();
            });
            main.appendChild(chevron);
        }

        row.appendChild(main);

        if (isUpd && expandedDiffs.has(ep.key)) {
            const diffEl = el('div', 'drift-diff');
            for (const c of computeDiff(ep.key, ep.op)) {
                const cls = c.breaking ? 'drift-diff-rem' : c.sign === '+' ? 'drift-diff-add' : c.sign === '-' ? 'drift-diff-rem' : 'drift-diff-neu';
                diffEl.appendChild(txt('div', c.sign + ' ' + c.text, cls));
            }
            const ackBtn = txt('button', '✓ Mark as done', 'drift-ack');
            ackBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                expandedDiffs.delete(ep.key);
                markDone(ep.key, ep.op);
                render();
            });
            row.appendChild(diffEl);
            row.appendChild(ackBtn);
        }

        return row;
    }

    function renderList(endpoints) {
        const list = document.getElementById('drift-list');
        const resetScroll = filter !== lastFilter;
        lastFilter = filter;
        const prevScrollTop = list.scrollTop;
        list.textContent = '';

        const visible = endpoints.filter(function (e) {
            const s = getStatus(e.key, e.op);
            return filter === 'all'     ? true
                 : filter === 'updated' ? s === 'updated'
                 : s === filter;
        });

        if (!visible.length) {
            const emptyText = endpoints.length === 0
                ? 'No endpoints found in the OpenAPI spec.'
                : 'No endpoints match this filter.';
            list.appendChild(txt('div', emptyText, 'drift-empty'));
            list.scrollTop = resetScroll ? 0 : prevScrollTop;
            return;
        }

        const allDone = visible.every(function (e) { return getStatus(e.key, e.op) === 'done'; });

        const selectAllRow = el('label', 'drift-select-all');
        const selectAllCb  = el('input', 'drift-select-all-cb');
        selectAllCb.type    = 'checkbox';
        selectAllCb.checked = allDone;
        selectAllCb.addEventListener('change', function () {
            for (const e of visible) {
                expandedDiffs.delete(e.key);
                if (selectAllCb.checked) markDone(e.key, e.op);
                else markTodo(e.key, e.op);
            }
            render();
        });
        selectAllRow.appendChild(selectAllCb);
        selectAllRow.appendChild(document.createTextNode('Mark all ' + visible.length + ' as reviewed'));
        list.appendChild(selectAllRow);

        const groups = new Map();
        for (const e of visible) {
            if (!groups.has(e.tag)) groups.set(e.tag, []);
            groups.get(e.tag).push(e);
        }

        for (const eps of groups.values()) {
            eps.sort(function (a, b) {
                const aUpd = getStatus(a.key, a.op) === 'updated' ? 0 : 1;
                const bUpd = getStatus(b.key, b.op) === 'updated' ? 0 : 1;
                return aUpd - bUpd;
            });
        }

        const tagOrder = new Map((spec.tags || []).map(function (t, i) { return [t.name, i]; }));
        const orderedTags = Array.from(groups.keys()).sort(function (a, b) {
            const aIdx = tagOrder.has(a) ? tagOrder.get(a) : Infinity;
            const bIdx = tagOrder.has(b) ? tagOrder.get(b) : Infinity;
            return aIdx - bIdx;
        });

        const frag = document.createDocumentFragment();
        for (const tag of orderedTags) {
            frag.appendChild(txt('div', tag, 'drift-tag'));
            for (const ep of groups.get(tag)) frag.appendChild(buildRow(ep));
        }
        list.appendChild(frag);
        list.scrollTop = resetScroll ? 0 : prevScrollTop;
    }

    function render() {
        if (!spec) return;
        const endpoints = extractEndpoints();
        renderPill(endpoints);
        renderFilterCounts(endpoints);
        renderRemovedBanner();
        if (panelOpen) renderList(endpoints);
    }

    function buildPanel() {
        const panel = el('div');
        panel.id = 'drift-panel';

        const header = el('div');
        header.id = 'drift-header';
        const title = txt('span', 'Drift');
        title.id = 'drift-header-title';
        const headerActions = el('div');
        headerActions.id = 'drift-header-actions';
        const refreshBtn = txt('button', '⟳');
        refreshBtn.id = 'drift-refresh';
        refreshBtn.setAttribute('aria-label', 'Refresh spec');
        refreshBtn.title = 'Refresh spec';
        refreshBtn.addEventListener('click', function () { refreshSpec(); });
        const closeBtn = txt('button', '×');
        closeBtn.id = 'drift-close';
        closeBtn.setAttribute('aria-label', 'Close Drift');
        closeBtn.addEventListener('click', function () { togglePanel(false); });
        headerActions.appendChild(refreshBtn);
        headerActions.appendChild(closeBtn);
        header.appendChild(title);
        header.appendChild(headerActions);

        const filtersEl = el('div');
        filtersEl.id = 'drift-filters';
        for (const f of ['all', 'todo', 'done', 'updated']) {
            const label = f.charAt(0).toUpperCase() + f.slice(1);
            const btn = el('button', 'drift-tf' + (f === 'all' ? ' active' : ''));
            btn.appendChild(document.createTextNode(label + ' '));
            const count = txt('span', '0', 'drift-tf-count');
            count.id = 'drift-tf-count-' + f;
            btn.appendChild(count);
            btn.addEventListener('click', function () {
                filter = f;
                filtersEl.querySelectorAll('.drift-tf').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                render();
            });
            filtersEl.appendChild(btn);
        }

        const removedEl = el('div');
        removedEl.id = 'drift-removed';

        const listEl = el('div');
        listEl.id = 'drift-list';

        const footer = el('div');
        footer.id = 'drift-footer';
        const footerLink = el('a');
        footerLink.href = 'https://github.com/wrteam-jay';
        footerLink.target = '_blank';
        footerLink.rel = 'noopener noreferrer';
        footerLink.textContent = 'Drift · by WRTeam';
        footer.appendChild(footerLink);

        panel.appendChild(header);
        panel.appendChild(filtersEl);
        panel.appendChild(removedEl);
        panel.appendChild(listEl);
        panel.appendChild(footer);
        document.body.appendChild(panel);
    }

    function renderRemovedBanner() {
        const removedEl = document.getElementById('drift-removed');
        if (!removedEl) return;
        removedEl.textContent = '';
        if (!removed.length) return;

        const banner = el('div', 'drift-removed-banner');
        const title = txt('div', removed.length + ' tracked endpoint' + (removed.length === 1 ? '' : 's') + ' removed from the API:', 'drift-removed-title');
        banner.appendChild(title);

        for (const r of removed) {
            const row = el('div', 'drift-removed-row');
            row.appendChild(txt('span', r.key, 'drift-removed-key'));
            const dismissBtn = txt('button', '×', 'drift-removed-dismiss');
            dismissBtn.setAttribute('aria-label', 'Dismiss ' + r.key);
            dismissBtn.addEventListener('click', function () {
                removed = removed.filter(function (x) { return x.key !== r.key; });
                saveRemoved();
                renderRemovedBanner();
            });
            row.appendChild(dismissBtn);
            banner.appendChild(row);
        }

        const clearAllBtn = txt('button', 'Dismiss all', 'drift-removed-clear');
        clearAllBtn.addEventListener('click', function () {
            removed = [];
            saveRemoved();
            renderRemovedBanner();
        });
        banner.appendChild(clearAllBtn);

        removedEl.appendChild(banner);
    }

    async function fetchSpec() {
        if (specFetched) return;
        specFetched = true;
        try {
            const res = await fetch('/docs/api.json', { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            spec = await res.json();
            pruneStaleState();
            const label = document.getElementById('drift-pill-label');
            const retry = document.getElementById('drift-pill-retry');
            if (label) label.textContent = 'Drift';
            if (retry) retry.hidden = true;
            render();
        } catch (e) {
            console.error('[drift]', e);
            const label = document.getElementById('drift-pill-label');
            const retry = document.getElementById('drift-pill-retry');
            if (label) label.textContent = 'Drift — error';
            if (retry) retry.hidden = false;
        }
    }

    function refreshSpec() {
        specFetched = false;
        fetchSpec();
    }

    function togglePanel(open) {
        panelOpen = (open === undefined) ? !panelOpen : open;
        const panel = document.getElementById('drift-panel');
        const pill  = document.getElementById('drift-pill');
        pill.style.visibility = panelOpen ? 'hidden' : 'visible';
        if (panelOpen) {
            panel.classList.add('open');
            if (!spec) {
                document.getElementById('drift-list').appendChild(
                    txt('div', 'Loading endpoints…', 'drift-empty')
                );
            }
            if (!specFetched) fetchSpec(); else render();
        } else {
            panel.classList.remove('open');
        }
    }

    function init() {
        loadState();
        loadRemoved();

        const pill = el('div');
        pill.id = 'drift-pill';
        pill.setAttribute('role', 'button');
        pill.setAttribute('tabindex', '0');
        pill.setAttribute('aria-label', 'Open Drift');
        pill.addEventListener('click', function () { togglePanel(); });
        pill.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); togglePanel(); }
        });
        const pillLabel  = txt('span', 'Drift');
        pillLabel.id = 'drift-pill-label';
        const pillCount  = txt('span', '—');
        pillCount.id = 'drift-pill-count';
        const pillUpd    = txt('span', '');
        pillUpd.id = 'drift-pill-upd';
        pillUpd.hidden = true;
        const pillRetry  = txt('button', '⟳ retry');
        pillRetry.id = 'drift-pill-retry';
        pillRetry.hidden = true;
        pillRetry.addEventListener('click', function (e) {
            e.stopPropagation();
            refreshSpec();
        });
        pill.appendChild(pillLabel);
        pill.appendChild(pillCount);
        pill.appendChild(pillUpd);
        pill.appendChild(pillRetry);
        document.body.appendChild(pill);

        buildPanel();

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && panelOpen) togglePanel(false);
        });
        document.addEventListener('click', function (e) {
            if (!panelOpen) return;
            const panel = document.getElementById('drift-panel');
            if (panel.contains(e.target) || pill.contains(e.target)) return;
            togglePanel(false);
        });

        setTimeout(fetchSpec, 300);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
</body>
</html>
