<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
   public function index()
{
    $transactions = Auth::user()->wallet->transactions()
                        ->with('wallet')
                        ->orderBy('id', 'desc')
                        ->paginate(20);
    return response()->json($transactions);
}

public function show($id)
{
    $transaction = Auth::user()->wallet->transactions()->findOrFail($id);
    return response()->json($transaction);
}
}