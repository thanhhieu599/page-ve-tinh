<?php

namespace Database\Factories;

use App\Models\Faq;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'question' => rtrim(fake()->sentence(), '.').'?',
            'answer' => fake()->paragraph(),
            'sort' => 0,
        ];
    }
}
