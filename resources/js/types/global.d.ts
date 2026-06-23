import type {
    Agent,
    Application,
    ApprovalItem,
    Llm,
    Policy,
    Project,
    Role,
    Run,
    Tool,
} from '@/maac/data';
import type { Auth } from '@/types/auth';
import type { Team } from '@/types/teams';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

/** Headline dashboard stat tiles (Phase 5 — real aggregates). */
export interface MaacDashboardStats {
    apps: number;
    projects: number;
    agents: number;
    tools: number;
    runsToday: number;
    waitingClient: number;
    success: number;
    failed: number;
    tokens: string;
    cost: string;
}

export interface MaacAlert {
    sev: string;
    title: string;
    desc: string;
    time: string;
    icon: string;
}

export interface MaacDashboard {
    stats: MaacDashboardStats;
    runStatus: { label: string; value: number; color: string }[];
    runsOverTime: number[];
    topAgents: { id: string; name: string; runs: number; app: string }[];
    alerts: MaacAlert[];
}

/** Operational monitoring summary (Phase 5). */
export interface MaacOperational {
    totalRuns: number;
    failedRuns: number;
    expiredRuns: number;
    waitingRuns: number;
    avgLatencyMs: number;
    errorRate: number;
    toolFailureRate: number;
    costAnomaly: boolean;
}

export interface MaacAuditEvent {
    id: string;
    action: string;
    label: string;
    actor: string;
    target: string | null;
    environment: string | null;
    time: string;
    at: string | null;
    metadata: Record<string, unknown> | null;
}

export interface MaacGovernanceSettings {
    retainPromptsDays: number;
    retainResponsesDays: number;
    retainToolArgumentsDays: number;
    retainToolResultsDays: number;
    auditRetentionDays: number;
    maskSensitiveInputs: boolean;
    maskSensitiveOutputs: boolean;
    blockRestrictedLogging: boolean;
    defaultDailyRunQuota: number | null;
}

export interface MaacQuota {
    id: string;
    scope: string;
    scopeKey: string;
    subjectId: string | null;
    environment: string;
    maxRunsPerDay: number | null;
    maxTokensPerDay: number | null;
    enabled: boolean;
}

export interface MaacApprovals {
    tools: ApprovalItem[];
    agents: ApprovalItem[];
    models: ApprovalItem[];
    data: ApprovalItem[];
}

/** A published SDK client package (Phase 6C). */
export interface MaacSdkPackage {
    language: string;
    name: string;
    version: string | null;
    registry?: string;
    status?: string;
}

/** A contract/SDK deprecation and its removal window (Phase 6C). */
export interface MaacSdkDeprecation {
    id?: string;
    summary?: string;
    deprecated_in?: string;
    removed_in?: string;
    guide?: string;
}

/** The versioned SDK platform identity (Phase 6C). */
export interface MaacSdkPlatform {
    api_version: string;
    minimum_client_version: string;
    current_client_version: string;
    languages: { value: string; label: string }[];
    packages: MaacSdkPackage[];
    deprecations: MaacSdkDeprecation[];
}

/** A reported SDK client version + its compatibility verdict (Phase 6C). */
export interface MaacSdkClient {
    language: string | null;
    version: string | null;
    status: string;
    compatible: boolean;
}

/** One application's SDK integration health (Phase 6C). */
export interface MaacSdkAppHealth {
    id: string;
    name: string;
    environment: string;
    lastSyncedAt: string | null;
    clients: MaacSdkClient[];
    compatible: boolean;
    tools: {
        total: number;
        implemented: number;
        outdated: number;
        incompatible: number;
        required: number;
    };
}

/** A client-side tool whose implementation has drifted from its contract (Phase 6C). */
export interface MaacSdkDrift {
    application: string;
    applicationId: string;
    tool: string;
    status: string;
    environment: string;
    contractVersion: string;
    implementedVersion: string | null;
    sdkVersion: string | null;
    handler: string | null;
}

/** The SDK versioning & compatibility dashboard dataset (Phase 6C). */
export interface MaacSdkCompatibility {
    platform: MaacSdkPlatform;
    applications: MaacSdkAppHealth[];
    drift: MaacSdkDrift[];
}

/** A single webhook delivery attempt (Phase 6D). */
export interface MaacWebhookDelivery {
    id: string;
    event: string;
    eventLabel: string;
    status: string;
    statusLabel: string;
    attempts: number;
    responseStatus: number | null;
    error: string | null;
    runId: string | null;
    lastAttemptedAt: string | null;
    deliveredAt: string | null;
    createdAt: string | null;
    replayable: boolean;
}

/** A registered webhook endpoint and its recent delivery history (Phase 6D). */
export interface MaacWebhookEndpoint {
    id: string;
    uuid: string;
    appId: string | null;
    appName: string | null;
    environment: string;
    url: string;
    events: string[];
    status: string;
    statusLabel: string;
    description: string | null;
    lastFour: string | null;
    lastDeliveredAt: string | null;
    lastFailedAt: string | null;
    createdAt: string | null;
    deliveries: MaacWebhookDelivery[];
}

/** A remote tool discovered on an MCP connector (Phase 6E). */
export interface MaacConnectorCapability {
    name: string;
    title: string | null;
    description: string | null;
    input_schema: Record<string, unknown>;
}

/** A registered external MCP connector and its discovered capabilities (Phase 6E). */
export interface MaacConnector {
    uuid: string;
    id: string;
    name: string;
    description: string | null;
    transport: string;
    serverUrl: string;
    authType: string;
    authHeader: string | null;
    authConfigured: boolean;
    sensitivity: string;
    requiresApproval: boolean;
    status: string;
    statusLabel: string;
    environments: string[];
    capabilities: MaacConnectorCapability[];
    toolCount: number | null;
    lastDiscovered: string | null;
    owner: string | null;
    createdAt: string | null;
}

export interface MaacProp {
    apps: Application[];
    projects: Project[];
    agents: Agent[];
    tools: Tool[];
    runs: Run[];
    llms: Llm[];
    dashboard: MaacDashboard;
    operational: MaacOperational;
    approvals: MaacApprovals;
    auditEvents: MaacAuditEvent[];
    roles: Role[];
    policies: Policy[];
    governanceSettings: MaacGovernanceSettings;
    quotas: MaacQuota[];
    sdkCompatibility: MaacSdkCompatibility;
    webhooks: MaacWebhookEndpoint[];
    connectors: MaacConnector[];
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            currentTeam: Team | null;
            teams: Team[];
            maac: MaacProp | null;
            [key: string]: unknown;
        };
    }
}
