<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Services\ExpenseTracker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Valide la méthode choisie : toute la logique métier est dans le service
 * ExpenseTracker, et les contrôleurs ne font que valider puis déléguer.
 *
 * Lancer : ./vendor/bin/sail artisan test --filter=ExpenseTrackerTest
 */
class ExpenseTrackerTest extends TestCase
{
    use RefreshDatabase;

    private ExpenseTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();

        // Le service est résolu par le conteneur, comme dans les contrôleurs
        $this->tracker = $this->app->make(ExpenseTracker::class);
    }

    public function test_the_service_step_by_step(): void
    {
        // Étape 1 : base vide -> liste vide et statistiques à zéro
        $this->assertCount(0, $this->tracker->list(), 'Étape 1 : la liste doit être vide');
        $this->assertEquals(
            ['total' => 0, 'count' => 0, 'by_category' => collect()],
            $this->tracker->stats(),
            'Étape 1 : les stats doivent être à zéro'
        );

        // Étape 2 : création d'une dépense -> enregistrée en base, montant et date formatés
        $courses = $this->tracker->create([
            'title'    => 'Courses',
            'amount'   => 42.5,
            'category' => 'food',
            'date'     => '2026-03-10',
        ]);

        $this->assertInstanceOf(Expense::class, $courses, 'Étape 2 : create() doit renvoyer un Expense');
        $this->assertTrue($courses->exists, 'Étape 2 : la dépense doit être en base');
        $this->assertSame('42.50', $courses->amount, 'Étape 2 : le montant doit avoir 2 décimales');
        $this->assertSame('2026-03-10', $courses->toArray()['date'], 'Étape 2 : la date doit être au format Y-m-d');
        $this->assertDatabaseCount('expenses', 1);

        // Étape 3 : on ajoute d'autres dépenses (dont deux à la même date)
        $this->tracker->create(['title' => 'Restaurant', 'amount' => 20, 'category' => 'food', 'date' => '2026-05-02']);
        $this->tracker->create(['title' => 'Métro', 'amount' => 5.5, 'category' => 'transport', 'date' => '2026-03-31']);
        $this->tracker->create(['title' => 'Taxi', 'amount' => 30, 'category' => 'transport', 'date' => '2026-03-31']);
        $this->tracker->create(['title' => 'Loyer', 'amount' => 700, 'category' => 'housing', 'date' => '2026-03-01']);

        // Étape 4 : liste triée de la plus récente à la plus ancienne
        // (à date égale, la dernière créée passe en premier)
        $this->assertSame(
            ['Restaurant', 'Taxi', 'Métro', 'Courses', 'Loyer'],
            $this->titles($this->tracker->list()),
            'Étape 4 : mauvais ordre de tri'
        );

        // Étape 5 : filtre par catégorie
        $this->assertSame(
            ['Restaurant', 'Courses'],
            $this->titles($this->tracker->list(['category' => 'food'])),
            'Étape 5 : le filtre category ne fonctionne pas'
        );

        // Étape 6 : filtre par période, bornes from et to incluses
        $this->assertSame(
            ['Taxi', 'Métro', 'Courses', 'Loyer'],
            $this->titles($this->tracker->list(['from' => '2026-03-01', 'to' => '2026-03-31'])),
            'Étape 6 : les bornes from / to doivent être incluses'
        );

        // Étape 7 : filtres combinés catégorie + période
        $this->assertSame(
            ['Courses'],
            $this->titles($this->tracker->list(['category' => 'food', 'from' => '2026-03-01', 'to' => '2026-03-31'])),
            'Étape 7 : les filtres combinés ne fonctionnent pas'
        );

        // Étape 8 : statistiques globales, catégories triées par total décroissant
        $stats = $this->tracker->stats();

        $this->assertSame(798.0, $stats['total'], 'Étape 8 : total global incorrect');
        $this->assertSame(5, $stats['count'], 'Étape 8 : nombre de dépenses incorrect');
        $this->assertSame([
            ['category' => 'housing', 'total' => 700.0, 'count' => 1],
            ['category' => 'food', 'total' => 62.5, 'count' => 2],
            ['category' => 'transport', 'total' => 35.5, 'count' => 2],
        ], $stats['by_category']->all(), 'Étape 8 : répartition par catégorie incorrecte');

        // Étape 9 : les stats filtrées sont cohérentes avec la liste filtrée
        $filters = ['from' => '2026-03-01', 'to' => '2026-03-31'];
        $list = $this->tracker->list($filters);
        $stats = $this->tracker->stats($filters);

        $this->assertSame($list->count(), $stats['count'], 'Étape 9 : count différent de la liste');
        $this->assertSame(
            round($list->sum(fn (Expense $e) => (float) $e->amount), 2),
            $stats['total'],
            'Étape 9 : total différent de la somme de la liste'
        );
    }

    public function test_controllers_delegate_to_the_service(): void
    {
        // Étape 1 : on remplace le service par un mock dans le conteneur
        $tracker = $this->createMock(ExpenseTracker::class);
        $this->app->instance(ExpenseTracker::class, $tracker);

        // Étape 2 : la liste passe les filtres validés au service
        $tracker->expects($this->once())
            ->method('list')
            ->with(['category' => 'food'])
            ->willReturn(new Collection());

        $this->getJson('/api/expenses?category=food')->assertOk()->assertExactJson([]);

        // Étape 3 : les stats passent par le même service
        $tracker->expects($this->once())
            ->method('stats')
            ->with(['from' => '2026-03-01'])
            ->willReturn(['total' => 12.5, 'count' => 1, 'by_category' => []]);

        $this->getJson('/api/expenses/stats?from=2026-03-01')
            ->assertOk()
            ->assertExactJson(['total' => 12.5, 'count' => 1, 'by_category' => []]);

        // Étape 4 : une requête invalide est bloquée avant d'arriver au service
        $tracker->expects($this->never())->method('create');

        $this->postJson('/api/expenses', ['title' => 'Sans montant'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'category', 'date']);
    }

    private function titles(Collection $expenses): array
    {
        return $expenses->pluck('title')->all();
    }
}
