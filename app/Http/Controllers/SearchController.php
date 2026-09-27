<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Global navbar search (Ctrl+K): products, customers, invoices, suppliers.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        $user = $request->user();
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $results = [];

        if ($user->can('products.view') && class_exists(Product::class) && Route::has('products.show')) {
            Product::query()
                ->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('sku', 'like', "%$q%")
                    ->orWhereHas('barcodes', fn ($b) => $b->where('barcode', $q)))
                ->limit(5)->get(['id', 'name', 'sku', 'retail_price'])
                ->each(function ($p) use (&$results) {
                    $results[] = ['icon' => 'bi-box-seam text-primary', 'title' => $p->name, 'subtitle' => $p->sku.' · '.money($p->retail_price), 'url' => route('products.show', $p)];
                });
        }

        if ($user->can('customers.view') && class_exists(Customer::class) && Route::has('customers.show')) {
            $phone = PhoneNumber::normalize($q);
            Customer::query()
                ->where(fn ($w) => $w->where('name', 'like', "%$q%")->when($phone, fn ($x) => $x->orWhere('phone', $phone)))
                ->limit(5)->get(['id', 'name', 'phone'])
                ->each(function ($c) use (&$results) {
                    $results[] = ['icon' => 'bi-person text-success', 'title' => $c->name, 'subtitle' => PhoneNumber::display($c->phone), 'url' => route('customers.show', $c)];
                });
        }

        if (($user->can('sales.view') || $user->can('sales.view_all')) && class_exists(Sale::class) && Route::has('sales.show')) {
            Sale::query()
                ->where('number', 'like', "%$q%")
                ->when(! $user->can('sales.view_all'), fn ($w) => $w->where('user_id', $user->id))
                ->limit(5)->get(['id', 'number', 'total', 'created_at', 'status'])
                ->each(function ($s) use (&$results) {
                    $results[] = ['icon' => 'bi-receipt text-info', 'title' => $s->number, 'subtitle' => money($s->total).' · '.format_date($s->created_at, true), 'url' => route('sales.show', $s)];
                });
        }

        if ($user->can('suppliers.view') && class_exists(Supplier::class) && Route::has('suppliers.show')) {
            Supplier::query()->where('name', 'like', "%$q%")->limit(3)->get(['id', 'name', 'phone'])
                ->each(function ($s) use (&$results) {
                    $results[] = ['icon' => 'bi-building text-warning', 'title' => $s->name, 'subtitle' => __('Supplier'), 'url' => route('suppliers.show', $s)];
                });
        }

        return response()->json($results);
    }
}
