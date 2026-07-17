<?php

use App\Http\Controllers\Api\Admin\AdministratorController as AdminAdministratorController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminUserPlanController;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\FileUploadController as AdminFileUploadController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetAdjustmentController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\FixedExpenseController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\LearnController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OccurrenceController;
use App\Http\Controllers\Api\OtherPaymentMethodController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PushNotificationController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\ResendVerificationController;
use App\Http\Controllers\Api\ResetPasswordController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\UserDefaultWorkspaceController;
use App\Http\Controllers\Api\VerifyEmailController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\Api\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Public auth routes
    Route::post('/auth/user/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/auth/admin/login', [AdminAuthController::class, 'login']);
    Route::post('/auth/register', RegisterController::class);

    // Email verification (public — signed URL validates identity)
    Route::get('/auth/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->name('verification.verify');

    // Resend verification (public — requires email in body, returns 200 always)
    Route::post('/auth/email/resend', ResendVerificationController::class);

    // Password reset (public — token validates identity)
    Route::post('/auth/password/forgot', ForgotPasswordController::class);
    Route::post('/auth/password/reset', ResetPasswordController::class);

    // Two-factor authentication (public — session_token validates identity)
    Route::post('/auth/user/verify-2fa', [TwoFactorController::class, 'verify'])->middleware('throttle:auth-2fa-verify');
    Route::post('/auth/user/resend-2fa', [TwoFactorController::class, 'resend'])->middleware('throttle:auth-2fa-resend');

    Route::get('/learn', [LearnController::class, 'index']);
    Route::get('/learn/features/{slug}', [LearnController::class, 'showFeature']);
    Route::get('/learn/{slug}', [LearnController::class, 'show']);

    Route::middleware('auth:api')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/push/devices', [PushNotificationController::class, 'upsertDevice']);
        Route::delete('/push/devices/{installationId}', [PushNotificationController::class, 'revokeDevice']);

        // Business routes — require verified email
        Route::middleware('verified')->group(function (): void {
            // In-app notifications
            Route::prefix('notifications')->group(function (): void {
                Route::get('/', [NotificationController::class, 'index']);
                Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
                Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
                Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
                Route::delete('/{id}', [NotificationController::class, 'destroy']);
            });

            // Subscriptions
            Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout']);
            Route::get('/user/subscription', [SubscriptionController::class, 'show']);

            // Occurrence payment
            Route::post('/occurrences/{occurrence}/pay', [OccurrenceController::class, 'pay']);

            // User preferences
            Route::put('/user/default-workspace', UserDefaultWorkspaceController::class);

            // Workspaces
            Route::apiResource('workspaces', WorkspaceController::class);

            // Workspace-scoped resources
            Route::prefix('workspaces/{workspace}')->group(function (): void {
                Route::get('expenses/total', [ExpenseController::class, 'total']);
                Route::get('expenses', [ExpenseController::class, 'index']);
                Route::apiResource('expenses', ExpenseController::class)->except(['index']);

                Route::get('categories', [CategoryController::class, 'index'])
                    ->middleware('workspace.can_manage_categories');
                Route::get('categories/valid', [CategoryController::class, 'valid']);
                Route::get('categories/sharing', [CategoryController::class, 'sharing'])
                    ->middleware('workspace.can_manage_categories');
                Route::post('categories/link-bulk', [CategoryController::class, 'bulkUpdateSharing'])
                    ->middleware('workspace.can_manage_categories');
                Route::post('categories', [CategoryController::class, 'store'])
                    ->middleware('workspace.can_manage_categories');
                Route::put('categories/{category}', [CategoryController::class, 'update']);
                Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
                Route::patch('categories/{category}/link', [CategoryController::class, 'updateSharing'])
                    ->middleware('workspace.can_manage_categories');
                Route::patch('categories/{category}/activation', [CategoryController::class, 'updateActivation'])
                    ->middleware('workspace.can_manage_categories');
                Route::patch('categories/{category}/default', [CategoryController::class, 'setDefault']);
                Route::post('categories/{category}/assign', [CategoryController::class, 'assign']);
                Route::delete('categories/{category}/assign', [CategoryController::class, 'unassign']);

                Route::get('payment-methods', [PaymentMethodController::class, 'workspaceIndex'])
                    ->middleware('workspace.owner');
                Route::post('payment-methods', [PaymentMethodController::class, 'store'])
                    ->middleware('workspace.owner');
                Route::patch('payment-methods/{method}/link', [PaymentMethodController::class, 'updateLink'])
                    ->middleware('workspace.owner');
                Route::post('payment-methods/link-bulk', [PaymentMethodController::class, 'linkBulk'])
                    ->middleware('workspace.owner');
                Route::get('payment-methods/valid', [PaymentMethodController::class, 'valid'])
                    ->middleware('workspace.member');

                Route::get('cards', [CardController::class, 'index']);
                Route::post('cards', [CardController::class, 'store']);
                Route::put('cards/{card}', [CardController::class, 'update']);
                Route::delete('cards/{card}', [CardController::class, 'destroy']);
                Route::patch('cards/{card}/default', [CardController::class, 'setDefault']);

                Route::get('other-payment-methods', [OtherPaymentMethodController::class, 'index']);
                Route::post('other-payment-methods', [OtherPaymentMethodController::class, 'store']);
                Route::put('other-payment-methods/{otherPaymentMethod}', [OtherPaymentMethodController::class, 'update']);
                Route::delete('other-payment-methods/{otherPaymentMethod}', [OtherPaymentMethodController::class, 'destroy']);
                Route::patch('other-payment-methods/{otherPaymentMethod}/default', [OtherPaymentMethodController::class, 'setDefault']);

                Route::get('fixed-expenses', [FixedExpenseController::class, 'index']);
                Route::post('fixed-expenses', [FixedExpenseController::class, 'store']);
                Route::put('fixed-expenses/{fixedExpense}', [FixedExpenseController::class, 'update']);
                Route::delete('fixed-expenses/{fixedExpense}', [FixedExpenseController::class, 'destroy']);

                Route::get('occurrences', [OccurrenceController::class, 'index']);

                // Analytics
                Route::get('analytics/summary', [AnalyticsController::class, 'summary']);
                Route::get('analytics/heatmap', [AnalyticsController::class, 'heatmap']);
                Route::get('analytics/projection', [AnalyticsController::class, 'projection']);
                Route::get('analytics/member-split', [AnalyticsController::class, 'memberSplit']);

                // Budgets
                Route::apiResource('budgets', BudgetController::class);
                Route::get('budgets-status', [BudgetController::class, 'status']);

                // Budget Adjustments
                Route::get('budget-adjustments', [BudgetAdjustmentController::class, 'index']);
                Route::post('budget-adjustments', [BudgetAdjustmentController::class, 'store']);
                Route::delete('budget-adjustments/{adjustment}', [BudgetAdjustmentController::class, 'destroy']);
                Route::get('budget-adjustments/available', [BudgetAdjustmentController::class, 'available']);

                // Members
                Route::get('members', [WorkspaceMemberController::class, 'index']);
                Route::post('members', [WorkspaceMemberController::class, 'store']);
                Route::put('members/{user}', [WorkspaceMemberController::class, 'update']);
                Route::delete('members/{user}', [WorkspaceMemberController::class, 'destroy']);
            });

            Route::prefix('user')
                ->middleware(['role:user|admin,api'])
                ->group(function (): void {
                    Route::get('/profile', [ProfileController::class, 'show'])
                        ->middleware('api.permission:profile.view');

                    Route::put('/profile', [ProfileController::class, 'update'])
                        ->middleware('api.permission:profile.update');

                    Route::put('/two-factor', [TwoFactorController::class, 'toggle'])
                        ->middleware('api.permission:two-factor.update');

                    Route::post('/files/upload', [FileUploadController::class, 'upload'])
                        ->middleware('api.permission:files.upload');

                    // Personal categories (L-1: moved into user scope group;
                    // L-2: write routes throttled per user).
                    Route::get('/categories', [CategoryController::class, 'myCategories']);
                    Route::post('/categories', [CategoryController::class, 'storeMine'])
                        ->middleware('throttle:api-writes');
                    Route::put('/categories/{category}', [CategoryController::class, 'updateMine'])
                        ->middleware('throttle:api-writes');
                    Route::delete('/categories/{category}', [CategoryController::class, 'destroyMine'])
                        ->middleware('throttle:api-writes');
                    Route::patch('/categories/{category}/default', [CategoryController::class, 'setDefaultMine'])
                        ->middleware('throttle:api-writes');
                    Route::patch('/categories/{category}/workspaces', [CategoryController::class, 'syncWorkspaces'])
                        ->middleware('throttle:api-writes');

                    // Personal payment methods (L-1 + L-2).
                    Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
                    Route::post('/payment-methods', [PaymentMethodController::class, 'storeMine'])
                        ->middleware('throttle:api-writes');
                    Route::put('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'updateMine'])
                        ->middleware('throttle:api-writes');
                    Route::patch('/payment-methods/{paymentMethod}/workspaces', [PaymentMethodController::class, 'syncWorkspaces'])
                        ->middleware('throttle:api-writes');
                    Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroyMine'])
                        ->middleware('throttle:api-writes');
                });
        });

        // Admin routes — no email verification required (accounts created manually)
        Route::prefix('admin')
            ->middleware(['role:admin,api'])
            ->group(function (): void {
                Route::get('/dashboard', [AdminDashboardController::class, 'index'])
                    ->middleware('api.permission:dashboard.view');

                Route::post('/files/upload', [AdminFileUploadController::class, 'upload'])
                    ->middleware('api.permission:files.upload');

                Route::get('/administrators/options', [AdminAdministratorController::class, 'options'])
                    ->middleware('api.permission:administrators.view');
                Route::get('/administrators', [AdminAdministratorController::class, 'index'])
                    ->middleware('api.permission:administrators.view');
                Route::post('/administrators', [AdminAdministratorController::class, 'store'])
                    ->middleware('api.permission:administrators.create');
                Route::get('/administrators/{administrator}', [AdminAdministratorController::class, 'show'])
                    ->middleware('api.permission:administrators.view');
                Route::put('/administrators/{administrator}', [AdminAdministratorController::class, 'update'])
                    ->middleware('api.permission:administrators.update');
                Route::delete('/administrators/{administrator}', [AdminAdministratorController::class, 'destroy'])
                    ->middleware('api.permission:administrators.delete');

                Route::post('/users/{user}/plan', AdminUserPlanController::class)
                    ->middleware('api.permission:users.assign-plan');

                Route::get('/users', [AdminUserController::class, 'index'])
                    ->middleware('api.permission:users.view');
                Route::get('/users/{user}', [AdminUserController::class, 'show'])
                    ->middleware('api.permission:users.view');
            });
    });

    // Stripe webhook — no auth middleware, validated by Stripe-Signature header
    Route::post('/webhooks/stripe', StripeWebhookController::class);
});
