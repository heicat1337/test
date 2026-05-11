<?php

namespace App\Filament\Resources\ApiTokens\Pages;

use App\Filament\Resources\ApiTokens\ApiTokenResource;
use App\Models\ApiToken;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateApiToken extends CreateRecord
{
    protected static string $resource = ApiTokenResource::class;

    /**
     * 走 ApiToken::issue() 生成明文 + hash，明文通过 persistent notification 显示一次。
     * Filament Resource 默认的 record 写入流程被 short-circuit 掉。
     */
    protected function handleRecordCreation(array $data): Model
    {
        $admin = Auth::user();

        [$plaintext, $token] = ApiToken::issue(
            name: $data['name'],
            scopes: $data['scopes'] ?? [],
            createdByAdminId: $admin?->id,
            expiresAt: !empty($data['expires_at'])
                ? \Carbon\Carbon::parse($data['expires_at'])
                : null,
        );

        if (!empty($data['status']) && $data['status'] !== 'active') {
            $token->update(['status' => $data['status']]);
        }

        Notification::make()
            ->title('Token 创建成功')
            ->body("请立即复制保存（关闭后不再显示）：\n\n`{$plaintext}`")
            ->persistent()
            ->success()
            ->send();

        return $token;
    }
}
