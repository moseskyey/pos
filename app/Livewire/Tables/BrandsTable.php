<?php

namespace App\Livewire\Tables;

use App\Livewire\Concerns\ModalForm;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class BrandsTable extends DataTable
{
    use ModalForm;

    protected string $defaultSort = 'name';

    protected string $defaultDirection = 'asc';

    protected ?string $formView = 'livewire.forms.brand';

    protected function title(): string
    {
        return __('Brands');
    }

    protected function query(): Builder
    {
        return Brand::query()->withCount('products');
    }

    protected function searchable(): array
    {
        return ['name'];
    }

    protected function filterDefinitions(): array
    {
        return [Filter::boolean('is_active', __('Active'))];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Brand'), 'name')->sortable()->html(fn ($b) => '<span class="fw-semibold">'.e($b->name).'</span>')->exportAs(fn ($b) => $b->name),
            Column::make(__('Products'), 'products_count')->sortable()->number(),
            Column::make(__('Status'), 'is_active')->boolean(),
        ];
    }

    protected function rowActions(mixed $row): ?string
    {
        return '<button class="btn btn-sm btn-light btn-icon" wire:click="openEdit('.$row->id.')" title="'.e(__('Edit')).'"><i class="bi bi-pencil"></i></button> '
            .'<button class="btn btn-sm btn-light btn-icon text-danger" wire:click="delete('.$row->id.')" wire:confirm="'.e(__('Delete this brand?')).'" title="'.e(__('Delete')).'"><i class="bi bi-trash"></i></button>';
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-award', 'title' => __('No brands yet'), 'message' => __('Add brands like Azam, Kilimanjaro or Coca-Cola.')];
    }

    protected function authorizeForm(): void
    {
        abort_unless(auth()->user()->can('catalog.manage'), 403);
    }

    protected function formFields(): array
    {
        return ['name' => '', 'is_active' => true];
    }

    protected function formRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($this->editingId)],
            'is_active' => ['boolean'],
        ];
    }

    protected function findRecord(int $id): mixed
    {
        return Brand::findOrFail($id);
    }

    protected function persist(array $data): void
    {
        $this->editingId ? Brand::findOrFail($this->editingId)->update($data) : Brand::create($data);
    }

    public function delete(int $id): void
    {
        $this->authorizeForm();
        $brand = Brand::findOrFail($id);
        $brand->products()->update(['brand_id' => null]);
        $brand->delete();
        $this->dispatch('toast', message: __('Brand deleted.'));
    }
}
