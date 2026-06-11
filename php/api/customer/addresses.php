<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);
if (empty($token)) jsonError('Authentication required', 401);

$customerId = getCustomerIdFromToken($token);
if (!$customerId) jsonError('Unauthorized', 401);

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $addresses = getCustomerAddresses($customerId);
        jsonResponse(['addresses' => $addresses]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['address_line1'])) jsonError('address_line1 is required');
        if (empty($input['city'])) jsonError('city is required');
        if (empty($input['country'])) jsonError('country is required');

        $id = createCustomerAddress($customerId, $input);
        $address = getCustomerAddress($id, $customerId);
        jsonResponse(['address' => $address], 201);
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['id'])) jsonError('address id is required');

        $addressId = (int)$input['id'];
        updateCustomerAddress($addressId, $customerId, $input);
        $address = getCustomerAddress($addressId, $customerId);
        jsonResponse(['address' => $address]);
        break;

    case 'DELETE':
        $input = json_decode(file_get_contents('php://input'), true);
        $addressId = (int)($input['id'] ?? ($_GET['id'] ?? 0));
        if (!$addressId) jsonError('address id is required');

        deleteCustomerAddress($addressId, $customerId);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonError('Method not allowed', 405);
}
