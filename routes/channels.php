<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::routes(['middleware' => ['api', 'auth:sanctum']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.user.{id}', fn ($user, $id) => (int) $user->id === (int) $id);

Broadcast::channel('chat.presence', function ($user) {
    return [
        'id' => $user->id,
        'name' => $user->fullname ?: ($user->name ?: $user->email),
    ];
});

Broadcast::channel('chat.team.{id}', function ($user, $id) {
    return DB::table('tkt_group_user')
        ->where('group_id', $id)
        ->where('user_id', $user->id)
        ->exists();
});
