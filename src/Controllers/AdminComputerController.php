<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\SessionManager;
use App\Services\NetworkComputerService;

final class AdminComputerController extends Controller
{
    private NetworkComputerService $networkService;

    public function __construct()
    {
        parent::__construct();
        $this->networkService = new NetworkComputerService();
    }

    public function index(): void
    {
        $this->guardAdmin();

        $computers = $this->networkService->listAllComputers();
        $this->render('admin.computers.index', [
            'title' => 'Gestion des postes',
            'computers' => $computers,
        ]);
    }

    public function create(): void
    {
        $this->guardAdmin();

        $this->render('admin.computers.create', [
            'title' => 'Ajouter un poste',
            'csrf_computer_create' => Csrf::token('admin.computer.create'),
        ]);
    }

    public function store(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.computer.create');

        $name = $this->request()->string('name');
        $hostname = $this->request()->string('hostname');
        $ipLan = $this->request()->string('ip_lan');
        $ipWifi = $this->request()->string('ip_wifi');
        $roomName = $this->request()->string('room_name');
        $description = $this->request()->string('description');
        $isActive = $this->request()->boolean('is_active', true);

        if ($name === '' || $hostname === '') {
            $this->abort(422, 'Nom et hostname obligatoires.');
            return;
        }

        Database::insert('lab_computers', [
            'name' => $name,
            'hostname' => $hostname,
            'ip_lan' => $ipLan !== '' ? $ipLan : null,
            'ip_wifi' => $ipWifi !== '' ? $ipWifi : null,
            'is_active' => $isActive ? 1 : 0,
            'room_name' => $roomName,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->redirect(base_url('admin/computers'));
    }

    public function edit(string $id): void
    {
        $this->guardAdmin();

        $computer = $this->networkService->findById((int) $id);
        if ($computer === null) {
            $this->abort(404, 'Poste introuvable.');
            return;
        }

        $this->render('admin.computers.edit', [
            'title' => 'Modifier un poste',
            'computer' => $computer,
            'csrf_computer_update' => Csrf::token('admin.computer.update'),
        ]);
    }

    public function update(string $id): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.computer.update');

        $computerId = (int) $id;
        $computer = $this->networkService->findById($computerId);

        if ($computer === null) {
            $this->abort(404, 'Poste introuvable.');
            return;
        }

        Database::execute(
            "
            UPDATE lab_computers
            SET name = :name,
                hostname = :hostname,
                ip_lan = :ip_lan,
                ip_wifi = :ip_wifi,
                is_active = :is_active,
                room_name = :room_name,
                description = :description,
                updated_at = NOW()
            WHERE id = :id
            ",
            [
                'id' => $computerId,
                'name' => $this->request()->string('name'),
                'hostname' => $this->request()->string('hostname'),
                'ip_lan' => $this->request()->string('ip_lan') ?: null,
                'ip_wifi' => $this->request()->string('ip_wifi') ?: null,
                'is_active' => $this->request()->boolean('is_active', true) ? 1 : 0,
                'room_name' => $this->request()->string('room_name'),
                'description' => $this->request()->string('description'),
            ]
        );

        $this->redirect(base_url('admin/computers'));
    }

    public function delete(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.computer.delete');

        $computerId = $this->request()->int('computer_id');
        if ($computerId <= 0) {
            $this->abort(422, 'ID poste invalide.');
            return;
        }

        $activeSessions = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM user_sessions WHERE computer_id = :id AND status = 'active'",
            ['id' => $computerId],
            0
        );

        if ($activeSessions > 0) {
            $this->abort(409, 'Impossible de supprimer un poste avec session active.');
            return;
        }

        Database::execute("DELETE FROM lab_computers WHERE id = :id", ['id' => $computerId]);

        $this->redirect(base_url('admin/computers'));
    }

    public function toggleActive(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.computer.toggle');

        $computerId = $this->request()->int('computer_id');
        $value = $this->request()->boolean('value');

        if ($computerId <= 0) {
            $this->abort(422, 'ID poste invalide.');
            return;
        }

        Database::execute(
            "
            UPDATE lab_computers
            SET is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
            ",
            [
                'id' => $computerId,
                'is_active' => $value ? 1 : 0,
            ]
        );

        $this->redirect(base_url('admin/computers'));
    }

    private function guardAdmin(): void
    {
        SessionManager::enforceTimeout();
        SessionManager::enforceIntegrity(false, true);

        if (!SessionManager::check() || !SessionManager::isAdmin()) {
            $this->redirect(base_url('login'));
            exit;
        }
    }
}