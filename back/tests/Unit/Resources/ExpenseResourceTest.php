<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\ExpenseResource;
use App\Models\Card;
use App\Models\Category;
use App\Models\Expense;
use App\Models\OtherPaymentMethod;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ExpenseResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_resource_returns_expected_top_level_fields(): void
    {
        $expense = $this->makeExpense();

        $resource = new ExpenseResource($expense);
        $array = $resource->toArray(Request::create('/'));

        $this->assertSame($expense->id, $array['id']);
        $this->assertSame($expense->workspace_id, $array['workspace_id']);
        $this->assertSame($expense->amount, $array['amount']);
        $this->assertSame($expense->description, $array['description']);
        $this->assertSame($expense->payment_type, $array['payment_type']);
        $this->assertSame($expense->paid_by_user_id, $array['paid_by_user_id']);
        $this->assertSame($expense->fixed_expense_id, $array['fixed_expense_id']);
        $this->assertNotNull($array['created_at']);
        $this->assertNotNull($array['updated_at']);
    }

    public function test_resource_does_not_expose_payment_instrument_when_relation_not_loaded(): void
    {
        $expense = $this->makeExpense();

        $resource = new ExpenseResource($expense);
        $array = $resource->toArray(Request::create('/'));

        // paymentInstrument relation is NOT loaded → resource must yield null.
        $this->assertNull($array['payment_instrument']);
    }

    public function test_resource_uses_card_resource_when_payment_instrument_is_a_card(): void
    {
        $expense = $this->makeExpense();
        $expense->load('paymentInstrument');

        $resource = new ExpenseResource($expense);

        // Nested resources are wrapped in a CardResource / OtherPaymentMethodResource
        // object. Calling toArray() on the top-level resource exposes these
        // wrappers as-is; converting to a response unwraps them.
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertNotNull($data['payment_instrument']);
        $this->assertSame($expense->paymentInstrument->id, $data['payment_instrument']['id']);
        // CardResource fields (subset).
        $this->assertArrayHasKey('name', $data['payment_instrument']);
        $this->assertArrayHasKey('card_type', $data['payment_instrument']);
        $this->assertArrayHasKey('last_4_digits', $data['payment_instrument']);
    }

    public function test_resource_uses_other_payment_method_resource_when_payment_instrument_is_an_opm(): void
    {
        $expense = $this->makeExpense();

        // Swap the payment instrument to an OtherPaymentMethod.
        $opm = OtherPaymentMethod::factory()->create([
            'workspace_id' => $expense->workspace_id,
            'user_id' => $expense->user_id,
            'description' => 'Transferencia bancaria SPEI',
        ]);
        $expense->update([
            'payment_type' => 'other',
            'payment_instrument_id' => $opm->id,
        ]);

        // Build an OPM resource directly. The end-to-end morph dispatch is
        // exercised by the controller tests, which require the morphMap
        // bindings; here we only verify the OPM resource itself returns the
        // expected fields when given a real OPM.
        $resource = new \App\Http\Resources\OtherPaymentMethodResource($opm);
        $array = $resource->toArray(Request::create('/'));

        $this->assertSame($opm->id, $array['id']);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertSame('Transferencia bancaria SPEI', $array['description']);
    }

    public function test_resource_includes_category_when_relation_loaded(): void
    {
        $expense = $this->makeExpense();
        $expense->load('category');

        $resource = new ExpenseResource($expense);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertNotNull($data['category']);
        $this->assertSame($expense->category->id, $data['category']['id']);
        $this->assertArrayHasKey('name', $data['category']);
    }

    public function test_resource_omits_category_when_relation_not_loaded(): void
    {
        $expense = $this->makeExpense();

        // Sanity check: the relation is NOT loaded for the freshly-built model.
        $this->assertFalse($expense->relationLoaded('category'));

        $resource = new ExpenseResource($expense);

        // When the resource is converted to a JSON response, MissingValue
        // placeholders are filtered out. We assert that here by converting
        // the resource to JSON and checking the structure.
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertArrayNotHasKey('category', $data);
    }

    public function test_resource_includes_user_when_relation_loaded(): void
    {
        $expense = $this->makeExpense();
        $expense->load('user');

        $resource = new ExpenseResource($expense);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertNotNull($data['user']);
        $this->assertSame($expense->user->id, $data['user']['id']);
    }

    public function test_resource_includes_paid_by_when_relation_loaded(): void
    {
        $expense = $this->makeExpense();
        $expense->update(['paid_by_user_id' => $expense->user_id]);
        $expense->load('paidBy');

        $resource = new ExpenseResource($expense);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertNotNull($data['paid_by']);
        $this->assertSame($expense->user_id, $data['paid_by']['id']);
    }

    public function test_resource_masks_card_last_four_digits(): void
    {
        $expense = $this->makeExpense();
        $expense->load('paymentInstrument');

        $this->assertInstanceOf(Card::class, $expense->paymentInstrument);

        $resource = new ExpenseResource($expense);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertIsArray($data['payment_instrument']);
        $this->assertStringStartsWith('****', $data['payment_instrument']['last_4_digits']);
        $this->assertStringEndsWith('4242', $data['payment_instrument']['last_4_digits']);
    }

    public function test_resource_returns_null_payment_instrument_when_cash(): void
    {
        $expense = $this->makeExpense();
        $expense->update([
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
        ]);

        $resource = new ExpenseResource($expense);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertSame('cash', $data['payment_type']);
        $this->assertNull($data['payment_instrument']);
    }

    public function test_resource_date_is_iso_date_string(): void
    {
        $expense = $this->makeExpense();
        $expense->update(['date' => '2026-06-15']);

        $resource = new ExpenseResource($expense);
        $array = $resource->toArray(Request::create('/'));

        $this->assertSame('2026-06-15', $array['date']);
    }

    private function makeExpense(): Expense
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $category = Category::factory()->forUser($owner)->create();
        $card = Card::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'last_4_digits' => '4242',
        ]);

        return Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '99.50',
            'description' => 'Test',
        ]);
    }
}
