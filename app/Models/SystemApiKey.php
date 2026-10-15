<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemApiKey extends Model
{
    protected $fillable = ['system_id', 'created_by', 'name', 'key_prefix', 'key_hash', 'abilities', 'last_used_at', 'revoked_at'];
    protected function casts(): array { return ['abilities' => 'array', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime']; }
    public function system(): BelongsTo { return $this->belongsTo(System::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
