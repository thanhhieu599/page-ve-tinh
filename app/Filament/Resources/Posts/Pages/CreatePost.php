<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\Concerns\GuardsContentImages;
use App\Filament\Resources\Posts\Concerns\GuardsContentLinks;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\Site;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePost extends CreateRecord
{
    use GuardsContentImages;
    use GuardsContentLinks;

    protected static string $resource = PostResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->ensureImagesComeFromLibrary($data);
        $this->ensureLinksFollowPolicy($data);

        return $data;
    }

    /**
     * Bài mới luôn thuộc site đang chọn và luôn bắt đầu ở trạng thái nháp.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $post = new Post($data);
        $post->status = PostStatus::Draft;

        $site = Filament::getTenant();
        if ($site instanceof Site) {
            $post->site()->associate($site);
        }

        $post->save();

        return $post;
    }
}
