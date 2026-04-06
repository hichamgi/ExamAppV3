(function () {
    'use strict';

    function togglePasswordVisibility() {
        document.querySelectorAll('.js-toggle-password').forEach(function (button) {
            button.addEventListener('click', function () {
                var targetId = button.getAttribute('data-target');
                var input = document.getElementById(targetId);

                if (!input) {
                    return;
                }

                var isPassword = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');

                var icon = button.querySelector('i');
                if (icon) {
                    icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
                }
            });
        });
    }

    async function loadAdminDashboardData() {
        if (!window.ExamAppPage || window.ExamAppPage.type !== 'admin-dashboard') {
            return;
        }

        var base = document.querySelector('meta[name="base-url"]');
        var baseUrl = base ? base.getAttribute('content') : '';

        var sessionsBox = document.getElementById('admin-active-sessions');
        var alertsBox = document.getElementById('admin-alerts');

        try {
            var sessionsUrl = baseUrl + '/api/admin/sessions';
            var alertsUrl = baseUrl + '/api/admin/alerts';

            var [sessionsResponse, alertsResponse] = await Promise.all([
                fetch(sessionsUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }),
                fetch(alertsUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
            ]);

            var sessionsRaw = await sessionsResponse.text();
            var alertsRaw = await alertsResponse.text();

            var sessionsData;
            var alertsData;

            try {
                sessionsData = JSON.parse(sessionsRaw);
            } catch (e) {
                throw new Error('Réponse sessions non JSON');
            }

            try {
                alertsData = JSON.parse(alertsRaw);
            } catch (e) {
                throw new Error('Réponse alerts non JSON');
            }

            if (!sessionsResponse.ok) {
                throw new Error('Erreur sessions HTTP ' + sessionsResponse.status);
            }

            if (!alertsResponse.ok) {
                throw new Error('Erreur alerts HTTP ' + alertsResponse.status);
            }

            if (sessionsBox) {
                if (!sessionsData.items || !sessionsData.items.length) {
                    sessionsBox.innerHTML = '<div class="text-secondary">Aucune session active.</div>';
                } else {
                    var rows = sessionsData.items.map(function (item) {

                        var stateBadge = item.is_stale
                            ? '<span class="badge text-bg-warning">Inactive</span>'
                            : '<span class="badge text-bg-success">Active</span>';

                        var actionBtn = item.is_stale
                            ? `<button class="btn btn-sm btn-outline-danger js-force-logout" data-id="${item.id}">
                                    <i class="bi bi-box-arrow-right"></i>
                            </button>`
                            : '';

                        return `
                            <tr>
                                <td>${escapeHtml(item.student_name || '')}</td>
                                <td>${escapeHtml(item.class_name || '')}</td>
                                <td>${escapeHtml(item.computer_name || '')}</td>
                                <td>${escapeHtml(item.ip_address || '')}</td>
                                <td>${escapeHtml(item.network_type || '')}</td>
                                <td>${escapeHtml(item.last_activity_at || '')}</td>
                                <td>${stateBadge}</td>
                                <td>${actionBtn}</td>
                            </tr>
                        `;
                    }).join('');

                    sessionsBox.innerHTML = `
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>Poste</th>
                                    <th>IP</th>
                                    <th>Réseau</th>
                                    <th>Dernière activité</th>
                                    <th>État</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>
                    `;
                    sessionsBox.querySelectorAll('.js-force-logout').forEach(function (btn) {
                        btn.addEventListener('click', async function () {

                            if (!confirm('Déconnecter cette session ?')) {
                                return;
                            }

                            var sessionId = btn.getAttribute('data-id');

                            try {
                                var formData = new FormData();
                                formData.append('_csrf', window.ExamAppPage.csrfForceLogout);
                                formData.append('session_id', sessionId);

                                var response = await fetch(baseUrl + '/api/admin/force-logout', {
                                    method: 'POST',
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json'
                                    },
                                    body: formData
                                });

                                var data = await response.json();

                                if (response.ok && data.success) {
                                    loadAdminDashboardData(); // refresh
                                } else {
                                    alert(data.message || 'Erreur lors de la déconnexion.');
                                }

                            } catch (e) {
                                alert('Erreur réseau.');
                            }
                        });
                    });
                }
            }

            if (alertsBox) {
                if (!alertsData.items || !alertsData.items.length) {
                    alertsBox.innerHTML = '<div class="text-secondary">Aucune alerte récente.</div>';
                } else {
                    alertsBox.innerHTML = alertsData.items.map(function (item) {
                        return `
                            <div class="alert-item">
                                <div class="fw-semibold mb-1">${escapeHtml(item.student_name || item.username_attempted || 'Alerte')}</div>
                                <div class="small text-secondary mb-1">
                                    ${escapeHtml(item.existing_computer_name || 'N/A')} → ${escapeHtml(item.attempted_computer_name || 'N/A')}
                                </div>
                                <div class="small">
                                    <span class="badge text-bg-light border">${escapeHtml(item.status || '')}</span>
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            }
        } catch (error) {
            console.error('loadAdminDashboardData error:', error);

            if (sessionsBox) {
                sessionsBox.innerHTML = '<div class="text-danger">Impossible de charger les sessions.</div>';
            }

            if (alertsBox) {
                alertsBox.innerHTML = '<div class="text-danger">Impossible de charger les alertes.</div>';
            }
        }
    }

    async function runStudentHeartbeat() {
        if (!window.ExamAppPage || window.ExamAppPage.type !== 'student-dashboard') {
            return;
        }

        if (!window.ExamAppPage.debug) {
            return;
        }

        var statusBox = document.getElementById('student-heartbeat-status');
        var url = window.ExamAppPage.heartbeatUrl;
        var csrf = window.ExamAppPage.csrfHeartbeat;

        if (!url || !csrf) {
            return;
        }

        async function ping() {
            try {
                var formData = new FormData();
                formData.append('_csrf', csrf);

                var response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                var data = await response.json();

                if (statusBox) {
                    statusBox.className = response.ok ? 'small text-success' : 'small text-danger';
                    statusBox.textContent = data.message || (response.ok ? 'Heartbeat OK' : 'Erreur heartbeat');
                }
            } catch (error) {
                if (statusBox) {
                    statusBox.className = 'small text-danger';
                    statusBox.textContent = 'Erreur de communication.';
                }
            }
        }

        ping();
        setInterval(ping, 30000);
    }

    async function runAdminHeartbeat() {
        var badge = document.getElementById('admin-heartbeat-badge');

        if (!badge) {
            return;
        }

        function setBadgeState(state) {
            var baseClass = 'badge rounded-pill bg-white px-2 py-2';

            badge.classList.remove('heartbeat-ok');

            switch (state) {
                case 'ok':
                    badge.className = baseClass + ' border border-success text-success';
                    badge.title = 'Heartbeat admin OK';
                    badge.classList.add('heartbeat-ok');
                    break;

                case 'error':
                    badge.className = baseClass + ' border border-danger text-danger';
                    badge.title = 'Erreur heartbeat admin';
                    break;

                case 'config':
                    badge.className = baseClass + ' border border-warning text-warning';
                    badge.title = 'Configuration heartbeat admin manquante';
                    break;

                default:
                    badge.className = baseClass + ' border border-secondary text-secondary';
                    badge.title = 'Heartbeat admin en attente';
                    break;
            }
        }

        setBadgeState('default');

        if (!window.ExamAppPage || !window.ExamAppPage.heartbeatUrl || !window.ExamAppPage.csrfHeartbeat) {
            setBadgeState('config');
            return;
        }

        var url = window.ExamAppPage.heartbeatUrl;
        var csrf = window.ExamAppPage.csrfHeartbeat;

        async function ping() {
            try {
                var formData = new FormData();
                formData.append('_csrf', csrf);

                var response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                var data = null;

                try {
                    data = await response.json();
                } catch (jsonError) {
                    data = null;
                }

                if (response.ok) {
                    setBadgeState('ok');
                } else {
                    setBadgeState('error');
                    console.error('Heartbeat admin HTTP error:', response.status, data);
                }
            } catch (error) {
                setBadgeState('error');
                console.error('Heartbeat admin network error:', error);
            }
        }

        await ping();
        setInterval(ping, 30000);
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    document.addEventListener('DOMContentLoaded', function () {
        togglePasswordVisibility();
        loadAdminDashboardData();
        runStudentHeartbeat();
        runAdminHeartbeat();
        
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');

        tooltipTriggerList.forEach(el => {
            new bootstrap.Tooltip(el);
        });
    });
})();
