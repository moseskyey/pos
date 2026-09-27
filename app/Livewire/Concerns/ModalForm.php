<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\On;

/**
 * Inline create/edit modal for simple master data tables.
 * The host must implement formFields(), formRules(), findRecord() and persist().
 */
trait ModalForm
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    abstract protected function formFields(): array;

    abstract protected function formRules(): array;

    abstract protected function persist(array $data): void;

    abstract protected function findRecord(int $id): mixed;

    protected function authorizeForm(): void {}

    #[On('open-create')]
    public function openCreate(): void
    {
        $this->authorizeForm();
        $this->resetValidation();
        $this->editingId = null;
        $this->form = $this->formFields();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeForm();
        $this->resetValidation();
        $record = $this->findRecord($id);
        $this->editingId = $record->getKey();
        $this->form = array_merge($this->formFields(), collect($record->only(array_keys($this->formFields())))
            ->map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v)->all());
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function saveForm(bool $andNew = false): void
    {
        $this->authorizeForm();
        $rules = collect($this->formRules())->mapWithKeys(fn ($r, $k) => ["form.$k" => $r])->all();
        $validated = $this->validate($rules, [], collect($this->formRules())->keys()->mapWithKeys(fn ($k) => ["form.$k" => str_replace('_', ' ', $k)])->all());
        $this->persist($validated['form']);

        $this->dispatch('toast', message: $this->editingId ? __('Saved successfully.') : __('Created successfully.'), type: 'success');

        if ($andNew) {
            $this->openCreate();
        } else {
            $this->showForm = false;
        }
    }
}
