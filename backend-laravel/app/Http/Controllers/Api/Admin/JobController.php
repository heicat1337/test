<?php

namespace App\Http\Controllers\Api\Admin;

use App\Services\Tasks\TaskLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobController extends AbstractAdminApiController
{
    public function __construct(private readonly TaskLifecycleService $tasks) {}

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->run($request, fn () => $this->tasks->getJob($id));
    }
}
