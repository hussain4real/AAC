<?php

use Maacc\Sdk\Webhooks\WebhookDeliveryVerifier;
use Maacc\Sdk\Webhooks\WebhookSignature;

test('the PHP SDK verifies and deduplicates webhook delivery metadata', function () {
    $secret = 'whsec_php_sdk';
    $body = '{"event":"run.completed"}';
    $timestamp = '1000';
    $verifier = new WebhookDeliveryVerifier($secret);
    $headers = [
        'signature' => 'sha256='.WebhookSignature::sign($body, $timestamp, $secret),
        'timestamp' => $timestamp,
        'delivery_id' => 'delivery-1',
        'sequence' => '9',
    ];

    $first = $verifier->verify($body, $headers, 1000);
    $second = $verifier->verify($body, $headers, 1000);

    expect($first)->toMatchArray([
        'accepted' => true,
        'duplicate' => false,
        'delivery_id' => 'delivery-1',
        'sequence' => 9,
    ])->and($second['duplicate'])->toBeTrue()
        ->and($verifier->verify($body, [...$headers, 'delivery_id' => ''], 1000)['accepted'])->toBeFalse();
});
