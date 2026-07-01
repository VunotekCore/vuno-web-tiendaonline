<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ReviewModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Traits\ApiResponse;

final class ReviewController
{
    use ApiResponse;

    private ?AuthService $auth = null;

    public function __construct(private ReviewModel $model) {}

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

    /** @param array<string, mixed> $data */
    private function int(array $data, string $key, int $default = 0): int
    {
        /** @var mixed $val */
        $val = $data[$key] ?? null;
        return is_numeric($val) ? (int) $val : $default;
    }

    private function isPost(): bool
    {
        /** @var mixed $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        return is_string($method) && $method === 'POST';
    }

    // =========================================================================
    //  API methods
    // =========================================================================

    /**
     * Public: get approved reviews + stats for a product.
     */
    public function list(): void
    {
        try {
            $productId = $this->queryString('product_id');
            if ($productId === '') {
                $this->jsonError('product_id is required');
            }

            $reviews = $this->model->getProductReviews($productId, true);
            $stats = $this->model->getStats($productId);

            $this->jsonResponse([
                'reviews' => $reviews,
                'stats' => $stats,
            ]);
        } catch (\Throwable $e) {
            $this->jsonError('Error loading reviews: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Public: create a new review.
     */
    public function create(): void
    {
        try {
            if (!$this->isPost()) {
                $this->jsonError('Method not allowed', 405);
            }

            $body = $this->input();
            $productId = $this->str($body, 'product_id');
            $rating = $this->int($body, 'rating');

            if ($productId === '' || $rating === 0) {
                $this->jsonError('product_id and rating are required');
            }
            if ($rating < 1 || $rating > 5) {
                $this->jsonError('Rating must be between 1 and 5');
            }

            $id = $this->model->insertReview([
                'product_id' => $productId,
                'reviewer_name' => $this->str($body, 'reviewer_name'),
                'reviewer_email' => $this->str($body, 'reviewer_email'),
                'rating' => $rating,
                'title' => $this->str($body, 'title'),
                'comment' => $this->str($body, 'comment'),
            ]);

            $this->jsonResponse(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            $this->jsonError('Error creating review: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Admin: list with pagination and filters.
     */
    public function adminList(): void
    {
        try {
            $result = $this->model->getAll(
                $this->queryInt('limit'),
                $this->queryInt('offset'),
                $this->queryString('status') ?: null,
                $this->queryString('search') ?: null,
            );
            $this->jsonResponse($result);
        } catch (\Throwable $e) {
            $this->jsonError('Error loading reviews: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Admin: approve a review.
     */
    public function approve(): void
    {
        try {
            if (!$this->isPost()) {
                $this->jsonError('Method not allowed', 405);
            }

            $body = $this->input();
            $id = $this->int($body, 'id');
            if ($id === 0) {
                $this->jsonError('Review ID is required');
            }

            $this->model->approveReview($id);
            $this->getAuth()->logAction('approve', 'review', (string) $id, 'Approved review');
            $this->jsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            $this->jsonError('Error approving review: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Admin: delete a review.
     */
    public function delete(): void
    {
        try {
            if (!$this->isPost()) {
                $this->jsonError('Method not allowed', 405);
            }

            $body = $this->input();
            $id = $this->int($body, 'id');
            if ($id === 0) {
                $this->jsonError('Review ID is required');
            }

            $this->model->deleteReview($id);
            $this->getAuth()->logAction('delete', 'review', (string) $id, 'Deleted review');
            $this->jsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            $this->jsonError('Error deleting review: ' . $e->getMessage(), 500);
        }
    }
}
