/* ============================================================
   MAAC — Tool contract form (Phase 7)
   Shared create/edit modal for tool contracts, used by the Tool
   Registry list and the tool detail page. Includes a small
   key→type schema editor that builds the `field => '<base>[?]
   [·format]'` map the Store/UpdateToolContractRequest validate.
   ============================================================ */
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    store as storeTool,
    update as updateTool,
} from '@/actions/App/Http/Controllers/Maac/ToolContractController';
import {
    Btn,
    Field,
    Input,
    Modal,
    Select,
    Textarea,
    Toggle,
} from '@/components/maac/ui';
import type { Tool } from '@/maac/data';
import {
    EXEC_MODE_OPTIONS,
    FieldError,
    HTTP_METHOD_OPTIONS,
    REMOTE_AUTH_OPTIONS,
    SENSITIVITY_OPTIONS,
    TOOL_SCOPE_OPTIONS,
    toEnumValue,
    useCurrentTeam,
} from '@/maac/forms';
import { useMaacData } from '@/maac/use-data';

type SchemaRow = { key: string; type: string };

/** Sentinel select value for "no owning application" (Radix disallows ''). */
const NO_APP = 'none';

function objToRows(obj: Record<string, string>): SchemaRow[] {
    const rows = Object.entries(obj).map(([key, type]) => ({ key, type }));

    return rows.length ? rows : [{ key: '', type: 'string' }];
}

function rowsToObj(rows: SchemaRow[]): Record<string, string> {
    return rows.reduce<Record<string, string>>((acc, row) => {
        const key = row.key.trim();

        if (key) {
            acc[key] = row.type.trim() || 'string';
        }

        return acc;
    }, {});
}

const parseTimeout = (value: string): number => parseInt(value, 10) || 15;

const parsePayloadKb = (value: string): number => {
    const [amount, unit] = value.split(' ');
    const num = parseInt(amount, 10) || 256;

    return unit === 'MB' ? num * 1024 : num;
};

function SchemaEditor({
    rows,
    onChange,
}: {
    rows: SchemaRow[];
    onChange: (rows: SchemaRow[]) => void;
}) {
    const update = (index: number, patch: Partial<SchemaRow>) =>
        onChange(
            rows.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {rows.map((row, index) => (
                <div key={index} style={{ display: 'flex', gap: 8 }}>
                    <Input
                        value={row.key}
                        onChange={(e) => update(index, { key: e.target.value })}
                        placeholder="field_name"
                        style={{ fontFamily: 'var(--mono)' }}
                    />
                    <Input
                        value={row.type}
                        onChange={(e) =>
                            update(index, { type: e.target.value })
                        }
                        placeholder="string"
                        style={{ fontFamily: 'var(--mono)', width: 170 }}
                    />
                    <Btn
                        variant="ghost"
                        size="icon"
                        icon="trash"
                        style={{ height: 36, width: 36, flexShrink: 0 }}
                        onClick={() =>
                            onChange(rows.filter((_, i) => i !== index))
                        }
                    />
                </div>
            ))}
            <Btn
                variant="soft"
                size="sm"
                icon="plus"
                onClick={() => onChange([...rows, { key: '', type: 'string' }])}
            >
                Add field
            </Btn>
        </div>
    );
}

export function ToolFormModal({
    tool,
    open,
    onClose,
}: {
    tool?: Tool;
    open: boolean;
    onClose: () => void;
}) {
    const team = useCurrentTeam();
    const MAAC = useMaacData();
    const isEdit = !!tool;
    const [inputRows, setInputRows] = useState<SchemaRow[]>(
        tool ? objToRows(tool.input) : [{ key: '', type: 'string' }],
    );
    const [outputRows, setOutputRows] = useState<SchemaRow[]>(
        tool ? objToRows(tool.output) : [{ key: '', type: 'string' }],
    );

    const connectorUuid = tool?.connector
        ? (MAAC.connectors.find((c) => c.id === tool.connector)?.uuid ?? '')
        : (MAAC.connectors[0]?.uuid ?? '');

    const knowledgeUuid =
        tool?.knowledgeSourceId ?? MAAC.knowledgeSources[0]?.uuid ?? '';

    const dataSourceUuid =
        tool?.dataSourceId ?? MAAC.dataSources[0]?.uuid ?? '';

    const form = useForm<{
        name: string;
        application_id: string;
        description: string;
        scope: string;
        execution_mode: string;
        sensitivity: string;
        requires_approval: boolean;
        timeout_seconds: number;
        max_payload_kb: number;
        version: string;
        input_schema: Record<string, string>;
        output_schema: Record<string, string>;
        http_method: string;
        http_endpoint: string;
        http_auth_type: string;
        http_auth_header: string;
        http_auth_credential: string;
        http_max_attempts: number;
        http_backoff_ms: number;
        mcp_connector_id: string;
        mcp_tool_name: string;
        knowledge_source_id: string;
        knowledge_top_k: number;
        knowledge_min_score: number;
        data_source_id: string;
        db_query: string;
        db_bindings: string;
        db_columns: string;
        db_row_limit: number;
        db_max_age_minutes: string;
        redaction: string;
    }>({
        name: tool?.name ?? '',
        application_id: tool?.appId
            ? (MAAC.appById(tool.appId)?.uuid ?? NO_APP)
            : NO_APP,
        description: tool?.desc ?? '',
        scope: tool ? toEnumValue(tool.scope) : 'project',
        execution_mode: tool ? tool.execMode : 'client',
        sensitivity: tool ? toEnumValue(tool.sensitivity) : 'internal',
        requires_approval: tool ? tool.approval : false,
        timeout_seconds: tool ? parseTimeout(tool.timeout) : 15,
        max_payload_kb: tool ? parsePayloadKb(tool.maxPayload) : 256,
        version: tool?.version ?? '1.0.0',
        input_schema: tool ? tool.input : {},
        output_schema: tool ? tool.output : {},
        http_method: tool?.httpConfig?.method ?? 'post',
        http_endpoint: tool?.httpConfig?.endpoint ?? '',
        http_auth_type: tool?.httpConfig?.authType ?? 'none',
        http_auth_header: tool?.httpConfig?.authHeader ?? '',
        http_auth_credential: '',
        http_max_attempts: tool?.httpConfig?.maxAttempts ?? 1,
        http_backoff_ms: tool?.httpConfig?.backoffMs ?? 0,
        mcp_connector_id: connectorUuid,
        mcp_tool_name: tool?.remoteTool ?? '',
        knowledge_source_id: knowledgeUuid,
        knowledge_top_k: tool?.knowledgeConfig?.topK ?? 5,
        knowledge_min_score: tool?.knowledgeConfig?.minScore ?? 0.1,
        data_source_id: dataSourceUuid,
        db_query: tool?.dbConfig?.query ?? '',
        db_bindings: (tool?.dbConfig?.bindings ?? []).join(', '),
        db_columns: (tool?.dbConfig?.columns ?? []).join(', '),
        db_row_limit: tool?.dbConfig?.rowLimit ?? 50,
        db_max_age_minutes: tool?.dbConfig?.maxAgeMinutes
            ? String(tool.dbConfig.maxAgeMinutes)
            : '',
        redaction: (tool?.redaction ?? []).join(', '),
    });

    const close = () => {
        form.clearErrors();
        onClose();
    };

    const submit = () => {
        if (!team) {
            return;
        }

        form.transform((data) => {
            const base = {
                name: data.name,
                application_id:
                    data.application_id === NO_APP ? '' : data.application_id,
                description: data.description,
                scope: data.scope,
                execution_mode: data.execution_mode,
                sensitivity: data.sensitivity,
                requires_approval: data.requires_approval,
                timeout_seconds: data.timeout_seconds,
                max_payload_kb: data.max_payload_kb,
                version: data.version,
                input_schema: rowsToObj(inputRows),
                output_schema: rowsToObj(outputRows),
                redaction: data.redaction
                    .split(',')
                    .map((path) => path.trim())
                    .filter(Boolean),
            };

            if (data.execution_mode === 'http') {
                return {
                    ...base,
                    http_config: {
                        method: data.http_method,
                        endpoint: data.http_endpoint,
                        auth: {
                            type: data.http_auth_type,
                            header: data.http_auth_header,
                            credential: data.http_auth_credential,
                        },
                        retry: {
                            max_attempts: data.http_max_attempts,
                            backoff_ms: data.http_backoff_ms,
                        },
                    },
                };
            }

            if (data.execution_mode === 'connector') {
                return {
                    ...base,
                    mcp_connector_id: data.mcp_connector_id,
                    mcp_tool_name: data.mcp_tool_name,
                };
            }

            if (data.execution_mode === 'knowledge') {
                return {
                    ...base,
                    knowledge_source_id: data.knowledge_source_id,
                    knowledge_config: {
                        top_k: data.knowledge_top_k,
                        min_score: data.knowledge_min_score,
                    },
                };
            }

            if (data.execution_mode === 'db') {
                return {
                    ...base,
                    data_source_id: data.data_source_id,
                    db_config: {
                        query: data.db_query,
                        bindings: data.db_bindings
                            .split(',')
                            .map((b) => b.trim())
                            .filter(Boolean),
                        columns: data.db_columns
                            .split(',')
                            .map((c) => c.trim())
                            .filter(Boolean),
                        row_limit: data.db_row_limit,
                        max_age_minutes:
                            data.db_max_age_minutes === ''
                                ? null
                                : parseInt(data.db_max_age_minutes, 10),
                    },
                };
            }

            return base;
        });

        if (tool) {
            form.put(updateTool([team.slug, tool.id]).url, {
                preserveScroll: true,
                onSuccess: () => onClose(),
            });

            return;
        }

        form.post(storeTool([team.slug]).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    const appOptions = [
        { value: NO_APP, label: 'None (global / platform tool)' },
        ...MAAC.apps.map((app) => ({
            value: app.uuid ?? app.id,
            label: app.name,
        })),
    ];
    /** Read a (possibly nested) server validation error by its dotted key. */
    const errFor = (key: string): string | undefined =>
        (form.errors as Record<string, string | undefined>)[key];
    const selectedConnector = MAAC.connectors.find(
        (c) => c.uuid === form.data.mcp_connector_id,
    );
    const remoteToolHint =
        selectedConnector && selectedConnector.capabilities.length > 0
            ? `Discovered tools: ${selectedConnector.capabilities.map((c) => c.name).join(', ')}`
            : 'Run discovery on the connector to list its available tools.';
    const configBox = {
        display: 'flex',
        flexDirection: 'column' as const,
        gap: 14,
        padding: 14,
        background: 'var(--surface-3)',
        borderRadius: 'var(--r-sm)',
        border: '1px solid var(--border-2)',
    };
    const half = { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 };
    const third = {
        display: 'grid',
        gridTemplateColumns: '1fr 1fr 1fr',
        gap: 14,
    };

    return (
        <Modal
            open={open}
            onClose={close}
            icon="tools"
            title={isEdit ? 'Edit tool contract' : 'Create Tool Contract'}
            sub="MAAC owns the contract; execution lives where the mode specifies."
            width={640}
            footer={
                <>
                    <Btn variant="ghost" onClick={close}>
                        Cancel
                    </Btn>
                    <Btn
                        variant="primary"
                        icon="check"
                        disabled={form.processing}
                        onClick={submit}
                    >
                        {isEdit ? 'Save changes' : 'Create Contract'}
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Field
                    label="Tool name"
                    required
                    hint="camelCase identifier the agent will call"
                >
                    <Input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="getBusinessData"
                        style={{ fontFamily: 'var(--mono)' }}
                    />
                    <FieldError error={form.errors.name} />
                </Field>
                <Field label="Description">
                    <Textarea
                        rows={2}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                        placeholder="What does this tool retrieve or do?"
                    />
                    <FieldError error={form.errors.description} />
                </Field>
                <div style={half}>
                    <Field label="Scope" required>
                        <Select
                            value={form.data.scope}
                            onChange={(v) => form.setData('scope', v)}
                            options={TOOL_SCOPE_OPTIONS}
                        />
                        <FieldError error={form.errors.scope} />
                    </Field>
                    <Field label="Execution mode" required>
                        <Select
                            value={form.data.execution_mode}
                            onChange={(v) => form.setData('execution_mode', v)}
                            options={EXEC_MODE_OPTIONS}
                        />
                        <FieldError error={form.errors.execution_mode} />
                    </Field>
                </div>
                <div style={half}>
                    <Field label="Data sensitivity" required>
                        <Select
                            value={form.data.sensitivity}
                            onChange={(v) => form.setData('sensitivity', v)}
                            options={SENSITIVITY_OPTIONS}
                        />
                        <FieldError error={form.errors.sensitivity} />
                    </Field>
                    {!isEdit && (
                        <Field
                            label="Owning application"
                            hint="Client-side tools belong to one application."
                        >
                            <Select
                                value={form.data.application_id}
                                onChange={(v) =>
                                    form.setData('application_id', v)
                                }
                                options={appOptions}
                            />
                            <FieldError error={form.errors.application_id} />
                        </Field>
                    )}
                </div>
                <div style={third}>
                    <Field label="Timeout (s)" required>
                        <Input
                            type="number"
                            min="1"
                            max="600"
                            value={form.data.timeout_seconds}
                            onChange={(e) =>
                                form.setData(
                                    'timeout_seconds',
                                    parseInt(e.target.value, 10) || 0,
                                )
                            }
                        />
                        <FieldError error={form.errors.timeout_seconds} />
                    </Field>
                    <Field label="Max payload (KB)" required>
                        <Input
                            type="number"
                            min="1"
                            max="10240"
                            value={form.data.max_payload_kb}
                            onChange={(e) =>
                                form.setData(
                                    'max_payload_kb',
                                    parseInt(e.target.value, 10) || 0,
                                )
                            }
                        />
                        <FieldError error={form.errors.max_payload_kb} />
                    </Field>
                    <Field label="Version">
                        <Input
                            value={form.data.version}
                            onChange={(e) =>
                                form.setData('version', e.target.value)
                            }
                            placeholder="1.0.0"
                            style={{ fontFamily: 'var(--mono)' }}
                        />
                        <FieldError error={form.errors.version} />
                    </Field>
                </div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 14,
                        padding: '11px 0',
                    }}
                >
                    <div style={{ flex: 1 }}>
                        <div style={{ fontSize: 13, fontWeight: 600 }}>
                            Requires approval
                        </div>
                        <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
                            Gate this tool behind a governance approval before
                            production use.
                        </div>
                    </div>
                    <Toggle
                        on={form.data.requires_approval}
                        onChange={(v) => form.setData('requires_approval', v)}
                    />
                </div>
                {form.data.execution_mode === 'http' && (
                    <div style={configBox}>
                        <div
                            style={{
                                fontSize: 12,
                                fontWeight: 700,
                                color: 'var(--text-2)',
                            }}
                        >
                            Remote HTTP execution
                        </div>
                        <div style={half}>
                            <Field label="Method" required>
                                <Select
                                    value={form.data.http_method}
                                    onChange={(v) =>
                                        form.setData('http_method', v)
                                    }
                                    options={HTTP_METHOD_OPTIONS}
                                />
                                <FieldError
                                    error={errFor('http_config.method')}
                                />
                            </Field>
                            <Field label="Authentication">
                                <Select
                                    value={form.data.http_auth_type}
                                    onChange={(v) =>
                                        form.setData('http_auth_type', v)
                                    }
                                    options={REMOTE_AUTH_OPTIONS}
                                />
                            </Field>
                        </div>
                        <Field
                            label="Endpoint URL"
                            required
                            hint="The host must be on the platform egress allowlist."
                        >
                            <Input
                                value={form.data.http_endpoint}
                                onChange={(e) =>
                                    form.setData(
                                        'http_endpoint',
                                        e.target.value,
                                    )
                                }
                                placeholder="https://api.example.com/v1/tool"
                                style={{ fontFamily: 'var(--mono)' }}
                            />
                            <FieldError
                                error={errFor('http_config.endpoint')}
                            />
                        </Field>
                        {form.data.http_auth_type === 'header' && (
                            <Field label="Header name" required>
                                <Input
                                    value={form.data.http_auth_header}
                                    onChange={(e) =>
                                        form.setData(
                                            'http_auth_header',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="X-Api-Key"
                                    style={{ fontFamily: 'var(--mono)' }}
                                />
                            </Field>
                        )}
                        {form.data.http_auth_type !== 'none' && (
                            <Field
                                label={
                                    isEdit
                                        ? 'Credential (leave blank to keep)'
                                        : 'Credential'
                                }
                                hint="Stored encrypted and never displayed again."
                            >
                                <Input
                                    type="password"
                                    value={form.data.http_auth_credential}
                                    onChange={(e) =>
                                        form.setData(
                                            'http_auth_credential',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={
                                        isEdit &&
                                        tool?.httpConfig?.authConfigured
                                            ? '•••••••• (unchanged)'
                                            : 'token or key value'
                                    }
                                />
                                <FieldError
                                    error={errFor(
                                        'http_config.auth.credential',
                                    )}
                                />
                            </Field>
                        )}
                        <div style={half}>
                            <Field
                                label="Max attempts"
                                hint="Retries on 5xx / connection errors."
                            >
                                <Input
                                    type="number"
                                    min="1"
                                    max="10"
                                    value={form.data.http_max_attempts}
                                    onChange={(e) =>
                                        form.setData(
                                            'http_max_attempts',
                                            parseInt(e.target.value, 10) || 1,
                                        )
                                    }
                                />
                            </Field>
                            <Field label="Backoff (ms)">
                                <Input
                                    type="number"
                                    min="0"
                                    value={form.data.http_backoff_ms}
                                    onChange={(e) =>
                                        form.setData(
                                            'http_backoff_ms',
                                            parseInt(e.target.value, 10) || 0,
                                        )
                                    }
                                />
                            </Field>
                        </div>
                    </div>
                )}
                {form.data.execution_mode === 'connector' && (
                    <div style={configBox}>
                        <div
                            style={{
                                fontSize: 12,
                                fontWeight: 700,
                                color: 'var(--text-2)',
                            }}
                        >
                            MCP connector mapping
                        </div>
                        {MAAC.connectors.length === 0 ? (
                            <div
                                style={{ fontSize: 12, color: 'var(--text-3)' }}
                            >
                                Register an MCP connector first, then map this
                                tool to one of its remote tools.
                            </div>
                        ) : (
                            <>
                                <Field label="MCP connector" required>
                                    <Select
                                        value={form.data.mcp_connector_id}
                                        onChange={(v) =>
                                            form.setData('mcp_connector_id', v)
                                        }
                                        options={MAAC.connectors.map((c) => ({
                                            value: c.uuid,
                                            label: c.name,
                                        }))}
                                    />
                                    <FieldError
                                        error={form.errors.mcp_connector_id}
                                    />
                                </Field>
                                <Field
                                    label="Remote tool name"
                                    required
                                    hint={remoteToolHint}
                                >
                                    <Input
                                        value={form.data.mcp_tool_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'mcp_tool_name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="lookup"
                                        style={{ fontFamily: 'var(--mono)' }}
                                    />
                                    <FieldError
                                        error={form.errors.mcp_tool_name}
                                    />
                                </Field>
                            </>
                        )}
                    </div>
                )}
                {form.data.execution_mode === 'knowledge' && (
                    <div style={configBox}>
                        <div
                            style={{
                                fontSize: 12,
                                fontWeight: 700,
                                color: 'var(--text-2)',
                            }}
                        >
                            Knowledge retrieval (RAG)
                        </div>
                        {MAAC.knowledgeSources.length === 0 ? (
                            <div
                                style={{ fontSize: 12, color: 'var(--text-3)' }}
                            >
                                Register a knowledge source first, then map this
                                tool to it.
                            </div>
                        ) : (
                            <>
                                <Field label="Knowledge source" required>
                                    <Select
                                        value={form.data.knowledge_source_id}
                                        onChange={(v) =>
                                            form.setData(
                                                'knowledge_source_id',
                                                v,
                                            )
                                        }
                                        options={MAAC.knowledgeSources.map(
                                            (s) => ({
                                                value: s.uuid,
                                                label: s.name,
                                            }),
                                        )}
                                    />
                                    <FieldError
                                        error={form.errors.knowledge_source_id}
                                    />
                                </Field>
                                <div style={half}>
                                    <Field
                                        label="Top K chunks"
                                        hint="Max passages to retrieve."
                                    >
                                        <Input
                                            type="number"
                                            min="1"
                                            max="50"
                                            value={form.data.knowledge_top_k}
                                            onChange={(e) =>
                                                form.setData(
                                                    'knowledge_top_k',
                                                    parseInt(
                                                        e.target.value,
                                                        10,
                                                    ) || 1,
                                                )
                                            }
                                        />
                                        <FieldError
                                            error={errFor(
                                                'knowledge_config.top_k',
                                            )}
                                        />
                                    </Field>
                                    <Field
                                        label="Min relevance"
                                        hint="0–1 query-term coverage."
                                    >
                                        <Input
                                            type="number"
                                            min="0"
                                            max="1"
                                            step="0.05"
                                            value={
                                                form.data.knowledge_min_score
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'knowledge_min_score',
                                                    parseFloat(
                                                        e.target.value,
                                                    ) || 0,
                                                )
                                            }
                                        />
                                        <FieldError
                                            error={errFor(
                                                'knowledge_config.min_score',
                                            )}
                                        />
                                    </Field>
                                </div>
                            </>
                        )}
                    </div>
                )}
                {form.data.execution_mode === 'db' && (
                    <div style={configBox}>
                        <div
                            style={{
                                fontSize: 12,
                                fontWeight: 700,
                                color: 'var(--text-2)',
                            }}
                        >
                            Read-only database query
                        </div>
                        {MAAC.dataSources.length === 0 ? (
                            <div
                                style={{ fontSize: 12, color: 'var(--text-3)' }}
                            >
                                Register a read-only data source first, then map
                                this tool to it.
                            </div>
                        ) : (
                            <>
                                <Field label="Data source" required>
                                    <Select
                                        value={form.data.data_source_id}
                                        onChange={(v) =>
                                            form.setData('data_source_id', v)
                                        }
                                        options={MAAC.dataSources.map((s) => ({
                                            value: s.uuid,
                                            label: s.name,
                                        }))}
                                    />
                                    <FieldError
                                        error={form.errors.data_source_id}
                                    />
                                </Field>
                                <Field
                                    label="Query (read-only SELECT)"
                                    required
                                    hint="Parameterized SELECT against the source's approved relations. Bind values with :name — never interpolate."
                                >
                                    <Textarea
                                        rows={3}
                                        value={form.data.db_query}
                                        onChange={(e) =>
                                            form.setData(
                                                'db_query',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="select region, calls from reporting_metrics where region = :region"
                                        style={{ fontFamily: 'var(--mono)' }}
                                    />
                                    <FieldError
                                        error={errFor('db_config.query')}
                                    />
                                </Field>
                                <div style={half}>
                                    <Field
                                        label="Bindings"
                                        hint="Comma-separated :name params, from the input schema."
                                    >
                                        <Input
                                            value={form.data.db_bindings}
                                            onChange={(e) =>
                                                form.setData(
                                                    'db_bindings',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="region"
                                            style={{
                                                fontFamily: 'var(--mono)',
                                            }}
                                        />
                                    </Field>
                                    <Field
                                        label="Return columns"
                                        hint="Result minimization — only these are returned."
                                    >
                                        <Input
                                            value={form.data.db_columns}
                                            onChange={(e) =>
                                                form.setData(
                                                    'db_columns',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="region, calls"
                                            style={{
                                                fontFamily: 'var(--mono)',
                                            }}
                                        />
                                    </Field>
                                </div>
                                <div style={half}>
                                    <Field
                                        label="Row limit"
                                        hint="Capped by the source's max rows."
                                    >
                                        <Input
                                            type="number"
                                            min="1"
                                            value={form.data.db_row_limit}
                                            onChange={(e) =>
                                                form.setData(
                                                    'db_row_limit',
                                                    parseInt(
                                                        e.target.value,
                                                        10,
                                                    ) || 1,
                                                )
                                            }
                                        />
                                        <FieldError
                                            error={errFor(
                                                'db_config.row_limit',
                                            )}
                                        />
                                    </Field>
                                    <Field
                                        label="Max data age (min)"
                                        hint="Optional freshness expectation."
                                    >
                                        <Input
                                            type="number"
                                            min="1"
                                            value={form.data.db_max_age_minutes}
                                            onChange={(e) =>
                                                form.setData(
                                                    'db_max_age_minutes',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="1440"
                                        />
                                    </Field>
                                </div>
                            </>
                        )}
                    </div>
                )}
                {(form.data.execution_mode === 'http' ||
                    form.data.execution_mode === 'connector' ||
                    form.data.execution_mode === 'knowledge' ||
                    form.data.execution_mode === 'db') && (
                    <Field
                        label="Redaction"
                        hint="Comma-separated result field paths to mask in the stored trace (the model still receives raw values)."
                    >
                        <Input
                            value={form.data.redaction}
                            onChange={(e) =>
                                form.setData('redaction', e.target.value)
                            }
                            placeholder="ssn, customer.email"
                            style={{ fontFamily: 'var(--mono)' }}
                        />
                    </Field>
                )}
                <Field
                    label="Input schema"
                    required
                    hint="Field → type. Base types: string, number, integer, boolean, object, array. Add ? for optional, ·format for a format (e.g. string·date)."
                >
                    <SchemaEditor rows={inputRows} onChange={setInputRows} />
                    <FieldError error={form.errors.input_schema} />
                </Field>
                <Field label="Output schema" required>
                    <SchemaEditor rows={outputRows} onChange={setOutputRows} />
                    <FieldError error={form.errors.output_schema} />
                </Field>
            </div>
        </Modal>
    );
}
