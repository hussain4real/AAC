import type { Agent, Application, Llm, Project, Run, Tool } from '@/maac/data';
import type { Auth } from '@/types/auth';
import type { Team } from '@/types/teams';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            currentTeam: Team | null;
            teams: Team[];
            maac: {
                apps: Application[];
                projects: Project[];
                agents: Agent[];
                tools: Tool[];
                runs: Run[];
                llms: Llm[];
            } | null;
            [key: string]: unknown;
        };
    }
}
