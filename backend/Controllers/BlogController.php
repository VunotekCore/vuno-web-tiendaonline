<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogCategoryModel;
use App\Models\BlogPostModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use App\Utils\Str;

final class BlogController
{
    use ApiResponse;

    private ?AuthService $auth = null;

    public function __construct(
        private BlogPostModel $postModel,
        private BlogCategoryModel $categoryModel,
    ) {}

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

    private function queryInt(string $key, int $default = 0): int
    {
        /** @var mixed $val */
        $val = $_GET[$key] ?? null;
        return is_numeric($val) ? (int) $val : $default;
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

    /** @param array<string, mixed> $data */
    private function int(array $data, string $key, int $default = 0): int
    {
        /** @var mixed $val */
        $val = $data[$key] ?? null;
        return is_numeric($val) ? (int) $val : $default;
    }

    private function isGet(): bool
    {
        /** @var mixed $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        return is_string($method) && $method === 'GET';
    }

    private function isPost(): bool
    {
        /** @var mixed $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        return is_string($method) && $method === 'POST';
    }

    // =========================================================================
    //  Post API methods
    // =========================================================================

    public function list(): void
    {
        try {
            if (!$this->isGet()) {
                $this->jsonError('Method not allowed', 405);
            }

            $page = max(1, $this->queryInt('page', 1));
            $limit = min(50, max(1, $this->queryInt('limit', 10)));
            $categoryId = max(0, $this->queryInt('category_id'));
            $lang = $this->queryString('lang') ?: null;

            $status = 'published';
            $rawStatus = $this->queryString('status');
            if ($rawStatus !== '') {
                // Only admin can filter by status
                if ($this->getAuth()->isLoggedIn()) {
                    $status = $rawStatus;
                }
            }

            $result = $this->postModel->getPosts($page, $limit, $status, $categoryId, $lang);
            $this->jsonResponse($result);
        } catch (\Throwable $e) {
            $this->jsonError('Error listing posts: ' . $e->getMessage(), 500);
        }
    }

    public function get(): void
    {
        try {
            if (!$this->isGet()) {
                $this->jsonError('Method not allowed', 405);
            }

            $slug = $this->queryString('slug');
            $id = $this->queryInt('id');
            $lang = $this->queryString('lang') ?: null;

            $post = null;
            if ($slug !== '') {
                $post = $this->postModel->getPostBySlug($slug, $lang);
            } elseif ($id > 0) {
                $post = $this->postModel->getPostById($id, $lang);
            } else {
                $this->jsonError('Provide id or slug parameter');
            }

            if ($post === null || ($post['status'] ?? '') !== 'published') {
                $this->jsonError('Post not found', 404);
            }

            $this->jsonResponse($post);
        } catch (\Throwable $e) {
            $this->jsonError('Error getting post: ' . $e->getMessage(), 500);
        }
    }

    public function create(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $title = $this->str($body, 'title');
        $slug = $this->str($body, 'slug');
        $content = $this->str($body, 'content');

        if ($title === '' || $slug === '' || $content === '') {
            $this->jsonError('Missing required fields: title, slug, content');
        }

        try {
            $id = $this->postModel->createPost([
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $this->str($body, 'excerpt'),
                'thumbnail_image' => $this->str($body, 'thumbnail_image') ?: null,
                'content' => $content,
                'featured_image' => $this->str($body, 'featured_image') ?: null,
                'author' => $this->str($body, 'author', 'Vunotek'),
                'status' => $this->str($body, 'status', 'draft'),
                'category_id' => $this->int($body, 'category_id') > 0 ? $this->int($body, 'category_id') : null,
            ]);

            $this->getAuth()->logAction('create', 'blog_post', (string) $id, 'Blog post created: ' . $title);
            $this->jsonResponse(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            $this->jsonError('Failed to create post: ' . $e->getMessage(), 500);
        }
    }

    public function update(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $id = $this->int($body, 'id');
        if ($id === 0) {
            $this->jsonError('Post ID is required');
        }

        $data = [];
        foreach (['title', 'slug', 'excerpt', 'thumbnail_image', 'content', 'featured_image', 'author', 'status', 'category_id'] as $key) {
            if (array_key_exists($key, $body)) {
                $data[$key] = $body[$key];
            }
        }

        if ($data === []) {
            $this->jsonError('No fields to update');
        }

        if (array_key_exists('category_id', $data)) {
            $data['category_id'] = !empty($data['category_id']) ? (int) $data['category_id'] : null;
        }

        try {
            $this->postModel->updatePost($id, $data);
            $this->getAuth()->logAction('update', 'blog_post', (string) $id, 'Blog post updated: ' . ($data['title'] ?? (string) $id));
            $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->jsonError('Failed to update post: ' . $e->getMessage(), 500);
        }
    }

    public function delete(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $id = $this->int($body, 'id');
        if ($id === 0) {
            $this->jsonError('Post ID is required');
        }

        try {
            $post = $this->postModel->getPostById($id);
            if ($post === null) {
                $this->jsonError('Post not found', 404);
            }

            $this->postModel->deletePost($id);
            $this->getAuth()->logAction('delete', 'blog_post', (string) $id, 'Blog post deleted: ' . ($post['title'] ?? (string) $id));
            $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->jsonError('Failed to delete post: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    //  Category API methods
    // =========================================================================

    public function categories(): void
    {
        try {
            if (!$this->isGet()) {
                $this->jsonError('Method not allowed', 405);
            }

            $lang = $this->queryString('lang') ?: null;
            $this->jsonResponse($this->categoryModel->getAll($lang));
        } catch (\Throwable $e) {
            $this->jsonError('Error listing categories: ' . $e->getMessage(), 500);
        }
    }

    public function createCategory(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $name = $this->str($body, 'name');
        if ($name === '') {
            $this->jsonError('Name is required');
        }
        if (strlen($name) > 200) {
            $this->jsonError('Name must be 200 characters or less');
        }

        try {
            $id = $this->categoryModel->create([
                'name' => $name,
                'slug' => Str::slugify($name),
                'description' => $this->str($body, 'description'),
            ]);
            $this->getAuth()->logAction('create', 'blog_category', (string) $id, 'Created blog category: ' . $name);
            $this->jsonResponse(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            $this->jsonError('Failed to create category: ' . $e->getMessage(), 500);
        }
    }

    public function updateCategory(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $id = $this->int($body, 'id');
        $name = $this->str($body, 'name');
        if ($id === 0 || $name === '') {
            $this->jsonError('ID and name are required');
        }
        if (strlen($name) > 200) {
            $this->jsonError('Name must be 200 characters or less');
        }

        $existing = $this->categoryModel->getById($id);
        if ($existing === null) {
            $this->jsonError('Category not found', 404);
        }

        try {
            $this->categoryModel->update($id, [
                'name' => $name,
                'slug' => Str::slugify($name),
            ]);
            $this->getAuth()->logAction('update', 'blog_category', (string) $id, 'Updated blog category: ' . $name);
            $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->jsonError('Failed to update category: ' . $e->getMessage(), 500);
        }
    }

    public function deleteCategory(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $id = $this->int($body, 'id');
        if ($id === 0) {
            $this->jsonError('ID is required');
        }

        try {
            $this->categoryModel->delete($id);
            $this->getAuth()->logAction('delete', 'blog_category', (string) $id, 'Deleted blog category: ' . ($this->str($body, 'name') ?: (string) $id));
            $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->jsonError('Failed to delete category: ' . $e->getMessage(), 500);
        }
    }
}
