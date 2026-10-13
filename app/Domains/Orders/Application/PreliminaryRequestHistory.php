<?php

namespace App\Domains\Orders\Application;

use App\Domains\Orders\Infrastructure\Persistence\Models\CheckoutIntent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PreliminaryRequestHistory
{
    public function listing(string $customerId, int $page, int $perPage): LengthAwarePaginator
    {
        return CheckoutIntent::query()->select(['id', 'customer_id', 'status', 'currency', 'created_at'])->where('customer_id', $customerId)
            ->withCount('items')->orderByDesc('created_at')->orderBy('id')->paginate($perPage, ['*'], 'page', $page);
    }

    public function detail(string $customerId, string $id): CheckoutIntent
    {
        return CheckoutIntent::query()->where('customer_id', $customerId)->whereKey($id)->with('items')->withCount('items')->firstOrFail();
    }
}
