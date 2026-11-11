export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header
            className={variant === 'small' ? 'space-y-1' : 'mb-6 space-y-1'}
        >
            <h2
                className={
                    variant === 'small' ? 't-title' : 'rm-display text-display'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="t-body text-muted-fg">{description}</p>
            )}
        </header>
    );
}
