/* ============================================================
   MAAC — Credential secret gate (Phase 7)
   Surfaces the one-time application credential secret flashed by
   CredentialController on generate/rotate (Inertia::flash(
   'credentialSecret', …)). The plaintext is never stored, so this
   modal is the only chance to copy it. Mounted once in the console
   layout so it works regardless of which screen triggered it.
   ============================================================ */
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Btn, CodeBlock, Field, Modal } from '@/components/maac/ui';
import type { CredentialSecretFlash } from '@/types/ui';

export function CredentialSecretGate() {
    const [secret, setSecret] = useState<CredentialSecretFlash | null>(null);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.credentialSecret as
                | CredentialSecretFlash
                | undefined;

            if (data) {
                setSecret(data);
            }
        });
    }, []);

    if (!secret) {
        return null;
    }

    return (
        <Modal
            open
            onClose={() => setSecret(null)}
            title="Credential secret"
            sub="Copy this secret now — for security it is never shown again."
            icon="key"
            width={520}
            footer={
                <Btn
                    variant="primary"
                    icon="check"
                    onClick={() => setSecret(null)}
                >
                    I've stored it safely
                </Btn>
            }
        >
            <div style={{ display: 'grid', gap: 14 }}>
                <Field label="Client ID">
                    <CodeBlock code={secret.clientId} copyable />
                </Field>
                <Field label="Client secret">
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
                    Store these in your application's secret manager. MAAC keeps
                    only a hashed copy and cannot recover the secret later.
                </div>
            </div>
        </Modal>
    );
}
