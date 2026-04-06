<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function success(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function created(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function error(string $message = 'Error', int $code = 400): JsonResponse
    {
        return response()->json(['message' => $message], $code);
    }

    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404);
    }
}
