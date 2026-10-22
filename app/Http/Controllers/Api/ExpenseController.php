<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExpenseTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseTracker $tracker)
    {
    }

    // GET /api/expenses — liste les dépenses avec filtres (category, from, to)
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => 'nullable|string|max:100',
            'from'     => 'nullable|date',
            'to'       => 'nullable|date|after_or_equal:from',
        ]);

        return response()->json($this->tracker->list($filters));
    }

    // POST /api/expenses — ajouter une nouvelle dépense
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'    => 'required|string|max:255',
            'amount'   => 'required|numeric|min:0.01|max:99999999.99',
            'category' => 'required|string|max:100',
            'date'     => 'required|date',
        ]);

        return response()->json($this->tracker->create($data), 201);
    }
}
