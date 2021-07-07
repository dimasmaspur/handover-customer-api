<?php

namespace App\Exceptions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;

class ExceptionTypeHandler
{
    public static function generalResponse($exception)
    {
        $responseData = [
            'error' => true,
            'code' => 500,
            'message' => $exception->getMessage(),
        ];

        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            $responseData = self::NotFoundHttpExceptionHandler($exception);
        } elseif ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            $responseData = self::HttpExceptionHandler($exception);
        } elseif ($exception instanceof \Illuminate\Validation\ValidationException) {
            $responseData = self::ValidationExceptionHandler($exception);
        } else {
            $responseData = self::FatalThrowableErrorHandler($exception);
        }

        return response()->json($responseData);
    }

    private static function NotFoundHttpExceptionHandler($exception)
    {
        return [
            'error' => true,
            'code' => $exception->getStatusCode(),
            'message' => 'Not Found! The specific API could not be found.'
        ];
    }

    private static function HttpExceptionHandler($exception)
    {
        $className = get_class($exception);
        $className = explode('\\', $className);
        $message = end($className) . ' on ' . Request::url();

        if (!env('APP_DEBUG')) {
            switch ($exception->getStatusCode()) {
                case 400:
                    $message = 'Bad Request! Your API request is invalid.';
                    break;
                case 401:
                    $message = 'Unauthorized! Your API key is wrong.';
                    break;
            }
        }

        if ($exception->getMessage())
            $message = $exception->getMessage();

        return [
            'error' => true,
            'code' => $exception->getStatusCode(),
            'message' => $message
        ];
    }

    private static function FatalThrowableErrorHandler($exception)
    {
        if (env('APP_DEBUG')) {
            $message = $exception->getMessage()
                . ' in ' . $exception->getFile()
                . ' on Line ' . $exception->getLine();
        } else {
            $message = 'Internal Server Error! We had a problem with our server. Try again later.';
        }

        return [
            'error' => true,
            'code' => 500,
            'message' => $message
        ];
    }

    private static function ValidationExceptionHandler($exception)
    {
        $message = implode(', ', Arr::flatten($exception->errors()));
        return [
            'error' => true,
            'code' => 200,
            'message' => $message,
            // 'message' => $exception->getMessage(),
            // 'message' => Arr::flatten($exception->errors())
            // 'validations' => Arr::flatten($exception->errors())
            // 'validations' => $exception->errors()
        ];
    }
}
