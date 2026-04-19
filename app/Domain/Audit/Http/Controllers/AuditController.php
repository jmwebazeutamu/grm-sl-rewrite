<?php

declare(strict_types=1);

namespace App\Domain\Audit\Http\Controllers;

use App\Domain\Audit\Http\Resources\AuditResource;
use App\Domain\Audit\Models\AuditEntry;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $entries = QueryBuilder::for(AuditEntry::class)
            ->allowedFilters([
                AllowedFilter::exact('action'),
                AllowedFilter::exact('actor_id'),
                AllowedFilter::scope('from', 'occurredAfter'),
                AllowedFilter::scope('to', 'occurredBefore'),
            ])
            ->allowedSorts(['occurred_at'])
            ->defaultSort('-occurred_at')
            ->with('actor:id,name')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/Audit/Index', [
            'entries' => AuditResource::collection($entries),
            'filters' => $request->only('filter'),
            'known_actions' => AuditEntry::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
        ]);
    }
}
