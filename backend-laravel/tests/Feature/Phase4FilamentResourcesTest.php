<?php

use App\Models\Admin;
use App\Models\AiModel;
use App\Models\ImageLibrary;
use App\Models\KeywordLibrary;
use App\Models\KnowledgeBase;
use App\Models\Prompt;
use App\Models\Task;
use App\Models\TitleLibrary;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use function Pest\Livewire\livewire;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->actingAs(
        Admin::firstOrCreate(['username' => 'pest-admin'], [
            'password' => 'test', 'role' => 'super_admin', 'status' => 'active',
        ])
    );
});

/**
 * 烟雾测试：14 个 Phase 4 Resource 的 list 页都能渲染。
 * 详细字段验证留给 Phase 5 真正接入业务时逐个补。
 */
$listPageClasses = [
    'AiModel'         => \App\Filament\Resources\AiModels\Pages\ListAiModels::class,
    'Prompt'          => \App\Filament\Resources\Prompts\Pages\ListPrompts::class,
    'Task'            => \App\Filament\Resources\Tasks\Pages\ListTasks::class,
    'JobQueue'        => \App\Filament\Resources\JobQueues\Pages\ListJobQueues::class,
    'TaskRun'         => \App\Filament\Resources\TaskRuns\Pages\ListTaskRuns::class,
    'WorkerHeartbeat' => \App\Filament\Resources\WorkerHeartbeats\Pages\ListWorkerHeartbeats::class,
    'KeywordLibrary'  => \App\Filament\Resources\KeywordLibraries\Pages\ListKeywordLibraries::class,
    'Keyword'         => \App\Filament\Resources\Keywords\Pages\ListKeywords::class,
    'TitleLibrary'    => \App\Filament\Resources\TitleLibraries\Pages\ListTitleLibraries::class,
    'Title'           => \App\Filament\Resources\Titles\Pages\ListTitles::class,
    'ImageLibrary'    => \App\Filament\Resources\ImageLibraries\Pages\ListImageLibraries::class,
    'Image'           => \App\Filament\Resources\Images\Pages\ListImages::class,
    'KnowledgeBase'   => \App\Filament\Resources\KnowledgeBases\Pages\ListKnowledgeBases::class,
    'UrlImportJob'    => \App\Filament\Resources\UrlImportJobs\Pages\ListUrlImportJobs::class,
];

foreach ($listPageClasses as $name => $class) {
    it("Filament list page $name renders", function () use ($class) {
        livewire($class)->assertOk();
    });
}

describe('AiModelResource create', function () {
    it('creates an AI model with encrypted api_key via Filament', function () {
        livewire(\App\Filament\Resources\AiModels\Pages\CreateAiModel::class)
            ->set('data.name', 'FilamentAI_' . uniqid())
            ->set('data.model_id', 'test-x')
            ->set('data.model_type', 'chat')
            ->set('data.api_url', 'https://api.test')
            ->set('data.api_key', 'sk-via-filament-form')
            ->set('data.status', 'active')
            ->call('create')
            ->assertHasNoFormErrors();

        $m = AiModel::where('model_id', 'test-x')->latest('id')->first();
        expect($m)->not->toBeNull();
        // api_key 在 DB 是 enc:v1: 开头
        $raw = (string) \DB::table('ai_models')->where('id', $m->id)->value('api_key');
        expect($raw)->toStartWith('enc:v1:');
        expect($m->api_key)->toBe('sk-via-filament-form');
    });
});

describe('TaskResource create with default attrs', function () {
    it('creates a task using existing libs / model', function () {
        $lib = TitleLibrary::create(['name' => 'TLF_' . uniqid()]);
        $ai  = AiModel::create([
            'name' => 'AIF_' . uniqid(), 'model_id' => 'm', 'model_type' => 'chat',
            'api_url' => 'https://x.test', 'api_key' => 'k', 'status' => 'active',
        ]);

        $prompt = Prompt::create(['name' => 'PF_' . uniqid(), 'type' => 'content', 'content' => 'x']);
        livewire(\App\Filament\Resources\Tasks\Pages\CreateTask::class)
            ->set('data.name', 'TaskF_' . uniqid())
            ->set('data.title_library_id', $lib->id)
            ->set('data.ai_model_id', $ai->id)
            ->set('data.prompt_id', $prompt->id)
            ->set('data.status', 'idle')
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Task::where('title_library_id', $lib->id)->count())->toBeGreaterThan(0);
    });
});
