/* ============================================================
   MAACC — Runtime data accessor (Phase 2 + Phase 5)
   Drop-in replacement for the Phase 1 `MAACC` fixture object. Reads the
   team's records from the shared `maacc` Inertia prop (served by
   App\Support\MaaccConsoleData) and exposes the same helper API the console
   screens already use. Phase 5 adds real governance/observability rollups
   (dashboard metrics, approvals, audit log, roles, policies, settings, quotas),
   falling back to the fixture only when the prop is unavailable.
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
import { MAACC as FIXTURE } from './data';
import type { Agent, Application, Llm, Project, Run, Tool } from './data';

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
    blockRestrictedLogging: true,
    defaultDailyRunQuota: null,
};

/** Resolve the team dataset from the shared prop, falling back to the fixture. */
export function useMaaccDataset(): MaaccDataset {
    const { maacc } = usePage().props;

    return useMemo(
        () => ({
            apps: maacc?.apps ?? FIXTURE.apps,
            projects: maacc?.projects ?? FIXTURE.projects,
            agents: maacc?.agents ?? FIXTURE.agents,
            tools: maacc?.tools ?? FIXTURE.tools,
            runs: maacc?.runs ?? FIXTURE.runs,
            llms: maacc?.llms ?? FIXTURE.llms,
        }),
        [maacc],
    );
}

export type MaaccData = MaaccDataset & {
    roles: typeof FIXTURE.roles;
    approvals: MaaccApprovals;
    policies: typeof FIXTURE.policies;
    sensitivityLevels: typeof FIXTURE.sensitivityLevels;
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
    execModeLabel: typeof FIXTURE.execModeLabel;
    implLabel: typeof FIXTURE.implLabel;
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
            roles: maacc?.roles ?? FIXTURE.roles,
            approvals: maacc?.approvals ?? FIXTURE.approvals,
            policies: maacc?.policies ?? FIXTURE.policies,
            sensitivityLevels: FIXTURE.sensitivityLevels,
            dashboard: maacc?.dashboard ?? FIXTURE.dashboard,
            operational: maacc?.operational ?? EMPTY_OPERATIONAL,
            auditEvents: maacc?.auditEvents ?? [],
            governanceSettings: maacc?.governanceSettings ?? DEFAULT_SETTINGS,
            quotas: maacc?.quotas ?? [],
            sdkCompatibility: maacc?.sdkCompatibility ?? EMPTY_SDK_COMPATIBILITY,
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
            execModeLabel: FIXTURE.execModeLabel,
            implLabel: FIXTURE.implLabel,
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
