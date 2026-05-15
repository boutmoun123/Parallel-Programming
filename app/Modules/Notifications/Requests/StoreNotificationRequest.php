<?php

namespace App\Modules\Notifications\Requests;

use App\Models\Notification;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer'],
            'order_id' => ['nullable', 'integer'],
            'type' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string'],
            'status' => ['nullable', Rule::in([
                Notification::STATUS_PENDING,
                Notification::STATUS_SENT,
                Notification::STATUS_FAILED,
            ])],
            'sent_at' => ['nullable', 'date'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation error',
            'data' => null,
            'errors' => $validator->errors(),
        ], 422));
    }
}
