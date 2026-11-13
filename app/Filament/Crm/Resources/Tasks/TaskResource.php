<?php

namespace App\Filament\Crm\Resources\Tasks;

use App\Enums\Crm\TaskType;
use App\Filament\Crm\Resources\Customers\CustomerResource;
use App\Filament\Crm\Resources\Customers\RelationManagers\TasksRelationManager;
use App\Filament\Crm\Resources\Tasks\Pages\ListTasks;
use App\Filament\Crm\Support\CrmFields;
use App\Models\Task;
use App\Models\User;
use App\Support\Crm\ReorderReminders;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Việc cần làm có hạn (gọi lại, gửi báo giá, chăm sóc, nhắc tái đặt hàng, thu nợ). */
class TaskResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = Task::class;

    protected static ?string $slug = 'viec-can-lam';

    protected static string|\UnitEnum|null $navigationGroup = 'Bán hàng';

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'việc';

    protected static ?string $pluralModelLabel = 'Việc cần làm';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(CrmFields::user())->with(['customer', 'assignee']);
    }

    /** Huy hiệu: việc của tôi đến hạn hôm nay hoặc quá hạn. */
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $count = $user instanceof User ? Task::query()->where('assigned_to', $user->id)->dueToday()->count() : 0;

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('Việc')->required()->maxLength(255)->columnSpanFull(),
            Select::make('type')->label('Loại')->options(TaskType::class)->default(TaskType::Callback)->required(),
            DateTimePicker::make('due_at')->label('Hạn')->timezone(config('app.display_timezone'))
                ->default(fn () => ReorderReminders::nineAm(now(config('app.display_timezone'))->addDay()->toDateString()))
                ->required()->native(false)->displayFormat('d/m/Y H:i'),
            CrmFields::owner('assigned_to', 'Người làm'),
            CrmFields::customer()->hiddenOn(TasksRelationManager::class),
            Textarea::make('notes')->label('Ghi chú')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_at')
            ->columns([
                IconColumn::make('done')->label('Xong')->state(fn (Task $record): bool => $record->done_at !== null)->boolean(),
                TextColumn::make('due_at')->label('Hạn')->dateTime('d/m/Y H:i', config('app.display_timezone'))->sortable()
                    ->color(fn (Task $record): ?string => $record->done_at === null && $record->due_at->isPast() ? 'danger' : null),
                TextColumn::make('title')->label('Việc')->wrap()->searchable(),
                TextColumn::make('type')->label('Loại')->badge(),
                TextColumn::make('customer.name')->label('Khách hàng')->placeholder('—')
                    ->url(fn (Task $record): ?string => $record->customer_id ? CustomerResource::getUrl('edit', ['record' => $record->customer_id]) : null),
                TextColumn::make('assignee.name')->label('Người làm'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Loại')->options(TaskType::class),
                SelectFilter::make('assigned_to')->label('Người làm')->options(fn () => User::salesPeople()->pluck('name', 'id')),
            ])
            ->recordActions([
                Action::make('done')->label('Xong')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (Task $record): bool => $record->done_at === null)
                    ->action(fn (Task $record) => $record->forceFill(['done_at' => now()])->save()),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListTasks::route('/')];
    }
}
