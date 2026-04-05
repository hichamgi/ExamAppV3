const attemptToken = window.ATTEMPT_TOKEN;
const config = window.EXAM_CONFIG;

const storageKey = `exam_draft:${attemptToken}`;

// ===== LOCAL STORAGE =====
function saveLocal(answers) {
    const data = {
        attemptToken,
        answers,
        updatedAt: new Date().toISOString(),
        locked: false
    };

    localStorage.setItem(storageKey, JSON.stringify(data));
}

function loadLocal() {
    const raw = localStorage.getItem(storageKey);
    return raw ? JSON.parse(raw) : null;
}

// ===== SYNC =====
setInterval(() => {
    const data = loadLocal();
    if (!data || data.locked) return;

    fetch(config.syncUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': config.csrf.sync
        },
        body: JSON.stringify({
            attempt_token: attemptToken,
            answers: data.answers
        })
    })
    .then(r => r.json())
    .then(res => {
        console.log('SYNC OK', res);
    })
    .catch(() => {
        console.warn('SYNC FAIL');
    });

}, 5000);

// ===== FINALIZE =====
function finalizeExam() {
    let data = loadLocal();

    if (!data) return;

    data.locked = true;
    data.finalized_at_client = new Date().toISOString();

    // hash anti-triche
    data.hash = sha256(JSON.stringify(data.answers));

    localStorage.setItem(storageKey, JSON.stringify(data));

    sendFinal(data);
}

// ===== SUBMIT =====
function sendFinal(data) {

    fetch(config.submitUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': config.csrf.submit
        },
        body: JSON.stringify({
            attempt_token: attemptToken,
            snapshot: data
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            console.log('SUBMITTED');

            // nettoyage complet
            Object.keys(localStorage).forEach(k => {
                if (k.startsWith('exam_draft:')) {
                    localStorage.removeItem(k);
                }
            });

        } else {
            retrySend(data);
        }
    })
    .catch(() => {
        retrySend(data);
    });
}

// ===== RETRY =====
function retrySend(data) {
    console.warn('Retry in 3s...');
    setTimeout(() => sendFinal(data), 3000);
}