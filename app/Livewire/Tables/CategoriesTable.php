<?php

namespace App\Livewire\Tables;

use App\Livewire\Concerns\ModalForm;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class CategoriesTable extends DataTable
{
    use ModalForm;

    protected string $defaultSort = 'sort_order';

    protected string $defaultDirection = 'asc';

    protected ?string $formView = 'livewire.forms.category';

    protected function title(): string
    {
        return __('Categories');
    }

    protected function query(): Builder
    {
        return Category::query()->with('parent')->withCount('products');
    }

    protected function searchable(): array
    {
        return ['name', 'description'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('parent_id', __('Level'), ['top' => __('Top-level'), 'sub' => __('Sub-categories')])
                ->query(fn ($q, $v) => $v === 'top' ? $q->whereNull('parent_id') : $q->whereNotNull('parent_id')),
            Filter::boolean('is_active', __('Active')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Category'), 'name')->sortable()->html(fn ($c) => '<div class="d-flex align-items-center gap-2">'
                .'<span class="rounded-circle d-inline-block" style="width:10px;height:10px;background:'.e($c->color ?: '#4F46E5').'"></span>'
                .($c->parent ? '<span class="text-body-secondary">'.e($c->parent->name).' ›</span> ' : '')
                .'<span class="fw-semibold">'.e($c->name).'</span></div>')->exportAs(fn ($c) => $c->fullName()),
            Column::make(__('Description'), 'description'),
            Column::make(__('Products'), 'products_count')->number(),
            Column::make(__('Order'), 'sort_order')->sortable()->number(),
            Column::make(__('Status'), 'is_active')->boolean(),
        ];
    }

    protected function rowActions(mixed $row): ?string
    {
        return '<button class="btn btn-sm btn-light btn-icon" wire:click="openEdit('.$row->id.')" title="'.e(__('Edit')).'"><i class="bi bi-pencil"></i></button> '
            .'<button class="btn btn-sm btn-light btn-icon text-danger" wire:click="delete('.$row->id.')" wire:confirm="'.e(__('Delete this category? Products will become uncategorised.')).'" title="'.e(__('Delete')).'"><i class="bi bi-trash"></i></button>';
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-tags', 'title' => __('No categories yet'), 'message' => __('Group products like Beverages, Groceries or Pharmacy.')];
    }

    protected function authorizeForm(): void
    {
        abort_unless(auth()->user()->can('catalog.manage'), 403);
    }

    protected function formFields(): array
    {
        return ['name' => '', 'parent_id' => null, 'description' => '', 'color' => '#4F46E5', 'sort_order' => 0, 'is_active' => true];
    }

    protected function formRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->whereNull('parent_id'), Rule::notIn([$this->editingId])],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    protected function findRecord(int $id): mixed
    {
        return Category::findOrFail($id);
    }

    protected function persist(array $data): void
    {
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['parent_id'] = $data['parent_id'] ?: null;
        if ($this->editingId) {
            $category = Category::findOrFail($this->editingId);
            if ($data['parent_id'] && $category->children()->exists()) {
                $this->addError('form.parent_id', __('A category with sub-categories cannot become a sub-category.'));

                return;
            }
            $category->update($data);
        } else {
            Category::create($data);
        }
    }

    public function delete(int $id): void
    {
        $this->authorizeForm();
        $category = Category::findOrFail($id);
        $category->children()->update(['parent_id' => null]);
        $category->products()->update(['category_id' => null]);
        $category->delete();
        $this->dispatch('toast', message: __('Category deleted.'));
    }

    public function render()
    {
        return parent::render()->with('parents', Category::query()->whereNull('parent_id')->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))->orderBy('name')->pluck('name', 'id'));
    }
}
