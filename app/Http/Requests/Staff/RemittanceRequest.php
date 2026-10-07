<?php

namespace App\Http\Requests\Staff;

use App\Enums\RemittanceMode;
use App\Models\Remittance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or update a remittance. The status is never accepted from the request.
 */
class RemittanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $remittance = $this->route('remittance');

        return $remittance
            ? $this->user()->can('update', $remittance)
            : $this->user()->can('create', Remittance::class);
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'reseller_id' => ['required', Rule::exists('resellers', 'id')->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'mode' => ['required', Rule::enum(RemittanceMode::class)],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'proof' => ['nullable', 'image', 'max:8192'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = collect($this->validated())->except('proof')->all();

        if ($this->hasFile('proof')) {
            $data['proof_path'] = $this->file('proof')->store('remittance-proofs', 'public');
        }

        return $data;
    }
}
