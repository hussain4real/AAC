/* ============================================================
   MAAC — LLM Providers
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Donut, DonutLegend, StatCard } from '@/components/maac/charts';
import { SectionHeader } from '@/components/maac/ui';
import {
    Badge,
    Btn,
    Card,
    Field,
    Input,
    Modal,
    PageHeader,
    Select,
    SensBadge,
    Table,
    Td,
    Toggle,
    Tr,
} from '@/components/maac/ui';
import { Icon } from '@/maac/icons';
import { useMaacData } from '@/maac/use-data';

export default function LLMProviders() {
    const MAAC = useMaacData();
    const [showAdd, setShowAdd] = useState(false);
    const approved = MAAC.llms.filter((l) => l.status === 'Approved');
    const totalRuns = MAAC.llms.reduce((s, l) => s + l.runs, 0);

    return (
        <>
            <Head title="LLM Providers" />
            <div className="route-anim">
                <PageHeader
                    title="LLM Providers"
                    sub="Company-approved model catalog. Admins control which models are enabled, in which environments, and for which sensitivity levels."
                    actions={
                        <Btn
                            variant="primary"
                            icon="plus"
                            onClick={() => setShowAdd(true)}
                        >
                            Add Provider
                        </Btn>
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
                        label="Approved models"
                        value={approved.length}
                        icon="check2"
                        tone="teal"
                    />
                    <StatCard
                        label="Providers"
                        value={new Set(MAAC.llms.map((l) => l.provider)).size}
                        icon="llm"
                        tone="purple"
                    />
                    <StatCard
                        label="Runs (7d)"
                        value={(totalRuns / 1000).toFixed(1) + 'K'}
                        icon="runs"
                        tone="blue"
                    />
                    <StatCard
                        label="On-prem models"
                        value={
                            MAAC.llms.filter((l) =>
                                l.provider.includes('On-Prem'),
                            ).length
                        }
                        icon="lock"
                        tone="amber"
                        sub="No data egress"
                    />
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 320px',
                        gap: 14,
                        marginBottom: 14,
                    }}
                >
                    <Card pad={false}>
                        <div style={{ padding: '14px 16px 12px' }}>
                            <SectionHeader
                                title="Model catalog"
                                icon="llm"
                                style={{ marginBottom: 0 }}
                            />
                        </div>
                        <Table
                            columns={[
                                { label: 'Model' },
                                { label: 'Context' },
                                { label: 'Cost / 1M', align: 'right' },
                                { label: 'Sensitivity' },
                                { label: 'Environments' },
                                { label: 'Status' },
                                { label: '' },
                            ]}
                        >
                            {MAAC.llms.map((l) => (
                                <Tr key={l.id}>
                                    <Td strong>
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 10,
                                            }}
                                        >
                                            <span
                                                style={{
                                                    width: 30,
                                                    height: 30,
                                                    borderRadius: 8,
                                                    background:
                                                        'var(--navy-900)',
                                                    color: '#fff',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    flexShrink: 0,
                                                }}
                                            >
                                                <Icon name="llm" size={15} />
                                            </span>
                                            <div>
                                                <div
                                                    style={{
                                                        color: 'var(--text)',
                                                        fontWeight: 600,
                                                    }}
                                                >
                                                    {l.name}
                                                </div>
                                                <div
                                                    className="mono"
                                                    style={{
                                                        fontSize: 11,
                                                        color: 'var(--text-3)',
                                                    }}
                                                >
                                                    {l.provider}
                                                </div>
                                            </div>
                                        </div>
                                    </Td>
                                    <Td mono>{l.ctx}</Td>
                                    <Td align="right" mono>
                                        ${l.inCost} / ${l.outCost}
                                    </Td>
                                    <Td>
                                        <SensBadge level={l.sensitivity} />
                                    </Td>
                                    <Td>
                                        <div
                                            style={{ display: 'flex', gap: 4 }}
                                        >
                                            {(
                                                [
                                                    'Production',
                                                    'Staging',
                                                    'Development',
                                                ] as const
                                            ).map((e) => (
                                                <span
                                                    key={e}
                                                    className="maac-tip"
                                                    data-tip={e}
                                                    style={{
                                                        width: 8,
                                                        height: 8,
                                                        borderRadius: 8,
                                                        background:
                                                            l.envs.includes(e)
                                                                ? 'var(--teal-500)'
                                                                : 'var(--border-2)',
                                                    }}
                                                />
                                            ))}
                                        </div>
                                    </Td>
                                    <Td>
                                        <Badge
                                            tone={
                                                l.status === 'Approved'
                                                    ? 'teal'
                                                    : l.status === 'Deprecated'
                                                      ? 'amber'
                                                      : 'red'
                                            }
                                            dot
                                        >
                                            {l.status}
                                        </Badge>
                                    </Td>
                                    <Td align="right">
                                        <Toggle
                                            on={l.status === 'Approved'}
                                            onChange={() => {}}
                                            size="sm"
                                        />
                                    </Td>
                                </Tr>
                            ))}
                        </Table>
                    </Card>

                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        <Card>
                            <SectionHeader
                                title="Usage by model"
                                sub="Share of runs (7d)"
                                icon="layers"
                            />
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 16,
                                    marginBottom: 14,
                                }}
                            >
                                <Donut
                                    size={120}
                                    thickness={15}
                                    centerLabel={
                                        (totalRuns / 1000).toFixed(0) + 'K'
                                    }
                                    centerSub="runs"
                                    data={approved.slice(0, 5).map((l, i) => ({
                                        label: l.name,
                                        value: l.runs,
                                        color: [
                                            'var(--purple-600)',
                                            'var(--purple-500)',
                                            'var(--teal-500)',
                                            'var(--blue-500)',
                                            'var(--orange-500)',
                                        ][i],
                                    }))}
                                />
                            </div>
                            <DonutLegend
                                data={approved.slice(0, 5).map((l, i) => ({
                                    label: l.name,
                                    value: l.runs,
                                    color: [
                                        'var(--purple-600)',
                                        'var(--purple-500)',
                                        'var(--teal-500)',
                                        'var(--blue-500)',
                                        'var(--orange-500)',
                                    ][i],
                                }))}
                            />
                        </Card>
                        <Card>
                            <SectionHeader
                                title="Sensitivity routing"
                                icon="shield"
                            />
                            <div
                                style={{
                                    fontSize: 12,
                                    color: 'var(--text-2)',
                                    lineHeight: 1.55,
                                    marginBottom: 12,
                                }}
                            >
                                Models are restricted by the data sensitivity
                                they may process.
                            </div>
                            {MAAC.sensitivityLevels.map((s) => (
                                <div
                                    key={s.name}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 9,
                                        padding: '7px 0',
                                        borderTop: '1px solid var(--border)',
                                    }}
                                >
                                    <SensBadge level={s.name} />
                                    <span
                                        style={{
                                            fontSize: 11.5,
                                            color: 'var(--text-3)',
                                            flex: 1,
                                        }}
                                    >
                                        {s.name === 'Restricted'
                                            ? 'On-prem only'
                                            : s.name === 'Confidential'
                                              ? 'Approved region'
                                              : 'Any approved'}
                                    </span>
                                </div>
                            ))}
                        </Card>
                    </div>
                </div>

                <Modal
                    open={showAdd}
                    onClose={() => setShowAdd(false)}
                    icon="llm"
                    title="Add Model Provider"
                    sub="Register an approved model in the catalog."
                    footer={
                        <>
                            <Btn
                                variant="ghost"
                                onClick={() => setShowAdd(false)}
                            >
                                Cancel
                            </Btn>
                            <Btn
                                variant="primary"
                                icon="check"
                                onClick={() => setShowAdd(false)}
                            >
                                Submit for Approval
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
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 14,
                            }}
                        >
                            <Field label="Provider" required>
                                <Select
                                    value="Azure OpenAI"
                                    onChange={() => {}}
                                    options={[
                                        'Azure OpenAI',
                                        'AWS Bedrock',
                                        'Google Vertex AI',
                                        'Milaha On-Prem GPU',
                                    ]}
                                />
                            </Field>
                            <Field label="Model name" required>
                                <Input placeholder="GPT-4o" />
                            </Field>
                        </div>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 14,
                            }}
                        >
                            <Field label="Context window" required>
                                <Input placeholder="128K" />
                            </Field>
                            <Field label="Sensitivity rating" required>
                                <Select
                                    value="Restricted"
                                    onChange={() => {}}
                                    options={[
                                        'Public',
                                        'Internal',
                                        'Confidential',
                                        'Restricted',
                                    ]}
                                />
                            </Field>
                        </div>
                        <Field label="Allowed environments">
                            <div style={{ display: 'flex', gap: 6 }}>
                                {(
                                    [
                                        'Production',
                                        'Staging',
                                        'Development',
                                    ] as const
                                ).map((e) => (
                                    <Badge
                                        key={e}
                                        tone={
                                            e === 'Development'
                                                ? 'purple'
                                                : 'neutral'
                                        }
                                        soft
                                    >
                                        {e}
                                    </Badge>
                                ))}
                            </div>
                        </Field>
                    </div>
                </Modal>
            </div>
        </>
    );
}
