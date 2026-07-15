import { usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';

type EnterpriseReadinessBannerProps = {
    className?: string;
};

export function EnterpriseReadinessBanner({
    className,
}: EnterpriseReadinessBannerProps) {
    const readiness = usePage().props.readiness;

    if (readiness.status === 'enterprise_ready') {
        return null;
    }

    return (
        <div
            role="status"
            data-test="enterprise-readiness-banner"
            className={cn(
                'border-y border-amber-300 bg-amber-50 px-4 py-2.5 text-amber-950 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-100',
                className,
            )}
        >
            <div className="mx-auto flex max-w-7xl items-start gap-2 text-sm">
                <AlertTriangle
                    aria-hidden="true"
                    className="mt-0.5 size-4 shrink-0"
                />
                <p>
                    <span className="font-semibold">
                        Enterprise readiness controls are active.
                    </span>{' '}
                    {readiness.message}
                    {readiness.changeOwner ? (
                        <span> Change owner: {readiness.changeOwner}.</span>
                    ) : null}
                </p>
            </div>
        </div>
    );
}
