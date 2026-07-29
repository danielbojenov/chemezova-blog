<?php

namespace App\Filament\Support;

use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Icons\Heroicon;

/**
 * Adds an "Insert affiliate link" toolbar button to the rich editor. The button
 * wraps the selection in a plain <a> pointing at the internal /go/{slug}
 * redirect of a registry AffiliateLink, so no TipTap extensions are needed —
 * the built-in Link mark carries the href.
 */
class AffiliateLinkPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('affiliateLink')
                ->label('Insert affiliate link')
                ->icon(Heroicon::Banknotes)
                ->action(arguments: '{ href: $getEditor().getAttributes(\'link\')?.href }')
                ->activeJsExpression('$getEditor()?.getAttributes(\'link\')?.href?.startsWith(\'/go/\')'),
        ];
    }

    public function getEditorActions(): array
    {
        return [
            AffiliateLinkAction::make(),
        ];
    }
}
