<?php

namespace App\Core\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
  public function register(): void
  {
    $this->renderable(function (NotFoundHttpException $e) {
      return response()->json(['status' => 'error', 'message' => 'Resource not found.'], 404);
    });

    $this->renderable(function (AuthenticationException $e) {
      return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
    });

    $this->renderable(function (ValidationException $e) {
      return response()->json([
        'status'  => 'error',
        'message' => 'Validation failed.',
        'errors'  => $e->errors(),
      ], 422);
    });
  }
}
