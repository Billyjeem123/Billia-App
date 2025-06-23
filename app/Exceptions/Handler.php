<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\AuthenticationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request  $request
     * @param Throwable $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ModelNotFoundException) {
            return $this->modelNotFoundResponse($exception);
        }

        if ($exception instanceof NotFoundHttpException) {
            return $this->notFoundResponse($exception);
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return $this->methodNotAllowedResponse($exception);
        }

        if ($exception instanceof UnauthorizedException) {
            return $this->unauthorizedResponse($exception);
        }
        if ($exception instanceof AuthenticationException ) {
            return $this->unauthenticated($request, $exception);
        }

        if ($exception instanceof ValidationException) {
            return $this->ValidationError($exception);
        }

        if ($exception instanceof Throwable) {
            return $this->internalServerErrorResponse($exception);
        }




        return parent::render($request, $exception);
    }




    protected function internalServerErrorResponse(Throwable $exception): JsonResponse
    {
        \Log::error('Internal Server Error: ' . $exception->getMessage());

        return response()->json([
            'errors' => [
                'code' => '500',
                'details' => 'An internal server error occurred. Please try again later.',
                'message' => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
                'source' => [
                    'pointer' => url()->current()
                ]
            ]
        ], 500);
    }

    protected function modelNotFoundResponse(ModelNotFoundException $exception): JsonResponse
    {
        return response()->json([
            'errors' => [
                'code' => '404',
                'details' => 'We cannot find the request you requested for.',
                'source' => [
                    'pointer' => url()->current()
                ]
            ]
        ], 404);
    }

    protected function ValidationError(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->readableErrors($exception->validator),  // Use readableError method
        ], 422);
    }

    public function readableErrors($validator)
    {
        return Arr::flatten($validator->messages()->get('*'));  // Flatten all messages
    }

    protected function notFoundResponse(NotFoundHttpException $exception): JsonResponse
    {
        return response()->json([
            'errors' => [
                'code' => '404',
                'details' => 'We cannot find the request you requested for.',
                'source' => [
                    'pointer' => url()->current()
                ]
            ]
        ], 404);
    }


    /**
     * Create a custom response for UnauthorizedException.
     *
     * @param UnauthorizedException $exception
     * @return JsonResponse
     */
    protected function unauthorizedResponse(UnauthorizedException $exception): JsonResponse
    {
        return response()->json([
            'errors' => [
                'code' => '403',
                'details' => 'You do not have the necessary permissions to access this resource.',
                'source' => [
                    'pointer' => url()->current()
                ]
            ]
        ], 403);
    }

    protected function methodNotAllowedResponse(MethodNotAllowedHttpException $exception): JsonResponse
    {
        return response()->json([
            'errors' => [
                'code' => '405',
                'details' => 'The method is not allowed for the requested URL.',
                'source' => [
                    'pointer' => url()->current()
                ]
            ]
        ], 405);
    }


    protected function unauthenticated($request, AuthenticationException $exception)
    {
        Log::error("Unauthenticated request detected: " . $request->url());
        Log::info("Headers: ", $request->headers->all());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'You must be logged in to access this resource.',
            ], 401);
        }

        return response()->json(['success' => false, 'message' => 'Please log in to access this resource.'], 401);
    }


}
