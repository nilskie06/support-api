<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\IssueSeverityController;
use App\Http\Controllers\Api\IssueTypeController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\SystemConfigurationController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\SystemApiKeyController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TktGroupController;
use App\Http\Controllers\Api\TktTypeController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

Route::get('/menus', [MenuController::class, 'index']);
Route::post('/menus', [MenuController::class, 'store']);
Route::post('/menus/reorder', [MenuController::class, 'reorder']);
Route::get('/menus/{id}', [MenuController::class, 'show']);
Route::put('/menus/{id}', [MenuController::class, 'update']);
Route::delete('/menus/{id}', [MenuController::class, 'destroy']);

Route::get('/tickets', [TicketController::class, 'index']);
Route::get('/tickets/analytics', [TicketController::class, 'analytics']);
Route::get('/tickets/lookups', [TicketController::class, 'lookups']);
Route::post('/tickets', [TicketController::class, 'store']);
Route::post('/tickets/bulk-delete', [TicketController::class, 'bulkDestroy']);
Route::get('/tickets/{id}', [TicketController::class, 'show']);
Route::post('/tickets/{id}', [TicketController::class, 'update']);
Route::post('/tickets/{id}/comments', [TicketController::class, 'storeComment'])->middleware('auth:sanctum');
Route::delete('/tickets/{id}/comments/{commentId}', [TicketController::class, 'deleteComment'])->middleware('auth:sanctum');
Route::post('/tickets/{id}/resolution', [TicketController::class, 'saveResolution'])->middleware('auth:sanctum');
Route::delete('/tickets/{id}/resolution/{resolutionId}', [TicketController::class, 'deleteResolution'])->middleware('auth:sanctum');
Route::put('/tickets/{id}', [TicketController::class, 'update']);
Route::delete('/tickets/{id}', [TicketController::class, 'destroy']);
Route::delete('/tickets/{ticketId}/attachments/{attachmentId}', [TicketController::class, 'destroyAttachment']);

Route::get('/boards', [BoardController::class, 'index']);
Route::post('/boards', [BoardController::class, 'store']);
Route::get('/boards/{id}', [BoardController::class, 'show']);
Route::put('/boards/{id}', [BoardController::class, 'update']);
Route::delete('/boards/{id}', [BoardController::class, 'destroy']);
Route::post('/boards/{id}/statuses', [BoardController::class, 'storeStatus']);
Route::put('/boards/{id}/statuses/{statusId}', [BoardController::class, 'updateStatus']);
Route::delete('/boards/{id}/statuses/{statusId}', [BoardController::class, 'destroyStatus']);
Route::post('/boards/{id}/statuses/reorder', [BoardController::class, 'reorderStatuses']);

Route::get('/issue-types', [IssueTypeController::class, 'index']);
Route::post('/issue-types', [IssueTypeController::class, 'store']);
Route::get('/issue-types/{id}', [IssueTypeController::class, 'show']);
Route::put('/issue-types/{id}', [IssueTypeController::class, 'update']);
Route::delete('/issue-types/{id}', [IssueTypeController::class, 'destroy']);

Route::get('/tkt-types', [TktTypeController::class, 'index']);
Route::post('/tkt-types/register-systems', [TktTypeController::class, 'registerSystems']);
Route::post('/tkt-types', [TktTypeController::class, 'store']);
Route::get('/tkt-types/{id}', [TktTypeController::class, 'show']);
Route::put('/tkt-types/{id}', [TktTypeController::class, 'update']);
Route::delete('/tkt-types/{id}', [TktTypeController::class, 'destroy']);

Route::get('/groups/lookups', [TktGroupController::class, 'lookups']);
Route::get('/groups', [TktGroupController::class, 'index']);
Route::post('/groups', [TktGroupController::class, 'store']);
Route::get('/groups/{id}', [TktGroupController::class, 'show']);
Route::put('/groups/{id}', [TktGroupController::class, 'update']);
Route::delete('/groups/{id}', [TktGroupController::class, 'destroy']);

Route::get('/severities', [IssueSeverityController::class, 'index']);
Route::post('/severities', [IssueSeverityController::class, 'store']);
Route::get('/severities/{id}', [IssueSeverityController::class, 'show']);
Route::put('/severities/{id}', [IssueSeverityController::class, 'update']);
Route::delete('/severities/{id}', [IssueSeverityController::class, 'destroy']);

Route::get('/issue-severities', [IssueSeverityController::class, 'index']);
Route::post('/issue-severities', [IssueSeverityController::class, 'store']);
Route::get('/issue-severities/{id}', [IssueSeverityController::class, 'show']);
Route::put('/issue-severities/{id}', [IssueSeverityController::class, 'update']);
Route::delete('/issue-severities/{id}', [IssueSeverityController::class, 'destroy']);

Route::get('/companies', [CompanyController::class, 'index']);
Route::post('/companies', [CompanyController::class, 'store']);
Route::get('/companies/{id}', [CompanyController::class, 'show']);
Route::put('/companies/{id}', [CompanyController::class, 'update']);
Route::delete('/companies/{id}', [CompanyController::class, 'destroy']);

Route::get('/systems', [SystemController::class, 'index']);
Route::post('/systems', [SystemController::class, 'store']);
Route::get('/systems/{id}', [SystemController::class, 'show']);
Route::put('/systems/{id}', [SystemController::class, 'update']);
Route::delete('/systems/{id}', [SystemController::class, 'destroy']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/systems/{system}/api-keys', [SystemApiKeyController::class, 'index']);
    Route::post('/systems/{system}/api-keys', [SystemApiKeyController::class, 'store']);
    Route::delete('/api-keys/{apiKey}', [SystemApiKeyController::class, 'revoke']);
});

Route::middleware('integration.key:tickets.create')->post('/integrations/tickets', function (\Illuminate\Http\Request $request, TicketController $controller) {
    $request->merge(['system' => $request->attributes->get('integration_api_key')->system->code]);
    return $controller->store($request);
});
Route::middleware('integration.key:users.enroll')->post('/integrations/users', [SystemApiKeyController::class, 'enroll']);
Route::middleware('integration.key:lookups.read')->get('/integrations/lookups/{resource}', function (string $resource) {
    $model = match ($resource) {
        'categories' => \App\Models\Board::class,
        'ticket-types' => \App\Models\IssueType::class,
        'issue-types' => \App\Models\TktType::class,
        'systems' => \App\Models\System::class,
        'departments' => \App\Models\Department::class,
        'stations' => \App\Models\Station::class,
        'severities' => \App\Models\IssueSeverity::class,
        default => null,
    };
    abort_unless($model, 404, 'Unknown lookup resource.');
    return response()->json(['data' => $model::query()->get()]);
});

Route::get('/departments', [DepartmentController::class, 'index']);
Route::post('/departments', [DepartmentController::class, 'store']);
Route::get('/departments/{id}', [DepartmentController::class, 'show']);
Route::put('/departments/{id}', [DepartmentController::class, 'update']);
Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

Route::get('/stations', [StationController::class, 'index']);
Route::post('/stations', [StationController::class, 'store']);
Route::get('/stations/{id}', [StationController::class, 'show']);
Route::put('/stations/{id}', [StationController::class, 'update']);
Route::delete('/stations/{id}', [StationController::class, 'destroy']);

Route::get('/users/lookups', [UserController::class, 'lookups']);
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);
Route::post('/users/{id}/assignments', [UserController::class, 'syncAssignments']);
Route::get('/users/{id}/menu-access', [UserController::class, 'getMenuAccess']);
Route::post('/users/{id}/menu-access', [UserController::class, 'syncMenuAccess']);

Route::get('/roles', [RoleController::class, 'index']);
Route::post('/roles', [RoleController::class, 'store']);
Route::get('/roles/{id}', [RoleController::class, 'show']);
Route::put('/roles/{id}', [RoleController::class, 'update']);
Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
Route::get('/roles/{id}/matrix', [RoleController::class, 'getMatrix']);
Route::post('/roles/{id}/matrix', [RoleController::class, 'saveMatrix']);
Route::post('/roles/{id}/copy-from', [RoleController::class, 'copyFrom']);

Route::get('/permissions', [PermissionController::class, 'index']);
Route::post('/permissions', [PermissionController::class, 'store']);
Route::get('/permissions/{id}', [PermissionController::class, 'show']);
Route::put('/permissions/{id}', [PermissionController::class, 'update']);
Route::delete('/permissions/{id}', [PermissionController::class, 'destroy']);

Route::get('/system-branding', [SystemConfigurationController::class, 'branding']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/chat/users', [ChatController::class, 'users']);
    Route::get('/chat/teams', [ChatController::class, 'teams']);
    Route::get('/chat/messages', [ChatController::class, 'messages']);
    Route::post('/chat/messages', [ChatController::class, 'send']);
    Route::post('/chat/messages/{id}/reactions', [ChatController::class, 'toggleReaction']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/chat/read', [NotificationController::class, 'readChat']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/user/unlock', [AuthController::class, 'unlock']);
    Route::get('/user/preferences', [AuthController::class, 'getPreferences']);
    Route::put('/user/preferences', [AuthController::class, 'updatePreferences']);
    Route::get('/user/menu-access', [UserController::class, 'currentUserMenuAccess']);
    Route::get('/user/systems', [UserController::class, 'currentUserSystems']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/system-configuration', [SystemConfigurationController::class, 'show']);
    Route::put('/system-configuration', [SystemConfigurationController::class, 'update']);
});
