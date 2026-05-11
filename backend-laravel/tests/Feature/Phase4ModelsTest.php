<?php

use App\Models\Admin;
use App\Models\AiModel;
use App\Models\ImageLibrary;
use App\Models\Keyword;
use App\Models\KeywordLibrary;
use App\Models\KnowledgeBase;
use App\Models\Prompt;
use App\Models\Task;
use App\Models\Title;
use App\Models\TitleLibrary;
use App\Models\UrlImportJob;
use App\Support\GeoFlowCrypt;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->actingAs(
        Admin::firstOrCreate(['username' => 'pest-admin'], [
            'password' => 'test', 'role' => 'super_admin', 'status' => 'active',
        ])
    );
});

describe('AiModel api_key encryption mutator', function () {
    it('writes ciphertext to PG and decrypts on read', function () {
        $m = AiModel::create([
            'name'      => 'TestModel_' . uniqid(),
            'model_id'  => 'test-model',
            'model_type'=> 'chat',
            'api_url'   => 'https://api.test',
            'api_key'   => 'sk-plain-secret-12345',
            'status'    => 'active',
        ]);
        // 直接查 DB 应该是密文
        $rawHash = (string) \DB::table('ai_models')->where('id', $m->id)->value('api_key');
        expect($rawHash)->toStartWith('enc:v1:');
        // 经 model 读出来是明文
        $fresh = AiModel::find($m->id);
        expect($fresh->api_key)->toBe('sk-plain-secret-12345');
    });

    it('compatible with raw GeoFlowCrypt::encrypt for old-backend interop', function () {
        $plain = 'sk-interop-' . uniqid();
        $cipher = GeoFlowCrypt::encrypt($plain);
        $m = AiModel::create([
            'name' => 'OldBackend',
            'model_id' => 'x', 'model_type' => 'chat',
            'api_url' => 'https://api.test',
            'api_key' => $cipher,  // 老 backend 写入风格——传密文进来
            'status' => 'active',
        ]);
        // 模型把 $cipher 当作明文又加密一次？不应该——mutator 应识别已加密并跳过
        $rawHash = (string) \DB::table('ai_models')->where('id', $m->id)->value('api_key');
        expect($rawHash)->toBe($cipher);  // 不重复加密
        expect(AiModel::find($m->id)->api_key)->toBe($plain);
    });
});

describe('Prompt model', function () {
    it('creates and retrieves', function () {
        $p = Prompt::create([
            'name'    => 'TestPrompt_' . uniqid(),
            'type'    => 'content',
            'content' => 'Write about {keyword}',
        ]);
        expect($p->type)->toBe('content');
        expect(Prompt::find($p->id)->content)->toBe('Write about {keyword}');
    });
});

describe('Task model with defaults', function () {
    it('creates with sensible default attribute values', function () {
        $lib = TitleLibrary::create(['name' => 'TL_' . uniqid()]);
        $ai  = AiModel::create([
            'name' => 'AI_' . uniqid(), 'model_id' => 'x', 'model_type' => 'chat',
            'api_url' => 'https://x.test', 'api_key' => 'k', 'status' => 'active',
        ]);

        $prompt = Prompt::create(['name' => 'P_' . uniqid(), 'type' => 'content', 'content' => 'x']);
        $t = Task::create([
            'name'             => 'T_' . uniqid(),
            'title_library_id' => $lib->id,
            'ai_model_id'      => $ai->id,
            'prompt_id'        => $prompt->id,
        ]);

        expect($t->status)->toBe('idle');
        expect($t->need_review)->toBe(1);
        expect($t->schedule_enabled)->toBe(1);
        expect($t->max_retry_count)->toBe(3);
        expect($t->category_mode)->toBe('smart');
        expect($t->author_type)->toBe('random');
    });

    it('relationships resolve', function () {
        $lib = TitleLibrary::create(['name' => 'TL2_' . uniqid()]);
        $ai  = AiModel::create([
            'name' => 'AI2_' . uniqid(), 'model_id' => 'x', 'model_type' => 'chat',
            'api_url' => 'https://x.test', 'api_key' => 'k', 'status' => 'active',
        ]);
        $kb = KnowledgeBase::create(['name' => 'KB_' . uniqid()]);

        $prompt = Prompt::create(['name' => 'P2_' . uniqid(), 'type' => 'content', 'content' => 'x']);
        $t = Task::create([
            'name' => 'TR_' . uniqid(),
            'title_library_id'  => $lib->id,
            'ai_model_id'       => $ai->id,
            'knowledge_base_id' => $kb->id,
            'prompt_id'         => $prompt->id,
        ]);

        $t = $t->fresh(['titleLibrary', 'aiModel', 'knowledgeBase']);
        expect($t->titleLibrary->id)->toBe($lib->id);
        expect($t->aiModel->id)->toBe($ai->id);
        expect($t->knowledgeBase->id)->toBe($kb->id);
    });
});

describe('Libraries hasMany', function () {
    it('KeywordLibrary -> Keywords', function () {
        $lib = KeywordLibrary::create(['name' => 'KL_' . uniqid()]);
        Keyword::create(['library_id' => $lib->id, 'keyword' => 'web3']);
        Keyword::create(['library_id' => $lib->id, 'keyword' => 'defi']);
        expect($lib->fresh()->keywords->pluck('keyword')->all())->toBe(['web3', 'defi']);
    });

    it('TitleLibrary -> Titles', function () {
        $lib = TitleLibrary::create(['name' => 'TL_' . uniqid()]);
        Title::create(['library_id' => $lib->id, 'title' => 'Hello']);
        expect($lib->fresh()->titles->pluck('title')->all())->toBe(['Hello']);
    });

    it('ImageLibrary minimal create', function () {
        $lib = ImageLibrary::create(['name' => 'IL_' . uniqid()]);
        expect($lib->image_count)->toBe(0);
    });
});

describe('UrlImportJob casts', function () {
    it('options_json/result_json round-trip as arrays', function () {
        $j = UrlImportJob::create([
            'url'               => 'https://example.test/article',
            'normalized_url'    => 'https://example.test/article',
            'source_domain'     => 'example.test',
            'status'            => 'pending',
            'progress_percent'  => 0,
            'options_json'      => ['publish' => true, 'category_id' => 1],
        ]);
        $fresh = UrlImportJob::find($j->id);
        expect($fresh->options_json)->toBe(['publish' => true, 'category_id' => 1]);
    });
});
