<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

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

        $errorData = [
            'type'       => get_class($err),
            'message'    => $err->getMessage(),
            'file'       => $err->getFile(),
            'line'       => $err->getLine(),
            'code'       => $err->getCode(),
            'trace'      => $err->getTraceAsString(),
            'url'        => request()->fullUrl(),
            'method'     => request()->method(),
            'ip'         => request()->ip(),
            'user_id'    => auth()->id() ?? 'Guest',
            'timestamp'  => now()->toDateTimeString(),
        ];

        $officialEmail = Config::get('himpri.constant.email.official');
        \Mail::to($officialEmail)->send(new \App\Mail\ExceptionReportMail($errorData));
        
        return response()->json([
            'error' => true,
            'status' => 'error',
            'message' => $message,
            'success' => false
        ], $code);
    }
}
