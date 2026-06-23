/* ============================================================
   MAAC — Navigation context
   Bridges the prototype's `useNav()` (go/back, persona, scope,
   env, theme) onto Inertia navigation + Wayfinder route helpers.
   All routes are team-scoped (/{current_team}/...).
   ============================================================ */
import { router, usePage } from '@inertiajs/react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import type { ReactNode } from 'react';
import ConsoleRoutes from '@/actions/App/Http/Controllers/Maac/ConsoleController';
import { useAppearance } from '@/hooks/use-appearance';
import { dashboard } from '@/routes';
import { computeScope, navAllowed, PERSONAS, SCREEN_OF } from './personas';
import type { Persona, PersonaId, Scope, ScreenId } from './personas';
import { useMaacDataset } from './use-data';

type Environment = 'Production' | 'Staging' | 'Development';

/** Logical screen names used by go()/href(), mirroring the prototype router. */
export type RouteName =
    | 'dashboard'
    | 'applications'
    | 'application'
    | 'projects'
    | 'agents'
    | 'createAgent'
    | 'agent'
    | 'tools'
    | 'tool'
    | 'sdk'
    | 'sdkDocs'
    | 'playground'
    | 'connectors'
    | 'runs'
    | 'run'
    | 'llm'
    | 'governance'
    | 'webhooks'
    | 'settings';

type GoParams = { id?: string; agent?: string } & Record<
    string,
    string | undefined
>;

export type MaacNav = {
    go: (name: RouteName, params?: GoParams) => void;
    href: (name: RouteName, params?: GoParams) => string;
    back: () => void;
    persona: Persona;
    setPersona: (p: Persona) => void;
    scope: Scope;
    env: Environment;
    setEnv: (env: Environment) => void;
    theme: 'light' | 'dark';
    setTheme: (theme: 'light' | 'dark') => void;
    activeScreen: ScreenId;
    team: string;
};

const NavContext = createContext<MaacNav | null>(null);

export function useMaacNav(): MaacNav {
    const ctx = useContext(NavContext);

    if (!ctx) {
        throw new Error('useMaacNav must be used within <MaacNavProvider>');
    }

    return ctx;
}

const SEGMENT_TO_SCREEN: Record<string, ScreenId> = {
    dashboard: 'dashboard',
    applications: 'applications',
    projects: 'projects',
    agents: 'agents',
    tools: 'tools',
    sdk: 'sdk',
    playground: 'playground',
    connectors: 'connectors',
    runs: 'runs',
    'llm-providers': 'llm',
    governance: 'governance',
    webhooks: 'webhooks',
    'platform-settings': 'settings',
};

function urlFor(name: RouteName, team: string, params: GoParams = {}): string {
    const id = params.id ?? '';

    switch (name) {
        case 'dashboard':
            return dashboard.url(team);
        case 'applications':
            return ConsoleRoutes.applications.url(team);
        case 'application':
            return ConsoleRoutes.application.url({
                current_team: team,
                application: id,
            });
        case 'projects':
            return ConsoleRoutes.projects.url(team);
        case 'agents':
            return ConsoleRoutes.agents.url(team);
        case 'createAgent':
            return ConsoleRoutes.createAgent.url(team);
        case 'agent':
            return ConsoleRoutes.agent.url({ current_team: team, agent: id });
        case 'tools':
            return ConsoleRoutes.tools.url(team);
        case 'tool':
            return ConsoleRoutes.tool.url({ current_team: team, tool: id });
        case 'sdk':
            return ConsoleRoutes.sdk.url(team);
        case 'sdkDocs':
            return ConsoleRoutes.sdkDocs.url(team);
        case 'playground':
            return ConsoleRoutes.playground.url(
                team,
                params.agent ? { query: { agent: params.agent } } : undefined,
            );
        case 'connectors':
            return ConsoleRoutes.connectors.url(team);
        case 'runs':
            return ConsoleRoutes.runs.url(team);
        case 'run':
            return ConsoleRoutes.run.url({ current_team: team, run: id });
        case 'llm':
            return ConsoleRoutes.llmProviders.url(team);
        case 'governance':
            return ConsoleRoutes.governance.url(team);
        case 'webhooks':
            return ConsoleRoutes.webhooks.url(team);
        case 'settings':
            return ConsoleRoutes.settings.url(team);
    }
}

function readPersona(): Persona {
    if (typeof window === 'undefined') {
        return PERSONAS[0];
    }

    const id = localStorage.getItem('maac-persona') as PersonaId | null;

    return PERSONAS.find((p) => p.id === id) ?? PERSONAS[0];
}

export function MaacNavProvider({ children }: { children: ReactNode }) {
    const page = usePage<{ currentTeam?: { slug: string } | null }>();
    const team = page.props.currentTeam?.slug ?? '';
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const [persona, setPersonaState] = useState<Persona>(readPersona);
    const [env, setEnvState] = useState<Environment>(() => {
        if (typeof window === 'undefined') {
            return 'Production';
        }

        return (
            (localStorage.getItem('maac-env') as Environment) || 'Production'
        );
    });

    const data = useMaacDataset();
    const scope = useMemo(() => computeScope(persona, data), [persona, data]);

    const activeScreen: ScreenId = useMemo(() => {
        const path = page.url.split('?')[0];
        const parts = path.split('/').filter(Boolean);
        // parts: [team, segment, ...]
        const segment = parts[1] ?? 'dashboard';

        return SEGMENT_TO_SCREEN[segment] ?? 'dashboard';
    }, [page.url]);

    const href = useCallback(
        (name: RouteName, params?: GoParams) => urlFor(name, team, params),
        [team],
    );

    const go = useCallback(
        (name: RouteName, params?: GoParams) => {
            router.visit(urlFor(name, team, params));
        },
        [team],
    );

    const back = useCallback(() => {
        window.history.back();
    }, []);

    const setPersona = useCallback(
        (p: Persona) => {
            setPersonaState(p);
            localStorage.setItem('maac-persona', p.id);
            router.visit(urlFor('dashboard', team)); // reset to a screen everyone can see
        },
        [team],
    );

    const setEnv = useCallback((next: Environment) => {
        setEnvState(next);
        localStorage.setItem('maac-env', next);
    }, []);

    const setTheme = useCallback(
        (t: 'light' | 'dark') => updateAppearance(t),
        [updateAppearance],
    );

    // If the current screen isn't allowed for this persona, bounce to dashboard.
    useEffect(() => {
        if (!navAllowed(persona.id, SCREEN_OF[activeScreen] ?? activeScreen)) {
            router.visit(urlFor('dashboard', team));
        }
    }, [activeScreen, persona.id, team]);

    const value: MaacNav = {
        go,
        href,
        back,
        persona,
        setPersona,
        scope,
        env,
        setEnv,
        theme: resolvedAppearance,
        setTheme,
        activeScreen,
        team,
    };

    return <NavContext.Provider value={value}>{children}</NavContext.Provider>;
}
