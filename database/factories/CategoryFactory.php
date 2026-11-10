<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'site_id' => Site::factory(),
            'slug' => Str::slug($name),
            'name' => ucfirst($name),
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(['parent_id' => $parent->id, 'site_id' => $parent->site_id]);
    }

    public function requiresExpertReview(): static
    {
        return $this->state(['requires_expert_review' => true]);
    }
}
