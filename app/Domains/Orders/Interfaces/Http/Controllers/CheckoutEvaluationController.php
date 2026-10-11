<?php

namespace App\Domains\Orders\Interfaces\Http\Controllers;

use App\Domains\Orders\Application\EvaluateCheckout;
use App\Domains\Orders\Interfaces\Http\Requests\CheckoutEvaluationRequest;
use Illuminate\Http\JsonResponse;

class CheckoutEvaluationController
{
    public function __construct(private readonly EvaluateCheckout $evaluate) {}

    public function __invoke(CheckoutEvaluationRequest $request): JsonResponse
    {
        $input = $request->validated();

        return response()->json(['success' => true, 'message' => 'تم تقييم السلة مبدئياً.',
            'data' => $this->evaluate->execute($request->user('customer')->getAuthIdentifier(), $input['currency'], $input['expected_cart_version'])]);
    }
}
