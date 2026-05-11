<?php

use App\Models\Admin;
use App\Models\AiModel;
use App\Models\ApiToken;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Prompt;
use App\Models\Task;
use App\Models\TitleLibrary;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function bearer(array $scopes, ?int $adminId = null): array
{
    $adminId ??= Admin::firstOrCreate(['username' => 'pest-admin'], [
        'password' => 'x', 'role' => 'super_admin', 'status' => 'active',
    ])->id;
    [$plain, ] = ApiToken::issue('pest-' . uniqid(), $scopes, $adminId);
    return ['Authorization' => "Bearer {$plain}"];
}

beforeEach(function () {
    $this->admin = Admin::firstOrCreate(['username' => 'pest-admin'], [
        'password' => 'x', 'role' => 'super_admin', 'status' => 'active',
    ]);
});

describe('ApiTokenAuth middleware', function () {
    it('rejects missing Authorization with 401', function () {
        $r = $this->getJson('/api/v1/catalog');
        $r->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized');
    });

    it('rejects malformed Authorization', function () {
        $r = $this->withHeaders(['Authorization' => 'NotBearer xxx'])->getJson('/api/v1/catalog');
        $r->assertStatus(401);
    });

    it('rejects unknown token hash', function () {
        $r = $this->withHeaders(['Authorization' => 'Bearer xua_unknown_token_does_not_exist'])
            ->getJson('/api/v1/catalog');
        $r->assertStatus(401)
            ->assertJsonPath('error.message', 'Token 无效或已过期');
    });

    it('rejects expired token', function () {
        [$plain] = ApiToken::issue('expired', ['catalog:read'], $this->admin->id, now()->subDay());
        $r = $this->withHeaders(['Authorization' => "Bearer {$plain}"])->getJson('/api/v1/catalog');
        $r->assertStatus(401);
    });

    it('rejects revoked token', function () {
        [$plain, $tok] = ApiToken::issue('revoked', ['catalog:read'], $this->admin->id);
        $tok->revoke();
        $r = $this->withHeaders(['Authorization' => "Bearer {$plain}"])->getJson('/api/v1/catalog');
        $r->assertStatus(401);
    });

    it('updates last_used_at on success', function () {
        [$plain, $tok] = ApiToken::issue('lu', ['catalog:read'], $this->admin->id);
        expect($tok->last_used_at)->toBeNull();

        $this->withHeaders(['Authorization' => "Bearer {$plain}"])->getJson('/api/v1/catalog');
        expect($tok->fresh()->last_used_at)->not->toBeNull();
    });
});

describe('RequireScope middleware', function () {
    it('403 when token lacks scope', function () {
        $r = $this->withHeaders(bearer(['nope:read']))->getJson('/api/v1/catalog');
        $r->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden')
            ->assertJsonPath('error.details.required_scope', 'catalog:read');
    });

    it('200 when token has the scope', function () {
        $r = $this->withHeaders(bearer(['catalog:read']))->getJson('/api/v1/catalog');
        $r->assertOk();
    });

    it('200 with wildcard scope', function () {
        $r = $this->withHeaders(bearer(['*']))->getJson('/api/v1/catalog');
        $r->assertOk();
    });
});

describe('GET /api/v1/catalog', function () {
    it('returns the 6 catalog buckets', function () {
        $r = $this->withHeaders(bearer(['catalog:read']))->getJson('/api/v1/catalog');
        $r->assertOk()
            ->assertJsonStructure([
                'data' => ['models', 'prompts', 'title_libraries', 'knowledge_bases', 'authors', 'categories'],
            ]);
    });
});

describe('Tasks management API', function () {
    function p53_taskFixture(): Task
    {
        $tl = TitleLibrary::create(['name' => 'TL_' . uniqid()]);
        $pr = Prompt::create(['name' => 'P_' . uniqid(), 'type' => 'content', 'content' => 'x']);
        $ai = AiModel::create([
            'name' => 'AI_' . uniqid(), 'model_id' => 'x', 'model_type' => 'chat',
            'api_url' => 'https://x.test', 'api_key' => 'k', 'status' => 'active',
        ]);
        return Task::create([
            'name' => 'T_' . uniqid(),
            'title_library_id' => $tl->id, 'prompt_id' => $pr->id,
            'ai_model_id' => $ai->id, 'status' => 'active',
        ]);
    }

    it('GET /tasks lists with pagination', function () {
        p53_taskFixture();
        $r = $this->withHeaders(bearer(['tasks:read']))->getJson('/api/v1/tasks?per_page=5');
        $r->assertOk()
            ->assertJsonStructure(['data' => ['items', 'pagination']]);
    });

    it('GET /tasks/{id} returns single task', function () {
        $t = p53_taskFixture();
        $r = $this->withHeaders(bearer(['tasks:read']))->getJson("/api/v1/tasks/{$t->id}");
        $r->assertOk()
            ->assertJsonPath('data.id', $t->id)
            ->assertJsonPath('data.status', 'active');
    });

    it('POST /tasks/{id}/start activates task', function () {
        $t = p53_taskFixture();
        $t->update(['status' => 'paused']);

        $r = $this->withHeaders(bearer(['tasks:write']))->postJson("/api/v1/tasks/{$t->id}/start");
        $r->assertOk()
            ->assertJsonPath('data.status', 'active');
    });

    it('POST /tasks/{id}/stop pauses task', function () {
        $t = p53_taskFixture();
        $r = $this->withHeaders(bearer(['tasks:write']))->postJson("/api/v1/tasks/{$t->id}/stop");
        $r->assertOk()
            ->assertJsonPath('data.status', 'paused');
    });

    it('PATCH writes need tasks:write scope', function () {
        $t = p53_taskFixture();
        // 只读 token 不行
        $r = $this->withHeaders(bearer(['tasks:read']))->patchJson("/api/v1/tasks/{$t->id}", ['name' => 'x']);
        $r->assertStatus(403);
        // 写权限通过
        $r = $this->withHeaders(bearer(['tasks:write']))->patchJson("/api/v1/tasks/{$t->id}", ['name' => 'Renamed']);
        $r->assertOk()->assertJsonPath('data.name', 'Renamed');
    });
});

describe('Articles admin API', function () {
    function p53_articleFixture(): array
    {
        $cat = Category::create(['name' => 'C_' . uniqid(), 'slug' => 'c-' . uniqid()]);
        $au  = Author::create(['name' => 'A_' . uniqid()]);
        return [$cat, $au];
    }

    it('POST /admin/articles creates a draft', function () {
        [$cat, $au] = p53_articleFixture();
        $r = $this->withHeaders(bearer(['articles:write']))->postJson('/api/v1/admin/articles', [
            'title' => 'Pest Draft', 'content' => 'body',
            'category_id' => $cat->id, 'author_id' => $au->id,
        ]);
        $r->assertCreated()
            ->assertJsonPath('data.title', 'Pest Draft')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.review_status', 'pending');
    });

    it('POST /admin/articles/{id}/review records audit', function () {
        [$cat, $au] = p53_articleFixture();
        $a = Article::create([
            'title' => 'R', 'slug' => 'r-' . uniqid(), 'content' => 'c',
            'category_id' => $cat->id, 'author_id' => $au->id,
        ]);
        $r = $this->withHeaders(bearer(['articles:publish']))->postJson("/api/v1/admin/articles/{$a->id}/review", [
            'review_status' => 'approved', 'review_note' => 'ok',
        ]);
        $r->assertOk()->assertJsonPath('data.review_status', 'approved');
    });

    it('POST /admin/articles/{id}/publish requires approved', function () {
        [$cat, $au] = p53_articleFixture();
        $a = Article::create([
            'title' => 'P', 'slug' => 'p-' . uniqid(), 'content' => 'c',
            'category_id' => $cat->id, 'author_id' => $au->id,
            'status' => 'draft', 'review_status' => 'pending',
        ]);
        $r = $this->withHeaders(bearer(['articles:publish']))->postJson("/api/v1/admin/articles/{$a->id}/publish");
        $r->assertStatus(409)->assertJsonPath('error.code', 'article_not_publishable');
    });

    it('POST /admin/articles/{id}/trash soft deletes', function () {
        [$cat, $au] = p53_articleFixture();
        $a = Article::create([
            'title' => 'T', 'slug' => 't-' . uniqid(), 'content' => 'c',
            'category_id' => $cat->id, 'author_id' => $au->id,
        ]);
        $r = $this->withHeaders(bearer(['articles:write']))->postJson("/api/v1/admin/articles/{$a->id}/trash");
        $r->assertOk()->assertJsonPath('data.trashed', true);
        expect(Article::find($a->id))->toBeNull();
    });
});

describe('Response envelope shape', function () {
    it('error responses include code/message/details + meta', function () {
        $r = $this->getJson('/api/v1/catalog');
        $r->assertJsonStructure([
            'success', 'data', 'error' => ['code', 'message'],
            'meta' => ['request_id', 'timestamp'],
        ]);
    });

    it('success responses include data + meta', function () {
        $r = $this->withHeaders(bearer(['catalog:read']))->getJson('/api/v1/catalog');
        $r->assertJsonStructure([
            'success', 'data', 'error',
            'meta' => ['request_id', 'timestamp'],
        ])->assertJsonPath('success', true)
          ->assertJsonPath('error', null);
    });
});
