/* ============================================================
   MAACC — Console layout
   Wraps console pages with the Milaha-themed shell (sidebar +
   topbar) and the nav/persona context. Scoped via `.maacc-theme`
   so Milaha tokens (and shadcn atoms within) take effect here
   without touching the rest of the starter-kit app.
   ============================================================ */
import type { ReactNode } from 'react';
import { CredentialSecretGate } from '@/components/maacc/credential-secret-gate';
import { Sidebar } from '@/components/maacc/sidebar';
import { Topbar } from '@/components/maacc/topbar';
import { WebhookSecretGate } from '@/components/maacc/webhook-secret-gate';
import { MaaccNavProvider } from '@/maacc/nav';

export default function MaaccLayout({ children }: { children: ReactNode }) {
    return (
        <MaaccNavProvider>
            <div
                className="maacc-theme"
                style={{ display: 'flex', height: '100vh', overflow: 'hidden' }}
            >
                <Sidebar />
                <div
                    style={{
                        flex: 1,
                        display: 'flex',
                        flexDirection: 'column',
                        minWidth: 0,
                    }}
                >
                    <Topbar />
                    <main
                        className="maacc-scroll"
                        {...{ 'scroll-region': '' }}
                        style={{
                            flex: 1,
                            overflowY: 'auto',
                            background: 'var(--bg)',
                        }}
                    >
                        <div
                            style={{
                                maxWidth: 1320,
                                margin: '0 auto',
                                padding: '22px 26px 60px',
                            }}
                        >
                            {children}
                        </div>
                    </main>
                </div>
                <CredentialSecretGate />
                <WebhookSecretGate />
            </div>
        </MaaccNavProvider>
    );
}
