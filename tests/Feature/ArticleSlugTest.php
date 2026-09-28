<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSlugTest extends TestCase
{
    use RefreshDatabase;

    private const PERSIAN_SLUG = 'دسته موتور ماشین-674';

    private const ENGLISH_SLUG = 'engine-belt-674';

    private function createArticle(string $slug): Article
    {
        return Article::create([
            'title' => 'Article ' . $slug,
            'slug' => $slug,
            'content' => 'Article content',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_web_route_resolves_persian_slug_url_encoded(): void
    {
        $this->createArticle(self::PERSIAN_SLUG);

        $response = $this->get('/articles/' . rawurlencode(self::PERSIAN_SLUG));

        $response->assertStatus(200);
    }

    public function test_web_route_resolves_persian_slug_raw_utf8_path(): void
    {
        $this->createArticle(self::PERSIAN_SLUG);

        $response = $this->get('/articles/' . self::PERSIAN_SLUG);

        $response->assertStatus(200);
    }

    public function test_api_returns_article_for_persian_slug(): void
    {
        $article = $this->createArticle(self::PERSIAN_SLUG);

        $response = $this->getJson('/api/articles/' . rawurlencode(self::PERSIAN_SLUG));

        $response->assertOk()->assertJsonPath('slug', $article->slug);
    }

    public function test_web_route_still_resolves_english_slug(): void
    {
        $this->createArticle(self::ENGLISH_SLUG);

        $response = $this->get('/articles/' . self::ENGLISH_SLUG);

        $response->assertStatus(200);
    }

    public function test_api_returns_article_for_english_slug(): void
    {
        $article = $this->createArticle(self::ENGLISH_SLUG);

        $response = $this->getJson('/api/articles/' . self::ENGLISH_SLUG);

        $response->assertOk()->assertJsonPath('slug', $article->slug);
    }

    public function test_api_still_returns_404_for_unknown_slug(): void
    {
        $this->createArticle(self::PERSIAN_SLUG);

        $this->getJson('/api/articles/' . rawurlencode('missing-article-slug'))
            ->assertNotFound();
    }
}
