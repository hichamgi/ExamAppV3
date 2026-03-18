<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;
use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\StudentController;
use App\Controllers\AdminClassController;
use App\Controllers\AdminComputerController;
use App\Controllers\AdminExamController;
use App\Controllers\AdminMonitoringController;
use App\Controllers\AdminStudentController;

final class App
{
    public function run(): void
    {
        $this->bootstrap();

        $GLOBALS['app_request'] = new Request();

        try {
            $router = $this->buildRouter();
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $uri = $this->getRequestUri();

            $dispatched = $router->dispatch($method, $uri);

            if ($dispatched === false) {
                $this->renderHttpError(404, 'Page non trouvée.');
            }
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    private function bootstrap(): void
    {
        $this->configurePhp();
        $this->configureTimezone();
        $this->registerErrorHandlers();
        $this->startSession();
    }

    private function configurePhp(): void
    {
        $debug = (bool) Config::get('app.debug', false);

        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        $logPath = (string) Config::get('app.paths.logs', 'storage/logs');
        $absoluteLogPath = $this->toAbsolutePath($logPath);

        if (!is_dir($absoluteLogPath)) {
            @mkdir($absoluteLogPath, 0775, true);
        }

        ini_set('error_log', $absoluteLogPath . '/php-error.log');

        error_reporting(E_ALL);
    }

    private function configureTimezone(): void
    {
        date_default_timezone_set('Africa/Casablanca');
    }

    private function registerErrorHandlers(): void
    {
        set_error_handler(function (
            int $severity,
            string $message,
            string $file,
            int $line
        ): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (Throwable $e): void {
            $this->handleException($e);
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();

            if ($error === null) {
                return;
            }

            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

            if (!in_array($error['type'], $fatalTypes, true)) {
                return;
            }

            $message = sprintf(
                '[FATAL] %s in %s:%d',
                $error['message'] ?? 'Unknown fatal error',
                $error['file'] ?? 'unknown',
                $error['line'] ?? 0
            );

            error_log($message);

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=UTF-8');
            }

            $debug = (bool) Config::get('app.debug', false);

            if ($debug) {
                echo '<h1>Erreur fatale</h1>';
                echo '<pre>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</pre>';
                return;
            }

            echo '<h1>Erreur interne</h1><p>Une erreur est survenue.</p>';
        });
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $timeoutMinutes = (int) Config::get('app.session.timeout', 15);
        $cookieLifetime = max(1, $timeoutMinutes) * 60;

        session_name('EXAMAPPSESSID');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $this->isHttps() ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', (string) $cookieLifetime);

        session_start();

        $now = time();

        if (!isset($_SESSION['_meta'])) {
            $_SESSION['_meta'] = [
                'created_at' => $now,
                'last_activity_at' => $now,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ];
            session_regenerate_id(true);
            return;
        }

        $lastActivity = (int) ($_SESSION['_meta']['last_activity_at'] ?? $now);

        if (($now - $lastActivity) > $cookieLifetime) {
            $this->destroySession();
            session_start();
            $_SESSION['_meta'] = [
                'created_at' => $now,
                'last_activity_at' => $now,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ];
            session_regenerate_id(true);
            return;
        }

        $_SESSION['_meta']['last_activity_at'] = $now;
    }

    private function buildRouter(): Router
    {
        $router = new Router();

        /*
        |--------------------------------------------------------------------------
        | Routes minimales de base
        |--------------------------------------------------------------------------
        */

        $router->get('/', function (): void {
            header('Location: ' . $this->baseUrl('/login'));
            exit;
        });

        $router->get('/login', [AuthController::class, 'showLogin']);
        $router->post('/login', [AuthController::class, 'login']);
        $router->post('/logout', [AuthController::class, 'logout']);

        $router->get('/admin/dashboard', [AdminController::class, 'dashboard']);
        $router->get('/student/dashboard', [StudentController::class, 'dashboard']);

        $router->get('/api/admin/sessions', [AdminController::class, 'activeSessions']);
        $router->get('/api/admin/alerts', [AdminController::class, 'loginAlerts']);
        $router->post('/api/student/heartbeat', [StudentController::class, 'heartbeat']);

        $router->post('/api/auth/login', [AuthController::class, 'login']);
        $router->post('/api/auth/logout', [AuthController::class, 'logout']);
        $router->get('/api/auth/session', [AuthController::class, 'sessionStatus']);

        /*
        |--------------------------------------------------------------------------
        | Admin - computers
        |--------------------------------------------------------------------------
        */
        $router->get('/admin/computers', [AdminComputerController::class, 'index']);
        $router->get('/admin/computers/create', [AdminComputerController::class, 'create']);
        $router->post('/admin/computers/store', [AdminComputerController::class, 'store']);
        $router->get('/admin/computers/{id}/edit', [AdminComputerController::class, 'edit']);
        $router->post('/admin/computers/{id}/update', [AdminComputerController::class, 'update']);
        $router->post('/admin/computers/delete', [AdminComputerController::class, 'delete']);
        $router->post('/admin/computers/toggle-active', [AdminComputerController::class, 'toggleActive']);

        /*
        |--------------------------------------------------------------------------
        | Admin - students
        |--------------------------------------------------------------------------
        */
        $router->get('/admin/students', [AdminStudentController::class, 'index']);
        $router->get('/admin/students/{id}', [AdminStudentController::class, 'show']);
        $router->post('/admin/students/toggle-active', [AdminStudentController::class, 'toggleActive']);
        //$router->post('/admin/students/toggle-can-login', [AdminStudentController::class, 'toggleCanLogin']);
        $router->post('/admin/students/force-logout', [AdminStudentController::class, 'forceLogout']);
        $router->post('/admin/students/toggle-login', [AdminStudentController::class, 'toggleCanLogin']);
        
        /*
        |--------------------------------------------------------------------------
        | Admin - classes
        |--------------------------------------------------------------------------
        */
        $router->get('/admin/classes', [AdminClassController::class, 'index']);
        $router->get('/admin/classes/{id}', [AdminClassController::class, 'show']);
        $router->post('/admin/classes/toggle-active', [AdminClassController::class, 'toggleActive']);
        $router->post('/admin/classes/allow-login', [AdminClassController::class, 'allowClassLogin']);
        $router->post('/admin/classes/deny-login', [AdminClassController::class, 'denyClassLogin']);
        $router->post('/admin/classes/allow-group-login', [AdminClassController::class, 'allowGroupLogin']);

        /*
        |--------------------------------------------------------------------------
        | Admin - exams
        |--------------------------------------------------------------------------
        */
        $router->get('/admin/exams', [AdminExamController::class, 'index']);
        $router->get('/admin/exams/export-semester', [AdminExamController::class, 'exportSemester']);

        $router->post('/admin/exams/toggle-active', [AdminExamController::class, 'toggleActive']);
        $router->post('/admin/exams/toggle-print', [AdminExamController::class, 'togglePrint']);
        $router->post('/admin/exams/save-assignment', [AdminExamController::class, 'saveAssignment']);
        $router->post('/admin/exams/generate-subjects', [AdminExamController::class, 'generateSubjects']);
        $router->post('/admin/exams/regenerate-student', [AdminExamController::class, 'regenerateStudentExam']);

        $router->get('/admin/exams/{id}', [AdminExamController::class, 'show']);

        /*
        |--------------------------------------------------------------------------
        | Admin - monitoring
        |--------------------------------------------------------------------------
        */
        $router->get('/admin/monitoring', [AdminMonitoringController::class, 'index']);
        $router->post('/admin/monitoring/force-logout', [AdminMonitoringController::class, 'forceLogout']);
        $router->post('/admin/monitoring/block-student', [AdminMonitoringController::class, 'blockStudent']);
        $router->post('/admin/monitoring/mark-cheat', [AdminMonitoringController::class, 'markCheat']);
        $router->post('/admin/monitoring/force-logout-ip', [AdminMonitoringController::class, 'forceLogoutIp']);

        $router->get('/health', function (): void {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'status' => 'ok',
                'app' => Config::get('app.name', 'ExamAppV3'),
                'env' => Config::get('app.env', 'production'),
                'time' => date('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        });

        return $router;
    }

    private function getRequestUri(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        $baseUrl = (string) Config::get('app.base_url', '');
        $baseUrl = rtrim($baseUrl, '/');

        if ($baseUrl !== '' && $baseUrl !== '/' && str_starts_with($path, $baseUrl)) {
            $path = substr($path, strlen($baseUrl));
        }

        return $path !== '' ? $path : '/';
    }

    private function baseUrl(string $path = ''): string
    {
        $baseUrl = (string) Config::get('app.base_url', '');
        $baseUrl = rtrim($baseUrl, '/');
        $path = '/' . ltrim($path, '/');

        return $baseUrl . $path;
    }

    private function handleException(Throwable $e): void
    {
        $debug = (bool) Config::get('app.debug', false);

        $message = sprintf(
            "[%s] %s in %s:%d\nStack trace:\n%s\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        error_log($message);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        if ($debug) {
            echo '<h1>Erreur application</h1>';
            echo '<pre>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }

        echo '<h1>Erreur interne</h1><p>Une erreur est survenue.</p>';
    }

    private function renderHttpError(int $statusCode, string $message): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<h1>' . $statusCode . '</h1>';
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    private function destroySession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
        }

        session_destroy();
    }

    private function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        return false;
    }

    private function toAbsolutePath(string $path): string
    {
        $trimmed = trim($path);

        if ($trimmed === '') {
            return BASE_PATH . '/storage/logs';
        }

        if (
            str_starts_with($trimmed, '/') ||
            preg_match('/^[A-Za-z]:\\\\/', $trimmed) === 1
        ) {
            return $trimmed;
        }

        return BASE_PATH . '/' . ltrim($trimmed, '/');
    }
}