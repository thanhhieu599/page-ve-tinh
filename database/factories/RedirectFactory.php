<?php

namespace Database\Factories;

use App\Models\Redirect;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'from_path' => '/'.fake()->unique()->slug(3),
            'to_path' => '/'.fake()->slug(3),
            'code' => Redirect::MOVED_PERMANENTLY,
        ];
    }

    public function gone(): static
    {
        return $this->state(['code' => Redirect::GONE, 'to_path' => null]);
    }
}
