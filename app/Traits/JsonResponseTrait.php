<?php

namespace App\Traits;

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
    
    protected function errorResponse($message, $code, $data=null)
    {
        return response()->json([
            'error' => true,
            'status' => 'error',
            'message' => $message,
            'data' => $data,
            'success' => false
        ], $code);
    }
}
