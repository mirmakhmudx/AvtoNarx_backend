<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\PublicApi\FilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FilterController extends Controller
{
    public function __construct(
        private readonly FilterService $filterService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $brandSlug = $request->query('brand');
        $brand = null;

        if ($brandSlug !== null) {
            $brand = Brand::query()->active()->where('slug', $brandSlug)->first();

            if ($brand === null) {
                return response()->json(array('message' => 'Marka topilmadi.'), 404);
            }
        }

        $filters = $this->filterService->getFilters($brand);

        return response()->json(array(
            'data' => array(
                'brand' => $brand ? array('id' => $brand->id, 'slug' => $brand->slug, 'name' => $brand->name) : null,
                'years' => $filters['years'],
                'regions' => $filters['regions'],
            ),
        ));
    }
}
