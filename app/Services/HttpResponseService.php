<?php

namespace App\Services;

use Illuminate\Support\MessageBag;

class HttpResponseService
{
    public static function success(string $message, $data = [], int $statusCode = 200)
    {
        return response()->json([
            'status'  => true,
            'message' => $message,
            'data'    => $data
        ], $statusCode);
    }

    public static function error(string $message, $errors = [], string $errorType = 'general', int $statusCode = 400)
    {
        if ($errors instanceof MessageBag) {
            $errors = $errors->toArray();
        }

        // If flat indexed array like errors()->all()
        elseif (is_array($errors) && array_keys($errors) === range(0, count($errors) - 1)) {
            $errors =  [$errors];
        }

        return response()->json([
            'status'     => false,
            'error_type' => $errorType,
            'message'    => $message,
            'errors'     => (object) $errors
        ], $statusCode);
    }

    public static function fatalError(string $message, $errors = [], int $statusCode = 500)
    {
        $randomErrorCode = 'C'.rand(111111111, 99999999);

        logger()->error('Server Error', [
            "Error {$randomErrorCode} occurred.",
            "errors" => $errors
        ]);

        return self::error( "An error {$randomErrorCode} occured, Please try again",[], "fatal", $statusCode);
    }

    public static function unauthorized(string $message = 'Unauthorized')
    {
        return self::error($message, [], 'general', 401);
    }

    public static function validationError(string $message = 'Validation failed', $errors = [], int $statusCode = 422)
    {
        return self::error($message, $errors, 'validation', $statusCode);
    }
}
