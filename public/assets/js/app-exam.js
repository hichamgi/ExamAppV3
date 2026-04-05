const attemptToken = window.ATTEMPT_TOKEN;

const storageKey = `exam_draft:${attemptToken}`;

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

setInterval(() => {
    const data = loadLocal();
    if (!data || data.locked) return;

    fetch('/student/exam/sync', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.CSRF_TOKEN
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

function finalizeExam() {
    let data = loadLocal();

    data.locked = true;
    data.finalized_at_client = new Date().toISOString();

    localStorage.setItem(storageKey, JSON.stringify(data));
    
    data.hash = sha256(JSON.stringify(data.answers));

    sendFinal(data);
}

function sendFinal(data) {

    fetch('/student/exam/submit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.CSRF_TOKEN
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
            localStorage.removeItem(storageKey);
        } else {
            retrySend(data);
        }
    })
    .catch(() => {
        retrySend(data);
    });
}

function retrySend(data) {
    console.warn('Retry in 3s...');
    setTimeout(() => sendFinal(data), 3000);
}