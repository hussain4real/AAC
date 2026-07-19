import assert from 'node:assert/strict';
import { test } from 'node:test';
import { signWebhook, verifyWebhook, WebhookDeliveryVerifier } from '../src/webhooks.ts';

const PAYLOAD = '{"event":"run.completed"}';
const SECRET = 'whsec_unit_secret';

test('verifies a signature it produced within the tolerance window', () => {
  const signature = `sha256=${signWebhook(PAYLOAD, '1000', SECRET)}`;

  assert.equal(verifyWebhook(PAYLOAD, signature, '1000', SECRET, 300, 1100), true);
  assert.equal(verifyWebhook(PAYLOAD, signWebhook(PAYLOAD, '1000', SECRET), '1000', SECRET, 300, 1000), true);
});

test('rejects a signature outside the tolerance window', () => {
  const signature = `sha256=${signWebhook(PAYLOAD, '1000', SECRET)}`;

  assert.equal(verifyWebhook(PAYLOAD, signature, '1000', SECRET, 300, 5000), false);
});

test('rejects a tampered payload, a bad signature, and a non-numeric timestamp', () => {
  const signature = `sha256=${signWebhook(PAYLOAD, '1000', SECRET)}`;

  assert.equal(verifyWebhook('{"event":"run.failed"}', signature, '1000', SECRET, 300, 1000), false);
  assert.equal(verifyWebhook(PAYLOAD, 'sha256=deadbeef', '1000', SECRET, 300, 1000), false);
  assert.equal(verifyWebhook(PAYLOAD, signature, 'not-a-number', SECRET, 300, 1000), false);
  assert.equal(verifyWebhook(PAYLOAD, signature, '', SECRET, 300, 1000), false);
});

test('verifies ordering metadata and identifies duplicate delivery ids', () => {
  const verifier = new WebhookDeliveryVerifier(SECRET);
  const timestamp = '1000';
  const headers = {
    signature: `sha256=${signWebhook(PAYLOAD, timestamp, SECRET)}`,
    timestamp,
    deliveryId: 'delivery-1',
    sequence: '7',
  };

  assert.deepEqual(verifier.verify(PAYLOAD, headers, 1000), {
    accepted: true,
    duplicate: false,
    deliveryId: 'delivery-1',
    sequence: 7,
    payload: JSON.parse(PAYLOAD),
  });
  assert.equal(verifier.verify(PAYLOAD, headers, 1000).duplicate, true);
  assert.equal(verifier.verify(PAYLOAD, { ...headers, deliveryId: '', sequence: 'bad' }, 1000).accepted, false);
});
