<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Models\UserModel;
use App\Services\AuthService;

require_once __DIR__ . '/../../../bootstrap.php';
setCorsHeaders();

$userModel = new UserModel(\App\Config\Database::getConnection());
$controller = new AuthController(new AuthService($userModel), $userModel);
$controller->totpVerify();
