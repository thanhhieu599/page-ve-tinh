<?php

namespace App\Filament\Crm\Widgets;

use App\Enums\LeadStatus;
use App\Filament\Crm\Resources\Leads\LeadResource;
use App\Filament\Crm\Support\CrmFields;
use App\Models\Lead;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Lead mới chưa giao (chỉ trưởng phòng/Admin). */
class UnassignedLeads extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected static ?string $heading = 'Lead mới chưa giao';

    public static function canView(): bool
    {
        return auth()->check() && CrmFields::user()->isSalesManager();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Lead::withoutGlobalScopes()->whereNull('assigned_to')->where('status', LeadStatus::New)->with('site'))
            ->defaultSort('created_at', 'desc')
            ->paginated([5])
            ->emptyStateHeading('Mọi lead đã được giao')
            ->columns([
                TextColumn::make('created_at')->label('Nhận')->since(),
                TextColumn::make('company')->label('Công ty')->wrap(),
                TextColumn::make('site.domain')->label('Site')->placeholder('Nhập tay')->badge()->color('gray'),
            ])
            ->recordUrl(fn (Lead $record): string => LeadResource::getUrl('edit', ['record' => $record]));
    }
}
