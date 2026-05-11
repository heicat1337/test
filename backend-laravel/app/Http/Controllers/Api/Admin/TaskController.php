<?php

namespace App\Http\Controllers\Api\Admin;

use App\Services\Tasks\TaskLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends AbstractAdminApiController
{
    public function __construct(private readonly TaskLifecycleService $tasks) {}

    public function index(Request $request): JsonResponse
    {
        return $this->run($request, fn () => $this->tasks->listTasks(
            (int) $request->query('page', 1),
            (int) $request->query('per_page', 20),
            [
                'status' => $request->query('status'),
                'search' => $request->query('search'),
            ],
        ));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->run($request, fn () => $this->tasks->createTask($request->all()), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->tasks->getTask($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->tasks->updateTask($id, $request->all()));
    }

    public function start(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->tasks->startTask(
            $id,
            (bool) $request->boolean('enqueue_now'),
        ));
    }

    public function stop(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->tasks->stopTask($id));
    }

    public function enqueue(Request $request, int $id): JsonResponse
    {
        $payload = $request->all();
        $jobType = (string) ($payload['job_type'] ?? 'generate_article');
        unset($payload['job_type']);
        return $this->run(
            $request,
            fn () => $this->tasks->enqueueTask($id, $jobType, $payload),
            201,
        );
    }

    public function jobs(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->tasks->listTaskJobs(
            $id,
            $request->query('status'),
            (int) $request->query('limit', 20),
        ));
    }
}
