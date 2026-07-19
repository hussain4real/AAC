/* ============================================================
   MAACC — Webhook secret gate (Phase 6D)
   Surfaces the one-time webhook signing secret flashed by
   WebhookEndpointController on register/rotate (Inertia::flash(
   'webhookSecret', …)). The plaintext is never stored, so this
   modal is the only chance to copy it. Mounted once in the console
   layout so it works regardless of which screen triggered it.
   ============================================================ */
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Btn, CodeBlock, Field, Modal } from '@/components/maacc/ui';
import type { WebhookSecretFlash } from '@/types/ui';

export function WebhookSecretGate() {
    const [secret, setSecret] = useState<WebhookSecretFlash | null>(null);
    const [acknowledged, setAcknowledged] = useState(false);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.webhookSecret as WebhookSecretFlash | undefined;

            if (data) {
                setSecret(data);
                setAcknowledged(false);
            }
        });
    }, []);

    if (!secret) {
        return null;
    }

    return (
        <Modal
            open
            onClose={() => undefined}
            title="Webhook signing secret"
            sub="Copy this secret now — for security it is never shown again."
            icon="key"
            width={520}
            footer={
                <Btn
                    variant="primary"
                    icon="check"
                    disabled={!acknowledged}
                    onClick={() => setSecret(null)}
                >
                    I've stored it safely
                </Btn>
            }
        >
            <div style={{ display: 'grid', gap: 14 }}>
                <Field label="Endpoint URL">
                    <CodeBlock code={secret.url} copyable />
                </Field>
                <Field label="Signing secret">
                    <CodeBlock code={secret.secret} copyable />
                </Field>
                <div
                    style={{
                        display: 'flex',
                        gap: 8,
                        alignItems: 'flex-start',
                        padding: '10px 12px',
                        borderRadius: 'var(--r-sm)',
                        background: 'var(--amber-100)',
                        color: 'var(--amber-500)',
                        fontSize: 12,
                        fontWeight: 600,
                    }}
                >
                    Verify every delivery's <code>X-Maacc-Signature</code>{' '}
                    header with this secret. MAACC keeps an encrypted copy to
                    sign deliveries but never displays it again.
                </div>
                <label
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        gap: 9,
                        fontSize: 12.5,
                        fontWeight: 600,
                    }}
                >
                    <input
                        type="checkbox"
                        checked={acknowledged}
                        onChange={(event) =>
                            setAcknowledged(event.target.checked)
                        }
                    />
                    I confirm that I stored the endpoint and signing secret
                    safely.
                </label>
            </div>
        </Modal>
    );
}
