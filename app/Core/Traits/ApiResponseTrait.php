<?php

namespace App\Core\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
  protected function success(mixed $data, string $message = 'Success', int $code = 200): JsonResponse
  {
    return response()->json([
      'status'  => 'success',
      'message' => $message,
      'data'    => $data,
    ], $code);
  }

  protected function created(mixed $data, string $message = 'Created'): JsonResponse
  {
    return $this->success($data, $message, 201);
  }

  protected function error(string $message, int $code = 400, mixed $errors = null): JsonResponse
  {
    return response()->json([
      'status'  => 'error',
      'message' => $message,
      'errors'  => $errors,
    ], $code);
  }

  protected function noContent(): JsonResponse
  {
    return response()->json(null, 204);
  }
}
