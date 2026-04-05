(function () {
    'use strict';

    function getPageConfig() {
        return window.ExamAppPage && window.ExamAppPage.type === 'student-exam'
            ? window.ExamAppPage
            : null;
    }

    function getStorageKey(config) {
        if (config && config.attemptToken) {
            return 'exam_draft:' + String(config.attemptToken);
        }

        if (config && config.userExamId) {
            return 'exam_draft:user_exam:' + String(config.userExamId);
        }

        return null;
    }

    function escapeSelector(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return String(value).replace(/["\\]/g, '\\$&');
    }

    function pad2(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function parseDateToMs(value) {
        const ts = Date.parse(value);
        return Number.isNaN(ts) ? 0 : ts;
    }

    function loadLocal(storageKey) {
        if (!storageKey) {
            return null;
        }

        try {
            const raw = localStorage.getItem(storageKey);
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

    function collectAnswers(form) {
        const formValues = {};
        const answers = {};
        const answersMulti = {};

        const fields = form.querySelectorAll('input[name], textarea[name], select[name]');

        fields.forEach(function (field) {
            const name = field.name || '';

            if (
                field.type === 'hidden'
                || name === '_csrf'
                || name === 'user_exam_id'
            ) {
                return;
            }

            if (field.type === 'radio') {
                if (field.checked) {
                    formValues[name] = field.value;

                    const match = name.match(/^answers\[(\d+)\]$/);
                    if (match) {
                        answers[match[1]] = field.value;
                    }
                }
                return;
            }

            if (field.type === 'checkbox') {
                if (field.checked) {
                    formValues[name] = field.value;
                }
                return;
            }

            const value = field.value ?? '';
            formValues[name] = value;

            let match = name.match(/^answers\[(\d+)\]$/);
            if (match) {
                answers[match[1]] = value;
                return;
            }

            match = name.match(/^answers_multi\[(\d+)\]\[(\d+)\]$/);
            if (match) {
                const questionId = match[1];
                const index = parseInt(match[2], 10);

                if (!Array.isArray(answersMulti[questionId])) {
                    answersMulti[questionId] = [];
                }

                answersMulti[questionId][index] = value;
            }
        });

        Object.keys(answersMulti).forEach(function (questionId) {
            const list = answersMulti[questionId].map(function (value) {
                return value == null ? '' : String(value);
            });

            answers[questionId] = JSON.stringify(list);
        });

        return {
            formValues: formValues,
            answers: answers,
            answersMulti: answersMulti
        };
    }

    function restoreAnswers(form, draft) {
        if (!draft || typeof draft !== 'object') {
            return;
        }

        const formValues = draft.formValues && typeof draft.formValues === 'object'
            ? draft.formValues
            : {};

        Object.keys(formValues).forEach(function (name) {
            const value = formValues[name];
            const selector = '[name="' + escapeSelector(name) + '"]';
            const fields = form.querySelectorAll(selector);

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

    async function sha256Hex(value) {
        if (!window.crypto || !window.crypto.subtle || !window.TextEncoder) {
            return '';
        }

        const data = new TextEncoder().encode(String(value));
        const hashBuffer = await window.crypto.subtle.digest('SHA-256', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));

        return hashArray.map(function (b) {
            return b.toString(16).padStart(2, '0');
        }).join('');
    }

    function getStatusClass(status) {
        switch (status) {
            case 'success':
                return 'color:#198754;border:1px solid #198754;';
            case 'warning':
                return 'color:#fd7e14;border:1px solid #fd7e14;';
            case 'error':
                return 'color:#dc3545;border:1px solid #dc3545;';
            default:
                return 'color:#0dcaf0;border:1px solid #0dcaf0;';
        }
    }

    function createDebugBadge(text, status) {
        return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#fff;'
            + getStatusClass(status)
            + 'margin-right:6px;font-weight:bold;">'
            + text
            + '</span>';
    }

    function createDebugPanel(enabled) {
        if (!enabled) {
            return null;
        }

        const panel = document.createElement('div');
        panel.id = 'exam-debug-panel';
        panel.style.position = 'fixed';
        panel.style.right = '12px';
        panel.style.bottom = '12px';
        panel.style.width = '460px';
        panel.style.maxHeight = '50vh';
        panel.style.overflow = 'auto';
        panel.style.zIndex = '99999';
        panel.style.background = '#111';
        panel.style.color = '#eee';
        panel.style.fontSize = '12px';
        panel.style.padding = '10px';
        panel.style.border = '1px solid #444';
        panel.style.borderRadius = '8px';
        panel.style.boxShadow = '0 8px 24px rgba(0,0,0,.35)';
        panel.style.fontFamily = 'monospace';

        const title = document.createElement('div');
        title.innerHTML = '<strong>Exam Debug</strong>';
        title.style.marginBottom = '8px';

        const summary = document.createElement('div');
        summary.id = 'exam-debug-summary';
        summary.style.position = 'sticky';
        summary.style.top = '0';
        summary.style.background = '#111';
        summary.style.paddingBottom = '8px';
        summary.style.marginBottom = '8px';
        summary.innerHTML =
            createDebugBadge('HEARTBEAT ?', 'info') +
            createDebugBadge('SYNC ?', 'info') +
            createDebugBadge('SUBMIT ?', 'info') +
            createDebugBadge('LOCAL ?', 'info');

        const body = document.createElement('div');
        body.id = 'exam-debug-body';

        panel.appendChild(title);
        panel.appendChild(summary);
        panel.appendChild(body);

        document.body.appendChild(panel);

        return {
            body: body,
            summary: summary
        };
    }

    function makeDebugger(config) {
        const enabled = !!(config && config.debug);
        const panel = createDebugPanel(enabled);
        const panelBody = panel ? panel.body : null;
        const panelSummary = panel ? panel.summary : null;

        const summaryState = {
            heartbeat: 'info',
            sync: 'info',
            submit: 'info',
            local: 'info'
        };

        function refreshSummary() {
            if (!enabled || !panelSummary) {
                return;
            }

            panelSummary.innerHTML =
                createDebugBadge('HEARTBEAT', summaryState.heartbeat) +
                createDebugBadge('SYNC', summaryState.sync) +
                createDebugBadge('SUBMIT', summaryState.submit) +
                createDebugBadge('LOCAL', summaryState.local);
        }

        function setState(channel, status) {
            if (!Object.prototype.hasOwnProperty.call(summaryState, channel)) {
                return;
            }

            summaryState[channel] = status;
            refreshSummary();
        }

        function write(type, tag, label, data) {
            if (!enabled) {
                return;
            }

            const time = new Date().toLocaleTimeString();
            const text = '[' + time + '] [' + tag + '] ' + label;

            if (type === 'error') {
                console.error(text, data);
            } else if (type === 'warn') {
                console.warn(text, data);
            } else {
                console.log(text, data);
            }

            if (!panelBody) {
                return;
            }

            const item = document.createElement('div');
            item.style.borderTop = '1px solid #333';
            item.style.paddingTop = '6px';
            item.style.marginTop = '6px';

            const head = document.createElement('div');
            head.innerHTML =
                createDebugBadge(tag, type === 'error' ? 'error' : (type === 'warn' ? 'warning' : 'info')) +
                '<span style="font-weight:bold">' + label + '</span>';

            const pre = document.createElement('pre');
            pre.style.whiteSpace = 'pre-wrap';
            pre.style.wordBreak = 'break-word';
            pre.style.margin = '4px 0 0';
            pre.textContent = typeof data === 'string' ? data : JSON.stringify(data, null, 2);

            item.appendChild(head);
            item.appendChild(pre);
            panelBody.prepend(item);
        }

        refreshSummary();

        return {
            info(tag, label, data) { write('info', tag, label, data); },
            warn(tag, label, data) { write('warn', tag, label, data); },
            error(tag, label, data) { write('error', tag, label, data); },
            state(channel, status) { setState(channel, status); }
        };
    }

    async function sendHeartbeat(config, debug) {
        if (!config || !config.heartbeatUrl || !config.csrfHeartbeat) {
            return;
        }

        const formData = new FormData();
        formData.append('_csrf', config.csrfHeartbeat);

        debug.info('HEARTBEAT', 'REQUEST', {
            url: config.heartbeatUrl,
            method: 'POST'
        });

        try {
            const response = await fetch(config.heartbeatUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            });

            let result = null;
            try {
                result = await response.json();
            } catch (e) {
                result = { parse_error: true };
            }

            if (!response.ok) {
                debug.state('heartbeat', 'warning');
                debug.warn('HEARTBEAT', 'RESPONSE NOT OK', {
                    status: response.status,
                    response: result
                });
                return;
            }

            debug.state('heartbeat', 'success');
            debug.info('HEARTBEAT', 'RESPONSE OK', {
                status: response.status,
                response: result
            });
        } catch (error) {
            debug.state('heartbeat', 'error');
            debug.error('HEARTBEAT', 'NETWORK ERROR', {
                message: error.message
            });
        }
    }

    async function sendSync(config, payload, debug) {
        if (!config || !config.syncUrl || !config.attemptToken || !config.csrfSync) {
            return false;
        }

        const requestPayload = {
            _csrf: config.csrfSync,
            attempt_token: config.attemptToken,
            answers: payload.answers || {}
        };

        debug.info('SYNC', 'REQUEST', {
            url: config.syncUrl,
            method: 'POST',
            payload: requestPayload
        });

        try {
            const response = await fetch(config.syncUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': config.csrfSync
                },
                body: JSON.stringify(requestPayload)
            });

            let result = null;
            try {
                result = await response.json();
            } catch (e) {
                result = { parse_error: true };
            }

            if (!response.ok) {
                debug.state('sync', 'warning');
                debug.warn('SYNC', 'RESPONSE NOT OK', {
                    status: response.status,
                    response: result
                });
                return false;
            }

            debug.state('sync', 'success');
            debug.info('SYNC', 'RESPONSE OK', {
                status: response.status,
                response: result
            });

            return !!(result && result.success);
        } catch (error) {
            debug.state('sync', 'error');
            debug.error('SYNC', 'NETWORK ERROR', {
                message: error.message
            });
            return false;
        }
    }

    async function sendFinal(config, snapshot, debug) {
        if (!config || !config.submitUrl || !config.attemptToken || !config.csrfSubmit) {
            return false;
        }

        const requestPayload = {
            _csrf: config.csrfSubmit,
            attempt_token: config.attemptToken,
            snapshot: snapshot
        };

        debug.info('SUBMIT', 'REQUEST', {
            url: config.submitUrl,
            method: 'POST',
            payload: requestPayload
        });

        try {
            const response = await fetch(config.submitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': config.csrfSubmit
                },
                body: JSON.stringify(requestPayload)
            });

            let result = null;
            try {
                result = await response.json();
            } catch (e) {
                result = { parse_error: true };
            }

            if (!response.ok) {
                debug.state('submit', 'warning');
                debug.warn('SUBMIT', 'RESPONSE NOT OK', {
                    status: response.status,
                    response: result
                });
                return false;
            }

            debug.state('submit', 'success');
            debug.info('SUBMIT', 'RESPONSE OK', {
                status: response.status,
                response: result
            });

            return !!(result && result.success);
        } catch (error) {
            debug.state('submit', 'error');
            debug.error('SUBMIT', 'NETWORK ERROR', {
                message: error.message
            });
            return false;
        }
    }

    function updateCountdownClass(minutesEl, secondsEl, remaining, total) {
        if (!minutesEl || !secondsEl || total <= 0) {
            return;
        }

        let className = 'text-bg-success';

        if (remaining <= 0) {
            className = 'text-bg-dark';
        } else if (remaining <= total / 4) {
            className = 'text-bg-danger';
        } else if (remaining <= total / 2) {
            className = 'text-bg-warning';
        }

        minutesEl.className = 'badge fs-4 px-3 py-2 ' + className;
        secondsEl.className = 'badge fs-4 px-3 py-2 ' + className;
    }

    function startExamCountdown(config, finalizeCallback, debug) {
        const countdown = document.getElementById('countdown');
        const minutesEl = document.getElementById('minutes');
        const secondsEl = document.getElementById('seconds');

        if (!countdown || !minutesEl || !secondsEl) {
            return;
        }

        const endsAtMs = parseDateToMs(config.endsAt || countdown.dataset.endsAt || '');
        const serverNowMs = parseDateToMs(config.serverNow || countdown.dataset.serverNow || '');

        if (!endsAtMs || !serverNowMs) {
            debug.warn('COUNTDOWN', 'INIT FAILED', {
                endsAt: config.endsAt || countdown.dataset.endsAt || '',
                serverNow: config.serverNow || countdown.dataset.serverNow || ''
            });
            return;
        }

        const clientNowMs = Date.now();
        const offsetMs = serverNowMs - clientNowMs;
        const totalSeconds = Math.max(0, Math.floor((endsAtMs - serverNowMs) / 1000));

        debug.info('COUNTDOWN', 'INIT', {
            endsAt: config.endsAt,
            serverNow: config.serverNow,
            totalSeconds: totalSeconds,
            offsetMs: offsetMs
        });

        let finalized = false;

        function tick() {
            const nowMs = Date.now() + offsetMs;
            let remaining = Math.floor((endsAtMs - nowMs) / 1000);

            if (remaining < 0) {
                remaining = 0;
            }

            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;

            minutesEl.textContent = pad2(minutes);
            secondsEl.textContent = pad2(seconds);

            updateCountdownClass(minutesEl, secondsEl, remaining, totalSeconds);

            if (remaining <= 0 && !finalized) {
                finalized = true;
                debug.warn('COUNTDOWN', 'REACHED ZERO', { remaining: remaining });
                finalizeCallback();
                return;
            }

            window.setTimeout(function () {
                window.requestAnimationFrame(tick);
            }, 1000);
        }

        tick();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const config = getPageConfig();
        if (!config) {
            return;
        }

        const debug = makeDebugger(config);

        const form = document.querySelector('form[action*="/student/exam/submit"]');
        if (!form) {
            debug.warn('FORM', 'NOT FOUND', {});
            return;
        }

        const storageKey = getStorageKey(config);
        cleanupOtherDrafts(storageKey);

        const existingDraft = loadLocal(storageKey);
        if (existingDraft) {
            restoreAnswers(form, existingDraft);
            debug.info('LOCAL', 'DRAFT RESTORED', existingDraft);
            debug.state('local', 'success');
        }

        let lastSerializedPayload = '';
        let examFinalizing = false;

        function persistDraft() {
            const payload = collectAnswers(form);
            const serialized = JSON.stringify(payload.formValues);

            if (serialized === lastSerializedPayload) {
                return payload;
            }

            lastSerializedPayload = serialized;

            const draft = {
                key: storageKey,
                userExamId: config.userExamId || null,
                attemptToken: config.attemptToken || null,
                updatedAt: new Date().toISOString(),
                locked: false,
                formValues: payload.formValues,
                answers: payload.answers,
                answers_multi: payload.answersMulti
            };

            saveLocal(storageKey, draft);
            debug.state('local', 'success');
            debug.info('LOCAL', 'DRAFT SAVED', draft);

            return payload;
        }

        async function finalizeExam() {
            if (examFinalizing) {
                debug.warn('SUBMIT', 'FINALIZE ALREADY RUNNING', {});
                return;
            }

            examFinalizing = true;

            let draft = loadLocal(storageKey);
            if (!draft) {
                const payload = collectAnswers(form);
                draft = {
                    key: storageKey,
                    userExamId: config.userExamId || null,
                    attemptToken: config.attemptToken || null,
                    updatedAt: new Date().toISOString(),
                    locked: false,
                    formValues: payload.formValues,
                    answers: payload.answers,
                    answers_multi: payload.answersMulti
                };
            }

            draft.locked = true;
            draft.finalized_at_client = new Date().toISOString();
            draft.hash = await sha256Hex(JSON.stringify(draft.answers || {}));

            saveLocal(storageKey, draft);
            debug.info('SUBMIT', 'LOCAL SNAPSHOT READY', draft);

            const ok = await sendFinal(config, draft, debug);

            if (ok) {
                try {
                    localStorage.removeItem(storageKey);
                } catch (error) {
                    // no-op
                }

                debug.info('SUBMIT', 'SUCCESS CLEANUP DONE', { storageKey: storageKey });

                if (config.dashboardUrl) {
                    window.location.href = config.dashboardUrl;
                    return;
                }
            }

            debug.warn('SUBMIT', 'FALLBACK HTML SUBMIT', {});
            HTMLFormElement.prototype.submit.call(form);
        }

        form.addEventListener('input', function () {
            persistDraft();
        });

        form.addEventListener('change', function () {
            persistDraft();
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            debug.info('SUBMIT', 'HTML SUBMIT INTERCEPTED', {});
            finalizeExam();
        });

        persistDraft();

        setInterval(function () {
            sendHeartbeat(config, debug);
        }, 30000);

        setInterval(function () {
            if (!config.syncUrl || !config.attemptToken || !config.csrfSync) {
                return;
            }

            const draft = loadLocal(storageKey);
            if (!draft || draft.locked) {
                return;
            }

            sendSync(config, {
                answers: draft.answers || {}
            }, debug);
        }, 5000);

        startExamCountdown(config, finalizeExam, debug);
    });
})();