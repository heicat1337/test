<?php

namespace App\Http\Controllers\Api\Admin;

use App\Services\Articles\ArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleAdminController extends AbstractAdminApiController
{
    public function __construct(private readonly ArticleService $articles) {}

    public function index(Request $request): JsonResponse
    {
        return $this->run($request, fn () => $this->articles->listArticles(
            (int) $request->query('page', 1),
            (int) $request->query('per_page', 20),
            [
                'task_id'       => $request->query('task_id'),
                'status'        => $request->query('status'),
                'review_status' => $request->query('review_status'),
                'author_id'     => $request->query('author_id'),
                'search'        => $request->query('search'),
            ],
        ));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->run($request, fn () => $this->articles->createArticle($request->all()), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->articles->getArticle($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->articles->updateArticle($id, $request->all()));
    }

    public function review(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->articles->reviewArticle(
            $id,
            (string) $request->input('review_status', ''),
            (string) $request->input('review_note', ''),
            $this->auditAdminId($request),
        ));
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->articles->publishArticle($id));
    }

    public function trash(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->articles->trashArticle($id));
    }
}
