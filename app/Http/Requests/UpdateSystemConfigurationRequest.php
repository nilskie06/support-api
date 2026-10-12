<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemConfigurationRequest extends FormRequest
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
            'app_name' => ['sometimes', 'required', 'string', 'max:255'],
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'support_email' => ['sometimes', 'required', 'email', 'max:255'],
            'hotline' => ['sometimes', 'required', 'string', 'max:50'],
            'timezone' => ['sometimes', 'required', Rule::in([
                'UTC',
                'Asia/Singapore',
                'America/New_York',
                'Europe/London',
            ])],
            'date_format' => ['sometimes', 'required', Rule::in([
                'YYYY-MM-DD',
                'DD/MM/YYYY',
                'MM/DD/YYYY',
            ])],
            'maintenance_mode' => ['sometimes', 'required', 'boolean'],
            'footer_copyright_text' => ['sometimes', 'required', 'string', 'max:255'],
            'footer_credit_text' => ['sometimes', 'required', 'string', 'max:255'],
            'preset_color' => ['sometimes', 'required', Rule::in([
                'default',
                'emerald',
                'sapphire',
                'crimson',
                'amber',
                'purple',
                'rose',
                'slate',
                'custom',
            ])],
            'primary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'button_radius' => ['sometimes', 'required', Rule::in(['square', 'subtle', 'rounded', 'pill'])],
            'button_weight' => ['sometimes', 'required', Rule::in(['400', '500', '600', '700'])],
            'button_hover_effect' => ['sometimes', 'required', Rule::in(['none', 'lift', 'glow'])],
            'input_radius' => ['sometimes', 'required', Rule::in(['square', 'subtle', 'rounded', 'pill'])],
            'input_border_width' => ['sometimes', 'required', Rule::in(['1px', '2px'])],
            'input_style' => ['sometimes', 'required', Rule::in(['default', 'subtle'])],
            'input_focus_ring' => ['sometimes', 'required', Rule::in(['primary', 'subtle', 'minimal'])],
            'checkbox_radius' => ['sometimes', 'required', Rule::in(['square', 'subtle', 'circle'])],
            'checkbox_size' => ['sometimes', 'required', Rule::in(['compact', 'default', 'large'])],
            'checkbox_border_width' => ['sometimes', 'required', Rule::in(['1px', '1.5px', '2px'])],
        ];
    }
}
