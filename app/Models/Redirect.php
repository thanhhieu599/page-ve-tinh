<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use App\Support\CanonicalPath;
use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Chuyển hướng theo site: 301 sang địa chỉ mới, hoặc 410 cho trang đã gỡ.
 */
class Redirect extends Model
{
    use BelongsToSite;

    /** @use HasFactory<RedirectFactory> */
    use HasFactory;

    public const MOVED_PERMANENTLY = 301;

    public const GONE = 410;

    protected $fillable = ['from_path', 'to_path', 'code'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'code' => 'integer',
            'hits' => 'integer',
        ];
    }

    /**
     * Lưu đường dẫn nguồn ở dạng chuẩn để khớp với URL đã chuẩn hoá.
     *
     * @return Attribute<string, string>
     */
    protected function fromPath(): Attribute
    {
        return Attribute::make(set: fn (string $value) => CanonicalPath::from($value));
    }
}
