<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Models\UserModel;
use App\Services\AuthService;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();

$userModel = new UserModel(\App\Config\Database::getConnection());
$authService = new AuthService($userModel);
$controller = new AuthController($authService, $userModel);

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!is_string($method)) {
    jsonError('Method not allowed', 405);
}

if ($method === 'GET') {
    $controller->listUsers();
} elseif ($method === 'POST') {
    $data = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($data) ? $data : [];
    $action = isset($input['action']) && is_string($input['action']) ? $input['action'] : '';
    if ($action === 'create') {
        $controller->createUser();
    } else {
        $controller->updateUser();
    }
} elseif ($method === 'PUT') {
    $controller->updateUser();
} elseif ($method === 'DELETE') {
    $controller->deleteUser();
} else {
    jsonError('Method not allowed', 405);
}
