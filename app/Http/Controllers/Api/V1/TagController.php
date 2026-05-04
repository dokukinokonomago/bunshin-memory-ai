<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TagContextRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Support\TenantUserContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function index(TagContextRequest $request): AnonymousResourceCollection
    {
        $context = TenantUserContext::fromUser($request->user());

        $tags = Tag::queryForContext($context)
            ->withCount('memories')
            ->orderByDesc('memories_count')
            ->orderBy('name')
            ->get();

        return TagResource::collection($tags);
    }
}
