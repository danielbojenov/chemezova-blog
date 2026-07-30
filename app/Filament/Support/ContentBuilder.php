<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Support\Enums\Size;

/**
 * The article content builder, which adds an "add first" action to Filament's builder.
 *
 * Filament only renders an add button after the last item, plus the hover-revealed add
 * button between items, so there is no way to prepend a block to a long article without
 * adding it at the bottom and moving it up one step at a time. This builder registers an
 * extra `addFirst` action and renders it above the item list; everything else is
 * inherited unchanged.
 *
 * @see resources/views/filament/forms/content-builder.blade.php
 */
class ContentBuilder extends Builder
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.content-builder';

    protected string|Closure|null $addFirstActionLabel = null;

    protected ?Closure $modifyAddFirstActionUsing = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            fn (ContentBuilder $component): Action => $component->getAddFirstAction(),
        ]);
    }

    public function getAddFirstAction(): Action
    {
        $action = Action::make($this->getAddFirstActionName())
            ->label(fn (ContentBuilder $component) => $component->getAddFirstActionLabel())
            ->color('gray')
            ->action(function (array $arguments, ContentBuilder $component, array $data = []): void {
                $newKey = $component->generateUuid();

                $items = $component->getRawState() ?? [];

                $newItem = [
                    'type' => $arguments['block'],
                    'data' => $data,
                ];

                if ($newKey) {
                    $items = [$newKey => $newItem, ...$items];
                } else {
                    array_unshift($items, $newItem);

                    $newKey = array_key_first($items);
                }

                $component->rawState($items);

                $component->getChildSchema($newKey)->fill(filled($data) ? $data : null);

                $component->collapsed(false, shouldMakeComponentCollapsible: false);

                $component->callAfterStateUpdated();

                $component->shouldPartiallyRenderAfterActionsCalled() ? $component->partiallyRender() : null;
            })
            ->livewireClickHandlerEnabled(false)
            ->button()
            ->size(Size::Small)
            ->visible(fn (ContentBuilder $component): bool => $component->isAddable() && count($component->getItems()));

        if ($this->modifyAddFirstActionUsing) {
            $action = $this->evaluate($this->modifyAddFirstActionUsing, [
                'action' => $action,
            ]) ?? $action;
        }

        return $action;
    }

    public function addFirstAction(?Closure $callback): static
    {
        $this->modifyAddFirstActionUsing = $callback;

        return $this;
    }

    public function getAddFirstActionName(): string
    {
        return 'addFirst';
    }

    public function addFirstActionLabel(string|Closure|null $label): static
    {
        $this->addFirstActionLabel = $label;

        return $this;
    }

    public function getAddFirstActionLabel(): string
    {
        return $this->evaluate($this->addFirstActionLabel) ?? $this->getAddActionLabel();
    }
}
