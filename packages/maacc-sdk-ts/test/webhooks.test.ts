import assert from 'node:assert/strict';
import { test } from 'node:test';
import { signWebhook, verifyWebhook } from '../src/webhooks.ts';

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
