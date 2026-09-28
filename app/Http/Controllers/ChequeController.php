<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\ChequeStatusRequest;
use App\Models\Cheque;
use App\Services\ChequeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChequeController extends Controller
{
    public function __construct(protected ChequeService $cheques) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Cheque::class);
        $pending = Cheque::query()->where('status', 'pending');

        return view('cheques.index', [
            'receivable' => (clone $pending)->where('direction', 'received')->sum('amount'),
            'payable' => (clone $pending)->where('direction', 'issued')->sum('amount'),
            'dueNow' => (clone $pending)->whereDate('cheque_date', '<=', today())->count(),
        ]);
    }

    public function update(ChequeStatusRequest $request, Cheque $cheque): RedirectResponse
    {
        $data = $request->validated();
        try {
            match ($data['status']) {
                'cleared' => $this->cheques->clear($cheque, $request->user()),
                'bounced' => $this->cheques->bounce($cheque, $request->user(), $data['note'] ?? null),
                'cancelled' => $this->cheques->cancel($cheque, $request->user()),
            };
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', match ($data['status']) {
            'cleared' => __('Cheque :n marked as cleared.', ['n' => $cheque->number]),
            'bounced' => __('Cheque :n marked as bounced. The payment has been reversed.', ['n' => $cheque->number]),
            default => __('Cheque :n cancelled.', ['n' => $cheque->number]),
        });
    }
}
