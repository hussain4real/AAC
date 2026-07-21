/* ============================================================
   MAACC — Runtime data accessor (Phase 2 + Phase 5)
   Drop-in replacement for the Phase 1 `MAACC` fixture object. Reads the
   team's records from the shared `maacc` Inertia prop (served by
   App\Support\MaaccConsoleData) and exposes the same helper API the console
   screens already use. Missing page data fails closed to an explicit empty
   contract; production never substitutes the historical demo fixture.
   ============================================================ */
import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import type {
    MaaccApprovals,
    MaaccAuditEvent,
    MaaccDashboard,
    MaaccEvaluation,
    MaaccEvaluationDataset,
    MaaccGovernanceSettings,
    MaaccConnector,
    MaaccDataSource,
    MaaccIncident,
    MaaccKnowledgeSource,
    MaaccOperational,
    MaaccProviderHealth,
    MaaccQuota,
    MaaccRoutingPolicy,
    MaaccSdkCompatibility,
    MaaccSsoConnection,
    MaaccVaultSecret,
    MaaccWebhookEndpoint,
} from '@/types/global';
import type {
    Agent,
    Application,
    Llm,
    Policy,
    Project,
    ProviderCatalogEntry,
    Role,
    Run,
    SensitivityLevel,
    Tool,
} from './data';

export type MaaccDataset = {
    apps: Application[];
    projects: Project[];
    agents: Agent[];
    tools: Tool[];
    runs: Run[];
    llms: Llm[];
};

/** Default operational metrics when no team dataset is present. */
const EMPTY_OPERATIONAL: MaaccOperational = {
    totalRuns: 0,
    failedRuns: 0,
    expiredRuns: 0,
    waitingRuns: 0,
    avgLatencyMs: 0,
    errorRate: 0,
    toolFailureRate: 0,
    costAnomaly: false,
};

/** Default SDK compatibility dataset when no team dataset is present. */
const EMPTY_SDK_COMPATIBILITY: MaaccSdkCompatibility = {
    platform: {
        api_version: '0.0.1',
        minimum_client_version: '0.0.1',
        current_client_version: '0.0.1',
        languages: [],
        packages: [],
        deprecations: [],
    },
    applications: [],
    drift: [],
};

/** Default governance settings when no team dataset is present. */
const DEFAULT_SETTINGS: MaaccGovernanceSettings = {
    retainPromptsDays: 90,
    retainResponsesDays: 90,
    retainToolArgumentsDays: 30,
    retainToolResultsDays: 30,
    auditRetentionDays: 365,
    maskSensitiveInputs: true,
    maskSensitiveOutputs: true,
    toolResultHandling: 'mask',
    blockRestrictedLogging: true,
    defaultDailyRunQuota: null,
};

const EMPTY_DASHBOARD: MaaccDashboard = {
    stats: {
        apps: 0,
        projects: 0,
        agents: 0,
        tools: 0,
        runsToday: 0,
        waitingClient: 0,
        success: 0,
        failed: 0,
        tokens: '0',
        cost: 'USD 0',
        costEstimated: true,
    },
    runStatus: [],
    runsOverTime: [],
    topAgents: [],
    alerts: [],
};

const SENSITIVITY_LEVELS: SensitivityLevel[] = [
    {
        name: 'Public',
        desc: 'No restriction. Safe for external models and logging.',
        color: 'slate',
    },
    {
        name: 'Internal',
        desc: 'Company-internal. Approved cloud models permitted.',
        color: 'blue',
    },
    {
        name: 'Confidential',
        desc: 'Restricted distribution. Masked in logs by default.',
        color: 'amber',
    },
    {
        name: 'Restricted',
        desc: 'Highest sensitivity. Raw logging is blocked.',
        color: 'red',
    },
];

const EXECUTION_MODE_LABELS: Record<string, string> = {
    hosted: 'MAACC-hosted',
    client: 'Client-side',
    http: 'Remote HTTP',
    connector: 'Connector server',
    knowledge: 'Knowledge retrieval',
    db: 'Read-only DB',
};

const IMPLEMENTATION_LABELS: Record<string, string> = {
    ready: 'Ready',
    implemented: 'Implemented',
    required: 'Requires implementation',
    outdated: 'Outdated',
    incompatible: 'Incompatible',
    disabled: 'Disabled',
    'n/a': 'Not required',
};

/** Resolve the bounded page dataset, failing closed when it is absent. */
export function useMaaccDataset(): MaaccDataset {
    const { maacc } = usePage().props;

    return useMemo(
        () => ({
            apps: maacc?.apps ?? [],
            projects: maacc?.projects ?? [],
            agents: maacc?.agents ?? [],
            tools: maacc?.tools ?? [],
            runs: maacc?.runs ?? [],
            llms: maacc?.llms ?? [],
        }),
        [maacc],
    );
}

export type MaaccData = MaaccDataset & {
    providerCatalog: ProviderCatalogEntry[];
    roles: Role[];
    approvals: MaaccApprovals;
    policies: Policy[];
    sensitivityLevels: SensitivityLevel[];
    dashboard: MaaccDashboard;
    operational: MaaccOperational;
    auditEvents: MaaccAuditEvent[];
    governanceSettings: MaaccGovernanceSettings;
    quotas: MaaccQuota[];
    sdkCompatibility: MaaccSdkCompatibility;
    webhooks: MaaccWebhookEndpoint[];
    connectors: MaaccConnector[];
    knowledgeSources: MaaccKnowledgeSource[];
    dataSources: MaaccDataSource[];
    evaluationDatasets: MaaccEvaluationDataset[];
    evaluations: MaaccEvaluation[];
    vaultSecrets: MaaccVaultSecret[];
    routingPolicies: MaaccRoutingPolicy[];
    providerHealth: MaaccProviderHealth[];
    incidents: MaaccIncident[];
    ssoConnections: MaaccSsoConnection[];
    pagination: NonNullable<
        ReturnType<typeof usePage>['props']['maacc']
    >['pagination'];
    meta: NonNullable<ReturnType<typeof usePage>['props']['maacc']>['meta'];
    execModeLabel: Record<string, string>;
    implLabel: Record<string, string>;
    byId: <T extends { id: string }>(list: T[], id: string) => T | undefined;
    appById: (id: string) => Application | undefined;
    agentById: (id: string) => Agent | undefined;
    projectById: (id: string) => Project | undefined;
    toolById: (id: string) => Tool | undefined;
    llmById: (id: string) => Llm | undefined;
    agentsByApp: (id: string) => Agent[];
    projectsByApp: (id: string) => Project[];
};

/**
 * Returns a `MAACC`-shaped object backed by real records. Use inside a component
 * (`const MAACC = useMaaccData();`) in place of importing the fixture.
 */
export function useMaaccData(): MaaccData {
    const dataset = useMaaccDataset();
    const { maacc } = usePage().props;

    return useMemo(
        () => ({
            ...dataset,
            providerCatalog: maacc?.providerCatalog ?? [],
            roles: maacc?.roles ?? [],
            approvals: maacc?.approvals ?? {
                tools: [],
                agents: [],
                models: [],
                data: [],
                runtime: [],
            },
            policies: maacc?.policies ?? [],
            sensitivityLevels: SENSITIVITY_LEVELS,
            dashboard: maacc?.dashboard ?? EMPTY_DASHBOARD,
            operational: maacc?.operational ?? EMPTY_OPERATIONAL,
            auditEvents: maacc?.auditEvents ?? [],
            governanceSettings: maacc?.governanceSettings ?? DEFAULT_SETTINGS,
            quotas: maacc?.quotas ?? [],
            sdkCompatibility:
                maacc?.sdkCompatibility ?? EMPTY_SDK_COMPATIBILITY,
            webhooks: maacc?.webhooks ?? [],
            connectors: maacc?.connectors ?? [],
            knowledgeSources: maacc?.knowledgeSources ?? [],
            dataSources: maacc?.dataSources ?? [],
            evaluationDatasets: maacc?.evaluationDatasets ?? [],
            evaluations: maacc?.evaluations ?? [],
            vaultSecrets: maacc?.vaultSecrets ?? [],
            routingPolicies: maacc?.routingPolicies ?? [],
            providerHealth: maacc?.providerHealth ?? [],
            incidents: maacc?.incidents ?? [],
            ssoConnections: maacc?.ssoConnections ?? [],
            pagination: maacc?.pagination ?? {},
            meta: maacc?.meta ?? null,
            execModeLabel: EXECUTION_MODE_LABELS,
            implLabel: IMPLEMENTATION_LABELS,
            byId: <T extends { id: string }>(list: T[], id: string) =>
                list.find((item) => item.id === id),
            appById: (id: string) => dataset.apps.find((app) => app.id === id),
            agentById: (id: string) =>
                dataset.agents.find((agent) => agent.id === id),
            projectById: (id: string) =>
                dataset.projects.find((project) => project.id === id),
            toolById: (id: string) =>
                dataset.tools.find((tool) => tool.id === id),
            llmById: (id: string) => dataset.llms.find((llm) => llm.id === id),
            agentsByApp: (id: string) =>
                dataset.agents.filter((agent) => agent.appId === id),
            projectsByApp: (id: string) =>
                dataset.projects.filter((project) => project.appId === id),
        }),
        [dataset, maacc],
    );
}
