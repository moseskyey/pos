<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Http\Requests\ProductImportRequest;
use App\Services\ProductImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductImportController extends Controller
{
    public function __construct(protected ProductImportService $service) {}

    public function create(Request $request): View
    {
        $this->authorize('products.import');
        $preview = null;
        $token = $request->session()->get('product_import');
        if ($token && Storage::disk('local')->exists("imports/$token.json")) {
            $preview = json_decode(Storage::disk('local')->get("imports/$token.json"), true);
        }

        return view('products.import', ['preview' => $preview]);
    }

    public function upload(ProductImportRequest $request): RedirectResponse
    {
        $this->authorize('products.import');
        $token = bin2hex(random_bytes(8));
        $path = $request->file('file')->storeAs('imports', "$token.".$request->file('file')->getClientOriginalExtension(), 'local');
        $rows = $this->service->preview(Storage::disk('local')->path($path));
        Storage::disk('local')->delete($path);
        Storage::disk('local')->put("imports/$token.json", json_encode($rows));
        $request->session()->put('product_import', $token);

        if (! $rows) {
            return back()->with('error', __('The file has no product rows.'));
        }

        return redirect()->route('products.import');
    }

    public function commit(Request $request): RedirectResponse
    {
        $this->authorize('products.import');
        $token = $request->session()->pull('product_import');
        abort_unless($token && Storage::disk('local')->exists("imports/$token.json"), 404);

        $rows = json_decode(Storage::disk('local')->get("imports/$token.json"), true);
        Storage::disk('local')->delete("imports/$token.json");
        [$created, $updated, $skipped] = $this->service->commit($rows, $request->user());

        return redirect()->route('products.index')->with('success', __('Import complete: :c created, :u updated, :s skipped.', ['c' => $created, 'u' => $updated, 's' => $skipped]));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $token = $request->session()->pull('product_import');
        if ($token) {
            Storage::disk('local')->delete("imports/$token.json");
        }

        return redirect()->route('products.import');
    }

    /** Download the rows that failed validation, with their errors, to fix and re-upload. */
    public function errors(Request $request): BinaryFileResponse
    {
        $this->authorize('products.import');
        $token = $request->session()->get('product_import');
        abort_unless($token && Storage::disk('local')->exists("imports/$token.json"), 404);

        $rows = collect(json_decode(Storage::disk('local')->get("imports/$token.json"), true))
            ->filter(fn ($row) => $row['errors'])
            ->map(fn ($row) => [...array_values(array_merge(array_fill_keys(array_keys(ProductImportService::COLUMNS), null), array_intersect_key($row['data'], ProductImportService::COLUMNS))), $row['row'], implode(' ', $row['errors'])])
            ->values()->all();
        abort_if($rows === [], 404);

        return Excel::download(
            new ArrayExport([...ProductImportService::headings(), __('Row'), __('Errors')], $rows, 'Errors'),
            'product-import-errors-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    public function template(Request $request): BinaryFileResponse
    {
        $this->authorize('products.import');

        return Excel::download(new ArrayExport(ProductImportService::headings(), $this->service->templateRows(), 'Products'), 'dukapos-products-template.xlsx');
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('products.import');
        activity('exports')->log('Exported all products');

        return Excel::download(new ArrayExport(ProductImportService::headings(), $this->service->exportRows(), 'Products'), 'products-'.now()->format('Ymd').'.xlsx');
    }
}
