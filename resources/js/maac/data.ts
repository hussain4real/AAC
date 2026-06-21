/* ============================================================
   MAAC — Mock data layer (Phase 1)
   Typed TS port of the handoff prototype's data.js. This is the
   client-side fixture that backs every console screen until the
   Phase 2 database-backed model lands.
   ============================================================ */

export type Environment = 'Production' | 'Staging' | 'Development';
export type Sensitivity = 'Public' | 'Internal' | 'Confidential' | 'Restricted';
export type ExecMode =
    | 'hosted'
    | 'client'
    | 'http'
    | 'connector'
    | 'knowledge'
    | 'db';
export type ToolScope = 'Global' | 'Project' | 'Agent';
export type ImplStatus =
    | 'ready'
    | 'implemented'
    | 'required'
    | 'outdated'
    | 'incompatible'
    | 'disabled'
    | 'n/a';
export type AgentStatus = 'Published' | 'Testing' | 'Draft' | 'Disabled';
export type AppStatus = 'Active' | 'Suspended' | 'Archived';
export type RunStatus =
    | 'completed'
    | 'failed'
    | 'waiting_for_client'
    | 'running'
    | 'requires_tool'
    | 'expired'
    | 'cancelled'
    | 'queued';

export type Llm = {
    id: string;
    name: string;
    code: string;
    provider: string;
    ctx: string;
    inCost: number;
    outCost: number;
    sensitivity: Sensitivity;
    envs: Environment[];
    status: 'Approved' | 'Deprecated' | 'Blocked';
    usagePct: number;
    runs: number;
    note: string;
};

export type Application = {
    id: string;
    name: string;
    code: string;
    dept: string;
    owner: string;
    ownerEmail: string;
    env: Environment;
    status: AppStatus;
    projects: number;
    agents: number;
    toolsRequired: number;
    toolsImplemented: number;
    lastConnected: string;
    stack: string;
    desc: string;
    credStatus: 'Active' | 'Revoked';
    /** Humanized time of the application's most recent SDK sync, or null. */
    lastSyncedAt?: string | null;
    region: string;
    created: string;
};

export type Project = {
    id: string;
    name: string;
    appId: string;
    env: Environment;
    desc: string;
    bizOwner: string;
    techOwner: string;
    status: 'Active' | 'Archived';
    llms: string[];
    agents: number;
    tools: number;
    runs7d: number;
};

export type ToolImplementationRecord = {
    env: Environment;
    status: ImplStatus;
    handler: string | null;
    version: string | null;
    language: string | null;
    lastValidated: string | null;
};

export type Tool = {
    id: string;
    name: string;
    scope: ToolScope;
    execMode: ExecMode;
    sensitivity: Sensitivity;
    approval: boolean;
    status: string;
    impl: ImplStatus;
    owner: string;
    appId: string | null;
    usedBy: string[];
    desc: string;
    timeout: string;
    maxPayload: string;
    input: Record<string, string>;
    output: Record<string, string>;
    version?: string;
    /** Per-environment client-side implementation records reported via the SDK. */
    implementations?: ToolImplementationRecord[];
};

export type Agent = {
    id: string;
    name: string;
    projectId: string;
    appId: string;
    llm: string;
    version: string;
    status: AgentStatus;
    successRate: number;
    lastRun: string;
    runs7d: number;
    desc: string;
    tools: string[];
    slug: string;
    temp: number;
    maxTokens: number;
    prompt: string;
};

export type Run = {
    id: string;
    agentId: string;
    appId: string;
    projectId: string;
    caller: string;
    status: RunStatus;
    llm: string;
    tools: string[];
    tokensIn: number;
    tokensOut: number;
    cost: number;
    latency: string;
    started: string;
    completed: string;
    input: string;
    error?: string;
};

// ---------- LLMs ----------
const llms: Llm[] = [
    {
        id: 'gpt-4o',
        name: 'GPT-4o',
        code: 'azure/gpt-4o',
        provider: 'Azure OpenAI',
        ctx: '128K',
        inCost: 2.5,
        outCost: 10.0,
        sensitivity: 'Restricted',
        envs: ['Production', 'Staging', 'Development'],
        status: 'Approved',
        usagePct: 34,
        runs: 6840,
        note: 'General reasoning, vision, broad tool use.',
    },
    {
        id: 'gpt-4o-mini',
        name: 'GPT-4o mini',
        code: 'azure/gpt-4o-mini',
        provider: 'Azure OpenAI',
        ctx: '128K',
        inCost: 0.15,
        outCost: 0.6,
        sensitivity: 'Internal',
        envs: ['Production', 'Staging', 'Development'],
        status: 'Approved',
        usagePct: 27,
        runs: 5410,
        note: 'Low-cost classification & summarization.',
    },
    {
        id: 'claude-37-sonnet',
        name: 'Claude 3.7 Sonnet',
        code: 'bedrock/claude-3-7-sonnet',
        provider: 'AWS Bedrock',
        ctx: '200K',
        inCost: 3.0,
        outCost: 15.0,
        sensitivity: 'Restricted',
        envs: ['Production', 'Staging', 'Development'],
        status: 'Approved',
        usagePct: 21,
        runs: 4230,
        note: 'Long-context analysis, careful tool reasoning.',
    },
    {
        id: 'claude-haiku',
        name: 'Claude 3.5 Haiku',
        code: 'bedrock/claude-3-5-haiku',
        provider: 'AWS Bedrock',
        ctx: '200K',
        inCost: 0.8,
        outCost: 4.0,
        sensitivity: 'Internal',
        envs: ['Production', 'Staging', 'Development'],
        status: 'Approved',
        usagePct: 9,
        runs: 1870,
        note: 'Fast, cheap extraction & routing.',
    },
    {
        id: 'gemini-15-pro',
        name: 'Gemini 1.5 Pro',
        code: 'vertex/gemini-1.5-pro',
        provider: 'Google Vertex AI',
        ctx: '1M',
        inCost: 1.25,
        outCost: 5.0,
        sensitivity: 'Restricted',
        envs: ['Staging', 'Development'],
        status: 'Approved',
        usagePct: 6,
        runs: 1180,
        note: 'Very long document ingestion.',
    },
    {
        id: 'llama3-70b',
        name: 'Llama 3.1 70B',
        code: 'onprem/llama-3.1-70b',
        provider: 'Milaha On-Prem GPU',
        ctx: '128K',
        inCost: 0.0,
        outCost: 0.0,
        sensitivity: 'Confidential',
        envs: ['Production', 'Staging', 'Development'],
        status: 'Approved',
        usagePct: 3,
        runs: 640,
        note: 'On-prem, no data egress. Highly sensitive workloads.',
    },
    {
        id: 'gpt-35-turbo',
        name: 'GPT-3.5 Turbo',
        code: 'azure/gpt-3.5-turbo',
        provider: 'Azure OpenAI',
        ctx: '16K',
        inCost: 0.5,
        outCost: 1.5,
        sensitivity: 'Internal',
        envs: ['Development'],
        status: 'Deprecated',
        usagePct: 0,
        runs: 90,
        note: 'Superseded by GPT-4o mini.',
    },
];

// ---------- Applications ----------
const apps: Application[] = [
    {
        id: 'MOP',
        name: 'Marine Operations Portal',
        code: 'marine-ops-portal',
        dept: 'Maritime & Logistics',
        owner: 'Khalid Al-Mansoori',
        ownerEmail: 'k.almansoori@milaha.com',
        env: 'Production',
        status: 'Active',
        projects: 3,
        agents: 4,
        toolsRequired: 6,
        toolsImplemented: 5,
        lastConnected: '2 min ago',
        stack: 'Laravel · PHP 8.3',
        desc: 'Vessel scheduling, berth allocation, and live operations dashboards for the fleet.',
        credStatus: 'Active',
        region: 'Qatar — Doha DC',
        created: '12 Jan 2026',
    },
    {
        id: 'FWS',
        name: 'Finance Workflow System',
        code: 'finance-workflow',
        dept: 'Finance',
        owner: 'Aisha Rahman',
        ownerEmail: 'a.rahman@milaha.com',
        env: 'Production',
        status: 'Active',
        projects: 2,
        agents: 3,
        toolsRequired: 4,
        toolsImplemented: 4,
        lastConnected: '14 min ago',
        stack: 'Spring Boot · Java 21',
        desc: 'Invoice approval, payment runs, and financial exception handling.',
        credStatus: 'Active',
        region: 'Qatar — Doha DC',
        created: '3 Feb 2026',
    },
    {
        id: 'PMA',
        name: 'Procurement Management App',
        code: 'procure-mgmt',
        dept: 'Procurement',
        owner: 'Yousef Haddad',
        ownerEmail: 'y.haddad@milaha.com',
        env: 'Staging',
        status: 'Active',
        projects: 2,
        agents: 2,
        toolsRequired: 5,
        toolsImplemented: 2,
        lastConnected: '1 hr ago',
        stack: 'Node.js · NestJS',
        desc: 'Purchase requisitions, supplier records, and procurement analytics.',
        credStatus: 'Active',
        region: 'Qatar — Doha DC',
        created: '19 Feb 2026',
    },
    {
        id: 'CSP',
        name: 'Customer Service Portal',
        code: 'customer-service',
        dept: 'Customer Experience',
        owner: 'Lina Farouk',
        ownerEmail: 'l.farouk@milaha.com',
        env: 'Production',
        status: 'Active',
        projects: 1,
        agents: 2,
        toolsRequired: 3,
        toolsImplemented: 3,
        lastConnected: '6 min ago',
        stack: 'Django · Python 3.12',
        desc: 'Customer interactions, case management, and CSAT tracking.',
        credStatus: 'Active',
        region: 'Qatar — Doha DC',
        created: '28 Feb 2026',
    },
    {
        id: 'VMS',
        name: 'Vessel Maintenance System',
        code: 'vessel-maint',
        dept: 'Marine & Technical Services',
        owner: 'Omar Sheikh',
        ownerEmail: 'o.sheikh@milaha.com',
        env: 'Development',
        status: 'Suspended',
        projects: 1,
        agents: 1,
        toolsRequired: 4,
        toolsImplemented: 1,
        lastConnected: '3 days ago',
        stack: '.NET 8 · C#',
        desc: 'Maintenance schedules, work orders, and asset risk monitoring.',
        credStatus: 'Revoked',
        region: 'Qatar — Doha DC',
        created: '5 Mar 2026',
    },
];

// ---------- Projects ----------
const projects: Project[] = [
    {
        id: 'prj_mop_ops',
        name: 'Fleet Operations Intelligence',
        appId: 'MOP',
        env: 'Production',
        desc: 'Operational summaries and exception detection across active voyages.',
        bizOwner: 'Khalid Al-Mansoori',
        techOwner: 'Reema Saleh',
        status: 'Active',
        llms: ['gpt-4o', 'claude-37-sonnet'],
        agents: 2,
        tools: 5,
        runs7d: 1840,
    },
    {
        id: 'prj_mop_berth',
        name: 'Berth & Schedule Advisor',
        appId: 'MOP',
        env: 'Production',
        desc: 'Decision support for berth allocation and schedule conflicts.',
        bizOwner: 'Khalid Al-Mansoori',
        techOwner: 'Reema Saleh',
        status: 'Active',
        llms: ['gpt-4o', 'gpt-4o-mini'],
        agents: 1,
        tools: 3,
        runs7d: 760,
    },
    {
        id: 'prj_mop_docs',
        name: 'Marine Document Review',
        appId: 'MOP',
        env: 'Staging',
        desc: 'Reviews bills of lading and shipping documents for completeness.',
        bizOwner: 'Noura Adel',
        techOwner: 'Reema Saleh',
        status: 'Active',
        llms: ['claude-37-sonnet'],
        agents: 1,
        tools: 2,
        runs7d: 210,
    },
    {
        id: 'prj_fws_appr',
        name: 'Approval & Exception Desk',
        appId: 'FWS',
        env: 'Production',
        desc: 'Reviews pending approvals and surfaces financial exceptions.',
        bizOwner: 'Aisha Rahman',
        techOwner: 'Tariq Nabil',
        status: 'Active',
        llms: ['gpt-4o', 'claude-37-sonnet'],
        agents: 2,
        tools: 4,
        runs7d: 1320,
    },
    {
        id: 'prj_fws_close',
        name: 'Month-End Close Assist',
        appId: 'FWS',
        env: 'Staging',
        desc: 'Assists analysts during the financial close cycle.',
        bizOwner: 'Aisha Rahman',
        techOwner: 'Tariq Nabil',
        status: 'Active',
        llms: ['claude-37-sonnet'],
        agents: 1,
        tools: 2,
        runs7d: 140,
    },
    {
        id: 'prj_pma_insight',
        name: 'Procurement Insight',
        appId: 'PMA',
        env: 'Staging',
        desc: 'Analyzes purchase requests and supplier performance.',
        bizOwner: 'Yousef Haddad',
        techOwner: 'Hadi Karam',
        status: 'Active',
        llms: ['gpt-4o-mini', 'gpt-4o'],
        agents: 2,
        tools: 5,
        runs7d: 430,
    },
    {
        id: 'prj_csp_trend',
        name: 'Customer Trend Analysis',
        appId: 'CSP',
        env: 'Production',
        desc: 'Surfaces emerging themes in customer interactions.',
        bizOwner: 'Lina Farouk',
        techOwner: 'Sami Diab',
        status: 'Active',
        llms: ['gpt-4o', 'claude-haiku'],
        agents: 2,
        tools: 3,
        runs7d: 980,
    },
    {
        id: 'prj_vms_risk',
        name: 'Maintenance Risk Watch',
        appId: 'VMS',
        env: 'Development',
        desc: 'Flags assets at risk based on maintenance history.',
        bizOwner: 'Omar Sheikh',
        techOwner: 'Bilal Aziz',
        status: 'Archived',
        llms: ['llama3-70b'],
        agents: 1,
        tools: 4,
        runs7d: 0,
    },
];

// ---------- Tools ----------
const tools: Tool[] = [
    {
        id: 'getOperationalRecords',
        name: 'getOperationalRecords',
        scope: 'Project',
        execMode: 'client',
        sensitivity: 'Confidential',
        approval: true,
        status: 'Active',
        impl: 'implemented',
        owner: 'MOP',
        appId: 'MOP',
        usedBy: ['ag_ops_summary', 'ag_doc_review'],
        desc: 'Retrieves approved operational voyage records for a date range.',
        timeout: '15s',
        maxPayload: '256 KB',
        input: {
            from_date: 'string·date',
            to_date: 'string·date',
            vessel_id: 'string?',
            status: 'string?',
        },
        output: { summary: 'object', records: 'array' },
    },
    {
        id: 'getPendingApprovals',
        name: 'getPendingApprovals',
        scope: 'Project',
        execMode: 'client',
        sensitivity: 'Restricted',
        approval: true,
        status: 'Active',
        impl: 'implemented',
        owner: 'FWS',
        appId: 'FWS',
        usedBy: ['ag_approval_review', 'ag_fin_exception'],
        desc: "Lists approval items pending the current user's action.",
        timeout: '10s',
        maxPayload: '128 KB',
        input: { queue: 'string', assignee_id: 'string?', limit: 'number?' },
        output: { items: 'array', total: 'number' },
    },
    {
        id: 'getProcurementRequests',
        name: 'getProcurementRequests',
        scope: 'Project',
        execMode: 'client',
        sensitivity: 'Confidential',
        approval: true,
        status: 'Active',
        impl: 'required',
        owner: 'PMA',
        appId: 'PMA',
        usedBy: ['ag_procure_insight'],
        desc: 'Fetches purchase requisitions filtered by status and department.',
        timeout: '15s',
        maxPayload: '256 KB',
        input: {
            from_date: 'string·date',
            to_date: 'string·date',
            department: 'string?',
            status: 'string?',
        },
        output: { summary: 'object', requests: 'array' },
    },
    {
        id: 'getCustomerInteractions',
        name: 'getCustomerInteractions',
        scope: 'Project',
        execMode: 'client',
        sensitivity: 'Restricted',
        approval: true,
        status: 'Active',
        impl: 'implemented',
        owner: 'CSP',
        appId: 'CSP',
        usedBy: ['ag_customer_trend'],
        desc: 'Returns anonymized customer interaction records for analysis.',
        timeout: '12s',
        maxPayload: '256 KB',
        input: {
            from_date: 'string·date',
            to_date: 'string·date',
            channel: 'string?',
        },
        output: { summary: 'object', interactions: 'array' },
    },
    {
        id: 'getFinancialTransactions',
        name: 'getFinancialTransactions',
        scope: 'Agent',
        execMode: 'client',
        sensitivity: 'Restricted',
        approval: true,
        status: 'Active',
        impl: 'outdated',
        owner: 'FWS',
        appId: 'FWS',
        usedBy: ['ag_fin_exception'],
        desc: 'Reads financial transactions for exception analysis. Masks account numbers.',
        timeout: '15s',
        maxPayload: '256 KB',
        input: {
            from_date: 'string·date',
            to_date: 'string·date',
            cost_center: 'string?',
            min_amount: 'number?',
        },
        output: { summary: 'object', transactions: 'array' },
    },
    {
        id: 'getMaintenanceSchedules',
        name: 'getMaintenanceSchedules',
        scope: 'Project',
        execMode: 'client',
        sensitivity: 'Confidential',
        approval: false,
        status: 'Active',
        impl: 'required',
        owner: 'VMS',
        appId: 'VMS',
        usedBy: ['ag_maint_risk'],
        desc: 'Returns maintenance schedules and overdue work orders by asset.',
        timeout: '15s',
        maxPayload: '256 KB',
        input: {
            asset_id: 'string?',
            overdue_only: 'boolean?',
            horizon_days: 'number?',
        },
        output: { summary: 'object', schedules: 'array' },
    },
    {
        id: 'searchPolicyDocuments',
        name: 'searchPolicyDocuments',
        scope: 'Global',
        execMode: 'knowledge',
        sensitivity: 'Internal',
        approval: false,
        status: 'Active',
        impl: 'ready',
        owner: 'Platform',
        appId: null,
        usedBy: ['ag_compliance', 'ag_doc_review'],
        desc: 'Semantic search over indexed company policy & manuals.',
        timeout: '8s',
        maxPayload: '512 KB',
        input: { query: 'string', top_k: 'number?' },
        output: { matches: 'array' },
    },
    {
        id: 'summarizeUploadedDocument',
        name: 'summarizeUploadedDocument',
        scope: 'Global',
        execMode: 'hosted',
        sensitivity: 'Internal',
        approval: false,
        status: 'Active',
        impl: 'ready',
        owner: 'Platform',
        appId: null,
        usedBy: ['ag_doc_review', 'ag_compliance'],
        desc: 'Summarizes an uploaded document passed inline to MAAC.',
        timeout: '20s',
        maxPayload: '1 MB',
        input: { document_ref: 'string', length: 'string?' },
        output: { summary: 'string', key_points: 'array' },
    },
    {
        id: 'webSearch',
        name: 'webSearch',
        scope: 'Global',
        execMode: 'hosted',
        sensitivity: 'Public',
        approval: false,
        status: 'Active',
        impl: 'ready',
        owner: 'Platform',
        appId: null,
        usedBy: ['ag_procure_insight', 'ag_customer_trend'],
        desc: 'Approved external web search via the platform gateway.',
        timeout: '10s',
        maxPayload: '256 KB',
        input: { query: 'string', recency_days: 'number?' },
        output: { results: 'array' },
    },
    {
        id: 'notifyWorkflowOwner',
        name: 'notifyWorkflowOwner',
        scope: 'Global',
        execMode: 'http',
        sensitivity: 'Internal',
        approval: true,
        status: 'Active',
        impl: 'ready',
        owner: 'Platform',
        appId: null,
        usedBy: ['ag_approval_review', 'ag_fin_exception'],
        desc: 'Sends a notification to a workflow owner via the internal Notify API.',
        timeout: '6s',
        maxPayload: '32 KB',
        input: {
            recipient_id: 'string',
            message: 'string',
            priority: 'string?',
        },
        output: { delivered: 'boolean', notification_id: 'string' },
    },
];

// ---------- Agents ----------
const agents: Agent[] = [
    {
        id: 'ag_ops_summary',
        name: 'Operations Summary Agent',
        projectId: 'prj_mop_ops',
        appId: 'MOP',
        llm: 'gpt-4o',
        version: 'v4',
        status: 'Published',
        successRate: 98.4,
        lastRun: '1 min ago',
        runs7d: 1240,
        desc: 'Summarizes daily fleet operations and surfaces voyage exceptions for duty managers.',
        tools: [
            'getOperationalRecords',
            'searchPolicyDocuments',
            'notifyWorkflowOwner',
        ],
        slug: 'operations-summary',
        temp: 0.3,
        maxTokens: 1500,
        prompt: "You are the Operations Summary Agent for Milaha's Marine Operations Portal. Produce concise, factual daily operations summaries for duty managers. Always ground statements in the operational records returned by tools. Flag any voyage with a delay over 6 hours or a compliance exception. Never speculate beyond the retrieved data.",
    },
    {
        id: 'ag_approval_review',
        name: 'Approval Review Agent',
        projectId: 'prj_fws_appr',
        appId: 'FWS',
        llm: 'claude-37-sonnet',
        version: 'v3',
        status: 'Published',
        successRate: 99.1,
        lastRun: '3 min ago',
        runs7d: 880,
        desc: 'Reviews pending approvals, checks policy thresholds, and recommends an action.',
        tools: [
            'getPendingApprovals',
            'searchPolicyDocuments',
            'notifyWorkflowOwner',
        ],
        slug: 'approval-review',
        temp: 0.2,
        maxTokens: 1200,
        prompt: 'You are the Approval Review Agent. Review pending approval items against finance policy. Recommend Approve, Reject, or Escalate with a one-line justification grounded in policy and the item details. Do not approve items above the policy threshold.',
    },
    {
        id: 'ag_procure_insight',
        name: 'Procurement Insight Agent',
        projectId: 'prj_pma_insight',
        appId: 'PMA',
        llm: 'gpt-4o-mini',
        version: 'v2',
        status: 'Testing',
        successRate: 94.7,
        lastRun: '22 min ago',
        runs7d: 310,
        desc: 'Analyzes purchase requisitions and supplier performance trends.',
        tools: ['getProcurementRequests', 'webSearch'],
        slug: 'procurement-insight',
        temp: 0.4,
        maxTokens: 1400,
        prompt: 'You are the Procurement Insight Agent. Analyze procurement requests and supplier data to surface cost-saving opportunities and supplier risk. Be specific and quantify findings where possible.',
    },
    {
        id: 'ag_customer_trend',
        name: 'Customer Trend Agent',
        projectId: 'prj_csp_trend',
        appId: 'CSP',
        llm: 'gpt-4o',
        version: 'v5',
        status: 'Published',
        successRate: 97.2,
        lastRun: '8 min ago',
        runs7d: 640,
        desc: 'Surfaces emerging themes and sentiment shifts in customer interactions.',
        tools: ['getCustomerInteractions', 'webSearch'],
        slug: 'customer-trend',
        temp: 0.5,
        maxTokens: 1600,
        prompt: 'You are the Customer Trend Agent. Identify emerging themes, recurring complaints, and sentiment shifts from customer interactions. Group findings into themes with representative examples and an estimated frequency.',
    },
    {
        id: 'ag_fin_exception',
        name: 'Financial Exception Agent',
        projectId: 'prj_fws_appr',
        appId: 'FWS',
        llm: 'claude-37-sonnet',
        version: 'v2',
        status: 'Published',
        successRate: 96.8,
        lastRun: '12 min ago',
        runs7d: 420,
        desc: 'Detects anomalous financial transactions and routes them for review.',
        tools: [
            'getFinancialTransactions',
            'getPendingApprovals',
            'notifyWorkflowOwner',
        ],
        slug: 'financial-exception',
        temp: 0.2,
        maxTokens: 1300,
        prompt: 'You are the Financial Exception Agent. Detect anomalous transactions (duplicate payments, threshold breaches, unusual vendors). Explain why each is flagged and recommend a next action. Treat all amounts as confidential.',
    },
    {
        id: 'ag_maint_risk',
        name: 'Maintenance Risk Agent',
        projectId: 'prj_vms_risk',
        appId: 'VMS',
        llm: 'llama3-70b',
        version: 'v1',
        status: 'Draft',
        successRate: 0,
        lastRun: '—',
        runs7d: 0,
        desc: 'Flags assets at elevated risk based on maintenance history and overdue work orders.',
        tools: ['getMaintenanceSchedules', 'searchPolicyDocuments'],
        slug: 'maintenance-risk',
        temp: 0.3,
        maxTokens: 1400,
        prompt: 'You are the Maintenance Risk Agent. Assess asset risk from maintenance schedules and overdue work orders. Rank assets by risk and recommend prioritized maintenance actions.',
    },
    {
        id: 'ag_doc_review',
        name: 'Document Review Agent',
        projectId: 'prj_mop_docs',
        appId: 'MOP',
        llm: 'claude-37-sonnet',
        version: 'v2',
        status: 'Testing',
        successRate: 95.5,
        lastRun: '35 min ago',
        runs7d: 180,
        desc: 'Reviews shipping documents for completeness and policy compliance.',
        tools: [
            'summarizeUploadedDocument',
            'searchPolicyDocuments',
            'getOperationalRecords',
        ],
        slug: 'document-review',
        temp: 0.2,
        maxTokens: 1800,
        prompt: 'You are the Document Review Agent. Review shipping documents for missing fields and policy compliance. List issues found and cite the relevant policy section.',
    },
    {
        id: 'ag_compliance',
        name: 'Compliance Assistant Agent',
        projectId: 'prj_fws_close',
        appId: 'FWS',
        llm: 'gpt-4o',
        version: 'v1',
        status: 'Disabled',
        successRate: 92.0,
        lastRun: '2 days ago',
        runs7d: 0,
        desc: 'Answers compliance questions grounded in company policy documents.',
        tools: ['searchPolicyDocuments', 'summarizeUploadedDocument'],
        slug: 'compliance-assistant',
        temp: 0.1,
        maxTokens: 1500,
        prompt: 'You are the Compliance Assistant Agent. Answer compliance questions strictly from indexed policy documents. Always cite the policy reference. If the answer is not in policy, say so.',
    },
];

// ---------- Runs ----------
const runs: Run[] = [
    {
        id: 'run_8fa31c',
        agentId: 'ag_ops_summary',
        appId: 'MOP',
        projectId: 'prj_mop_ops',
        caller: 'k.almansoori',
        status: 'completed',
        llm: 'gpt-4o',
        tools: ['getOperationalRecords', 'notifyWorkflowOwner'],
        tokensIn: 3120,
        tokensOut: 840,
        cost: 0.0162,
        latency: '4.2s',
        started: '08 Jun 09:41:22',
        completed: '08 Jun 09:41:26',
        input: "Summarize this morning's vessel operations and flag any delays over 6 hours.",
    },
    {
        id: 'run_7be902',
        agentId: 'ag_approval_review',
        appId: 'FWS',
        projectId: 'prj_fws_appr',
        caller: 'a.rahman',
        status: 'completed',
        llm: 'claude-37-sonnet',
        tools: ['getPendingApprovals', 'searchPolicyDocuments'],
        tokensIn: 2480,
        tokensOut: 610,
        cost: 0.0166,
        latency: '3.8s',
        started: '08 Jun 09:38:05',
        completed: '08 Jun 09:38:09',
        input: 'Review my pending approvals and recommend an action for each.',
    },
    {
        id: 'run_a14d70',
        agentId: 'ag_procure_insight',
        appId: 'PMA',
        projectId: 'prj_pma_insight',
        caller: 'y.haddad',
        status: 'waiting_for_client',
        llm: 'gpt-4o-mini',
        tools: ['getProcurementRequests'],
        tokensIn: 1840,
        tokensOut: 0,
        cost: 0.0003,
        latency: '—',
        started: '08 Jun 09:44:51',
        completed: '—',
        input: 'Which suppliers had the most delayed deliveries last quarter?',
    },
    {
        id: 'run_c92e18',
        agentId: 'ag_customer_trend',
        appId: 'CSP',
        projectId: 'prj_csp_trend',
        caller: 'l.farouk',
        status: 'completed',
        llm: 'gpt-4o',
        tools: ['getCustomerInteractions', 'webSearch'],
        tokensIn: 4210,
        tokensOut: 1180,
        cost: 0.0223,
        latency: '6.1s',
        started: '08 Jun 09:30:14',
        completed: '08 Jun 09:30:20',
        input: 'What are the top emerging complaint themes this week?',
    },
    {
        id: 'run_d33a55',
        agentId: 'ag_fin_exception',
        appId: 'FWS',
        projectId: 'prj_fws_appr',
        caller: 't.nabil',
        status: 'failed',
        llm: 'claude-37-sonnet',
        tools: ['getFinancialTransactions'],
        tokensIn: 1920,
        tokensOut: 0,
        cost: 0.0058,
        latency: '15.0s',
        started: '08 Jun 09:22:40',
        completed: '08 Jun 09:22:55',
        input: 'Find duplicate payments over QAR 50,000 this month.',
        error: "Client tool 'getFinancialTransactions' returned a schema-incompatible result (missing 'summary'). Run failed validation.",
    },
    {
        id: 'run_e07b29',
        agentId: 'ag_ops_summary',
        appId: 'MOP',
        projectId: 'prj_mop_ops',
        caller: 'r.saleh',
        status: 'completed',
        llm: 'gpt-4o',
        tools: ['getOperationalRecords'],
        tokensIn: 2980,
        tokensOut: 720,
        cost: 0.0146,
        latency: '3.9s',
        started: '08 Jun 09:15:02',
        completed: '08 Jun 09:15:06',
        input: 'Give me a berth utilization summary for Hamad Port today.',
    },
    {
        id: 'run_f51c84',
        agentId: 'ag_doc_review',
        appId: 'MOP',
        projectId: 'prj_mop_docs',
        caller: 'n.adel',
        status: 'completed',
        llm: 'claude-37-sonnet',
        tools: ['summarizeUploadedDocument', 'searchPolicyDocuments'],
        tokensIn: 5210,
        tokensOut: 1340,
        cost: 0.0357,
        latency: '7.4s',
        started: '08 Jun 08:58:31',
        completed: '08 Jun 08:58:38',
        input: 'Review this bill of lading for missing fields and policy compliance.',
    },
    {
        id: 'run_b88a02',
        agentId: 'ag_customer_trend',
        appId: 'CSP',
        projectId: 'prj_csp_trend',
        caller: 's.diab',
        status: 'running',
        llm: 'gpt-4o',
        tools: ['getCustomerInteractions'],
        tokensIn: 1620,
        tokensOut: 0,
        cost: 0.004,
        latency: '—',
        started: '08 Jun 09:45:10',
        completed: '—',
        input: 'Compare sentiment between phone and email channels this month.',
    },
    {
        id: 'run_19fd47',
        agentId: 'ag_approval_review',
        appId: 'FWS',
        projectId: 'prj_fws_appr',
        caller: 'a.rahman',
        status: 'expired',
        llm: 'claude-37-sonnet',
        tools: ['getPendingApprovals'],
        tokensIn: 1240,
        tokensOut: 0,
        cost: 0.0037,
        latency: '60.0s',
        started: '08 Jun 08:40:00',
        completed: '08 Jun 08:41:00',
        input: 'Review approvals for the procurement queue.',
        error: 'Pending client-side tool execution exceeded the 60s timeout. Run expired.',
    },
    {
        id: 'run_2c6e91',
        agentId: 'ag_procure_insight',
        appId: 'PMA',
        projectId: 'prj_pma_insight',
        caller: 'h.karam',
        status: 'completed',
        llm: 'gpt-4o-mini',
        tools: ['getProcurementRequests', 'webSearch'],
        tokensIn: 2210,
        tokensOut: 540,
        cost: 0.0006,
        latency: '5.2s',
        started: '08 Jun 08:31:18',
        completed: '08 Jun 08:31:23',
        input: 'Summarize open requisitions for the Engineering department.',
    },
    {
        id: 'run_5a0db3',
        agentId: 'ag_fin_exception',
        appId: 'FWS',
        projectId: 'prj_fws_appr',
        caller: 't.nabil',
        status: 'completed',
        llm: 'claude-37-sonnet',
        tools: ['getFinancialTransactions', 'notifyWorkflowOwner'],
        tokensIn: 3340,
        tokensOut: 910,
        cost: 0.0237,
        latency: '5.6s',
        started: '08 Jun 08:12:44',
        completed: '08 Jun 08:12:50',
        input: 'Any unusual vendor payments in the last 7 days?',
    },
    {
        id: 'run_44b7ec',
        agentId: 'ag_ops_summary',
        appId: 'MOP',
        projectId: 'prj_mop_ops',
        caller: 'k.almansoori',
        status: 'cancelled',
        llm: 'gpt-4o',
        tools: [],
        tokensIn: 480,
        tokensOut: 0,
        cost: 0.0012,
        latency: '1.1s',
        started: '08 Jun 07:55:09',
        completed: '08 Jun 07:55:10',
        input: 'Summarize operations — cancelled by caller.',
    },
];

// ---------- Governance ----------
export type Role = {
    name: string;
    users: number;
    desc: string;
    perms: string[];
};
const roles: Role[] = [
    {
        name: 'MAAC Admin',
        users: 4,
        desc: 'Full platform control: global settings, models, credentials, policies, audit.',
        perms: [
            'Manage platform',
            'Approve models',
            'Manage global tools',
            'Access all audit logs',
            'Revoke credentials',
        ],
    },
    {
        name: 'Platform Owner',
        users: 2,
        desc: 'Owns roadmap, operating model, and governance policy.',
        perms: [
            'Manage policies',
            'Approve tools',
            'View all usage',
            'Manage roles',
        ],
    },
    {
        name: 'Application Owner',
        users: 9,
        desc: 'Approves agent use cases and data access for their application.',
        perms: [
            'Approve agents',
            'Manage app members',
            'View app usage',
            'Manage credentials',
        ],
    },
    {
        name: 'Project Owner',
        users: 14,
        desc: 'Registers and manages projects, approves project agents and tools.',
        perms: [
            'Manage projects',
            'Approve project tools',
            'View project usage',
        ],
    },
    {
        name: 'Agent Developer',
        users: 38,
        desc: 'Creates agents, defines tool contracts, implements SDK handlers.',
        perms: [
            'Create agents',
            'Define tool contracts',
            'Test in playground',
            'Implement SDK handlers',
        ],
    },
    {
        name: 'Security Reviewer',
        users: 6,
        desc: 'Reviews data access boundaries, logs, and run traces.',
        perms: [
            'View all run traces',
            'Review tool contracts',
            'Access audit logs',
            'Flag policy violations',
        ],
    },
    {
        name: 'Business Viewer',
        users: 27,
        desc: 'Read-only access to agents, dashboards, and permitted reports.',
        perms: ['View agents', 'View dashboards', 'View permitted reports'],
    },
];

export type ApprovalSubject = {
    kind: string;
    fields: { k: string; v: string }[];
    description?: string | null;
    systemPrompt?: string | null;
    tools?: string[];
    inputSchema?: Record<string, string>;
    outputSchema?: Record<string, string>;
};

export type ApprovalItem = {
    id: string;
    title: string;
    app: string;
    requestedBy: string;
    sensitivity?: Sensitivity;
    env?: string;
    waiting: string;
    type: string;
    summary?: string;
    subject?: ApprovalSubject | null;
    blockers?: string[];
};
const approvals: {
    tools: ApprovalItem[];
    agents: ApprovalItem[];
    models: ApprovalItem[];
    data: ApprovalItem[];
} = {
    tools: [
        {
            id: 'getProcurementRequests',
            title: 'getProcurementRequests',
            app: 'Procurement Management App',
            requestedBy: 'h.karam',
            sensitivity: 'Confidential',
            waiting: '2 days',
            type: 'Client-side tool contract',
        },
        {
            id: 'getMaintenanceSchedules',
            title: 'getMaintenanceSchedules',
            app: 'Vessel Maintenance System',
            requestedBy: 'b.aziz',
            sensitivity: 'Confidential',
            waiting: '4 hours',
            type: 'Client-side tool contract',
        },
    ],
    agents: [
        {
            id: 'ag_procure_insight',
            title: 'Procurement Insight Agent',
            app: 'Procurement Management App',
            requestedBy: 'h.karam',
            env: 'Staging → Production',
            waiting: '1 day',
            type: 'Agent publication',
        },
        {
            id: 'ag_doc_review',
            title: 'Document Review Agent',
            app: 'Marine Operations Portal',
            requestedBy: 'n.adel',
            env: 'Staging → Production',
            waiting: '6 hours',
            type: 'Agent publication',
        },
    ],
    models: [
        {
            id: 'gemini-15-pro',
            title: 'Gemini 1.5 Pro → Production',
            app: 'Platform',
            requestedBy: 'platform.ops',
            sensitivity: 'Restricted',
            waiting: '3 days',
            type: 'Model environment promotion',
        },
    ],
    data: [
        {
            id: 'req_finance',
            title: 'Raw tool-result logging for getFinancialTransactions',
            app: 'Finance Workflow System',
            requestedBy: 't.nabil',
            sensitivity: 'Restricted',
            waiting: '5 hours',
            type: 'Sensitive data access',
        },
    ],
};

export type Policy = { name: string; on: boolean; desc: string };
const policies: Policy[] = [
    {
        name: 'Client-side data isolation',
        on: true,
        desc: 'MAAC must never hold credentials for or directly query application production databases.',
    },
    {
        name: 'Tool result masking',
        on: true,
        desc: 'Restricted & Confidential tool results are masked before being written to logs.',
    },
    {
        name: 'Model access by sensitivity',
        on: true,
        desc: 'Confidential workloads may only use on-prem or approved-region models.',
    },
    {
        name: 'Approval before production',
        on: true,
        desc: 'Agents and client-side tools require owner approval before production use.',
    },
    {
        name: 'Prompt & tool-call guardrails',
        on: true,
        desc: 'Input/output guardrails screen prompts and tool arguments for policy violations.',
    },
    {
        name: 'Credential auto-rotation',
        on: false,
        desc: 'Automatically rotate application client secrets every 90 days.',
    },
];

export type SensitivityLevel = {
    name: Sensitivity;
    desc: string;
    color: string;
};
const sensitivityLevels: SensitivityLevel[] = [
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
        desc: 'Highest sensitivity. On-prem models preferred; raw logging blocked.',
        color: 'red',
    },
];

// ---------- Dashboard rollups ----------
const dashboard = {
    stats: {
        apps: 5,
        projects: 8,
        agents: 8,
        tools: 10,
        runsToday: 1284,
        waitingClient: 7,
        success: 1198,
        failed: 31,
        tokens: '4.82M',
        cost: 'QAR 1,940',
    },
    runStatus: [
        { label: 'Completed', value: 1198, color: 'var(--teal-500)' },
        { label: 'Waiting for client', value: 7, color: 'var(--orange-600)' },
        { label: 'Running', value: 18, color: 'var(--blue-500)' },
        { label: 'Failed', value: 31, color: 'var(--red-500)' },
        { label: 'Expired', value: 9, color: 'var(--amber-500)' },
        { label: 'Cancelled', value: 21, color: 'var(--text-3)' },
    ],
    runsOverTime: [
        40,
        52,
        48,
        61,
        72,
        68,
        90,
        84,
        110,
        96,
        128,
        140,
        118,
        150,
        134,
        162,
        148,
        176,
        158,
        190,
        172,
        205,
        188,
        (1284 / 6) | 0,
    ],
    topAgents: [
        {
            id: 'ag_ops_summary',
            name: 'Operations Summary Agent',
            runs: 1240,
            app: 'MOP',
        },
        {
            id: 'ag_approval_review',
            name: 'Approval Review Agent',
            runs: 880,
            app: 'FWS',
        },
        {
            id: 'ag_customer_trend',
            name: 'Customer Trend Agent',
            runs: 640,
            app: 'CSP',
        },
        {
            id: 'ag_fin_exception',
            name: 'Financial Exception Agent',
            runs: 420,
            app: 'FWS',
        },
        {
            id: 'ag_procure_insight',
            name: 'Procurement Insight Agent',
            runs: 310,
            app: 'PMA',
        },
    ],
    alerts: [
        {
            sev: 'high',
            title: 'Schema-incompatible tool result',
            desc: 'getFinancialTransactions returned a result missing required fields on 2 runs.',
            time: '23 min ago',
            icon: 'shield-alert',
        },
        {
            sev: 'med',
            title: 'Credential revoked',
            desc: 'Vessel Maintenance System credentials were revoked by an admin.',
            time: '1 hr ago',
            icon: 'key',
        },
        {
            sev: 'med',
            title: '7 runs waiting for client tools',
            desc: 'Procurement requests tool not yet implemented in Staging.',
            time: '5 min ago',
            icon: 'clock',
        },
        {
            sev: 'low',
            title: 'Model promotion pending',
            desc: 'Gemini 1.5 Pro awaiting approval for Production.',
            time: '3 days ago',
            icon: 'sparkles',
        },
    ],
};

export const execModeLabel: Record<string, string> = {
    hosted: 'MAAC-hosted',
    client: 'Client-side',
    http: 'Remote HTTP',
    connector: 'Connector server',
    knowledge: 'Knowledge retrieval',
    db: 'Read-only DB',
};
export const implLabel: Record<string, string> = {
    ready: 'Ready',
    implemented: 'Implemented',
    required: 'Requires implementation',
    outdated: 'Outdated',
    incompatible: 'Incompatible',
    disabled: 'Disabled',
    'n/a': 'Not required',
};

// ---------- helpers ----------
export const MAAC = {
    llms,
    apps,
    projects,
    tools,
    agents,
    runs,
    roles,
    approvals,
    policies,
    sensitivityLevels,
    dashboard,
    execModeLabel,
    implLabel,
    byId<T extends { id: string }>(list: T[], id: string): T | undefined {
        return list.find((x) => x.id === id);
    },
    appById(id: string): Application | undefined {
        return apps.find((a) => a.id === id);
    },
    agentsByApp(id: string): Agent[] {
        return agents.filter((a) => a.appId === id);
    },
    projectsByApp(id: string): Project[] {
        return projects.filter((p) => p.appId === id);
    },
    toolById(id: string): Tool | undefined {
        return tools.find((t) => t.id === id);
    },
    agentById(id: string): Agent | undefined {
        return agents.find((a) => a.id === id);
    },
    projectById(id: string): Project | undefined {
        return projects.find((p) => p.id === id);
    },
    llmById(id: string): Llm | undefined {
        return llms.find((l) => l.id === id);
    },
};

export type MaacData = typeof MAAC;
