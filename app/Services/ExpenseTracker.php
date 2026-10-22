<?php
namespace App\Services;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Logique métier des dépenses, partagée par les contrôleurs
 * (ExpenseController, StatisticController) ou toute autre ressource.
 */
class ExpenseTracker
{
    // Liste les dépenses, les plus récentes d'abord
    public function list(array $filters = []): Collection
    {
        return $this->filtered($filters)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }

    public function create(array $data): Expense
    {
        return Expense::create($data);
    }

    // Total, nombre de dépenses et répartition par catégorie
    public function stats(array $filters = []): array
    {
        $byCategory = $this->filtered($filters)
            ->select(
                'category',
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('category')
            ->orderByDesc('total')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'total'    => round((float) $row->total, 2),
                'count'    => (int) $row->count,
            ]);

        return [
            'total'       => round($byCategory->sum('total'), 2),
            'count'       => $byCategory->sum('count'),
            'by_category' => $byCategory->values(),
        ];
    }

    // Filtres communs : category, from, to (dates incluses)
    private function filtered(array $filters): Builder
    {
        return Expense::query()
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('date', '<=', $to));
    }
}
