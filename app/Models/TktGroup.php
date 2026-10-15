<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'department_id', 'approver_id', 'created_by', 'updated_by'])]
class TktGroup extends Model
{
    use SoftDeletes;

    protected $table = 'tkt_groups';

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function approvers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tkt_group_approver', 'group_id', 'user_id');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'tkt_group_company', 'group_id', 'company_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tkt_group_user', 'group_id', 'user_id');
    }
}
