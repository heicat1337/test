<?php

use App\Models\AiModel;
use App\Services\Ai\AiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

function mockChatResponse(string $content): void
{
    Http::fake([
        '*/chat/completions' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => $content]]],
        ], 200),
    ]);
}

beforeEach(function () {
    $this->model = AiModel::create([
        'name'       => 'AI_' . uniqid(),
        'model_id'   => 'gpt-test',
        'model_type' => 'chat',
        'api_url'    => 'https://api.test',
        'api_key'    => 'sk-fake-test-key',  // 经 PgTextArray-style mutator 加密存
        'status'     => 'active',
        'daily_limit' => 0,
        'used_today'  => 0,
        'total_used'  => 0,
    ]);
    $this->svc = new AiService();
});

describe('processPromptVariables', function () {
    it('replaces simple {{var}}', function () {
        $out = $this->svc->processPromptVariables('hello {{name}}', ['name' => 'world']);
        expect($out)->toBe('hello world');
    });

    it('replaces multiple variables', function () {
        $out = $this->svc->processPromptVariables('{{a}} + {{b}} = {{c}}', ['a' => '1', 'b' => '2', 'c' => '3']);
        expect($out)->toBe('1 + 2 = 3');
    });

    it('handles {{#if var}}...{{/if}} truthy', function () {
        $out = $this->svc->processPromptVariables('A{{#if x}}-mid-{{/if}}B', ['x' => 'yes']);
        expect($out)->toBe('A-mid-B');
    });

    it('handles {{#if var}}...{{/if}} falsy / missing', function () {
        $out = $this->svc->processPromptVariables('A{{#if x}}-mid-{{/if}}B', []);
        expect($out)->toBe('AB');

        $out = $this->svc->processPromptVariables('A{{#if x}}-mid-{{/if}}B', ['x' => '']);
        expect($out)->toBe('AB');
    });
});

describe('generateContent', function () {
    it('returns success payload with plain text content', function () {
        mockChatResponse('Hello from AI');

        $r = $this->svc->generateContent($this->model->id, 'write hello');
        expect($r['success'])->toBeTrue();
        expect($r['content'])->toBe('Hello from AI');
        expect($r['model'])->toBe($this->model->name);
    });

    it('strips <think>...</think> tags', function () {
        mockChatResponse("<think>let me reason</think>\nFinal answer");

        $r = $this->svc->generateContent($this->model->id, 'q');
        expect($r['content'])->toBe('Final answer');
    });

    it('strips leading prose before first markdown heading', function () {
        mockChatResponse("Some thinking text here\n# Real Title\n\nbody");

        $r = $this->svc->generateContent($this->model->id, 'q');
        expect($r['content'])->toStartWith('# Real Title');
    });

    it('fails when model not found', function () {
        $r = $this->svc->generateContent(9999999, 'x');
        expect($r['success'])->toBeFalse();
        expect($r['error'])->toContain('AI 模型不存在');
    });

    it('respects daily_limit', function () {
        $this->model->update(['daily_limit' => 5, 'used_today' => 5]);
        $r = $this->svc->generateContent($this->model->id, 'x');
        expect($r['success'])->toBeFalse();
        expect($r['error'])->toContain('已达上限');
    });

    it('returns error on HTTP non-200', function () {
        Http::fake(['*/chat/completions' => Http::response(['error' => 'rate limit'], 429)]);
        $r = $this->svc->generateContent($this->model->id, 'x');
        expect($r['success'])->toBeFalse();
        expect($r['error'])->toContain('HTTP 状态码: 429');
    });
});

describe('updateModelUsage', function () {
    it('increments used_today and total_used same day', function () {
        $this->model->update(['used_today' => 2, 'total_used' => 100]);
        $this->svc->updateModelUsage($this->model->id);

        $fresh = $this->model->fresh();
        expect($fresh->used_today)->toBe(3);
        expect($fresh->total_used)->toBe(101);
    });

    it('resets used_today across day boundary', function () {
        // 把 updated_at 改到昨天
        AiModel::whereKey($this->model->id)->update([
            'used_today' => 50,
            'total_used' => 500,
            'updated_at' => now()->subDay(),
        ]);

        $this->svc->updateModelUsage($this->model->id);

        $fresh = AiModel::find($this->model->id);
        expect($fresh->used_today)->toBe(1);
        expect($fresh->total_used)->toBe(501);
    });
});

describe('callApi endpoint resolution', function () {
    it('appends /v1/chat/completions to bare host', function () {
        Http::fake([
            'https://api.test/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'ok']]],
            ], 200),
        ]);

        $r = $this->svc->generateContent($this->model->id, 'q');
        expect($r['success'])->toBeTrue();
        Http::assertSent(fn ($req) => $req->url() === 'https://api.test/v1/chat/completions');
    });

    it('keeps explicit /chat/completions suffix', function () {
        $this->model->update(['api_url' => 'https://api.test/v2/chat/completions']);
        Http::fake([
            'https://api.test/v2/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'ok']]],
            ], 200),
        ]);

        $r = $this->svc->generateContent($this->model->id, 'q');
        expect($r['success'])->toBeTrue();
        Http::assertSent(fn ($req) => $req->url() === 'https://api.test/v2/chat/completions');
    });
});
