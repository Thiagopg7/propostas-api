<?php

namespace App\Http\Requests;

use App\Enums\ProposalOrigin;
use App\Enums\ProposalStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchProposalRequest extends FormRequest
{
    public const SORTABLE_COLUMNS = ['id', 'product', 'monthly_value', 'status', 'origin', 'created_at'];

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
            'status' => ['sometimes', Rule::enum(ProposalStatus::class)],
            'origin' => ['sometimes', Rule::enum(ProposalOrigin::class)],
            'client_id' => ['sometimes', 'integer', 'exists:clients,id'],
            'product' => ['sometimes', 'string', 'max:255'],
            'min_value' => ['sometimes', 'numeric', 'gte:0'],
            'max_value' => ['sometimes', 'numeric', 'gte:0'],
            'sort' => ['sometimes', Rule::in(self::SORTABLE_COLUMNS)],
            'order' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'integer' => 'O campo :attribute deve ser um número inteiro.',
            'numeric' => 'O campo :attribute deve ser um número.',
            'string' => 'O campo :attribute deve ser um texto.',
            'gte' => 'O campo :attribute deve ser maior ou igual a :value.',
            'max' => 'O campo :attribute deve ter no máximo :max caracteres.',
            'exists' => 'O :attribute informado não existe.',
            'status.enum' => 'O :attribute selecionado é inválido.',
            'origin.enum' => 'O :attribute selecionado é inválido.',
            'sort.in' => 'O campo :attribute deve ser uma das colunas permitidas.',
            'order.in' => 'O campo :attribute deve ser asc ou desc.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => 'status',
            'origin' => 'origem',
            'client_id' => 'cliente',
            'product' => 'produto',
            'min_value' => 'valor mínimo',
            'max_value' => 'valor máximo',
            'sort' => 'ordenação',
            'order' => 'direção',
        ];
    }
}
