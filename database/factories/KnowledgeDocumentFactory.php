<?php

namespace Database\Factories;

use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeDocument>
 */
class KnowledgeDocumentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<KnowledgeDocument>
     */
    protected $model = KnowledgeDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = fake()->sentence(14)."\n\n".fake()->sentence(14);

        return [
            'knowledge_source_id' => KnowledgeSource::factory(),
            'title' => fake()->sentence(4),
            'uri' => 'https://docs.example.com/'.fake()->unique()->slug(3),
            'body' => $body,
            'checksum' => hash('sha256', $body),
            'metadata' => ['author' => fake()->name(), 'published_at' => '2026-01-01'],
            'indexed_at' => null,
        ];
    }
}
