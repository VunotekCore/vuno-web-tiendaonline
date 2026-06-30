<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Config\Database;
use App\Controllers\CurrencyController;
use App\Models\CurrencyModel;
use App\Models\UserModel;
use App\Services\AuthService;

setCorsHeaders();

$db = Database::getConnection();
$auth = new AuthService(new UserModel($db));
$auth->startSession();
if (!$auth->isLoggedIn()) jsonError('Unauthorized', 401);
$auth->requireRole('superadmin');

$controller = new CurrencyController(new CurrencyModel($db), $auth);
$controller->create();
