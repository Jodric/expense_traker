<?php
namespace App\Models;

use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    protected $fillable = ['title', 'amount', 'category', 'date'];

    protected function casts(): array
    {
        return [
            'date'   => 'date:Y-m-d',
            'amount' => 'decimal:2',
        ];
    }
}
