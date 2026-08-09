<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnmatchedCandidates\BulkIgnoreCandidatesRequest;
use App\Http\Requests\UnmatchedCandidates\ResolveCandidateRequest;
use App\Http\Resources\UnmatchedCandidates\UnmatchedCandidateResource;
use App\Models\UnmatchedBrandModelCandidate;
use App\Services\Parser\UnmatchedCandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnmatchedCandidateController extends Controller
{
    public function __construct(
        private readonly UnmatchedCandidateService $candidateService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', UnmatchedBrandModelCandidate::class);

        $brandFilter = $request->query('brand');

        return UnmatchedCandidateResource::collection(
            $this->candidateService->listPending($brandFilter)
        );
    }

    public function countsByBrand(): JsonResponse
    {
        $this->authorize('viewAny', UnmatchedBrandModelCandidate::class);

        return response()->json([
            'brands' => $this->candidateService->pendingCountsByBrand(),
        ]);
    }

    public function resolve(ResolveCandidateRequest $request, UnmatchedBrandModelCandidate $unmatchedCandidate): JsonResponse
    {
        $this->authorize('resolve', $unmatchedCandidate);

        $data = $request->validated();

        $carModel = $this->candidateService->resolve(
            $unmatchedCandidate,
            $data['brand_id'],
            $data['model_name'],
            $data['model_slug'],
            $data['production_from'] ?? null,
        );

        return response()->json([
            'message' => 'Model yaratildi va parser target faollashtirildi.',
            'car_model_id' => $carModel->id,
        ]);
    }

    public function ignore(UnmatchedBrandModelCandidate $unmatchedCandidate): JsonResponse
    {
        $this->authorize('ignore', $unmatchedCandidate);

        $this->candidateService->ignore($unmatchedCandidate);

        return response()->json(['message' => 'Candidate e\'tiborsiz qoldirildi.']);
    }

    public function bulkIgnore(BulkIgnoreCandidatesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', UnmatchedBrandModelCandidate::class);

        $count = $this->candidateService->bulkIgnore($request->validated()['ids']);

        return response()->json([
            'message' => $count.' ta candidate e\'tiborsiz qoldirildi.',
            'count' => $count,
        ]);
    }

    public function ignoreAllPending(Request $request): JsonResponse
    {
        $this->authorize('viewAny', UnmatchedBrandModelCandidate::class);

        $brandFilter = $request->query('brand');

        $count = $this->candidateService->ignoreAllPending($brandFilter);

        return response()->json([
            'message' => $count.' ta candidate e\'tiborsiz qoldirildi.',
            'count' => $count,
        ]);
    }
}
