import { Form, Head, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TeamInvitationAlert from '@/components/team-invitation-alert';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import type { TeamInvitationContext } from '@/types';

type SsoConnection = { name: string; loginUrl: string };

type Props = {
    status?: string;
    canResetPassword: boolean;
    teamInvitation?: TeamInvitationContext | null;
    ssoConnections?: SsoConnection[];
};

export default function Login({
    status,
    canResetPassword,
    teamInvitation,
    ssoConnections = [],
}: Props) {
    const page = usePage<{ errors: { sso?: string } }>();
    const ssoError = page.props.errors?.sso;
    const registrationEnabled = page.props.readiness.registrationEnabled;

    return (
        <>
            <Head title="Log in" />

            {teamInvitation && (
                <TeamInvitationAlert
                    invitation={teamInvitation}
                    action="Log in"
                />
            )}

            {ssoError && (
                <div className="mb-4 rounded-md bg-red-50 px-4 py-3 text-center text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-400">
                    {ssoError}
                </div>
            )}

            {ssoConnections.length > 0 && (
                <div className="mb-6 flex flex-col gap-3">
                    {ssoConnections.map((connection) => (
                        <Button
                            key={connection.loginUrl}
                            asChild
                            variant="outline"
                            className="w-full"
                        >
                            <a href={connection.loginUrl}>
                                Continue with {connection.name}
                            </a>
                        </Button>
                    ))}
                    <div className="relative my-1 text-center text-xs text-muted-foreground">
                        <span className="bg-background px-2">
                            or sign in with email
                        </span>
                    </div>
                </div>
            )}

            <PasskeyVerify />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Password</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            Forgot password?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </Button>
                        </div>

                        {registrationEnabled ? (
                            <div className="text-center text-sm text-muted-foreground">
                                Don't have an account?{' '}
                                <TextLink
                                    href={register({
                                        query: {
                                            invitation: teamInvitation?.code,
                                        },
                                    })}
                                    data-test="register-link"
                                    tabIndex={5}
                                >
                                    Sign up
                                </TextLink>
                            </div>
                        ) : null}
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
