<?php

namespace App\Http\Requests;

use App\Enums\ProposalOrigin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProposalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'product' => ['required', 'string', 'max:255'],
            'monthly_value' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'origin' => ['required', Rule::enum(ProposalOrigin::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'integer' => 'O campo :attribute deve ser um número inteiro.',
            'numeric' => 'O campo :attribute deve ser um número.',
            'gt' => 'O campo :attribute deve ser maior que :value.',
            'decimal' => 'O campo :attribute deve ter no máximo duas casas decimais.',
            'string' => 'O campo :attribute deve ser um texto.',
            'product.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'monthly_value.max' => 'O campo :attribute não pode ser maior que :max.',
            'exists' => 'O :attribute informado não existe.',
            'origin.enum' => 'O :attribute selecionado é inválido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'client_id' => 'cliente',
            'product' => 'produto',
            'monthly_value' => 'valor mensal',
            'origin' => 'origem',
        ];
    }
}
