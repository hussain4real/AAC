/* ============================================================
   MAACC — Tool Registry (list)
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { StatCard } from '@/components/maacc/charts';
import { ToolFormModal } from '@/components/maacc/tool-form';
import {
    Badge,
    Btn,
    ExecChip,
    ImplBadge,
    PageHeader,
    SensBadge,
    Select,
    Table,
    Td,
    Tr,
    inputStyle,
    scopeBadge,
} from '@/components/maacc/ui';
import { effectiveImpl } from '@/maacc/data';
import { Icon } from '@/maacc/icons';
import { useMaaccNav } from '@/maacc/nav';
import { useMaaccData } from '@/maacc/use-data';

export default function Tools() {
    const { go, scope } = useMaaccNav();
    const MAACC = useMaaccData();
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
            ['required', 'outdated', 'incompatible'].includes(effectiveImpl(t)),
        ).length,
        approval: scope.tools.filter((t) => t.approval).length,
    };

    return (
        <>
            <Head title="Tool Registry" />
            <div className="route-anim">
                <PageHeader
                    title="Tool Registry"
                    sub="Central catalog of tool contracts. MAACC owns the definition & governance; execution lives where the contract specifies."
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
                            className="maacc-input"
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
                            ...Object.keys(MAACC.execModeLabel).map((k) => ({
                                value: k,
                                label: MAACC.execModeLabel[k],
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
                                    <ImplBadge status={effectiveImpl(t)} />
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

                <ToolFormModal
                    open={showCreate}
                    onClose={() => setShowCreate(false)}
                />
            </div>
        </>
    );
}
