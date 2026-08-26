<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Services\Products\ProductSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductSearchController extends Controller
{
    public function search(Request $request, ProductSearchService $productSearch)
    {
        $query = $request->query('query');

        // البحث عن المستخدم (محاسب أو مدير) ثم تفويض البحث للخدمة المشتركة.
        $user = Auth::guard('accountant')->user() ?: Auth::guard('web')->user();

        if (!$user || !$user->store_id) {
            return response()->json([]);
        }

        $products = $productSearch->quickSaleResults((int) $user->store_id, $query);

        return response()
            ->json($products)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
