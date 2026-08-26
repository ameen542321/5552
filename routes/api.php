<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cashier\QuickSaleController;

Route::get('/ping', function () {
    return 'pong';
});
// Route::get('/products/search', function () {
//     $query = request('query');
//     $storeId = auth()->user()->store_id;

//     return \App\Models\Product::where('store_id', $storeId)
//         ->where(function ($q) use ($query) {
//             $q->where('name', 'like', "%$query%")
//               ->orWhere('barcode', 'like', "%$query%");
//         })
//         ->limit(10)
//         ->get();
// });

// Route::get('/quick-sale/credit-persons', [QuickSaleController::class, 'creditPersons'])
//     ->middleware('auth')
//     ->name('quick-sale.credit-persons');
