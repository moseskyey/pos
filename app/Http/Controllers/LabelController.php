<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrintLabelsRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LabelController extends Controller
{
    public const SIZES = [
        'a4-30' => ['label' => 'A4 sheet · 30 labels (3×10, 70×29.7mm)', 'cols' => 3, 'per_page' => 30, 'w' => '70mm', 'h' => '29.7mm'],
        'a4-40' => ['label' => 'A4 sheet · 40 labels (4×10, 52.5×29.7mm)', 'cols' => 4, 'per_page' => 40, 'w' => '52.5mm', 'h' => '29.7mm'],
        'a4-65' => ['label' => 'A4 sheet · 65 labels (5×13, 38.1×21.2mm)', 'cols' => 5, 'per_page' => 65, 'w' => '38.1mm', 'h' => '21.2mm'],
        'roll-50x25' => ['label' => 'Label roll · 50×25mm', 'cols' => 1, 'per_page' => 1, 'w' => '50mm', 'h' => '25mm'],
    ];

    public function index(Request $request): View
    {
        $this->authorize('products.labels');
        $ids = collect(explode(',', (string) $request->query('products')))->filter()->map(fn ($id) => (int) $id);
        $selected = Product::query()->sellable()->whereIn('id', $ids)->orderBy('name')->get();

        return view('labels.index', [
            'selected' => $selected,
            'products' => Product::query()->active()->sellable()->orderBy('name')->limit(1000)->get(['id', 'name', 'sku']),
            'sizes' => self::SIZES,
        ]);
    }

    public function print(PrintLabelsRequest $request): View
    {
        $this->authorize('products.labels');
        $data = $request->validated();

        $products = Product::with('barcodes')->whereIn('id', collect($data['items'])->pluck('product_id'))->get()->keyBy('id');
        $labels = [];
        foreach ($data['items'] as $item) {
            $product = $products[$item['product_id']];
            for ($i = 0; $i < $item['quantity']; $i++) {
                $labels[] = $product;
            }
        }
        activity('products')->withProperties(['labels' => count($labels)])->log('Printed barcode labels');

        return view('labels.print', [
            'labels' => $labels,
            'size' => self::SIZES[$data['size']],
            'sizeKey' => $data['size'],
            'showPrice' => $request->boolean('show_price', true),
            'showName' => $request->boolean('show_name', true),
        ]);
    }
}
