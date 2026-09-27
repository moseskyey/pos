<?php

namespace App\Livewire\Tables;

use App\Livewire\Concerns\ModalForm;
use App\Models\Unit;
use App\Models\UnitConversion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class UnitsTable extends DataTable
{
    use ModalForm;

    protected string $defaultSort = 'name';

    protected string $defaultDirection = 'asc';

    protected ?string $formView = 'livewire.forms.unit';

    public array $conversion = ['from_unit_id' => null, 'to_unit_id' => null, 'factor' => null];

    protected function title(): string
    {
        return __('Units');
    }

    protected function query(): Builder
    {
        return Unit::query()->with('conversions.toUnit');
    }

    protected function searchable(): array
    {
        return ['name', 'short_name'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Unit'), 'name')->sortable()->html(fn ($u) => '<span class="fw-semibold">'.e($u->name).'</span>')->exportAs(fn ($u) => $u->name),
            Column::make(__('Symbol'), 'short_name')->sortable()->html(fn ($u) => '<span class="badge text-bg-secondary-soft font-monospace">'.e($u->short_name).'</span>')->exportAs(fn ($u) => $u->short_name),
            Column::make(__('Decimals'), 'allow_decimal')->html(fn ($u) => $u->allow_decimal ? '<i class="bi bi-check-circle-fill text-success"></i> '.e(__('Yes')) : '<span class="text-body-secondary">'.e(__('Whole numbers')).'</span>')->exportAs(fn ($u) => $u->allow_decimal ? 'Yes' : 'No'),
            Column::make(__('Default conversions'))->html(fn ($u) => $u->conversions->map(fn ($c) => '<span class="badge text-bg-info-soft me-1">1 '.e($u->short_name).' = '.e(qty($c->factor)).' '.e($c->toUnit->short_name).'</span>')->join('') ?: '—')
                ->exportAs(fn ($u) => $u->conversions->map(fn ($c) => '1 '.$u->short_name.' = '.qty($c->factor).' '.$c->toUnit->short_name)->join(', ')),
            Column::make(__('Status'), 'is_active')->boolean(),
        ];
    }

    protected function rowActions(mixed $row): ?string
    {
        return '<button class="btn btn-sm btn-light btn-icon" wire:click="openEdit('.$row->id.')" title="'.e(__('Edit')).'"><i class="bi bi-pencil"></i></button>';
    }

    protected function authorizeForm(): void
    {
        abort_unless(auth()->user()->can('catalog.manage'), 403);
    }

    protected function formFields(): array
    {
        return ['name' => '', 'short_name' => '', 'allow_decimal' => false, 'is_active' => true];
    }

    protected function formRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'short_name' => ['required', 'string', 'max:10', Rule::unique('units', 'short_name')->ignore($this->editingId)],
            'allow_decimal' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    protected function findRecord(int $id): mixed
    {
        return Unit::findOrFail($id);
    }

    protected function persist(array $data): void
    {
        $this->editingId ? Unit::findOrFail($this->editingId)->update($data) : Unit::create($data);
    }

    public function addConversion(): void
    {
        $this->authorizeForm();
        $data = $this->validate([
            'conversion.from_unit_id' => ['required', 'exists:units,id', 'different:conversion.to_unit_id'],
            'conversion.to_unit_id' => ['required', 'exists:units,id'],
            'conversion.factor' => ['required', 'numeric', 'gt:0'],
        ], [], ['conversion.from_unit_id' => 'from unit', 'conversion.to_unit_id' => 'to unit', 'conversion.factor' => 'factor'])['conversion'];
        UnitConversion::updateOrCreate(['from_unit_id' => $data['from_unit_id'], 'to_unit_id' => $data['to_unit_id']], ['factor' => $data['factor']]);
        $this->conversion = ['from_unit_id' => null, 'to_unit_id' => null, 'factor' => null];
        $this->dispatch('toast', message: __('Conversion saved.'));
    }

    public function deleteConversion(int $id): void
    {
        $this->authorizeForm();
        UnitConversion::whereKey($id)->delete();
    }

    public function render()
    {
        return parent::render()->with([
            'unitOptions' => Unit::query()->orderBy('name')->get()->mapWithKeys(fn ($u) => [$u->id => $u->label()]),
            'conversions' => UnitConversion::query()->with(['fromUnit', 'toUnit'])->get(),
        ]);
    }
}
