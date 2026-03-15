<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\SessionManager;
use App\Services\AuthService;

final class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
    }

    public function showLogin(): void
    {
        if (SessionManager::check()) {
            $redirectUrl = SessionManager::isAdmin()
                ? $this->baseUrl('/admin/dashboard')
                : $this->baseUrl('/student/dashboard');

            $this->redirect($redirectUrl);
            return;
        }

        $this->render('auth.login', [
            'title' => 'Connexion',
            'csrf_login' => Csrf::token('auth.login'),
            'classes' => $this->authService->getActiveClasses(),
        ], 'layouts.main');
    }

    public function login(): void
    {
        $request = $this->request();

        if (!$request->isPost()) {
            $this->abort(405, 'Méthode non autorisée.');
            return;
        }

        Csrf::assertRequest($request, 'auth.login');

        $role = $request->string('role');
        $password = $request->string('password');

        if ($role === 'admin') {
            $identifier = $request->string('identifier');
            $result = $this->authService->loginAdmin($identifier, $password, $request);
            $this->respondAuthResult($result);
            return;
        }

        $codeMassar = $request->string('code_massar');
        $classId = $request->int('class_id');

        $result = $this->authService->loginStudent($codeMassar, $password, $classId, $request);
        $this->respondAuthResult($result);
    }

    public function logout(): void
    {
        $request = $this->request();

        if ($request->isPost()) {
            Csrf::assertRequest($request, 'auth.logout');
        }

        $this->authService->logout();

        if ($request->expectsJson()) {
            $this->json([
                'success' => true,
                'message' => 'Déconnexion réussie.',
                'redirect_url' => $this->baseUrl('/login'),
            ]);
            return;
        }

        $this->redirect($this->baseUrl('/login'));
    }

    public function sessionStatus(): void
    {
        if (!SessionManager::check()) {
            $this->json([
                'success' => true,
                'authenticated' => false,
            ]);
            return;
        }

        $this->authService->refreshAuthenticatedSession(true);

        $this->json([
            'success' => true,
            'authenticated' => true,
            'user' => $this->authService->currentUser(),
            'role' => SessionManager::role(),
            'csrf' => [
                'auth_logout' => Csrf::token('auth.logout'),
            ],
        ]);
    }

    private function respondAuthResult(array $result): void
    {
        $status = (int) ($result['status'] ?? 200);

        if ($this->request()->expectsJson()) {
            $payload = $result;
            unset($payload['status']);

            if (($result['success'] ?? false) === true) {
                $payload['csrf'] = [
                    'auth_logout' => Csrf::token('auth.logout'),
                ];
            }

            $this->json($payload, $status);
            return;
        }

        if (($result['success'] ?? false) === true) {
            $this->redirect((string) ($result['redirect_url'] ?? $this->baseUrl('/')));
            return;
        }

        $this->render('auth.login', [
            'title' => 'Connexion',
            'error' => (string) ($result['message'] ?? 'Erreur de connexion.'),
            'reason' => (string) ($result['reason'] ?? 'error'),
            'conflict' => ($result['reason'] ?? '') === 'concurrent_session_detected' ? $result : null,
            'old' => [
                'role' => $this->request()->string('role'),
                'identifier' => $this->request()->string('identifier'),
                'code_massar' => $this->request()->string('code_massar'),
                'class_id' => $this->request()->int('class_id'),
            ],
            'csrf_login' => Csrf::refresh('auth.login'),
        ], 'layouts.main');
    }
}
