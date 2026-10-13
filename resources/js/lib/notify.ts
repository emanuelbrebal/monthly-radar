/**
 * Pub/sub mínimo pra erro de ação (pin, checklist, descartar…) sem precisar
 * de um contexto React ou biblioteca de toast — só alguns componentes soltos
 * publicam, um único `<ErrorToast />` no topo da página escuta. Ver
 * docs/03-ui-inventario.md → microcopy "erro geral".
 */
type Listener = (message: string) => void;

let listeners: Listener[] = [];

export function notifyError(message: string): void {
    listeners.forEach((listener) => listener(message));
}

export function onNotifyError(listener: Listener): () => void {
    listeners.push(listener);

    return () => {
        listeners = listeners.filter((l) => l !== listener);
    };
}
