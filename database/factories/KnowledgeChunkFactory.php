<?php

namespace Database\Factories;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSource;
use App\Support\Runtime\Knowledge\Tokenizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeChunk>
 */
class KnowledgeChunkFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<KnowledgeChunk>
     */
    protected $model = KnowledgeChunk::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = fake()->sentence(12);
        $tokens = Tokenizer::tokenize($content);

        return [
            'knowledge_document_id' => KnowledgeDocument::factory(),
            'knowledge_source_id' => KnowledgeSource::factory(),
            'ordinal' => 0,
            'content' => $content,
            'tokens' => $tokens,
            'token_count' => count($tokens),
        ];
    }

    /**
     * Build a chunk with the given content (and its derived tokens).
     */
    public function withContent(string $content): static
    {
        return $this->state(function (array $attributes) use ($content): array {
            $tokens = Tokenizer::tokenize($content);

            return [
                'content' => $content,
                'tokens' => $tokens,
                'token_count' => count($tokens),
            ];
        });
    }
}
