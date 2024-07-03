<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * Override parent method to replace validate fail redirect with global error info json format
     *
     * @param  \Illuminate\Validation\ValidationException  $e
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function convertValidationExceptionToResponse(\Illuminate\Validation\ValidationException $e, $request)
    {
        if ($e->response) {
            return $e->response;
        }

        return response()->json([
            'errorCode' => 40000,
            'errorInfo' => $e->validator->getMessageBag()->getMessages()
        ], 400);
    }
}
