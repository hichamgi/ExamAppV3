(function () {
    'use strict';

    function getBaseUrl() {
        var base = document.querySelector('meta[name="base-url"]');
        return base ? (base.getAttribute('content') || '') : '';
    }

    function joinUrl(base, path) {
        base = String(base || '').replace(/\/+$/, '');
        path = String(path || '').replace(/^\/+/, '');

        if (path === '') {
            return base || '';
        }

        return base + '/' + path;
    }

    function escapeSelector(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return String(value).replace(/["\\]/g, '\\$&');
    }

    function getPageConfig() {
        return window.ExamAppPage && window.ExamAppPage.type === 'student-exam'
            ? window.ExamAppPage
            : null;
    }

    function getStorageKey(config) {
        if (window.ATTEMPT_TOKEN && String(window.ATTEMPT_TOKEN).trim() !== '') {
            return 'exam_draft:' + String(window.ATTEMPT_TOKEN).trim();
        }

        if (config && config.userExamId) {
            return 'exam_draft:user_exam:' + String(config.userExamId);
        }

        return null;
    }

    function loadLocal(storageKey) {
        if (!storageKey) {
            return null;
        }

        try {
            var raw = localStorage.getItem(storageKey);
            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            return null;
        }
    }

    function saveLocal(storageKey, data) {
        if (!storageKey) {
            return;
        }

        try {
            localStorage.setItem(storageKey, JSON.stringify(data));
        } catch (error) {
            // no-op
        }
    }

    function collectAnswers(form) {
        var data = {
            answers: {},
            answers_multi: {}
        };

        var fields = form.querySelectorAll('input[name], textarea[name], select[name]');

        fields.forEach(function (field) {
            var name = field.name || '';

            if (
                field.type === 'hidden'
                || name === '_csrf'
                || name === 'user_exam_id'
            ) {
                return;
            }

            if (field.type === 'radio') {
                if (!field.checked) {
                    return;
                }

                data.answers[name] = field.value;
                return;
            }

            if (field.type === 'checkbox') {
                if (!field.checked) {
                    return;
                }

                data.answers[name] = field.value;
                return;
            }

            if (field.tagName === 'SELECT' || field.tagName === 'TEXTAREA' || field.type === 'text' || field.type === 'number') {
                if (name.indexOf('answers_multi[') === 0) {
                    data.answers_multi[name] = field.value;
                } else {
                    data.answers[name] = field.value;
                }
            }
        });

        return data;
    }

    function restoreAnswers(form, draft) {
        if (!draft || typeof draft !== 'object') {
            return;
        }

        var flatValues = Object.assign({}, draft.answers || {}, draft.answers_multi || {});

        Object.keys(flatValues).forEach(function (name) {
            var value = flatValues[name];
            var selector = '[name="' + escapeSelector(name) + '"]';
            var fields = form.querySelectorAll(selector);

            if (!fields.length) {
                return;
            }

            fields.forEach(function (field) {
                if (field.type === 'radio' || field.type === 'checkbox') {
                    field.checked = String(field.value) === String(value);
                    return;
                }

                field.value = value;
            });
        });
    }

    function cleanupOtherDrafts(activeStorageKey) {
        try {
            Object.keys(localStorage).forEach(function (key) {
                if (key.indexOf('exam_draft:') === 0 && key !== activeStorageKey) {
                    localStorage.removeItem(key);
                }
            });
        } catch (error) {
            // no-op
        }
    }

    async function sha256Hex(value) {
        if (!window.crypto || !window.crypto.subtle || !window.TextEncoder) {
            return '';
        }

        var data = new TextEncoder().encode(String(value));
        var hashBuffer = await window.crypto.subtle.digest('SHA-256', data);
        var hashArray = Array.from(new Uint8Array(hashBuffer));

        return hashArray.map(function (b) {
            return b.toString(16).padStart(2, '0');
        }).join('');
    }

    async function sendHeartbeat(config) {
        if (!config || !config.heartbeatUrl || !config.csrfHeartbeat) {
            return;
        }

        var formData = new FormData();
        formData.append('_csrf', config.csrfHeartbeat);

        try {
            await fetch(config.heartbeatUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            });
        } catch (error) {
            // no-op
        }
    }

    async function sendSync(config, payload) {
        if (!config || !config.syncUrl || !config.attemptToken || !config.csrfSync) {
            return false;
        }

        try {
            var response = await fetch(config.syncUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': config.csrfSync
                },
                body: JSON.stringify({
                    _csrf: config.csrfSync,
                    attempt_token: config.attemptToken,
                    answers: payload
                })
            });

            if (!response.ok) {
                return false;
            }

            var result = await response.json();
            return !!(result && result.success);
        } catch (error) {
            return false;
        }
    }

    async function sendFinal(config, snapshot) {
        if (!config || !config.submitUrl || !config.attemptToken || !config.csrfSubmit) {
            return false;
        }

        try {
            var response = await fetch(config.submitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': config.csrfSubmit
                },
                body: JSON.stringify({
                    _csrf: config.csrfSubmit,
                    attempt_token: config.attemptToken,
                    snapshot: snapshot
                })
            });

            if (!response.ok) {
                return false;
            }

            var result = await response.json();
            return !!(result && result.success);
        } catch (error) {
            return false;
        }
    }

    function buildRuntimeConfig(page) {
        var baseUrl = getBaseUrl();

        return {
            baseUrl: baseUrl,
            userExamId: page.userExamId || null,
            heartbeatUrl: page.heartbeatUrl || joinUrl(baseUrl, '/api/student/heartbeat'),
            csrfHeartbeat: page.csrfHeartbeat || '',
            attemptToken: page.attemptToken || window.ATTEMPT_TOKEN || '',
            csrfSync: page.csrfSync || window.CSRF_TOKEN || '',
            csrfSubmit: page.csrfSubmit || window.CSRF_TOKEN || '',
            syncUrl: page.syncUrl || '',
            submitUrl: page.submitUrl || ''
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        var page = getPageConfig();
        if (!page) {
            return;
        }

        var form = document.querySelector('form[action*="/student/exam/submit"]');
        if (!form) {
            return;
        }

        var config = buildRuntimeConfig(page);
        var storageKey = getStorageKey(config);

        cleanupOtherDrafts(storageKey);

        var existingDraft = loadLocal(storageKey);
        if (existingDraft) {
            restoreAnswers(form, existingDraft);
        }

        var lastSerializedPayload = '';

        function persistDraft() {
            var payload = collectAnswers(form);
            var serialized = JSON.stringify(payload);

            if (serialized === lastSerializedPayload) {
                return payload;
            }

            lastSerializedPayload = serialized;

            saveLocal(storageKey, {
                key: storageKey,
                userExamId: config.userExamId,
                attemptToken: config.attemptToken || null,
                updatedAt: new Date().toISOString(),
                locked: false,
                answers: payload.answers,
                answers_multi: payload.answers_multi
            });

            return payload;
        }

        form.addEventListener('input', function () {
            persistDraft();
        });

        form.addEventListener('change', function () {
            persistDraft();
        });

        persistDraft();

        setInterval(function () {
            sendHeartbeat(config);
        }, 30000);

        setInterval(function () {
            if (!config.syncUrl || !config.attemptToken || !config.csrfSync) {
                return;
            }

            var draft = loadLocal(storageKey);
            if (!draft || draft.locked) {
                return;
            }

            var payload = {
                answers: draft.answers || {},
                answers_multi: draft.answers_multi || {}
            };

            sendSync(config, payload);
        }, 5000);

        form.addEventListener('submit', async function () {
            var draft = loadLocal(storageKey) || {
                answers: {},
                answers_multi: {}
            };

            draft.locked = true;
            draft.finalized_at_client = new Date().toISOString();
            draft.hash = await sha256Hex(JSON.stringify({
                answers: draft.answers || {},
                answers_multi: draft.answers_multi || {}
            }));

            saveLocal(storageKey, draft);

            if (!config.submitUrl || !config.attemptToken || !config.csrfSubmit) {
                return;
            }

            var ok = await sendFinal(config, draft);

            if (ok) {
                try {
                    localStorage.removeItem(storageKey);
                } catch (error) {
                    // no-op
                }
            }
        });
    });
})();