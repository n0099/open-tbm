<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class Handler extends \Illuminate\Foundation\Exceptions\Handler
{
    /**
     * Override parent method to replace validate fail redirect with global error info json format
     * @param  \Illuminate\Http\Request  $request
     */
    protected function convertValidationExceptionToResponse(ValidationException $e, $request): Response
    {
        return $e->response ?? response()->json([
            'errorCode' => 40000,
            'errorInfo' => $e->validator->getMessageBag()->getMessages(),
        ], 400);
    }
}
