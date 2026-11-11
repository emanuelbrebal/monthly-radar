// Components
import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Verificar e-mail" />

            {status === 'verification-link-sent' && (
                <div className="t-body mb-4 text-center font-semibold text-green">
                    Link de verificação reenviado.
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            reenviar link de verificação
                        </Button>

                        <TextLink
                            href={logout()}
                            className="t-body mx-auto block"
                        >
                            sair
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'verificar e-mail',
    description: 'Abra o link de verificação enviado para o seu e-mail.',
};
