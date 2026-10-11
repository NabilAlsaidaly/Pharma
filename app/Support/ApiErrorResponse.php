<?php

namespace App\Support;

use App\Domains\Cart\Domain\Exceptions\CartConflict;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ApiErrorResponse
{
    public static function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api', 'api/*')) {
            return null;
        }

        $status = match (true) {
            $exception instanceof CartConflict => 409,
            $exception instanceof QueryException && in_array($exception->errorInfo[1] ?? null, [1205, 1213], true) => 503,
            $exception instanceof ValidationException => 422,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };

        [$code, $message] = match ($status) {
            400 => ['BAD_REQUEST', 'الطلب غير صالح.'],
            401 => ['UNAUTHENTICATED', 'يجب تسجيل الدخول.'],
            403 => ['FORBIDDEN', 'لا تملك صلاحية تنفيذ هذا الطلب.'],
            404 => ['RESOURCE_NOT_FOUND', 'المورد المطلوب غير موجود.'],
            405 => ['METHOD_NOT_ALLOWED', 'طريقة الطلب غير مسموحة.'],
            409 => ['RESOURCE_CONFLICT', 'يوجد تعارض في الطلب.'],
            413 => ['PAYLOAD_TOO_LARGE', 'حجم الطلب يتجاوز الحد المسموح.'],
            419 => ['SESSION_EXPIRED', 'انتهت صلاحية الجلسة.'],
            422 => ['VALIDATION_FAILED', 'بيانات الطلب غير صالحة.'],
            429 => ['RATE_LIMITED', 'يرجى المحاولة لاحقاً.'],
            503 => ['SERVICE_UNAVAILABLE', 'الخدمة غير متاحة مؤقتاً.'],
            default => ['INTERNAL_ERROR', 'حدث خطأ داخلي. يرجى المحاولة لاحقاً.'],
        };

        // Preserve protocol headers such as Retry-After and Allow, never exception messages.
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => $exception instanceof ValidationException ? (object) $exception->errors() : ($exception instanceof CartConflict ? (object) ['selection' => [$exception->reason]] : (object) []),
            ],
            'request_id' => $request->attributes->get('request_id'),
        ], $status, $headers);
    }
}
