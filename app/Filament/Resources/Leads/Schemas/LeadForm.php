<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        $tz = config('app.display_timezone');

        return $schema
            ->columns(1)
            ->components([
                Section::make('Theo dõi bán hàng')->columns(3)->schema([
                    Select::make('status')->label('Trạng thái')->options(LeadStatus::class)->required(),
                    TextInput::make('deal_value')->label('Giá trị đơn (VNĐ)')->integer()->minValue(0)->suffix('đ'),
                    DateTimePicker::make('won_at')->label('Ngày chốt')->seconds(false)->timezone($tz),
                ]),

                Section::make('Khách hàng')->columns(2)->schema([
                    TextEntry::make('name')->label('Họ tên'),
                    TextEntry::make('company')->label('Công ty'),
                    TextEntry::make('email')->label('Email')->copyable(),
                    TextEntry::make('phone')->label('Điện thoại')->copyable(),
                    TextEntry::make('need')->label('Nhu cầu')->columnSpanFull(),
                    TextEntry::make('product.name')->label('Sản phẩm')->placeholder('—'),
                    TextEntry::make('is_verified')->label('Kiểm tra chống bot')
                        ->state(fn (Lead $record): string => $record->is_verified ? 'Đã xác minh' : 'Chưa xác minh (gửi khi tắt JavaScript hoặc chưa bật Turnstile)'),
                ]),

                Section::make('Nguồn')->columns(2)->collapsible()->schema([
                    TextEntry::make('utm_source')->label('utm_source')->placeholder('—'),
                    TextEntry::make('utm_medium')->label('utm_medium')->placeholder('—'),
                    TextEntry::make('utm_campaign')->label('utm_campaign')->placeholder('—'),
                    TextEntry::make('landing_url')->label('Trang vào đầu tiên')->placeholder('—'),
                    TextEntry::make('page_url')->label('Trang gửi form')->placeholder('—'),
                ]),

                Section::make('Bằng chứng đồng ý xử lý dữ liệu')->collapsible()->collapsed()->schema([
                    TextEntry::make('consent_text')->label('Nội dung đã đồng ý'),
                    TextEntry::make('consent_at')->label('Thời điểm')->dateTime('d/m/Y H:i:s', $tz),
                    TextEntry::make('ip')->label('IP'),
                ]),
            ]);
    }
}
