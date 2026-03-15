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
            var [sessionsResponse, alertsResponse] = await Promise.all([
                fetch((window.location.origin ? '' : '') + baseUrl + '/api/admin/sessions', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }),
                fetch((window.location.origin ? '' : '') + baseUrl + '/api/admin/alerts', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
            ]);

            var sessionsData = await sessionsResponse.json();
            var alertsData = await alertsResponse.json();

            if (sessionsBox) {
                if (!sessionsData.items || !sessionsData.items.length) {
                    sessionsBox.innerHTML = '<div class="text-secondary">Aucune session active.</div>';
                } else {
                    var rows = sessionsData.items.map(function (item) {
                        return `
                            <tr>
                                <td>${escapeHtml(item.student_name || '')}</td>
                                <td>${escapeHtml(item.class_name || '')}</td>
                                <td>${escapeHtml(item.computer_name || '')}</td>
                                <td>${escapeHtml(item.ip_address || '')}</td>
                                <td>${escapeHtml(item.network_type || '')}</td>
                                <td>${escapeHtml(item.last_activity_at || '')}</td>
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
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>
                    `;
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
    });
})();
