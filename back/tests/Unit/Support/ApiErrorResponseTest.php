<?php

namespace Tests\Unit\Support;

use App\Support\Api\ApiErrorResponse;
use App\Support\Http\RequestId;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');

        $request = Request::create('/api/v1/test', 'GET');
        $request->attributes->set(RequestId::ATTRIBUTE, 'req-test-123');

        $this->app->instance('request', $request);
    }

    public function test_validation_payload_includes_field_errors(): void
    {
        $response = ApiErrorResponse::validation([
            'email' => ['The email field is required.'],
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'status' => 422,
            'code' => 'validation_error',
            'message' => 'The given data was invalid.',
            'request_id' => 'req-test-123',
            'fieldErrors' => [
                'email' => ['The email field is required.'],
            ],
        ], $response->getData(true));
    }

    public function test_message_falls_back_to_safe_default_when_empty(): void
    {
        $response = ApiErrorResponse::notFound('');

        $this->assertSame([
            'status' => 404,
            'code' => 'not_found',
            'message' => 'The requested resource was not found.',
            'request_id' => 'req-test-123',
        ], $response->getData(true));
    }

    public function test_validation_payload_preserves_form_compatible_dot_notation_keys(): void
    {
        $response = ApiErrorResponse::validation([
            'users.0.email' => ['The users.0.email field is required.'],
        ]);

        $this->assertSame([
            'status' => 422,
            'code' => 'validation_error',
            'message' => 'The given data was invalid.',
            'request_id' => 'req-test-123',
            'fieldErrors' => [
                'users.0.email' => ['The users.0.email field is required.'],
            ],
        ], $response->getData(true));
    }

    public function test_unprocessable_payload_can_include_meta_without_field_errors(): void
    {
        $response = ApiErrorResponse::unprocessableEntity(
            'Insufficient funds in the selected category.',
            'budget_adjustment_insufficient_funds',
            meta: [
                'suggested_categories' => [
                    ['category_id' => 'cat-1', 'available' => '120.00'],
                ],
            ],
        );

        $this->assertSame([
            'status' => 422,
            'code' => 'budget_adjustment_insufficient_funds',
            'message' => 'Insufficient funds in the selected category.',
            'request_id' => 'req-test-123',
            'meta' => [
                'suggested_categories' => [
                    ['category_id' => 'cat-1', 'available' => '120.00'],
                ],
            ],
        ], $response->getData(true));
    }

    public function test_error_payload_omits_request_id_when_request_context_is_unavailable(): void
    {
        $this->app->forgetInstance('request');

        $response = ApiErrorResponse::serverError();

        $this->assertSame([
            'status' => 500,
            'code' => 'server_error',
            'message' => 'An unexpected error occurred.',
        ], $response->getData(true));
    }
}
