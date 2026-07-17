<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAdministratorsAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{paginator: LengthAwarePaginator, sort_by: string, sort_dir: string, search: string}
     */
    public function execute(array $filters): array
    {
        $perPage = $filters['per_page'] ?? 10;
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $search = $this->normalizeSearchTerm((string) ($filters['search'] ?? ''));

        $query = User::query()
            ->role('admin', 'api')
            ->with(['roles:id,name', 'permissions:id,name']);

        $likeOperator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        if ($search !== '') {
            $searchPattern = '%'.$search.'%';
            $query->where(function ($builder) use ($searchPattern, $likeOperator): void {
                $builder
                    ->where('name', $likeOperator, $searchPattern)
                    ->orWhere('email', $likeOperator, $searchPattern);
            });
        }

        $paginator = $query
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();

        return [
            'paginator' => $paginator,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'search' => $search,
        ];
    }

    private function normalizeSearchTerm(string $search): string
    {
        $trimmed = trim($search);
        $withoutUserWildcards = trim($trimmed, '%');

        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $withoutUserWildcards);
    }
}
