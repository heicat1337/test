<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Models\Article;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('article')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('内容')
                        ->schema([
                            TextInput::make('title')
                                ->label('标题')
                                ->required()
                                ->maxLength(500)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (?string $state, callable $set, ?Article $record) {
                                    // 创建时根据 title 自动填充 slug；编辑时不动
                                    if (!$record && filled($state)) {
                                        $set('slug', Str::slug($state) ?: 'article-' . Str::random(8));
                                    }
                                }),

                            TextInput::make('slug')
                                ->label('Slug')
                                ->required()
                                ->maxLength(500)
                                ->unique(ignoreRecord: true)
                                ->helperText('URL 路径，英文/数字/-'),

                            Textarea::make('excerpt')
                                ->label('摘要')
                                ->rows(2)
                                ->maxLength(500)
                                ->helperText('展示在文章卡片上，留空将自动截取正文前 200 字'),

                            Textarea::make('content')
                                ->label('正文')
                                ->required()
                                ->rows(20)
                                ->helperText('Markdown 或 HTML。下一阶段会接入 Filament rich editor。'),
                        ])
                        ->columns(1),

                    Tab::make('分类与作者')
                        ->schema([
                            Select::make('category_id')
                                ->label('分类')
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('author_id')
                                ->label('作者')
                                ->relationship('author', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->columns(2),

                    Tab::make('SEO')
                        ->schema([
                            TextInput::make('original_keyword')
                                ->label('原始关键词')
                                ->maxLength(200)
                                ->helperText('AI 生成文章时输入的种子关键词'),

                            TagsInput::make('keywords')
                                ->label('关键词 / 标签')
                                ->separator(',')
                                ->helperText('CSV 存储；前端文章页 meta keywords 用')
                                ->columnSpanFull(),

                            Textarea::make('meta_description')
                                ->label('Meta Description')
                                ->rows(2)
                                ->maxLength(300)
                                ->columnSpanFull(),

                            TextInput::make('featured_image')
                                ->label('封面图 URL')
                                ->url()
                                ->maxLength(500)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Tab::make('状态与发布')
                        ->schema([
                            Select::make('status')
                                ->label('状态')
                                ->options([
                                    'draft'     => '草稿',
                                    'published' => '已发布',
                                    'archived'  => '已归档',
                                ])
                                ->default('draft')
                                ->required(),

                            Select::make('review_status')
                                ->label('审核状态')
                                ->options([
                                    'pending'  => '待审核',
                                    'approved' => '已通过',
                                    'rejected' => '已拒绝',
                                ])
                                ->default('pending')
                                ->required(),

                            DateTimePicker::make('published_at')
                                ->label('发布时间')
                                ->placeholder('留空表示未发布'),

                            Toggle::make('is_featured')
                                ->label('精选'),

                            Toggle::make('is_ai_generated')
                                ->label('AI 生成')
                                ->disabled()
                                ->helperText('由生成任务自动写入'),

                            TextInput::make('view_count')
                                ->label('阅读量')
                                ->numeric()
                                ->disabled(),
                        ])
                        ->columns(2),
                ]),
        ]);
    }
}
