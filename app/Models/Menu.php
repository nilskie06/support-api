<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'parent_id',
    'code',
    'name',
    'icon',
    'url',
    'order',
    'status',
    'is_side_menu',
])]
class Menu extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tkt_menus';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'order' => 'integer',
            'status' => 'boolean',
            'is_side_menu' => 'boolean',
        ];
    }

    /**
     * Parent menu relationship.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Immediate children submenus ordered by sort index.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    /**
     * Recursive submenus for multi-level nesting.
     */
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    /**
     * Scope to fetch root menus only.
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('parent_id')->orWhere('parent_id', 0);
        })->orderBy('order');
    }

    /**
     * User access entries for this menu.
     */
    public function userAccess(): HasMany
    {
        return $this->hasMany(UserMenuAcces::class, 'menu_id');
    }
}
