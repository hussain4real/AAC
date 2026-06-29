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
    runtime: ApprovalItem[];
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

/** An ingested document within a knowledge source (Phase 6F). */
export interface MaacKnowledgeDocument {
    id: string;
    title: string;
    uri: string | null;
    chunkCount: number | null;
    indexedAt: string | null;
    metadata: Record<string, unknown>;
    uploaded: boolean;
    originalFilename: string | null;
    fileSize: number | null;
    createdAt: string | null;
}

/** A governed knowledge (RAG) source and its documents (Phase 6F). */
export interface MaacKnowledgeSource {
    uuid: string;
    id: string;
    name: string;
    description: string | null;
    status: string;
    statusLabel: string;
    sensitivity: string;
    requiresApproval: boolean;
    environments: string[];
    documentCount: number;
    chunkCount: number;
    toolCount: number | null;
    lastIndexed: string | null;
    owner: string | null;
    documents: MaacKnowledgeDocument[];
    createdAt: string | null;
}

/** A governed read-only data source backing `db` tools (Phase 8A). */
export interface MaacDataSource {
    uuid: string;
    id: string;
    name: string;
    description: string | null;
    connectionType: string;
    connectionTypeLabel: string;
    driver: string | null;
    status: string;
    statusLabel: string;
    sensitivity: string;
    requiresApproval: boolean;
    environments: string[];
    allowedRelations: string[];
    maxRows: number;
    statementTimeoutMs: number;
    maxResultKb: number;
    credentialManaged: boolean;
    stalenessThresholdMinutes: number | null;
    dataRefreshed: string | null;
    toolCount: number | null;
    owner: string | null;
    createdAt: string | null;
}

/** A single assertion verdict recorded for an evaluation case (Phase 6F). */
export interface MaacEvaluationCheck {
    type: string;
    passed: boolean;
    detail: string;
}

/** A case in a golden evaluation dataset (Phase 6F). */
export interface MaacEvaluationCase {
    id: string;
    name: string;
    kind: string;
    kindLabel: string;
    input: string;
    expectations: {
        expected_contains?: string[];
        expected_tool?: string | null;
        forbidden_phrases?: string[];
        expects_citation?: boolean;
        max_cost?: number | null;
        max_latency_ms?: number | null;
    };
    toolStubs: Record<string, Record<string, unknown>> | null;
    ordinal: number;
}

/** A golden evaluation dataset (Phase 6F). */
export interface MaacEvaluationDataset {
    uuid: string;
    id: string;
    name: string;
    description: string | null;
    projectId: string | null;
    project: string | null;
    caseCount: number | null;
    cases: MaacEvaluationCase[];
    createdAt: string | null;
}

/** A per-case evaluation result (Phase 6F). */
export interface MaacEvaluationResult {
    id: string;
    caseName: string;
    kind: string;
    kindLabel: string;
    passed: boolean;
    checks: MaacEvaluationCheck[];
    citations: Array<Record<string, unknown>>;
    cost: number;
    latencyMs: number;
    output: string | null;
    failureReason: string | null;
    runSlug: string | null;
}

/** An evaluation run of a dataset against an agent (Phase 6F). */
export interface MaacEvaluation {
    id: string;
    label: string;
    status: string;
    statusLabel: string;
    isRequired: boolean;
    environment: string;
    datasetId: string;
    datasetName: string | null;
    agentId: string;
    agentSlug: string | null;
    agentName: string | null;
    agentVersion: string;
    modelCode: string | null;
    promptFingerprint: string | null;
    casesTotal: number;
    casesPassed: number;
    passRate: number;
    totalCost: number;
    avgLatencyMs: number;
    correctnessRate: number;
    safetyRate: number;
    citationRate: number;
    completedAt: string | null;
    createdAt: string | null;
    results: MaacEvaluationResult[];
}

/** Phase 6G — a vault-held secret (never the plaintext). */
export interface MaacVaultSecret {
    uuid: string;
    id: string;
    name: string;
    reference: string;
    kind: string;
    kindLabel: string;
    lastFour: string | null;
    version: number;
    boundModel: string[];
    rotatedAt: string | null;
    lastAccessed: string | null;
    accessedCount: number;
    createdBy: string | null;
    createdAt: string | null;
}

/** Phase 6G — an advanced model routing policy. */
export interface MaacRoutingPolicy {
    uuid: string;
    id: string;
    name: string;
    agentId: string;
    agentName: string | null;
    strategy: string;
    strategyLabel: string;
    primaryProviderId: string | null;
    primaryProvider: string | null;
    fallbackProviderIds: string[];
    maxCostPer1k: number | null;
    maxLatencyMs: number | null;
    enabled: boolean;
    createdAt: string | null;
}

/** Phase 6G — a recent-health snapshot for a model provider. */
export interface MaacProviderHealth {
    id: string;
    name: string;
    code: string;
    sampleSize: number;
    failureRate: number;
    healthy: boolean;
    avgLatencyMs: number | null;
}

/** Phase 6G — a break-glass / incident-response action. */
export interface MaacIncident {
    id: string;
    type: string;
    typeLabel: string;
    severity: string;
    actor: string;
    subject: string | null;
    subjectType: string | null;
    reason: string;
    environment: string | null;
    reverted: boolean;
    revertedAt: string | null;
    time: string;
    at: string | null;
    action: string;
}

/** Phase 6G — an enterprise identity (SSO) connection. */
export interface MaacSsoConnection {
    uuid: string;
    id: string;
    name: string;
    provider: string;
    providerLabel: string;
    authorizeUrl: string;
    tokenUrl: string;
    userinfoUrl: string;
    clientId: string;
    secretConfigured: boolean;
    scopes: string;
    emailClaim: string;
    nameClaim: string;
    groupsClaim: string;
    defaultTeamRole: string;
    groupRoleMappings: Array<{
        group: string;
        team_role: string;
        maac_role?: string;
        project_slug?: string;
    }>;
    autoProvision: boolean;
    status: string;
    statusLabel: string;
    redirectUri: string;
    loginUrl: string;
    identityCount: number | null;
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
    knowledgeSources: MaacKnowledgeSource[];
    dataSources: MaacDataSource[];
    evaluationDatasets: MaacEvaluationDataset[];
    evaluations: MaacEvaluation[];
    vaultSecrets: MaacVaultSecret[];
    routingPolicies: MaacRoutingPolicy[];
    providerHealth: MaacProviderHealth[];
    incidents: MaacIncident[];
    ssoConnections: MaacSsoConnection[];
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
