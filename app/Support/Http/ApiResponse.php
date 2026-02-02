<?php

namespace App\Support\Http;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        if ($data instanceof JsonResource) {
            return $data
                ->additional([
                    'success' => true,
                    'meta'    => $meta,
                ])
                ->response()
                ->setStatusCode($status);
        }

        if ($data instanceof Paginator) {
            return response()->json([
                'success' => true,
                'data'    => $data->items(),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => method_exists($data, 'total') ? $data->total() : null,
                ],
            ], $status);
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => $meta,
        ], $status);
    }

    public static function error(
        string|array $message,
        int $status = 400,
        ?string $code = null
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'data'    => null,
            'meta'    => null,
            'errors'  => is_array($message)
                ? $message
                : [
                    'message' => $message,
                    'code'    => $code,
                ],
        ], $status);
    }
}
