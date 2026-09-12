<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryTreeResource;
use App\Services\CategoryTreeService;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryTreeService $categoryTree
    ) {
    }

    /**
     * Get the active category tree with no depth limit.
     */
    public function index()
    {
        return CategoryTreeResource::collection(
            $this->categoryTree->tree()
        );
    }
}
