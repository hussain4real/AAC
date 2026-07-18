import type {
    Agent,
    Application,
    ApprovalItem,
    Llm,
    Policy,
    Project,
    ProviderCatalogEntry,
    Role,
    Run,
    Tool,
} from '@/maacc/data';
import type { Auth } from '@/types/auth';
import type { Team } from '@/types/teams';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

/** Headline dashboard stat tiles (Phase 5 — real aggregates). */
export interface MaaccDashboardStats {
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

export interface MaaccAlert {
    sev: string;
    title: string;
    desc: string;
    time: string;
    icon: string;
}

export interface MaaccDashboard {
    stats: MaaccDashboardStats;
    runStatus: { label: string; value: number; color: string }[];
    runsOverTime: number[];
    topAgents: { id: string; name: string; runs: number; app: string }[];
    alerts: MaaccAlert[];
}

export interface EnterpriseReadinessState {
    status: string;
    message: string;
    registrationEnabled: boolean;
    teamCreationEnabled: boolean;
    realSensitiveDataEnabled: boolean;
    changeOwner: string | null;
}

/** Operational monitoring summary (Phase 5). */
export interface MaaccOperational {
    totalRuns: number;
    failedRuns: number;
    expiredRuns: number;
    waitingRuns: number;
    avgLatencyMs: number;
    errorRate: number;
    toolFailureRate: number;
    costAnomaly: boolean;
}

export interface MaaccAuditEvent {
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

export interface MaaccGovernanceSettings {
    retainPromptsDays: number;
    retainResponsesDays: number;
    retainToolArgumentsDays: number;
    retainToolResultsDays: number;
    auditRetentionDays: number;
    maskSensitiveInputs: boolean;
    maskSensitiveOutputs: boolean;
    toolResultHandling: 'store' | 'mask' | 'exclude';
    blockRestrictedLogging: boolean;
    defaultDailyRunQuota: number | null;
}

export interface MaaccQuota {
    id: string;
    scope: string;
    scopeKey: string;
    subjectId: string | null;
    environment: string;
    maxRunsPerDay: number | null;
    maxTokensPerDay: number | null;
    enabled: boolean;
}

export interface MaaccApprovals {
    tools: ApprovalItem[];
    agents: ApprovalItem[];
    models: ApprovalItem[];
    data: ApprovalItem[];
    runtime: ApprovalItem[];
}

/** A published SDK client package (Phase 6C). */
export interface MaaccSdkPackage {
    language: string;
    name: string;
    version: string | null;
    registry?: string;
    status?: string;
}

/** A contract/SDK deprecation and its removal window (Phase 6C). */
export interface MaaccSdkDeprecation {
    id?: string;
    summary?: string;
    deprecated_in?: string;
    removed_in?: string;
    guide?: string;
}

/** The versioned SDK platform identity (Phase 6C). */
export interface MaaccSdkPlatform {
    api_version: string;
    minimum_client_version: string;
    current_client_version: string;
    languages: { value: string; label: string }[];
    packages: MaaccSdkPackage[];
    deprecations: MaaccSdkDeprecation[];
}

/** A reported SDK client version + its compatibility verdict (Phase 6C). */
export interface MaaccSdkClient {
    language: string | null;
    version: string | null;
    status: string;
    compatible: boolean;
}

/** One application's SDK integration health (Phase 6C). */
export interface MaaccSdkAppHealth {
    id: string;
    name: string;
    environment: string;
    lastSyncedAt: string | null;
    clients: MaaccSdkClient[];
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
export interface MaaccSdkDrift {
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
export interface MaaccSdkCompatibility {
    platform: MaaccSdkPlatform;
    applications: MaaccSdkAppHealth[];
    drift: MaaccSdkDrift[];
}

/** A single webhook delivery attempt (Phase 6D). */
export interface MaaccWebhookDelivery {
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
export interface MaaccWebhookEndpoint {
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
    deliveries: MaaccWebhookDelivery[];
}

/** A remote tool discovered on an MCP connector (Phase 6E). */
export interface MaaccConnectorCapability {
    name: string;
    title: string | null;
    description: string | null;
    input_schema: Record<string, unknown>;
}

/** A registered external MCP connector and its discovered capabilities (Phase 6E). */
export interface MaaccConnector {
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
    capabilities: MaaccConnectorCapability[];
    toolCount: number | null;
    lastDiscovered: string | null;
    owner: string | null;
    createdAt: string | null;
}

/** An ingested document within a knowledge source (Phase 6F). */
export interface MaaccKnowledgeDocument {
    id: string;
    title: string;
    uri: string | null;
    chunkCount: number | null;
    indexedAt: string | null;
    metadata: Record<string, unknown>;
    uploaded: boolean;
    originalFilename: string | null;
    fileSize: number | null;
    ingestionStatus:
        'pending' | 'scanning' | 'indexed' | 'quarantined' | 'failed';
    quarantineReason: string | null;
    processedAt: string | null;
    createdAt: string | null;
}

/** A governed knowledge (RAG) source and its documents (Phase 6F). */
export interface MaaccKnowledgeSource {
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
    documents: MaaccKnowledgeDocument[];
    createdAt: string | null;
}

/** A governed read-only data source backing `db` tools (Phase 8A). */
export interface MaaccDataSource {
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
export interface MaaccEvaluationCheck {
    type: string;
    passed: boolean;
    detail: string;
}

/** A case in a golden evaluation dataset (Phase 6F). */
export interface MaaccEvaluationCase {
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
export interface MaaccEvaluationDataset {
    uuid: string;
    id: string;
    name: string;
    description: string | null;
    projectId: string | null;
    project: string | null;
    caseCount: number | null;
    cases: MaaccEvaluationCase[];
    createdAt: string | null;
}

/** A per-case evaluation result (Phase 6F). */
export interface MaaccEvaluationResult {
    id: string;
    caseName: string;
    kind: string;
    kindLabel: string;
    passed: boolean;
    checks: MaaccEvaluationCheck[];
    citations: Array<Record<string, unknown>>;
    cost: number;
    latencyMs: number;
    output: string | null;
    failureReason: string | null;
    runSlug: string | null;
}

/** An evaluation run of a dataset against an agent (Phase 6F). */
export interface MaaccEvaluation {
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
    results: MaaccEvaluationResult[];
}

/** Phase 6G — a vault-held secret (never the plaintext). */
export interface MaaccVaultSecret {
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
export interface MaaccRoutingPolicy {
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
export interface MaaccProviderHealth {
    id: string;
    name: string;
    code: string;
    sampleSize: number;
    failureRate: number;
    healthy: boolean;
    avgLatencyMs: number | null;
}

/** Phase 6G — a break-glass / incident-response action. */
export interface MaaccIncident {
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
export interface MaaccSsoConnection {
    uuid: string;
    id: string;
    name: string;
    provider: string;
    providerLabel: string;
    issuer: string;
    authorizeUrl: string;
    tokenUrl: string;
    userinfoUrl: string;
    jwksUrl: string;
    clientId: string;
    secretConfigured: boolean;
    scopes: string;
    emailClaim: string;
    nameClaim: string;
    groupsClaim: string;
    allowedDomains: string[];
    defaultTeamRole: string;
    groupRoleMappings: Array<{
        group: string;
        team_role: string;
        maacc_role?: string;
        project_slug?: string;
    }>;
    autoProvision: boolean;
    status: string;
    statusLabel: string;
    testedAt: string | null;
    approvedAt: string | null;
    createdBy: number | null;
    approvedBy: number | null;
    redirectUri: string;
    loginUrl: string;
    identityCount: number | null;
    createdAt: string | null;
}

export interface MaaccProp {
    apps: Application[];
    projects: Project[];
    agents: Agent[];
    tools: Tool[];
    runs: Run[];
    llms: Llm[];
    providerCatalog: ProviderCatalogEntry[];
    dashboard: MaaccDashboard;
    operational: MaaccOperational;
    approvals: MaaccApprovals;
    auditEvents: MaaccAuditEvent[];
    roles: Role[];
    policies: Policy[];
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
    memberDirectory: Array<{ id: number; name: string; email: string }>;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            readiness: EnterpriseReadinessState;
            sidebarOpen: boolean;
            currentTeam: Team | null;
            teams: Team[];
            maacc: MaaccProp | null;
            [key: string]: unknown;
        };
    }
}
