<?php

use App\Support\GeoFlowCrypt;

it('round-trips plaintext through encrypt/decrypt', function () {
    $plain = 'sk-test-abc-123';
    $cipher = GeoFlowCrypt::encrypt($plain);
    expect($cipher)->toStartWith('enc:v1:');
    expect(GeoFlowCrypt::decrypt($cipher))->toBe($plain);
});

it('handles empty string', function () {
    expect(GeoFlowCrypt::encrypt(''))->toBe('');
    expect(GeoFlowCrypt::decrypt(''))->toBe('');
});

it('passes already-encrypted value through unchanged', function () {
    $cipher = GeoFlowCrypt::encrypt('original');
    expect(GeoFlowCrypt::encrypt($cipher))->toBe($cipher);
});

it('returns plain string when decrypt input lacks prefix', function () {
    // 兼容老明文数据：没 enc:v1: 前缀直接返回
    expect(GeoFlowCrypt::decrypt('legacy-plaintext'))->toBe('legacy-plaintext');
});

it('isEncrypted recognizes prefix', function () {
    expect(GeoFlowCrypt::isEncrypted('enc:v1:abc'))->toBeTrue();
    expect(GeoFlowCrypt::isEncrypted('plain'))->toBeFalse();
    expect(GeoFlowCrypt::isEncrypted(''))->toBeFalse();
    expect(GeoFlowCrypt::isEncrypted(null))->toBeFalse();
});

it('handles unicode plaintext', function () {
    $plain = '玄猫密钥-🦄';
    expect(GeoFlowCrypt::decrypt(GeoFlowCrypt::encrypt($plain)))->toBe($plain);
});
