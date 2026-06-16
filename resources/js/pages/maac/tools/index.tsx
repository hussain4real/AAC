/* ============================================================
   MAAC — Tool Registry (list)
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { StatCard } from '@/components/maac/charts';
import {
    Badge,
    Btn,
    ExecChip,
    Field,
    ImplBadge,
    Input,
    Modal,
    PageHeader,
    SensBadge,
    Select,
    Table,
    Td,
    Textarea,
    Tr,
    inputStyle,
    scopeBadge,
} from '@/components/maac/ui';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';

export default function Tools() {
    const { go, scope } = useMaacNav();
    const MAAC = useMaacData();
    const [q, setQ] = useState('');
    const [scopeF, setScopeF] = useState('All');
    const [mode, setMode] = useState('All');
    const [showCreate, setShowCreate] = useState(false);

    const list = scope.tools.filter(
        (t) =>
            (scopeF === 'All' || t.scope === scopeF) &&
            (mode === 'All' || t.execMode === mode) &&
            (t.name.toLowerCase().includes(q.toLowerCase()) ||
                t.desc.toLowerCase().includes(q.toLowerCase())),
    );

    const counts = {
        total: scope.tools.length,
        client: scope.tools.filter((t) => t.execMode === 'client').length,
        needsImpl: scope.tools.filter((t) =>
            ['required', 'outdated', 'incompatible'].includes(t.impl),
        ).length,
        approval: scope.tools.filter((t) => t.approval).length,
    };

    return (
        <>
            <Head title="Tool Registry" />
            <div className="route-anim">
                <PageHeader
                    title="Tool Registry"
                    sub="Central catalog of tool contracts. MAAC owns the definition & governance; execution lives where the contract specifies."
                    actions={
                        <>
                            <Btn
                                variant="default"
                                icon="sdk"
                                onClick={() => go('sdk')}
                            >
                                SDK Center
                            </Btn>
                            <Btn
                                variant="primary"
                                icon="plus"
                                onClick={() => setShowCreate(true)}
                            >
                                Create Tool
                            </Btn>
                        </>
                    }
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4,1fr)',
                        gap: 12,
                        marginBottom: 16,
                    }}
                >
                    <StatCard
                        label="Total tools"
                        value={counts.total}
                        icon="tools"
                        tone="purple"
                    />
                    <StatCard
                        label="Client-side"
                        value={counts.client}
                        icon="link"
                        tone="orange"
                        sub="Implemented by applications"
                    />
                    <StatCard
                        label="Need implementation"
                        value={counts.needsImpl}
                        icon="alert"
                        tone="red"
                    />
                    <StatCard
                        label="Require approval"
                        value={counts.approval}
                        icon="shield"
                        tone="amber"
                    />
                </div>

                <div
                    style={{
                        display: 'flex',
                        gap: 9,
                        marginBottom: 14,
                        flexWrap: 'wrap',
                        alignItems: 'center',
                    }}
                >
                    <div style={{ position: 'relative', width: 240 }}>
                        <Icon
                            name="search"
                            size={15}
                            style={{
                                position: 'absolute',
                                left: 11,
                                top: '50%',
                                transform: 'translateY(-50%)',
                                color: 'var(--text-3)',
                            }}
                        />
                        <input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search tools…"
                            className="maac-input"
                            style={{ ...inputStyle, paddingLeft: 34 }}
                        />
                    </div>
                    <Select
                        value={scopeF}
                        onChange={setScopeF}
                        options={[
                            { value: 'All', label: 'All scopes' },
                            'Global',
                            'Project',
                            'Agent',
                        ]}
                        style={{ width: 150 }}
                    />
                    <Select
                        value={mode}
                        onChange={setMode}
                        options={[
                            { value: 'All', label: 'All execution modes' },
                            ...Object.keys(MAAC.execModeLabel).map((k) => ({
                                value: k,
                                label: MAAC.execModeLabel[k],
                            })),
                        ]}
                        style={{ width: 200 }}
                    />
                    <div style={{ flex: 1 }} />
                    <span style={{ fontSize: 12, color: 'var(--text-3)' }}>
                        {list.length} tools
                    </span>
                </div>

                <Table
                    columns={[
                        { label: 'Tool' },
                        { label: 'Scope' },
                        { label: 'Execution mode' },
                        { label: 'Sensitivity' },
                        { label: 'Approval', align: 'center' },
                        { label: 'Used by', align: 'center' },
                        { label: 'Status' },
                        { label: '' },
                    ]}
                >
                    {list.map((t) => (
                        <Tr key={t.id} onClick={() => go('tool', { id: t.id })}>
                            <Td strong>
                                <div>
                                    <span
                                        className="mono"
                                        style={{
                                            fontSize: 13,
                                            fontWeight: 600,
                                            color: 'var(--text)',
                                        }}
                                    >
                                        {t.name}
                                    </span>
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: 'var(--text-3)',
                                            fontWeight: 400,
                                            maxWidth: 320,
                                            whiteSpace: 'nowrap',
                                            overflow: 'hidden',
                                            textOverflow: 'ellipsis',
                                        }}
                                    >
                                        {t.desc}
                                    </div>
                                </div>
                            </Td>
                            <Td>{scopeBadge(t.scope)}</Td>
                            <Td>
                                <ExecChip mode={t.execMode} />
                            </Td>
                            <Td>
                                <SensBadge level={t.sensitivity} />
                            </Td>
                            <Td align="center">
                                {t.approval ? (
                                    <Icon
                                        name="check"
                                        size={15}
                                        style={{ color: 'var(--teal-600)' }}
                                    />
                                ) : (
                                    <span style={{ color: 'var(--text-3)' }}>
                                        —
                                    </span>
                                )}
                            </Td>
                            <Td align="center" mono>
                                {t.usedBy.length}
                            </Td>
                            <Td>
                                {t.execMode === 'client' ? (
                                    <ImplBadge status={t.impl} />
                                ) : (
                                    <Badge tone="teal" dot>
                                        Ready
                                    </Badge>
                                )}
                            </Td>
                            <Td align="right">
                                <Icon
                                    name="chevright"
                                    size={15}
                                    style={{ color: 'var(--text-3)' }}
                                />
                            </Td>
                        </Tr>
                    ))}
                </Table>

                <Modal
                    open={showCreate}
                    onClose={() => setShowCreate(false)}
                    icon="tools"
                    title="Create Tool Contract"
                    sub="Define a tool. Execution mode determines where it runs."
                    width={600}
                    footer={
                        <>
                            <Btn
                                variant="ghost"
                                onClick={() => setShowCreate(false)}
                            >
                                Cancel
                            </Btn>
                            <Btn
                                variant="primary"
                                icon="check"
                                onClick={() => setShowCreate(false)}
                            >
                                Create Contract
                            </Btn>
                        </>
                    }
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 16,
                        }}
                    >
                        <Field
                            label="Tool name"
                            required
                            hint="camelCase identifier the agent will call"
                        >
                            <Input
                                placeholder="getBusinessData"
                                style={{ fontFamily: 'var(--mono)' }}
                            />
                        </Field>
                        <Field label="Description" required>
                            <Textarea
                                rows={2}
                                placeholder="What does this tool retrieve or do?"
                            />
                        </Field>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 14,
                            }}
                        >
                            <Field label="Scope" required>
                                <Select
                                    value="Project"
                                    onChange={() => {}}
                                    options={['Global', 'Project', 'Agent']}
                                />
                            </Field>
                            <Field label="Execution mode" required>
                                <Select
                                    value="client"
                                    onChange={() => {}}
                                    options={Object.keys(
                                        MAAC.execModeLabel,
                                    ).map((k) => ({
                                        value: k,
                                        label: MAAC.execModeLabel[k],
                                    }))}
                                />
                            </Field>
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                gap: 10,
                                padding: '11px 13px',
                                background: 'var(--orange-100)',
                                borderRadius: 'var(--r-md)',
                                border: '1px solid var(--orange-400)',
                            }}
                        >
                            <Icon
                                name="link"
                                size={17}
                                style={{
                                    color: 'var(--orange-600)',
                                    flexShrink: 0,
                                }}
                            />
                            <div
                                style={{
                                    fontSize: 12,
                                    color: 'var(--text-2)',
                                    lineHeight: 1.5,
                                }}
                            >
                                Client-side tools are defined here but{' '}
                                <b>implemented in the owning application</b> via
                                the SDK. MAAC will generate stubs to copy.
                            </div>
                        </div>
                    </div>
                </Modal>
            </div>
        </>
    );
}
