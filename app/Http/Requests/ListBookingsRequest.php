<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class ListBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    public function from(): ?CarbonImmutable
    {
        return $this->filled('from')
            ? CarbonImmutable::parse($this->input('from'))->utc()
            : null;
    }

    public function to(): ?CarbonImmutable
    {
        return $this->filled('to')
            ? CarbonImmutable::parse($this->input('to'))->utc()
            : null;
    }
}
