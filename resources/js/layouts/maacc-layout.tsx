/* ============================================================
   MAACC — Console layout
   Wraps console pages with the Milaha-themed shell (sidebar +
   topbar) and the nav/persona context. Scoped via `.maacc-theme`
   so Milaha tokens (and shadcn atoms within) take effect here
   without touching the rest of the starter-kit app.
   ============================================================ */
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { EnterpriseReadinessBanner } from '@/components/enterprise-readiness-banner';
import { CredentialSecretGate } from '@/components/maacc/credential-secret-gate';
import { Sidebar } from '@/components/maacc/sidebar';
import { Topbar } from '@/components/maacc/topbar';
import { WebhookSecretGate } from '@/components/maacc/webhook-secret-gate';
import { MaaccNavProvider } from '@/maacc/nav';

export default function MaaccLayout({ children }: { children: ReactNode }) {
    const [navigationOpen, setNavigationOpen] = useState(false);

    useEffect(() => {
        if (!navigationOpen) {
            return;
        }

        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setNavigationOpen(false);
            }
        };

        document.addEventListener('keydown', closeOnEscape);

        return () => document.removeEventListener('keydown', closeOnEscape);
    }, [navigationOpen]);

    return (
        <MaaccNavProvider>
            <div className="maacc-theme maacc-shell">
                <a className="maacc-skip-link" href="#maacc-main-content">
                    Skip to main content
                </a>
                <Sidebar className="maacc-sidebar-desktop" />
                {navigationOpen && (
                    <div className="maacc-navigation-layer">
                        <button
                            type="button"
                            className="maacc-navigation-backdrop"
                            aria-label="Close navigation"
                            onClick={() => setNavigationOpen(false)}
                        />
                        <Sidebar
                            className="maacc-sidebar-mobile"
                            onNavigate={() => setNavigationOpen(false)}
                        />
                    </div>
                )}
                <div className="maacc-shell-content">
                    <Topbar onOpenNavigation={() => setNavigationOpen(true)} />
                    <EnterpriseReadinessBanner />
                    <main
                        id="maacc-main-content"
                        tabIndex={-1}
                        className="maacc-scroll"
                        {...{ 'scroll-region': '' }}
                        style={{
                            flex: 1,
                            overflowY: 'auto',
                            background: 'var(--bg)',
                        }}
                    >
                        <div className="maacc-page-content">{children}</div>
                    </main>
                </div>
                <CredentialSecretGate />
                <WebhookSecretGate />
            </div>
        </MaaccNavProvider>
    );
}
