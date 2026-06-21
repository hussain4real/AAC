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
