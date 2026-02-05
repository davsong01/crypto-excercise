<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use App\Services\HttpResponseService;

class BaseRequest extends FormRequest
{
    public function failedValidation(Validator $validator)
    {
        throw new ValidationException($validator, HttpResponseService::error(
            'Validation failed',
            $validator->errors(),
            '',
            422
        ));
    }
}
