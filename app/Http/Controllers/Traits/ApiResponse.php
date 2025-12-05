<?php

namespace App\Http\Controllers\Traits;

/**
 * Trait ApiResponse
 * Provee helpers para respuestas JSON estandarizadas.
 */
trait ApiResponse
{
    /**
     * Respuesta exitosa.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success($data = null, ?string $message = null, int $status = 200)
    {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if ($message) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    /**
     * Respuesta de error.
     *
     * @param string $message
     * @param mixed $errors
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error(string $message, $errors = null, int $status = 400)
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (!is_null($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
