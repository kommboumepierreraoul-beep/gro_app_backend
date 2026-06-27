<?php
// tests/Unit/Services/DeepSeekServiceTest.php

namespace Tests\Unit\Services;

use App\Services\AI\DeepSeekService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests unitaires du DeepSeekService.
 * Utilise Http::fake() pour mock les appels API.
 */
class DeepSeekServiceTest extends TestCase
{
    private DeepSeekService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Renseigne une clé factice pour les tests
        config(['services.deepseek.api_key' => 'sk-test-fake-key']);
        $this->service = new DeepSeekService();
    }

    // ──────────────────────────────────────────────────────────
    // chat()
    // ──────────────────────────────────────────────────────────

    /** @test */
    public function chat_returns_success_response(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Bonjour, comment puis-je vous aider ?']]],
                'usage'   => ['total_tokens' => 42, 'prompt_tokens' => 20, 'completion_tokens' => 22],
                'model'   => 'deepseek-chat',
            ], 200),
        ]);

        $result = $this->service->chat([
            ['role' => 'user', 'content' => 'Bonjour'],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Bonjour, comment puis-je vous aider ?', $result['content']);
        $this->assertEquals(42, $result['usage']['total_tokens']);
    }

    /** @test */
    public function chat_returns_error_on_api_failure(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $result = $this->service->chat([
            ['role' => 'user', 'content' => 'Test'],
        ]);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['error']);
    }

    /** @test */
    public function chat_returns_error_on_rate_limit(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response(['error' => 'Too Many Requests'], 429),
        ]);

        $result = $this->service->chat([['role' => 'user', 'content' => 'Test']]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Quota', $result['error']);
    }

    /** @test */
    public function chat_returns_error_on_unauthorized(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $result = $this->service->chat([['role' => 'user', 'content' => 'Test']]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('API', $result['error']);
    }

    // ──────────────────────────────────────────────────────────
    // moderateContent()
    // ──────────────────────────────────────────────────────────

    /** @test */
    public function moderate_content_returns_safe_for_normal_text(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => [
                    'content' =>
                    '{"is_safe": true, "score": 0.02, "categories": [], "reason": "Contenu approprié"}'
                ]]],
                'usage' => ['total_tokens' => 50],
            ], 200),
        ]);

        $result = $this->service->moderateContent('Bonjour tout le monde, belle journée !');

        $this->assertTrue($result['is_safe']);
        $this->assertLessThan(0.5, $result['score']);
        $this->assertEmpty($result['categories']);
    }

    /** @test */
    public function moderate_content_detects_hate_speech(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => [
                    'content' =>
                    '{"is_safe": false, "score": 0.95, "categories": ["hate_speech", "harassment"], "reason": "Discours haineux détecté"}'
                ]]],
                'usage' => ['total_tokens' => 60],
            ], 200),
        ]);

        $result = $this->service->moderateContent('Contenu haineux simulé pour test');

        $this->assertFalse($result['is_safe']);
        $this->assertGreaterThan(0.8, $result['score']);
        $this->assertContains('hate_speech', $result['categories']);
    }

    /** @test */
    public function moderate_content_returns_safe_on_api_error(): void
    {
        // Fail open : en cas d'erreur API, on considère le contenu sûr
        Http::fake([
            'api.deepseek.com/*' => Http::response([], 500),
        ]);

        $result = $this->service->moderateContent('Contenu quelconque');

        $this->assertTrue($result['is_safe']);
    }

    /** @test */
    public function moderate_content_handles_malformed_json(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Réponse non-JSON du modèle']]],
                'usage' => ['total_tokens' => 30],
            ], 200),
        ]);

        $result = $this->service->moderateContent('Test malformed JSON');

        // Doit retourner le résultat par défaut sans planter
        $this->assertArrayHasKey('is_safe', $result);
        $this->assertArrayHasKey('score', $result);
    }

    // ──────────────────────────────────────────────────────────
    // generateTags()
    // ──────────────────────────────────────────────────────────

    /** @test */
    public function generate_tags_returns_array_of_strings(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => '["photographie", "débutant", "appareil-photo", "lumière", "composition"]']]],
                'usage' => ['total_tokens' => 40],
            ], 200),
        ]);

        $tags = $this->service->generateTags('Je débute en photographie et cherche des conseils…');

        $this->assertIsArray($tags);
        $this->assertCount(5, $tags);
        $this->assertContains('photographie', $tags);
    }

    /** @test */
    public function generate_tags_respects_max_tags_limit(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => '["tag1", "tag2", "tag3", "tag4", "tag5", "tag6", "tag7"]']]],
                'usage' => ['total_tokens' => 30],
            ], 200),
        ]);

        $tags = $this->service->generateTags('Contenu test', maxTags: 3);

        $this->assertCount(3, $tags);
    }

    /** @test */
    public function generate_tags_returns_empty_on_api_error(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response([], 500)]);

        $tags = $this->service->generateTags('Contenu test');

        $this->assertIsArray($tags);
        $this->assertEmpty($tags);
    }

    // ──────────────────────────────────────────────────────────
    // summarizeThread()
    // ──────────────────────────────────────────────────────────

    /** @test */
    public function summarize_thread_returns_string(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Les membres ont discuté de photographie et de composition.']]],
                'usage' => ['total_tokens' => 80],
            ], 200),
        ]);

        $summary = $this->service->summarizeThread([
            ['author' => 'Alice', 'content' => 'Quels conseils pour la photographie ?'],
            ['author' => 'Bob',   'content' => 'La composition est très importante.'],
            ['author' => 'Alice', 'content' => 'Merci, je vais essayer.'],
        ]);

        $this->assertIsString($summary);
        $this->assertNotEmpty($summary);
    }

    /** @test */
    public function summarize_thread_requires_at_least_two_messages(): void
    {
        $summary = $this->service->summarizeThread([
            ['author' => 'Alice', 'content' => 'Seul message'],
        ]);

        $this->assertStringContainsString('Pas assez', $summary);
    }
}
