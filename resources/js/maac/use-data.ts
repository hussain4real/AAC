/* ============================================================
   MAAC — Runtime data accessor (Phase 2)
   Drop-in replacement for the Phase 1 `MAAC` fixture object. Reads the
   team's records from the shared `maac` Inertia prop (served by
   App\Support\MaacConsoleData) and exposes the same helper API the console
   screens already use. Governance/dashboard display rollups still come from
   the fixture until they are backed by real aggregates (Phase 5).
   ============================================================ */
import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { MAAC as FIXTURE } from './data';
import type { Agent, Application, Llm, Project, Run, Tool } from './data';

export type MaacDataset = {
    apps: Application[];
    projects: Project[];
    agents: Agent[];
    tools: Tool[];
    runs: Run[];
    llms: Llm[];
};

/** Resolve the team dataset from the shared prop, falling back to the fixture. */
export function useMaacDataset(): MaacDataset {
    const { maac } = usePage().props;

    return useMemo(
        () => ({
            apps: maac?.apps ?? FIXTURE.apps,
            projects: maac?.projects ?? FIXTURE.projects,
            agents: maac?.agents ?? FIXTURE.agents,
            tools: maac?.tools ?? FIXTURE.tools,
            runs: maac?.runs ?? FIXTURE.runs,
            llms: maac?.llms ?? FIXTURE.llms,
        }),
        [maac],
    );
}

export type MaacData = MaacDataset & {
    roles: typeof FIXTURE.roles;
    approvals: typeof FIXTURE.approvals;
    policies: typeof FIXTURE.policies;
    sensitivityLevels: typeof FIXTURE.sensitivityLevels;
    dashboard: typeof FIXTURE.dashboard;
    execModeLabel: typeof FIXTURE.execModeLabel;
    implLabel: typeof FIXTURE.implLabel;
    byId: <T extends { id: string }>(list: T[], id: string) => T | undefined;
    appById: (id: string) => Application | undefined;
    agentById: (id: string) => Agent | undefined;
    projectById: (id: string) => Project | undefined;
    toolById: (id: string) => Tool | undefined;
    llmById: (id: string) => Llm | undefined;
    agentsByApp: (id: string) => Agent[];
    projectsByApp: (id: string) => Project[];
};

/**
 * Returns a `MAAC`-shaped object backed by real records. Use inside a component
 * (`const MAAC = useMaacData();`) in place of importing the fixture.
 */
export function useMaacData(): MaacData {
    const dataset = useMaacDataset();

    return useMemo(
        () => ({
            ...dataset,
            roles: FIXTURE.roles,
            approvals: FIXTURE.approvals,
            policies: FIXTURE.policies,
            sensitivityLevels: FIXTURE.sensitivityLevels,
            dashboard: FIXTURE.dashboard,
            execModeLabel: FIXTURE.execModeLabel,
            implLabel: FIXTURE.implLabel,
            byId: <T extends { id: string }>(list: T[], id: string) =>
                list.find((item) => item.id === id),
            appById: (id: string) => dataset.apps.find((app) => app.id === id),
            agentById: (id: string) =>
                dataset.agents.find((agent) => agent.id === id),
            projectById: (id: string) =>
                dataset.projects.find((project) => project.id === id),
            toolById: (id: string) =>
                dataset.tools.find((tool) => tool.id === id),
            llmById: (id: string) => dataset.llms.find((llm) => llm.id === id),
            agentsByApp: (id: string) =>
                dataset.agents.filter((agent) => agent.appId === id),
            projectsByApp: (id: string) =>
                dataset.projects.filter((project) => project.appId === id),
        }),
        [dataset],
    );
}
