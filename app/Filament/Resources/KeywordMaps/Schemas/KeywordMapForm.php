<?php

namespace App\Filament\Resources\KeywordMaps\Schemas;

use App\Enums\KeywordIntent;
use App\Filament\Support\TenantSite;
use App\Models\KeywordMap;
use App\Support\CanonicalPath;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

class KeywordMapForm
{
    public static function configure(Schema $schema): Schema
    {
        $domain = TenantSite::site()->domain ?? '';

        return $schema->components([
            TextInput::make('keyword')
                ->label('Từ khoá')
                ->required()
                ->maxLength(191)
                ->disabledOn('edit')
                ->helperText('Mỗi từ khoá chỉ có một trang đích trên toàn hệ thống, để các trang không tranh thứ hạng của nhau.')
                ->rules([
                    fn (Get $get, ?KeywordMap $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        $owner = KeywordMap::ownerOf((string) $value, $record?->id);

                        if ($owner !== null && ! (self::canReassign() && $get('override'))) {
                            $fail("Từ khoá này đã thuộc trang {$owner->targetUrl()}. "
                                .(self::canReassign() ? 'Bật "Chuyển từ khoá sang trang này" để ghi đè.' : 'Chỉ Admin được chuyển từ khoá sang trang khác.'));
                        }
                    },
                ]),

            TextInput::make('target_url')
                ->label('Trang đích')
                ->prefix($domain)
                ->placeholder('/kien-thuc/cach-chon-giay-in-a4')
                ->required()
                // Đổi trang đích = chuyển từ khoá sang trang khác: chỉ Admin.
                ->disabled(fn (string $operation): bool => $operation === 'edit' && ! self::canReassign())
                ->maxLength(255)
                ->helperText('Đường dẫn trong site, dạng chuẩn: bắt đầu bằng "/", chữ thường, không "/" cuối.')
                ->rules([
                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $path = (string) $value;
                        if (! str_starts_with($path, '/') || CanonicalPath::from($path) !== $path) {
                            $fail('Nhập đường dẫn dạng chuẩn trong site, ví dụ /kien-thuc/cach-chon-giay-in-a4.');
                        }
                    },
                ]),

            Select::make('intent')
                ->label('Mục đích tìm kiếm')
                ->options(KeywordIntent::class)
                ->required(),

            Toggle::make('override')
                ->label('Chuyển từ khoá sang trang này (ghi đè trang đang giữ)')
                ->helperText('Trang cũ sẽ mất từ khoá này. Nhớ sửa nội dung trang cũ để không còn nhắm từ khoá.')
                ->visible(fn (string $operation): bool => $operation === 'create' && self::canReassign())
                ->dehydrated(false),
        ]);
    }

    public static function canReassign(): bool
    {
        return Gate::allows('reassign', KeywordMap::class);
    }
}
