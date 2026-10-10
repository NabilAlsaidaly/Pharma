<?php

namespace App\Domains\Identity\Interfaces\Http\Middleware;

use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use App\Domains\Identity\Domain\Exceptions\AccountSuspended;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use Closure;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCustomer
{
    public function __construct(private readonly Factory $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');
        if ($customer instanceof Customer && $customer->account_status === CustomerAccountStatus::Suspended) {
            $this->auth->guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Log::warning('customer_login_blocked_suspended', ['customer_id' => $customer->id, 'outcome' => 'blocked']);
            throw new AccountSuspended;
        }

        return $next($request);
    }
}
