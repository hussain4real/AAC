/* ============================================================
   MAAC — Form helpers (Phase 7)
   Shared bits for wiring the console's create/edit modals to the
   real backend write endpoints: enum option lists (value = the
   lowercase enum the FormRequests expect, label = the Title-case
   the console displays), the current-team accessor used to build
   team-scoped Wayfinder URLs, and an inline validation-error line.
   ============================================================ */
import { usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { Icon } from '@/maac/icons';
import type { Team } from '@/types/teams';

export type Option = { value: string; label: string };

/** Application / project / model / run environments (App\Enums\Environment). */
export const ENV_OPTIONS: Option[] = [
    { value: 'development', label: 'Development' },
    { value: 'sandbox', label: 'Sandbox' },
    { value: 'staging', label: 'Staging' },
    { value: 'production', label: 'Production' },
];

/** Application lifecycle status (App\Enums\AppStatus). */
export const APP_STATUS_OPTIONS: Option[] = [
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'archived', label: 'Archived' },
];

/** Project lifecycle status (App\Enums\ProjectStatus). */
export const PROJECT_STATUS_OPTIONS: Option[] = [
    { value: 'active', label: 'Active' },
    { value: 'archived', label: 'Archived' },
];

/** Agent lifecycle status (App\Enums\AgentStatus). */
export const AGENT_STATUS_OPTIONS: Option[] = [
    { value: 'draft', label: 'Draft' },
    { value: 'testing', label: 'Testing' },
    { value: 'published', label: 'Published' },
    { value: 'disabled', label: 'Disabled' },
];

/** Approved-model catalog status (App\Enums\LlmStatus). */
export const LLM_STATUS_OPTIONS: Option[] = [
    { value: 'approved', label: 'Approved' },
    { value: 'deprecated', label: 'Deprecated' },
    { value: 'blocked', label: 'Blocked' },
];

/** Data sensitivity classification (App\Enums\Sensitivity). */
export const SENSITIVITY_OPTIONS: Option[] = [
    { value: 'public', label: 'Public' },
    { value: 'internal', label: 'Internal' },
    { value: 'confidential', label: 'Confidential' },
    { value: 'restricted', label: 'Restricted' },
];

/** Tool execution mode (App\Enums\ExecMode). */
export const EXEC_MODE_OPTIONS: Option[] = [
    { value: 'hosted', label: 'MAAC-hosted' },
    { value: 'client', label: 'Client-side' },
    { value: 'http', label: 'Remote HTTP' },
    { value: 'connector', label: 'Connector server' },
    { value: 'knowledge', label: 'Knowledge retrieval' },
    { value: 'db', label: 'Read-only DB' },
];

/** Tool contract scope (App\Enums\ToolScope). */
export const TOOL_SCOPE_OPTIONS: Option[] = [
    { value: 'global', label: 'Global' },
    { value: 'project', label: 'Project' },
    { value: 'agent', label: 'Agent' },
];

/** Remote HTTP tool method constraint (App\Enums\HttpMethod). */
export const HTTP_METHOD_OPTIONS: Option[] = [
    { value: 'get', label: 'GET' },
    { value: 'post', label: 'POST' },
    { value: 'put', label: 'PUT' },
    { value: 'patch', label: 'PATCH' },
    { value: 'delete', label: 'DELETE' },
];

/** Remote HTTP / MCP connector auth scheme (App\Enums\RemoteAuthType). */
export const REMOTE_AUTH_OPTIONS: Option[] = [
    { value: 'none', label: 'No authentication' },
    { value: 'bearer', label: 'Bearer token' },
    { value: 'header', label: 'Custom header' },
];

/** Read-only data source surface type (App\Enums\DbConnectionType). */
export const DB_CONNECTION_TYPE_OPTIONS: Option[] = [
    { value: 'read_replica', label: 'Read replica' },
    { value: 'materialized_view', label: 'Materialized view' },
    { value: 'reporting_schema', label: 'Reporting schema' },
    { value: 'curated_view', label: 'Curated view' },
];

/** Quota scope (App\Enums\QuotaScope). */
export const QUOTA_SCOPE_OPTIONS: Option[] = [
    { value: 'platform', label: 'Platform' },
    { value: 'application', label: 'Application' },
    { value: 'project', label: 'Project' },
    { value: 'agent', label: 'Agent' },
    { value: 'model', label: 'Model' },
];

/** Governance approval types (App\Enums\ApprovalType). */
export const APPROVAL_TYPE_OPTIONS: Option[] = [
    { value: 'agent_publication', label: 'Agent publication' },
    { value: 'tool_contract', label: 'Tool contract' },
    { value: 'model_access', label: 'Model environment access' },
    { value: 'credential_change', label: 'Production credential change' },
    { value: 'knowledge_ingestion', label: 'Knowledge source ingestion' },
    { value: 'data_source_access', label: 'Data source access' },
];

/** Evaluation case workflow kinds (App\Enums\EvaluationCaseKind). */
export const EVALUATION_CASE_KIND_OPTIONS: Option[] = [
    { value: 'no_tool', label: 'No tool' },
    { value: 'client_tool', label: 'Client-side tool' },
    { value: 'remote_tool', label: 'Remote HTTP tool' },
    { value: 'connector', label: 'MCP connector' },
    { value: 'rag', label: 'Knowledge retrieval (RAG)' },
];

/**
 * Convert a Title-case display label (as serialized by the API resources)
 * back to the lowercase enum value the FormRequests validate against. Every
 * MAAC enum value is the single-word lowercase of its label, so this is a safe
 * round-trip for prefilling edit forms.
 */
export function toEnumValue(label?: string | null): string {
    return (label ?? '').toLowerCase();
}

/** The current team, used to build team-scoped Wayfinder URLs. */
export function useCurrentTeam(): Team | null {
    return usePage().props.currentTeam;
}

/**
 * A toggleable chip multi-select. Backs array fields like a project's allowed
 * models or an agent's assigned tools — each chip carries the value (a record
 * UUID) the FormRequest validates against.
 */
export function ChipMultiSelect({
    options,
    selected,
    onToggle,
    empty = 'No options available.',
}: {
    options: Option[];
    selected: string[];
    onToggle: (value: string) => void;
    empty?: string;
}) {
    if (options.length === 0) {
        return (
            <div style={{ fontSize: 12, color: 'var(--text-3)' }}>{empty}</div>
        );
    }

    return (
        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
            {options.map((o) => {
                const on = selected.includes(o.value);

                return (
                    <button
                        key={o.value}
                        type="button"
                        onClick={() => onToggle(o.value)}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 5,
                            height: 26,
                            padding: '0 10px',
                            fontSize: 12,
                            fontWeight: 600,
                            borderRadius: 999,
                            cursor: 'pointer',
                            background: on
                                ? 'var(--primary-soft)'
                                : 'var(--surface-3)',
                            color: on ? 'var(--primary)' : 'var(--text-2)',
                            border: `1px solid ${on ? 'var(--primary-soft-2)' : 'var(--border-2)'}`,
                            transition: 'all .12s',
                        }}
                    >
                        <Icon name={on ? 'check' : 'plus'} size={12} />
                        {o.label}
                    </button>
                );
            })}
        </div>
    );
}

/** Inline validation-error line rendered beneath a Field. */
export function FieldError({ error }: { error?: string }) {
    if (!error) {
        return null;
    }

    const style: CSSProperties = {
        fontSize: 11.5,
        color: 'var(--red-600)',
        marginTop: 5,
        fontWeight: 500,
    };

    return <div style={style}>{error}</div>;
}
