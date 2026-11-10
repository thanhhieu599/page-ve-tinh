<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** Hàng chờ duyệt của site đang chọn nằm ở tab "Chờ duyệt". */
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Tất cả')];

        foreach (PostStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status))
                ->badge($status === PostStatus::Pending ? Post::query()->where('status', $status)->count() : null)
                ->badgeColor($status->getColor());
        }

        return $tabs;
    }
}
