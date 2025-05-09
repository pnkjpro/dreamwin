<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait JsonResponseTrait
{
    protected function successResponse($data, $message = null, $code = 200)
    {
        return response()->json([
            'error' => false,
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'success' => true
        ], $code);
    }
    
    protected function errorResponse($data, $message = null, $code = 422)
    {
        return response()->json([
            'error' => true,
            'status' => 'error',
            'message' => $message,
            'data' => $data,
            'success' => false
        ], $code);
    }

    protected function exceptionHandler($err, $message = null, $code = 500)
    {
        \Log::channel('exception')->error("Exception:", [
            'line' => $err->getLine(),
            'errorMessage' => $err->getMessage(),
            'file' => $err->getFile()
        ]);
        
        return response()->json([
            'error' => true,
            'status' => 'error',
            'message' => $message,
            'success' => false
        ], $code);
    }
}
