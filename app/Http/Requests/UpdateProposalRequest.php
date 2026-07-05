<?php

namespace App\Http\Requests;

use App\Enums\ProposalOrigin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProposalRequest extends FormRequest
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
            'version' => ['required', 'integer', 'min:1'],
            'product' => ['required_without_all:monthly_value,origin', 'string', 'max:255'],
            'monthly_value' => ['required_without_all:product,origin', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'origin' => ['required_without_all:product,monthly_value', Rule::enum(ProposalOrigin::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'required_without_all' => 'Informe ao menos um campo para atualização (produto, valor mensal ou origem).',
            'integer' => 'O campo :attribute deve ser um número inteiro.',
            'min' => 'O campo :attribute deve ser no mínimo :min.',
            'numeric' => 'O campo :attribute deve ser um número.',
            'gt' => 'O campo :attribute deve ser maior que :value.',
            'decimal' => 'O campo :attribute deve ter no máximo duas casas decimais.',
            'string' => 'O campo :attribute deve ser um texto.',
            'product.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'monthly_value.max' => 'O campo :attribute não pode ser maior que :max.',
            'origin.enum' => 'A :attribute selecionada é inválida.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'version' => 'versão',
            'product' => 'produto',
            'monthly_value' => 'valor mensal',
            'origin' => 'origem',
        ];
    }
}
