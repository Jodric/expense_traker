<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExpenseTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatisticController extends Controller
{
    public function __construct(private ExpenseTracker $tracker)
    {
    }

    // GET /api/expenses/stats — total, nombre de dépenses, groupé par catégorie
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => 'nullable|string|max:100',
            'from'     => 'nullable|date',
            'to'       => 'nullable|date|after_or_equal:from',
        ]);

        return response()->json($this->tracker->stats($filters));
    }
}
