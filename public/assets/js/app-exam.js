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

    async function sendHeartbeat(config) {
        if (!config || !config.heartbeatUrl || !config.csrfHeartbeat) {
            return;
        }

        const formData = new FormData();
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
            const response = await fetch(config.syncUrl, {
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
                    answers: payload.answers || {}
                })
            });

            if (!response.ok) {
                return false;
            }

            const result = await response.json();
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
            const response = await fetch(config.submitUrl, {
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

            const result = await response.json();
            return !!(result && result.success);
        } catch (error) {
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

    function startExamCountdown(config, finalizeCallback) {
        const countdown = document.getElementById('countdown');
        const minutesEl = document.getElementById('minutes');
        const secondsEl = document.getElementById('seconds');

        if (!countdown || !minutesEl || !secondsEl) {
            return;
        }

        const endsAtMs = parseDateToMs(config.endsAt || countdown.dataset.endsAt || '');
        const serverNowMs = parseDateToMs(config.serverNow || countdown.dataset.serverNow || '');

        if (!endsAtMs || !serverNowMs) {
            return;
        }

        const clientNowMs = Date.now();
        const offsetMs = serverNowMs - clientNowMs;
        const totalSeconds = Math.max(0, Math.floor((endsAtMs - serverNowMs) / 1000));

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

        const form = document.querySelector('form[action*="/student/exam/submit"]');
        if (!form) {
            return;
        }

        const storageKey = getStorageKey(config);
        cleanupOtherDrafts(storageKey);

        const existingDraft = loadLocal(storageKey);
        if (existingDraft) {
            restoreAnswers(form, existingDraft);
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

            saveLocal(storageKey, {
                key: storageKey,
                userExamId: config.userExamId || null,
                attemptToken: config.attemptToken || null,
                updatedAt: new Date().toISOString(),
                locked: false,
                formValues: payload.formValues,
                answers: payload.answers,
                answers_multi: payload.answersMulti
            });

            return payload;
        }

        async function finalizeExam() {
            if (examFinalizing) {
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

            const ok = await sendFinal(config, draft);

            if (ok) {
                try {
                    localStorage.removeItem(storageKey);
                } catch (error) {
                    // no-op
                }

                if (config.dashboardUrl) {
                    window.location.href = config.dashboardUrl;
                    return;
                }
            }

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
            finalizeExam();
        });

        persistDraft();

        setInterval(function () {
            sendHeartbeat(config);
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
            });
        }, 5000);

        startExamCountdown(config, finalizeExam);
    });
})();