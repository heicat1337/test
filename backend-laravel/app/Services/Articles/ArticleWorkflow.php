<?php

namespace App\Services\Articles;

use Carbon\Carbon;

/**
 * 文章 status/review_status workflow state machine。
 * 与老 backend includes/functions.php::normalize_article_workflow_state 完全对齐。
 *
 * 规则：
 *   - status        ∈ ['draft', 'published', 'private']
 *   - review_status ∈ ['pending', 'approved', 'rejected', 'auto_approved']
 *   - review pending/rejected → status 强制 'draft'
 *   - status published + review pending/rejected → review 修为 'approved'
 *   - review auto_approved → status 强制 'published'
 *   - status published + review pending → review 修为 'approved'
 *   - status published → published_at 填当前时间（若空）；否则 published_at = null
 */
class ArticleWorkflow
{
    public const STATUSES = ['draft', 'published', 'private'];
    public const REVIEW_STATUSES = ['pending', 'approved', 'rejected', 'auto_approved'];

    /**
     * @return array{status:string, review_status:string, published_at:?string}
     */
    public static function normalize(string $status, string $reviewStatus, ?string $publishedAt = null): array
    {
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'draft';
        }
        if (!in_array($reviewStatus, self::REVIEW_STATUSES, true)) {
            $reviewStatus = 'pending';
        }

        if (in_array($reviewStatus, ['pending', 'rejected'], true)) {
            $status = 'draft';
        }

        if ($status === 'published' && in_array($reviewStatus, ['pending', 'rejected'], true)) {
            $reviewStatus = 'approved';
        }

        if ($status !== 'published' && $reviewStatus === 'auto_approved') {
            $status = 'published';
        }

        if ($status === 'published' && $reviewStatus === 'pending') {
            $reviewStatus = 'approved';
        }

        if ($status === 'published') {
            $publishedAt = $publishedAt ?: Carbon::now()->toDateTimeString();
        } else {
            $publishedAt = null;
        }

        return [
            'status'        => $status,
            'review_status' => $reviewStatus,
            'published_at'  => $publishedAt,
        ];
    }
}
