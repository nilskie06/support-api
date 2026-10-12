<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'configuration_key',
    'app_name',
    'company_name',
    'support_email',
    'hotline',
    'timezone',
    'date_format',
    'maintenance_mode',
    'footer_copyright_text',
    'footer_credit_text',
    'preset_color',
    'primary_color',
    'button_radius',
    'button_weight',
    'button_hover_effect',
    'input_radius',
    'input_border_width',
    'input_style',
    'input_focus_ring',
    'checkbox_radius',
    'checkbox_size',
    'checkbox_border_width',
])]
class SystemConfiguration extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tkt_system_configurations';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'configuration_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'maintenance_mode' => 'boolean',
        ];
    }
}
