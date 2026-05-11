<?php

namespace App\Filament\Resources\Tasks\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('task')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('基本')
                        ->schema([
                            TextInput::make('name')
                                ->label('任务名')
                                ->required()
                                ->maxLength(200)
                                ->columnSpanFull(),

                            Select::make('status')
                                ->label('状态')
                                ->options([
                                    'idle'      => '闲置',
                                    'running'   => '运行中',
                                    'completed' => '已完成',
                                    'error'     => '错误',
                                    'paused'    => '暂停',
                                ])
                                ->default('idle')
                                ->required(),

                            Toggle::make('schedule_enabled')
                                ->label('启用调度'),

                            Toggle::make('is_loop')
                                ->label('循环执行'),

                            TextInput::make('publish_interval')
                                ->label('发布间隔 (秒)')
                                ->numeric()
                                ->default(3600),

                            TextInput::make('draft_limit')
                                ->label('草稿上限')
                                ->numeric()
                                ->default(0)
                                ->helperText('0 = 无限制'),

                            TextInput::make('max_retry_count')
                                ->label('最大重试次数')
                                ->numeric()
                                ->default(3),
                        ])
                        ->columns(2),

                    Tab::make('资源关联')
                        ->schema([
                            Select::make('title_library_id')
                                ->label('标题库')
                                ->relationship('titleLibrary', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('image_library_id')
                                ->label('图库')
                                ->relationship('imageLibrary', 'name')
                                ->searchable()
                                ->preload(),

                            TextInput::make('image_count')
                                ->label('每篇图片数')
                                ->numeric()
                                ->default(0),

                            Select::make('knowledge_base_id')
                                ->label('知识库')
                                ->relationship('knowledgeBase', 'name')
                                ->searchable()
                                ->preload(),
                        ])
                        ->columns(2),

                    Tab::make('AI & Prompt')
                        ->schema([
                            Select::make('ai_model_id')
                                ->label('AI 模型')
                                ->relationship('aiModel', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('model_selection_mode')
                                ->label('模型选择策略')
                                ->options([
                                    'fixed'    => '固定模型',
                                    'failover' => '故障切换',
                                    'random'   => '随机',
                                ])
                                ->default('fixed'),

                            Select::make('prompt_id')
                                ->label('标题 Prompt')
                                ->relationship('prompt', 'name')
                                ->searchable()
                                ->preload(),

                            Select::make('content_prompt_id')
                                ->label('正文 Prompt')
                                ->relationship('contentPrompt', 'name')
                                ->searchable()
                                ->preload(),
                        ])
                        ->columns(2),

                    Tab::make('作者与分类')
                        ->schema([
                            Select::make('author_type')
                                ->label('作者选择')
                                ->options([
                                    'random' => '随机',
                                    'fixed'  => '指定',
                                ])
                                ->default('random'),

                            Select::make('custom_author_id')
                                ->label('指定作者')
                                ->relationship('customAuthor', 'name')
                                ->searchable()
                                ->preload(),

                            Select::make('category_mode')
                                ->label('分类策略')
                                ->options([
                                    'smart' => '智能',
                                    'fixed' => '固定',
                                ])
                                ->default('smart'),

                            Select::make('fixed_category_id')
                                ->label('固定分类')
                                ->relationship('fixedCategory', 'name')
                                ->searchable()
                                ->preload(),
                        ])
                        ->columns(2),

                    Tab::make('发布控制')
                        ->schema([
                            Toggle::make('need_review')
                                ->label('需要审核')
                                ->helperText('开启后文章默认状态为待审'),

                            Toggle::make('auto_keywords')
                                ->label('自动生成关键词'),

                            Toggle::make('auto_description')
                                ->label('自动生成 Meta Description'),
                        ])
                        ->columns(3),

                    Tab::make('运行状态')
                        ->schema([
                            TextInput::make('created_count')
                                ->label('已生成')
                                ->numeric()
                                ->disabled(),

                            TextInput::make('published_count')
                                ->label('已发布')
                                ->numeric()
                                ->disabled(),

                            TextInput::make('loop_count')
                                ->label('循环次数')
                                ->numeric()
                                ->disabled(),

                            DateTimePicker::make('next_run_at')
                                ->label('下次运行'),

                            DateTimePicker::make('last_run_at')
                                ->label('上次运行')
                                ->disabled(),

                            DateTimePicker::make('last_success_at')
                                ->label('上次成功')
                                ->disabled(),
                        ])
                        ->columns(2),
                ]),
        ]);
    }
}
