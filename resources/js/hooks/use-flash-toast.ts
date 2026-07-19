import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    useEffect(() => {
        const removeFlash = router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            toast[data.type](data.message);
        });
        const removeHttpException = router.on('httpException', (event) => {
            const status = (event as CustomEvent).detail?.response?.status as
                number | undefined;
            const message =
                status === 403
                    ? 'Your authorization changed. Refresh or ask an administrator for access.'
                    : status === 409
                      ? 'This record changed while you were working. Refresh and review the latest version.'
                      : status === 429
                        ? 'Too many requests. Wait briefly, then retry.'
                        : status && status >= 500
                          ? 'The server could not complete the request. Your changes were not confirmed.'
                          : 'The request could not be completed.';

            toast.error(message);
        });
        const removeNetworkError = router.on('networkError', () => {
            toast.error(
                'MAACC is offline or unreachable. Check your connection and retry; no success was recorded.',
            );
        });

        return () => {
            removeFlash();
            removeHttpException();
            removeNetworkError();
        };
    }, []);
}
