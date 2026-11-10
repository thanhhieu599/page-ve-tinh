<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'site_id' => Site::factory(),
            'slug' => Str::slug($title),
            'locale' => 'vi',
            'title' => $title,
            'excerpt' => fake()->paragraph(),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'status' => PostStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }
}
