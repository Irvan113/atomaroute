<?php

namespace Tests\Feature;

use App\Models\FinanceTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class FinanceEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_income_expense_and_savings_transactions()
    {
        $user = User::factory()->create();

        $comp = Volt::actingAs($user)->test('journal.index');

        // Create income
        $comp->call('openCreateFinance', 'income')
            ->set('f_amount', '1000000')
            ->set('f_category', 'Salary')
            ->set('f_description', 'Gaji Pokok')
            ->set('f_date', Carbon::today()->toDateString())
            ->call('saveFinance');

        $this->assertDatabaseHas('finance_transactions', [
            'user_id' => $user->id,
            'type' => 'income',
            'amount' => 1000000,
            'category' => 'Salary',
            'description' => 'Gaji Pokok',
        ]);

        // Create pure expense
        $comp->call('openCreateFinance', 'expense')
            ->set('f_amount', '150000')
            ->set('f_category', 'Food & Drinks')
            ->set('f_description', 'Makan Siang')
            ->set('f_date', Carbon::today()->toDateString())
            ->call('saveFinance');

        $this->assertDatabaseHas('finance_transactions', [
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => 150000,
            'category' => 'Food & Drinks',
            'description' => 'Makan Siang',
        ]);

        // Create savings
        $comp->call('openCreateFinance', 'savings')
            ->set('f_amount', '300000')
            ->set('f_category', 'Tabungan Impian')
            ->set('f_description', 'Beli Laptop')
            ->set('f_date', Carbon::today()->toDateString())
            ->call('saveFinance');

        $this->assertDatabaseHas('finance_transactions', [
            'user_id' => $user->id,
            'type' => 'savings',
            'amount' => 300000,
            'category' => 'Tabungan Impian',
            'description' => 'Beli Laptop',
        ]);
    }

    public function test_user_can_edit_existing_finance_transaction()
    {
        $user = User::factory()->create();

        $t = FinanceTransaction::create([
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => 50000,
            'category' => 'Transport',
            'description' => 'Bensin motor',
            'date' => Carbon::today(),
        ]);

        $comp = Volt::actingAs($user)->test('journal.index');

        $comp->call('openEditFinance', $t->id)
            ->assertSet('editingFinanceId', $t->id)
            ->assertSet('f_amount', '50000')
            ->assertSet('f_category', 'Transport')
            ->assertSet('f_description', 'Bensin motor')
            ->set('f_amount', '75000')
            ->set('f_description', 'Bensin motor & tol')
            ->call('saveFinance');

        $t->refresh();
        $this->assertEquals(75000, (float) $t->amount);
        $this->assertEquals('Bensin motor & tol', $t->description);
    }

    public function test_user_can_delete_finance_transaction()
    {
        $user = User::factory()->create();

        $t = FinanceTransaction::create([
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => 50000,
            'category' => 'Food & Drinks',
            'date' => Carbon::today(),
        ]);

        $comp = Volt::actingAs($user)->test('journal.index');
        $comp->call('deleteFinance', $t->id);

        $this->assertDatabaseMissing('finance_transactions', [
            'id' => $t->id,
        ]);
    }

    public function test_savings_is_separated_from_pure_expense_and_calculates_balance()
    {
        $user = User::factory()->create();
        $today = Carbon::today();

        // Income: 1.000.000
        FinanceTransaction::create([
            'user_id' => $user->id,
            'type' => 'income',
            'amount' => 1000000,
            'category' => 'Salary',
            'date' => $today,
        ]);

        // Pure Expense: 300.000
        FinanceTransaction::create([
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => 300000,
            'category' => 'Food & Drinks',
            'date' => $today,
        ]);

        // Savings: 200.000
        FinanceTransaction::create([
            'user_id' => $user->id,
            'type' => 'savings',
            'amount' => 200000,
            'category' => 'Tabungan Darurat',
            'date' => $today,
        ]);

        $comp = Volt::actingAs($user)->test('journal.index');
        $stats = $comp->get('financeStats');

        // Verify that pure expense does not lump savings
        $this->assertEquals(1000000, $stats['income']);
        $this->assertEquals(300000, $stats['expense']);
        $this->assertEquals(200000, $stats['savings']);

        // Balance = 1.000.000 - 300.000 - 200.000 = 500.000
        $this->assertEquals(500000, $stats['balance']);
    }

    public function test_category_breakdown_calculates_percentages_and_ranks()
    {
        $user = User::factory()->create();
        $today = Carbon::today();

        // Expenses:
        // Food & Drinks: 600.000 (60%)
        // Transport: 300.000 (30%)
        // Bills: 100.000 (10%)
        FinanceTransaction::create([
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => 600000,
            'category' => 'Food & Drinks',
            'date' => $today,
        ]);
        FinanceTransaction::create([
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => 300000,
            'category' => 'Transport',
            'date' => $today,
        ]);
        FinanceTransaction::create([
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => 100000,
            'category' => 'Bills',
            'date' => $today,
        ]);

        $comp = Volt::actingAs($user)->test('journal.index');
        $stats = $comp->get('financeStats');

        $breakdown = $stats['category_breakdown'];
        $this->assertCount(3, $breakdown);

        // Ranked #1: Food & Drinks
        $this->assertEquals('Food & Drinks', $breakdown[0]['category']);
        $this->assertEquals(600000, $breakdown[0]['amount']);
        $this->assertEquals(60.0, $breakdown[0]['percentage']);

        // Ranked #2: Transport
        $this->assertEquals('Transport', $breakdown[1]['category']);
        $this->assertEquals(300000, $breakdown[1]['amount']);
        $this->assertEquals(30.0, $breakdown[1]['percentage']);

        // Ranked #3: Bills
        $this->assertEquals('Bills', $breakdown[2]['category']);
        $this->assertEquals(100000, $breakdown[2]['amount']);
        $this->assertEquals(10.0, $breakdown[2]['percentage']);
    }

    public function test_monthly_budget_tracker_calculation_and_overbudget_detection()
    {
        $user = User::factory()->create([
            'monthly_budget' => 1000000,
        ]);
        $today = Carbon::today();

        FinanceTransaction::create([
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => 650000,
            'category' => 'Food & Drinks',
            'date' => $today,
        ]);

        $comp = Volt::actingAs($user)->test('journal.index');
        $stats = $comp->get('financeStats');

        $this->assertEquals(1000000, $stats['user_budget']);
        $this->assertEquals(65.0, $stats['budget_percentage']);
        $this->assertEquals(350000, $stats['remaining_budget']);
        $this->assertFalse($stats['is_over_budget']);

        // Now add another expense to trigger overbudget
        FinanceTransaction::create([
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => 450000,
            'category' => 'Shopping',
            'date' => $today,
        ]);

        $comp = Volt::actingAs($user)->test('journal.index');
        $newStats = $comp->get('financeStats');

        // Total expense: 1.100.000
        $this->assertEquals(110.0, $newStats['budget_percentage']);
        $this->assertEquals(-100000, $newStats['remaining_budget']);
        $this->assertTrue($newStats['is_over_budget']);

        // Test updating budget through saveBudget()
        $comp->set('monthly_budget_input', '2000000')
            ->call('saveBudget');

        $user->refresh();
        $this->assertEquals(2000000, (float) $user->monthly_budget);
    }

    public function test_finance_search_and_category_filtering()
    {
        $user = User::factory()->create();
        $today = Carbon::today();

        $t1 = FinanceTransaction::create([
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => 50000,
            'category' => 'Food & Drinks',
            'description' => 'Kopi Starbucks latte',
            'date' => $today,
        ]);

        $t2 = FinanceTransaction::create([
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => 25000,
            'category' => 'Transport',
            'description' => 'Ojek online ke kantor',
            'date' => $today,
        ]);

        $comp = Volt::actingAs($user)->test('journal.index');

        // Filter by search
        $comp->set('financeSearch', 'Starbucks');
        $grouped = $comp->get('groupedFinances');
        $all = $grouped->flatten();
        $this->assertCount(1, $all);
        $this->assertEquals($t1->id, $all->first()->id);

        // Filter by category
        $comp->set('financeSearch', '')
            ->set('financeCategoryFilter', 'Transport');
        $groupedCat = $comp->get('groupedFinances');
        $allCat = $groupedCat->flatten();
        $this->assertCount(1, $allCat);
        $this->assertEquals($t2->id, $allCat->first()->id);
    }

    public function test_dynamic_navbar_fab_triggers_finance_modal_when_on_finance_tab()
    {
        $user = User::factory()->create();

        $comp = Volt::actingAs($user)->test('journal.index');

        // When activeTab is set to finance, it dispatches journal-tab-changed
        $comp->set('activeTab', 'finance')
            ->assertDispatched('journal-tab-changed', tab: 'finance');

        // Calling openCreateModal (the listener for open-add-journal-modal) when on finance tab opens finance modal
        $comp->call('openCreateModal')
            ->assertSet('showFinanceModal', true)
            ->assertSet('showCreateModal', false);

        // Calling openCreateFinanceFromEvent (the listener for open-add-finance-modal) opens finance modal
        $comp->set('showFinanceModal', false);
        $comp->dispatch('open-add-finance-modal');
        $comp->assertSet('showFinanceModal', true);
    }
}
