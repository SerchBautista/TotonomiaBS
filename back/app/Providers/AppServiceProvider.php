<?php

namespace App\Providers;

use App\Actions\AnalyticsAction;
use App\Actions\AssignUserPlanAction;
use App\Actions\BudgetStatusAction;
use App\Actions\CalculateEffectiveBudgetAction;
use App\Actions\CheckBudgetThresholdAction;
use App\Actions\CreateAdministratorAction;
use App\Actions\CreateDefaultWorkspaceAction;
use App\Actions\CreateOccurrenceAction;
use App\Actions\CreateWorkspaceAction;
use App\Actions\DestroyBudgetAction;
use App\Actions\GetLearnCatalogAction;
use App\Actions\GetLearnFeatureAction;
use App\Actions\GetLearnTopicAction;
use App\Actions\HandleCheckoutCompletedAction;
use App\Actions\HandleInvoicePaidAction;
use App\Actions\HandleSubscriptionDeletedAction;
use App\Actions\HandleSubscriptionUpdatedAction;
use App\Actions\MarkOverdueOccurrencesAction;
use App\Actions\PayOccurrenceAction;
use App\Actions\ProcessFixedExpensesAction;
use App\Actions\RegisterExpenseAction;
use App\Actions\RegisterUserAction;
use App\Actions\StoreBudgetAction;
use App\Actions\SuggestCategoriesForAdjustmentAction;
use App\Actions\UpdateAdministratorAction;
use App\Actions\UpdateBudgetAction;
use App\Contracts\AnalyticsHeatmapActionInterface;
use App\Contracts\AnalyticsMemberSplitActionInterface;
use App\Contracts\AnalyticsProjectionActionInterface;
use App\Contracts\AnalyticsSummaryActionInterface;
use App\Contracts\AssignUserPlanActionInterface;
use App\Contracts\AuthenticatorInterface;
use App\Contracts\BudgetStatusActionInterface;
use App\Contracts\CalculateEffectiveBudgetActionInterface;
use App\Contracts\CheckBudgetThresholdActionInterface;
use App\Contracts\CreateAdministratorActionInterface;
use App\Contracts\CreateDefaultWorkspaceActionInterface;
use App\Contracts\CreateOccurrenceActionInterface;
use App\Contracts\CreateWorkspaceActionInterface;
use App\Contracts\DestroyBudgetActionInterface;
use App\Contracts\FileServiceInterface;
use App\Contracts\GetLearnCatalogActionInterface;
use App\Contracts\GetLearnFeatureActionInterface;
use App\Contracts\GetLearnTopicActionInterface;
use App\Contracts\HandleCheckoutCompletedActionInterface;
use App\Contracts\HandleInvoicePaidActionInterface;
use App\Contracts\HandleSubscriptionDeletedActionInterface;
use App\Contracts\HandleSubscriptionUpdatedActionInterface;
use App\Contracts\LearnContentProviderInterface;
use App\Contracts\MarkOverdueOccurrencesActionInterface;
use App\Contracts\PaymentGatewayContract;
use App\Contracts\PayOccurrenceActionInterface;
use App\Contracts\ProcessFixedExpensesActionInterface;
use App\Contracts\RegisterExpenseActionInterface;
use App\Contracts\RegisterUserActionInterface;
use App\Contracts\StoreBudgetActionInterface;
use App\Contracts\StripeSubscriptionServiceInterface;
use App\Contracts\SuggestCategoriesForAdjustmentActionInterface;
use App\Contracts\UpdateAdministratorActionInterface;
use App\Contracts\UpdateBudgetActionInterface;
use App\Events\UserRegistered;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\AuthController as UserAuthController;
use App\Listeners\AssignFreePlanListener;
use App\Listeners\CreateDefaultWorkspaceListener;
use App\Models\BudgetAdjustment;
use App\Models\Card;
use App\Models\OtherPaymentMethod;
use App\Policies\BudgetAdjustmentPolicy;
use App\Services\Auth\AdminLoginService;
use App\Services\Auth\UserLoginService;
use App\Services\DummyGatewayService;
use App\Services\FileService;
use App\Services\Learn\JsonFileLearnContentProvider;
use App\Services\StripeGatewayService;
use App\Services\StripeSubscriptionService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            config(['logging.default' => 'commands']);
        }

        // --- Dependency Inversion: Interface → Concrete bindings ---

        // AuthenticatorInterface: contextual binding per controller
        // UserAuthController gets UserLoginService; AdminAuthController gets AdminLoginService
        $this->app->when(UserAuthController::class)
            ->needs(AuthenticatorInterface::class)
            ->give(UserLoginService::class);

        $this->app->when(AdminAuthController::class)
            ->needs(AuthenticatorInterface::class)
            ->give(AdminLoginService::class);

        // FileServiceInterface → FileService
        $this->app->bind(FileServiceInterface::class, FileService::class);

        // Action interfaces → concrete implementations
        $this->app->bind(RegisterExpenseActionInterface::class, RegisterExpenseAction::class);
        $this->app->bind(CreateWorkspaceActionInterface::class, CreateWorkspaceAction::class);
        $this->app->bind(AnalyticsSummaryActionInterface::class, AnalyticsAction::class);
        $this->app->bind(AnalyticsHeatmapActionInterface::class, AnalyticsAction::class);
        $this->app->bind(AnalyticsProjectionActionInterface::class, AnalyticsAction::class);
        $this->app->bind(AnalyticsMemberSplitActionInterface::class, AnalyticsAction::class);
        $this->app->bind(ProcessFixedExpensesActionInterface::class, ProcessFixedExpensesAction::class);
        $this->app->bind(CreateOccurrenceActionInterface::class, CreateOccurrenceAction::class);
        $this->app->bind(PayOccurrenceActionInterface::class, PayOccurrenceAction::class);
        $this->app->bind(MarkOverdueOccurrencesActionInterface::class, MarkOverdueOccurrencesAction::class);
        $this->app->bind(StoreBudgetActionInterface::class, StoreBudgetAction::class);
        $this->app->bind(UpdateBudgetActionInterface::class, UpdateBudgetAction::class);
        $this->app->bind(DestroyBudgetActionInterface::class, DestroyBudgetAction::class);
        $this->app->bind(BudgetStatusActionInterface::class, BudgetStatusAction::class);
        $this->app->bind(CheckBudgetThresholdActionInterface::class, CheckBudgetThresholdAction::class);
        $this->app->bind(CalculateEffectiveBudgetActionInterface::class, CalculateEffectiveBudgetAction::class);
        $this->app->bind(SuggestCategoriesForAdjustmentActionInterface::class, SuggestCategoriesForAdjustmentAction::class);
        $this->app->bind(AssignUserPlanActionInterface::class, AssignUserPlanAction::class);
        $this->app->bind(RegisterUserActionInterface::class, RegisterUserAction::class);
        $this->app->bind(LearnContentProviderInterface::class, JsonFileLearnContentProvider::class);
        $this->app->bind(GetLearnCatalogActionInterface::class, GetLearnCatalogAction::class);
        $this->app->bind(GetLearnTopicActionInterface::class, GetLearnTopicAction::class);
        $this->app->bind(GetLearnFeatureActionInterface::class, GetLearnFeatureAction::class);
        $this->app->bind(CreateDefaultWorkspaceActionInterface::class, CreateDefaultWorkspaceAction::class);

        // Administrator action bindings
        $this->app->bind(CreateAdministratorActionInterface::class, CreateAdministratorAction::class);
        $this->app->bind(UpdateAdministratorActionInterface::class, UpdateAdministratorAction::class);

        // Stripe webhook action bindings
        $this->app->bind(HandleCheckoutCompletedActionInterface::class, HandleCheckoutCompletedAction::class);
        $this->app->bind(HandleSubscriptionUpdatedActionInterface::class, HandleSubscriptionUpdatedAction::class);
        $this->app->bind(HandleSubscriptionDeletedActionInterface::class, HandleSubscriptionDeletedAction::class);
        $this->app->bind(HandleInvoicePaidActionInterface::class, HandleInvoicePaidAction::class);
        $this->app->bind(StripeSubscriptionServiceInterface::class, StripeSubscriptionService::class);

        // PaymentGatewayContract: dummy (local) o stripe (producción)
        $gateway = config('services.payment_gateway', 'dummy') === 'stripe'
            ? StripeGatewayService::class
            : DummyGatewayService::class;
        $this->app->bind(PaymentGatewayContract::class, $gateway);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth-login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(config('two-factor.rate_limits.login.max_attempts', 10))
                ->by($email !== '' ? $email.'|'.$request->ip() : $request->ip());
        });

        RateLimiter::for('auth-2fa-verify', function (Request $request) {
            $sessionToken = (string) $request->input('session_token');

            return Limit::perMinute(config('two-factor.rate_limits.verify.max_attempts', 20))
                ->by($sessionToken !== '' ? $sessionToken.'|'.$request->ip() : $request->ip());
        });

        RateLimiter::for('auth-2fa-resend', function (Request $request) {
            return Limit::perMinute(config('two-factor.rate_limits.resend.max_attempts', 5))
                ->by($request->ip());
        });

        // Per-user write throttle: protects the new user-scoped mutation
        // endpoints (personal categories, personal payment methods, etc.)
        // from burst-write abuse. Falls back to the IP for unauthenticated
        // requests, but those should already be rejected by the auth middleware
        // before reaching the throttle.
        RateLimiter::for('api-writes', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(60)->by(
                $userId !== null ? 'writes:'.$userId : 'writes:'.$request->ip()
            );
        });

        Password::defaults(function () {
            $rule = Password::min(8)->mixedCase()->numbers();

            return $this->app->environment('testing') ? $rule : $rule->uncompromised();
        });

        \Illuminate\Support\Facades\Gate::policy(BudgetAdjustment::class, BudgetAdjustmentPolicy::class);

        Relation::morphMap([
            'card' => Card::class,
            'other' => OtherPaymentMethod::class,
        ]);

        $this->app->make(Markdown::class)->loadComponentsFrom([resource_path('views/mail')]);
        $this->app->make('view')->prependNamespace('notifications', resource_path('views/notifications'));

        Event::listen(UserRegistered::class, AssignFreePlanListener::class);
        Event::listen(Verified::class, CreateDefaultWorkspaceListener::class);

        // Override email verification link to point to the frontend SPA
        VerifyEmail::createUrlUsing(function (object $notifiable): string {
            $signedUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addHours(24),
                ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
            );

            parse_str((string) parse_url($signedUrl, PHP_URL_QUERY), $queryParams);

            $path = (string) parse_url($signedUrl, PHP_URL_PATH);
            preg_match('/verify\/([^\/]+)\/([^\/]+)$/', $path, $matches);

            $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

            return $frontendUrl.'/user/verify-email?'.http_build_query([
                'id' => $matches[1] ?? $notifiable->getKey(),
                'hash' => $matches[2] ?? sha1($notifiable->getEmailForVerification()),
                'expires' => $queryParams['expires'] ?? '',
                'signature' => $queryParams['signature'] ?? '',
            ]);
        });

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            Log::channel('commands')->info('Artisan command started', [
                'command' => $event->command,
            ]);
        });

        Event::listen(CommandFinished::class, function (CommandFinished $event): void {
            Log::channel('commands')->info('Artisan command finished', [
                'command' => $event->command,
                'exit_code' => $event->exitCode,
            ]);
        });
    }
}
