/* ============================================================
   MAAC — Personas, role-based nav, and scope computation
   Phase 1 RBAC is a front-end UX mock (the "Switch view" menu).
   Real RBAC arrives with the Phase 2 ownership model.
   ============================================================ */
import type { Agent, Application, Project, Run, Tool } from './data';
import type { MaacDataset } from './use-data';

export type ScreenId =
    | 'dashboard'
    | 'applications'
    | 'projects'
    | 'agents'
    | 'tools'
    | 'sdk'
    | 'journey'
    | 'playground'
    | 'connectors'
    | 'knowledge'
    | 'dataSources'
    | 'evaluations'
    | 'runs'
    | 'llm'
    | 'governance'
    | 'webhooks'
    | 'vault'
    | 'routing'
    | 'identity'
    | 'incidents'
    | 'settings';

export type NavItem = { id: ScreenId; label: string; icon: string };
export type NavGroup = { title: string | null; items: NavItem[] };

export const NAV_GROUPS: NavGroup[] = [
    {
        title: null,
        items: [{ id: 'dashboard', label: 'Dashboard', icon: 'dashboard' }],
    },
    {
        title: 'Build',
        items: [
            { id: 'applications', label: 'Applications', icon: 'apps' },
            { id: 'projects', label: 'Projects', icon: 'projects' },
            { id: 'agents', label: 'Agents', icon: 'agents' },
            { id: 'tools', label: 'Tools', icon: 'tools' },
        ],
    },
    {
        title: 'Integrate',
        items: [
            { id: 'sdk', label: 'SDK Implementation', icon: 'sdk' },
            { id: 'journey', label: 'Version Journey', icon: 'clock' },
            { id: 'connectors', label: 'MCP Connectors', icon: 'layers' },
            { id: 'knowledge', label: 'Knowledge Sources', icon: 'book' },
            { id: 'dataSources', label: 'Data Sources', icon: 'database' },
            { id: 'playground', label: 'Agent Playground', icon: 'playground' },
        ],
    },
    {
        title: 'Validate',
        items: [{ id: 'evaluations', label: 'Evaluation Lab', icon: 'flask' }],
    },
    {
        title: 'Operate',
        items: [
            { id: 'runs', label: 'Runs & Audit Logs', icon: 'runs' },
            { id: 'webhooks', label: 'Webhooks', icon: 'send' },
            { id: 'llm', label: 'LLM Providers', icon: 'llm' },
            { id: 'routing', label: 'Model Routing', icon: 'flow' },
        ],
    },
    {
        title: 'Govern',
        items: [
            { id: 'governance', label: 'Governance', icon: 'governance' },
            { id: 'vault', label: 'Secrets Vault', icon: 'key' },
            { id: 'identity', label: 'Enterprise Identity', icon: 'lock' },
            {
                id: 'incidents',
                label: 'Incident Response',
                icon: 'shield-alert',
            },
            { id: 'settings', label: 'Settings', icon: 'settings' },
        ],
    },
];

/** Maps a route/page key to the nav screen it belongs to (for active highlighting + access checks). */
export const SCREEN_OF: Record<string, ScreenId> = {
    dashboard: 'dashboard',
    applications: 'applications',
    application: 'applications',
    projects: 'projects',
    agents: 'agents',
    agent: 'agents',
    createAgent: 'agents',
    tools: 'tools',
    tool: 'tools',
    sdk: 'sdk',
    journey: 'journey',
    playground: 'playground',
    connectors: 'connectors',
    knowledge: 'knowledge',
    dataSources: 'dataSources',
    evaluations: 'evaluations',
    runs: 'runs',
    run: 'runs',
    llm: 'llm',
    governance: 'governance',
    webhooks: 'webhooks',
    vault: 'vault',
    routing: 'routing',
    identity: 'identity',
    incidents: 'incidents',
    settings: 'settings',
};

export type PersonaId = 'admin' | 'projadmin' | 'dev';
export type Persona = {
    id: PersonaId;
    name: string;
    role: string;
    view: string;
    short: string;
    blurb: string;
    scope: 'all' | 'projects' | 'member';
    appIds?: string[];
    projectIds?: string[];
    tone: string;
};

export const PERSONAS: Persona[] = [
    {
        id: 'admin',
        name: 'Layla Hassan',
        role: 'MAAC Admin',
        view: 'Admin view',
        short: 'Admin',
        blurb: 'Full platform access — every application, project, agent, tool, run and governance queue.',
        scope: 'all',
        tone: 'var(--purple-600)',
    },
    {
        id: 'projadmin',
        name: 'Khalid Al-Mansoori',
        role: 'Project Admin',
        view: 'Project view',
        short: 'Project',
        blurb: 'Owns the Marine Operations Portal — sees all agents & tools across its projects.',
        scope: 'projects',
        appIds: ['MOP'],
        projectIds: ['prj_mop_ops', 'prj_mop_berth', 'prj_mop_docs'],
        tone: 'var(--teal-500)',
    },
    {
        id: 'dev',
        name: 'Reema Saleh',
        role: 'Agent Developer',
        view: 'Developer view',
        short: 'Developer',
        blurb: 'Member of 2 projects — sees only the agents & tools inside those projects.',
        scope: 'member',
        projectIds: ['prj_mop_ops', 'prj_fws_appr'],
        tone: 'var(--blue-500)',
    },
];

const ROLE_ALLOWED: Record<PersonaId, ScreenId[] | null> = {
    admin: null, // all nav
    projadmin: [
        'dashboard',
        'applications',
        'projects',
        'agents',
        'tools',
        'sdk',
        'journey',
        'connectors',
        'knowledge',
        'dataSources',
        'evaluations',
        'playground',
        'runs',
        'governance',
        'webhooks',
        'routing',
        'settings',
    ],
    dev: [
        'dashboard',
        'projects',
        'agents',
        'tools',
        'sdk',
        'journey',
        'knowledge',
        'evaluations',
        'playground',
        'runs',
        'settings',
    ],
};

export function navAllowed(personaId: PersonaId, navId: ScreenId): boolean {
    const a = ROLE_ALLOWED[personaId];

    return !a || a.includes(navId);
}

export type Scope = {
    isAll: boolean;
    role: Persona;
    apps: Application[];
    projects: Project[];
    agents: Agent[];
    tools: Tool[];
    runs: Run[];
    appIds?: Set<string>;
    projIds?: Set<string>;
    agentIds?: Set<string>;
    has: {
        app: (id: string) => boolean;
        project: (id: string) => boolean;
        agent: (id: string) => boolean;
        tool: (id: string) => boolean;
        run: (id: string) => boolean;
    };
};

export function computeScope(persona: Persona, data: MaacDataset): Scope {
    if (!persona || persona.scope === 'all') {
        return {
            isAll: true,
            role: persona,
            apps: data.apps,
            projects: data.projects,
            agents: data.agents,
            tools: data.tools,
            runs: data.runs,
            has: {
                app: () => true,
                project: () => true,
                agent: () => true,
                tool: () => true,
                run: () => true,
            },
        };
    }

    const projIds = new Set(persona.projectIds || []);
    const projects = data.projects.filter((p) => projIds.has(p.id));
    const appIds = new Set([
        ...(persona.appIds || []),
        ...projects.map((p) => p.appId),
    ]);
    const apps = data.apps.filter((a) => appIds.has(a.id));
    const agents = data.agents.filter((a) => projIds.has(a.projectId));
    const agentIds = new Set(agents.map((a) => a.id));
    const tools = data.tools.filter(
        (t) => t.scope === 'Global' || t.usedBy.some((u) => agentIds.has(u)),
    );
    const toolIds = new Set(tools.map((t) => t.id));
    const runs = data.runs.filter((r) => agentIds.has(r.agentId));

    return {
        isAll: false,
        role: persona,
        apps,
        projects,
        agents,
        tools,
        runs,
        appIds,
        projIds,
        agentIds,
        has: {
            app: (id: string) => appIds.has(id),
            project: (id: string) => projIds.has(id),
            agent: (id: string) => agentIds.has(id),
            tool: (id: string) => toolIds.has(id),
            run: (id: string) => runs.some((r) => r.id === id),
        },
    };
}
