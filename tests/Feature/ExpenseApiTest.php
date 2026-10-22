<?php

namespace Tests\Feature;

use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_expense(): void
    {
        $response = $this->postJson('/api/expenses', [
            'title'    => 'Courses',
            'amount'   => 42.5,
            'category' => 'food',
            'date'     => '2026-10-01',
        ]);

        $response->assertCreated()
            ->assertJson([
                'title'    => 'Courses',
                'amount'   => '42.50',
                'category' => 'food',
                'date'     => '2026-10-01',
            ]);

        $this->assertDatabaseHas('expenses', ['title' => 'Courses', 'category' => 'food']);
    }

    public function test_it_rejects_invalid_expenses(): void
    {
        $this->postJson('/api/expenses', ['amount' => -5, 'date' => 'pas-une-date'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'amount', 'category', 'date']);
    }

    public function test_it_lists_expenses_most_recent_first(): void
    {
        Expense::factory()->create(['title' => 'Ancienne', 'date' => '2026-01-01']);
        Expense::factory()->create(['title' => 'Récente', 'date' => '2026-06-01']);

        $this->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.title', 'Récente')
            ->assertJsonPath('1.title', 'Ancienne');
    }

    public function test_it_filters_expenses_by_category_and_period(): void
    {
        Expense::factory()->create(['category' => 'food', 'date' => '2026-03-10']);
        Expense::factory()->create(['category' => 'food', 'date' => '2026-05-10']);
        Expense::factory()->create(['category' => 'transport', 'date' => '2026-03-15']);

        $this->getJson('/api/expenses?category=food&from=2026-03-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.date', '2026-03-10');
    }

    public function test_it_rejects_a_period_where_to_is_before_from(): void
    {
        $this->getJson('/api/expenses?from=2026-05-01&to=2026-04-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }

    public function test_it_returns_statistics_grouped_by_category(): void
    {
        Expense::factory()->create(['category' => 'food', 'amount' => 10.25]);
        Expense::factory()->create(['category' => 'food', 'amount' => 20]);
        Expense::factory()->create(['category' => 'transport', 'amount' => 5.5]);

        $this->getJson('/api/expenses/stats')
            ->assertOk()
            ->assertExactJson([
                'total'       => 35.75,
                'count'       => 3,
                'by_category' => [
                    ['category' => 'food', 'total' => 30.25, 'count' => 2],
                    ['category' => 'transport', 'total' => 5.5, 'count' => 1],
                ],
            ]);
    }

    public function test_statistics_are_empty_when_there_are_no_expenses(): void
    {
        $this->getJson('/api/expenses/stats')
            ->assertOk()
            ->assertExactJson(['total' => 0, 'count' => 0, 'by_category' => []]);
    }
}
