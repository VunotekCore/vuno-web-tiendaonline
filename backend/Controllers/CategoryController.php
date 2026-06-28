<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CategoryModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use App\Utils\Str;

final class CategoryController
{
    use ApiResponse;

    private ?AuthService $auth = null;

    public function __construct(private CategoryModel $model) {}

    private function getAuth(): AuthService
    {
        if ($this->auth === null) {
            $this->auth = new AuthService(new UserModel(\App\Config\Database::getConnection()));
        }
        return $this->auth;
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        $raw = json_decode((string) file_get_contents('php://input'), true);
        return is_array($raw) ? $raw : [];
    }

    private function queryString(string $key, string $default = ''): string
    {
        /** @var mixed $val */
        $val = $_GET[$key] ?? null;
        return is_string($val) ? $val : $default;
    }

    private function queryInt(string $key): ?int
    {
        /** @var mixed $val */
        $val = $_GET[$key] ?? null;
        return is_numeric($val) ? (int) $val : null;
    }

    /** @param array<string, mixed> $data */
    private function str(array $data, string $key, string $default = ''): string
    {
        /** @var mixed $val */
        $val = $data[$key] ?? null;
        if (is_string($val)) return $val;
        if (is_scalar($val)) return (string) $val;
        return $default;
    }

    // =========================================================================
    //  API methods
    // =========================================================================

    public function list(): void
    {
        $result = $this->model->getAll(
            $this->queryInt('limit'),
            $this->queryInt('offset'),
            $this->queryString('lang') ?: null,
            $this->queryString('search') ?: null,
        );
        $this->jsonResponse($result);
    }

    public function create(): void
    {
        /** @var mixed $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if (!is_string($method) || $method !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $name = $this->str($body, 'name');
        if ($name === '') {
            $this->jsonError('Name is required');
        }
        if (strlen($name) > 100) {
            $this->jsonError('Name must be 100 characters or less');
        }

        $slug = Str::slugify($name);
        $id = 'cat-' . $slug;
        $category = ['id' => $id, 'name' => $name, 'slug' => $slug];

        $this->model->save($category);
        $this->getAuth()->logAction('create', 'category', $id, 'Created category: ' . $name);
        $this->jsonResponse($category, 201);
    }

    public function update(): void
    {
        /** @var mixed $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if (!is_string($method) || $method !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $id = $this->str($body, 'id');
        $name = $this->str($body, 'name');
        if ($id === '' || $name === '') {
            $this->jsonError('ID and name are required');
        }
        if (strlen($name) > 100) {
            $this->jsonError('Name must be 100 characters or less');
        }

        $existing = $this->model->getById($id);
        if ($existing === null) {
            $this->jsonError('Category not found', 404);
        }

        $existing['name'] = $name;
        $existing['slug'] = Str::slugify($name);
        $this->model->save($existing);
        $this->getAuth()->logAction('update', 'category', $id, 'Updated category: ' . $name, [
            'from' => $this->str($body, 'name', $id),
            'to' => $name,
        ]);
        $this->jsonResponse($existing);
    }

    public function delete(): void
    {
        /** @var mixed $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if (!is_string($method) || $method !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $id = $this->str($body, 'id');
        if ($id === '') {
            $this->jsonError('ID is required');
        }

        $this->model->delete($id);
        $this->getAuth()->logAction('delete', 'category', $id, 'Deleted category: ' . ($this->str($body, 'name') ?: $id));
        $this->jsonResponse(['success' => true]);
    }
}
