<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class AlreadyCheckedInException extends Exception
{
    /**
     * Render the exception into an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'คุณได้ทำการเช็คชื่อเข้างานของวันนี้ไปแล้ว',
        ], 409);
    }
}

