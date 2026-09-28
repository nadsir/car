<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSlugTest extends TestCase
{
    use RefreshDatabase;

    /** Real slug stored in the articles table (Persian yeh U+06CC, no spaces). */
    private const PERSIAN_SLUG = 'شسیبسشیبیسب';

    /** Persian slug containing regular spaces (URL must carry them as %20). */
    private const PERSIAN_SLUG_WITH_SPACE = 'دسته موتور ماشین-674';

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

    // 1 + 2. Persian slug in DB: URL-encoded and raw UTF-8 on the web route

    public function test_web_route_serves_persian_slug_url_encoded(): void
    {
        $this->createArticle(self::PERSIAN_SLUG);

        $this->get('/articles/' . rawurlencode(self::PERSIAN_SLUG))
            ->assertStatus(200);
    }

    public function test_web_route_serves_persian_slug_raw_utf8(): void
    {
        $this->createArticle(self::PERSIAN_SLUG);

        $this->get('/articles/' . self::PERSIAN_SLUG)
            ->assertStatus(200);
    }

    public function test_web_route_serves_persian_slug_with_space_url_encoded(): void
    {
        $this->createArticle(self::PERSIAN_SLUG_WITH_SPACE);

        $this->get('/articles/' . rawurlencode(self::PERSIAN_SLUG_WITH_SPACE))
            ->assertStatus(200);
    }

    // 5. API stays correct for Persian slugs

    public function test_api_returns_persian_slug_article_url_encoded(): void
    {
        $article = $this->createArticle(self::PERSIAN_SLUG);

        $this->getJson('/api/articles/' . rawurlencode(self::PERSIAN_SLUG))
            ->assertOk()
            ->assertJsonPath('slug', $article->slug);
    }

    public function test_api_returns_persian_slug_article_raw_utf8(): void
    {
        $article = $this->createArticle(self::PERSIAN_SLUG);

        $this->getJson('/api/articles/' . self::PERSIAN_SLUG)
            ->assertOk()
            ->assertJsonPath('slug', $article->slug);
    }

    public function test_api_returns_persian_slug_with_space_url_encoded(): void
    {
        $article = $this->createArticle(self::PERSIAN_SLUG_WITH_SPACE);

        $this->getJson('/api/articles/' . rawurlencode(self::PERSIAN_SLUG_WITH_SPACE))
            ->assertOk()
            ->assertJsonPath('slug', $article->slug);
    }

    // 3. English slug regression

    public function test_web_route_serves_english_slug(): void
    {
        $this->createArticle(self::ENGLISH_SLUG);

        $this->get('/articles/' . self::ENGLISH_SLUG)
            ->assertStatus(200);
    }

    public function test_api_returns_english_slug_article(): void
    {
        $article = $this->createArticle(self::ENGLISH_SLUG);

        $this->getJson('/api/articles/' . self::ENGLISH_SLUG)
            ->assertOk()
            ->assertJsonPath('slug', $article->slug);
    }

    // 4. Unknown slug: API 404s with the expected message, web route serves the SPA shell

    public function test_api_returns_404_with_message_for_unknown_persian_slug(): void
    {
        $this->createArticle(self::PERSIAN_SLUG);

        $this->getJson('/api/articles/' . rawurlencode('شسیبسشیبسب'))
            ->assertNotFound()
            ->assertJson(['message' => 'Article not found.']);
    }

    public function test_web_route_serves_spa_shell_for_unknown_slug(): void
    {
        $this->createArticle(self::PERSIAN_SLUG);

        $this->get('/articles/' . rawurlencode('شسیبسشیبسب'))
            ->assertStatus(200);
    }

    // The server must decode the URL exactly once: a double-encoded slug is a different string.

    public function test_api_does_not_match_double_encoded_persian_slug(): void
    {
        $article = $this->createArticle(self::PERSIAN_SLUG);

        $doubleEncoded = rawurlencode(rawurlencode(self::PERSIAN_SLUG));

        $this->getJson('/api/articles/' . $doubleEncoded)
            ->assertNotFound()
            ->assertJson(['message' => 'Article not found.']);

        $this->assertStringContainsString('%', rawurldecode($doubleEncoded));
        $this->assertNotSame($article->slug, rawurldecode($doubleEncoded));
    }

    // Browser pipeline: location.pathname holds an encoded segment, the client must
    // decode it and then encode exactly once for the API request.

    public function test_decoded_pathname_pipeline_reaches_persian_article(): void
    {
        $article = $this->createArticle(self::PERSIAN_SLUG);

        $pathSegment = rawurlencode(self::PERSIAN_SLUG);
        $slug = rawurldecode($pathSegment);

        $this->getJson('/api/articles/' . rawurlencode($slug))
            ->assertOk()
            ->assertJsonPath('slug', $article->slug);
    }

    public function test_article_detail_page_decodes_pathname_before_calling_api(): void
    {
        $source = file_get_contents(base_path('resources/js/ArticleDetailPage.vue'));

        $this->assertStringNotContainsString(
            'encodeURIComponent(match[1])',
            $source,
            'ArticleDetailPage must not encode the already percent-encoded location.pathname segment.'
        );

        $this->assertStringContainsString(
            'decodeURIComponent(',
            $source,
            'ArticleDetailPage must decode the location.pathname segment before encoding it for the API.'
        );
    }
}
