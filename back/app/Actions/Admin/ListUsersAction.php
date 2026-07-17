<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListUsersAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{paginator: LengthAwarePaginator, sort_by: string, sort_dir: string, search: string}
     */
    public function execute(array $filters): array
    {
        $perPage = $filters['per_page'] ?? 10;
        $sortBy = $filters['sort_by'] ?? 'created_at';
        // Map frontend field names to database columns
        $sortBy = $sortBy === 'registered_at' ? 'created_at' : $sortBy;
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $search = $this->normalizeSearchTerm((string) ($filters['search'] ?? ''));

        $query = User::query()
            ->with('roles')
            ->role('user', 'api')
            ->whereDoesntHave('roles', function ($q): void {
                $q->where('name', 'admin');
            });

        $likeOperator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        if ($search !== '') {
            $searchPattern = '%'.$search.'%';
            $query->where(function ($builder) use ($searchPattern, $likeOperator): void {
                $builder
                    ->where('name', $likeOperator, $searchPattern)
                    ->orWhere('email', $likeOperator, $searchPattern);
            });
        }

        if (isset($filters['plan'])) {
            if ($filters['plan'] === 'premium') {
                $query->whereHas('roles', function ($q): void {
                    $q->where('name', 'premium');
                });
            } else {
                $query->whereDoesntHave('roles', function ($q): void {
                    $q->where('name', 'premium');
                });
            }
        }

        if (isset($filters['subscription_status'])) {
            if ($filters['subscription_status'] === 'active') {
                $query->where('subscription_ends_at', '>', now());
            } else {
                $query->where(function ($builder): void {
                    $builder->whereNull('subscription_ends_at')
                        ->orWhere('subscription_ends_at', '<=', now());
                });
            }
        }

        if (isset($filters['email_verified'])) {
            if ($filters['email_verified'] === 'verified') {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
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
