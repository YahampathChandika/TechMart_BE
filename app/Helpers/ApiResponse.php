<?php

namespace App\Helpers;

class ApiResponse
{
    /**
     * Success response
     */
    public static function success($message = 'Success', $data = null, $meta = null, $statusCode = 200)
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if (!is_null($data)) {
            $response['data'] = $data;
        }

        if (!is_null($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Error response
     */
    public static function error($message = 'Error', $errors = null, $statusCode = 400)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Validation error response
     */
    public static function validationError($errors, $message = 'Validation failed')
    {
        return self::error($message, $errors, 422);
    }

    /**
     * Not found response
     */
    public static function notFound($message = 'Resource not found')
    {
        return self::error($message, null, 404);
    }

    /**
     * Unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized')
    {
        return self::error($message, null, 401);
    }

    /**
     * Forbidden response
     */
    public static function forbidden($message = 'Access denied')
    {
        return self::error($message, null, 403);
    }

    /**
     * Server error response
     */
    public static function serverError($message = 'Internal server error')
    {
        return self::error($message, null, 500);
    }

    /**
     * Paginated response
     */
    public static function paginated($data, $message = 'Data retrieved successfully')
    {
        $meta = [
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
        ];

        return self::success($message, $data->items(), $meta);
    }
}