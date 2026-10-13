/**
 * fetch() com o header X-XSRF-TOKEN que o VerifyCsrfToken do Laravel espera
 * fora de requests feitos pelo Inertia (que já cuida disso sozinho). Usado
 * pelo polling de varredura e pelo botão de pin da ItemBox.
 */
export function csrfFetch(input: string, init: RequestInit = {}): Promise<Response> {
    const token = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return fetch(input, {
        ...init,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
            ...init.headers,
        },
    });
}
