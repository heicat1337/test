<?php

namespace App\Http\Controllers\Api\Admin;

use App\Services\Catalog\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends AbstractAdminApiController
{
    public function __construct(private readonly CatalogService $catalog) {}

    public function index(Request $request): JsonResponse
    {
        return $this->run($request, fn () => $this->catalog->getCatalog());
    }
}
