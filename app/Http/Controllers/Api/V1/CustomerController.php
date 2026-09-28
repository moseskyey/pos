<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiListRequest;
use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(ApiListRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);
        $filters = $request->validated();

        return CustomerResource::collection(Customer::query()
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%$term%")->orWhere('phone', 'like', "%$term%")))
            ->when($filters['updated_since'] ?? null, fn ($q, $since) => $q->where('updated_at', '>=', $since))
            ->orderBy('id')->paginate($request->perPage())->withQueryString());
    }

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer);
    }

    public function store(CustomerRequest $request, CustomerLedgerService $ledger): JsonResponse
    {
        $data = $request->customerData();
        $customer = DB::transaction(function () use ($data, $ledger) {
            $customer = Customer::create(Arr::except($data, ['opening_balance']) + ['opening_balance' => $data['opening_balance'] ?? 0]);
            $ledger->openingBalance($customer, $data['opening_balance'] ?? 0);

            return $customer;
        });

        return (new CustomerResource($customer->fresh()))->response()->setStatusCode(201);
    }
}
