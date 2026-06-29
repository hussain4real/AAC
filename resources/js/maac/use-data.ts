/* ============================================================
   MAAC — Runtime data accessor (Phase 2 + Phase 5)
   Drop-in replacement for the Phase 1 `MAAC` fixture object. Reads the
   team's records from the shared `maac` Inertia prop (served by
   App\Support\MaacConsoleData) and exposes the same helper API the console
   screens already use. Phase 5 adds real governance/observability rollups
   (dashboard metrics, approvals, audit log, roles, policies, settings, quotas),
   falling back to the fixture only when the prop is unavailable.
   ============================================================ */
import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import type {
    MaacApprovals,
    MaacAuditEvent,
    MaacDashboard,
    MaacEvaluation,
    MaacEvaluationDataset,
    MaacGovernanceSettings,
    MaacConnector,
    MaacDataSource,
    MaacIncident,
    MaacKnowledgeSource,
    MaacOperational,
    MaacProviderHealth,
    MaacQuota,
    MaacRoutingPolicy,
    MaacSdkCompatibility,
    MaacSsoConnection,
    MaacVaultSecret,
    MaacWebhookEndpoint,
} from '@/types/global';
import { MAAC as FIXTURE } from './data';
import type { Agent, Application, Llm, Project, Run, Tool } from './data';

export type MaacDataset = {
    apps: Application[];
    projects: Project[];
    agents: Agent[];
    tools: Tool[];
    runs: Run[];
    llms: Llm[];
};

/** Default operational metrics when no team dataset is present. */
const EMPTY_OPERATIONAL: MaacOperational = {
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
const EMPTY_SDK_COMPATIBILITY: MaacSdkCompatibility = {
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
const DEFAULT_SETTINGS: MaacGovernanceSettings = {
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
export function useMaacDataset(): MaacDataset {
    const { maac } = usePage().props;

    return useMemo(
        () => ({
            apps: maac?.apps ?? FIXTURE.apps,
            projects: maac?.projects ?? FIXTURE.projects,
            agents: maac?.agents ?? FIXTURE.agents,
            tools: maac?.tools ?? FIXTURE.tools,
            runs: maac?.runs ?? FIXTURE.runs,
            llms: maac?.llms ?? FIXTURE.llms,
        }),
        [maac],
    );
}

export type MaacData = MaacDataset & {
    roles: typeof FIXTURE.roles;
    approvals: MaacApprovals;
    policies: typeof FIXTURE.policies;
    sensitivityLevels: typeof FIXTURE.sensitivityLevels;
    dashboard: MaacDashboard;
    operational: MaacOperational;
    auditEvents: MaacAuditEvent[];
    governanceSettings: MaacGovernanceSettings;
    quotas: MaacQuota[];
    sdkCompatibility: MaacSdkCompatibility;
    webhooks: MaacWebhookEndpoint[];
    connectors: MaacConnector[];
    knowledgeSources: MaacKnowledgeSource[];
    dataSources: MaacDataSource[];
    evaluationDatasets: MaacEvaluationDataset[];
    evaluations: MaacEvaluation[];
    vaultSecrets: MaacVaultSecret[];
    routingPolicies: MaacRoutingPolicy[];
    providerHealth: MaacProviderHealth[];
    incidents: MaacIncident[];
    ssoConnections: MaacSsoConnection[];
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
 * Returns a `MAAC`-shaped object backed by real records. Use inside a component
 * (`const MAAC = useMaacData();`) in place of importing the fixture.
 */
export function useMaacData(): MaacData {
    const dataset = useMaacDataset();
    const { maac } = usePage().props;

    return useMemo(
        () => ({
            ...dataset,
            roles: maac?.roles ?? FIXTURE.roles,
            approvals: maac?.approvals ?? FIXTURE.approvals,
            policies: maac?.policies ?? FIXTURE.policies,
            sensitivityLevels: FIXTURE.sensitivityLevels,
            dashboard: maac?.dashboard ?? FIXTURE.dashboard,
            operational: maac?.operational ?? EMPTY_OPERATIONAL,
            auditEvents: maac?.auditEvents ?? [],
            governanceSettings: maac?.governanceSettings ?? DEFAULT_SETTINGS,
            quotas: maac?.quotas ?? [],
            sdkCompatibility: maac?.sdkCompatibility ?? EMPTY_SDK_COMPATIBILITY,
            webhooks: maac?.webhooks ?? [],
            connectors: maac?.connectors ?? [],
            knowledgeSources: maac?.knowledgeSources ?? [],
            dataSources: maac?.dataSources ?? [],
            evaluationDatasets: maac?.evaluationDatasets ?? [],
            evaluations: maac?.evaluations ?? [],
            vaultSecrets: maac?.vaultSecrets ?? [],
            routingPolicies: maac?.routingPolicies ?? [],
            providerHealth: maac?.providerHealth ?? [],
            incidents: maac?.incidents ?? [],
            ssoConnections: maac?.ssoConnections ?? [],
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
        [dataset, maac],
    );
}
