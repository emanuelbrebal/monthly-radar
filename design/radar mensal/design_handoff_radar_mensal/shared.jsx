/* shared.jsx — common UI elements & data for radar mensal */

// ── Sample ideas data ────────────────────────────────────────────
const IDEAS = [
  {
    id: 'splitwise-nordeste',
    title: 'Divisor de despesas para repúblicas universitárias',
    summary: 'Tipo Splitwise, mas otimizado para repúblicas com 6+ pessoas e contas de gás compartilhadas — comum em capitais do Nordeste.',
    source: 'producthunt.com · 03 abr',
    category: 'fintech · social',
    model: 'SaaS · freemium',
    tags: ['bem tropicalizado', 'poucos concorrentes', 'TAM grande'],
    stars: 4,
    verdict: 'pos',
    art: 'screenshot · app de divisão de contas',
  },
  {
    id: 'cardapio-ia',
    title: 'Gerador de cardápio digital com IA para bares de rua',
    summary: 'Foto do cardápio escrito à mão vira menu digital + QR code em 2 min. Cresceu na Tailândia, sem player forte no Brasil.',
    source: 'indiehackers.com · 11 abr',
    category: 'foodtech · IA',
    model: 'SaaS · mensal R$29',
    tags: ['inédita no brasil', 'boa recepção do público', 'ideia boa de melhorar'],
    stars: 5,
    verdict: 'pos',
    art: 'cardápio papel → digital',
  },
  {
    id: 'agendamento-barbearia',
    title: 'Agendamento de barbearia com pagamento PIX antecipado',
    summary: 'Reduz faltas em 70% segundo case na Indonésia. Mercado de barbearias cresce 14%/ano no NE.',
    source: 'twitter.com/@buildinpublic',
    category: 'beauty · servicos',
    model: 'SaaS · R$49/mês por loja',
    tags: ['bem tropicalizado', 'mercado grande', 'ideia comum'],
    stars: 3,
    verdict: 'neu',
    art: 'agenda + pix',
  },
  {
    id: 'feirante-ai',
    title: 'App de gestão de estoque para feirantes (offline-first)',
    summary: 'Funciona sem internet, sincroniza no fim do dia. Modelo similar pegou no Quênia (M-Kopa style).',
    source: 'a16z.com/africa · 28 mar',
    category: 'comércio · b2b',
    model: 'freemium + take rate',
    tags: ['inédita no brasil', 'difícil distribuição'],
    stars: 4,
    verdict: 'pos',
    art: 'feira + tablet',
  },
  {
    id: 'condominio-pix',
    title: 'Cobrança recorrente de condomínio via PIX automático',
    summary: 'Síndicos amadores controlam tudo em planilha. 700k condomínios no BR, e poucos têm software dedicado.',
    source: 'reddit.com/r/empreendedorismo',
    category: 'fintech · imóveis',
    model: 'SaaS · % do volume',
    tags: ['poucos concorrentes', 'regulação pesada'],
    stars: 4,
    verdict: 'neu',
    art: 'planilha de condomínio',
  },
  {
    id: 'lavanderia',
    title: 'Marketplace de lavanderia self-service por hora',
    summary: 'Você reserva uma máquina, paga por tempo de uso. Comum em SF e NYC.',
    source: 'producthunt.com · 02 abr',
    category: 'serviços',
    model: 'take rate 15%',
    tags: ['ideia mais ou menos', 'mercado saturado'],
    stars: 2,
    verdict: 'neg',
    art: 'lavanderia',
  },
];

// ── Tag system — pre-baked color treatments ─────────────────────
const TAG_STYLES = {
  'bem tropicalizado':       { kind: 'pos', icon: '◉' },
  'inédita no brasil':       { kind: 'pos', icon: '◇' },
  'poucos concorrentes':     { kind: 'pos', icon: '↑' },
  'boa recepção do público': { kind: 'pos', icon: '♥' },
  'TAM grande':              { kind: 'pos', icon: '∞' },
  'mercado grande':          { kind: 'pos', icon: '∞' },
  'ideia boa':               { kind: 'pos', icon: '★' },
  'ideia boa de melhorar':   { kind: 'neu', icon: '✎' },
  'ideia mais ou menos':     { kind: 'neu', icon: '~' },
  'ideia comum':             { kind: 'neu', icon: '=' },
  'difícil distribuição':    { kind: 'neu', icon: '?' },
  'regulação pesada':        { kind: 'neg', icon: '!' },
  'mercado saturado':        { kind: 'neg', icon: '×' },
  'ideia muito comum':       { kind: 'neg', icon: '×' },
};

function Tag({ label, size = 'sm' }) {
  const s = TAG_STYLES[label] || { kind: 'neu', icon: '·' };
  const pad = size === 'lg' ? '5px 10px 4px' : '4px 8px 3px';
  const fs = size === 'lg' ? 11 : 10;
  return (
    <span className={'rm-badge ' + s.kind} style={{ padding: pad, fontSize: fs }}>
      <span style={{ opacity: 0.7 }}>{s.icon}</span>
      <span>{label}</span>
    </span>
  );
}

function Stars({ value, max = 5, size = 14, color }) {
  const filled = '★'.repeat(value);
  const empty = '☆'.repeat(max - value);
  return (
    <span className="rm-stars" style={{ fontSize: size, color: color || 'var(--gold)' }}>
      {filled}<span style={{ opacity: 0.35 }}>{empty}</span>
    </span>
  );
}

function Placeholder({ label, h = 120, w = '100%', radius = 0 }) {
  return (
    <div className="placeholder-img" style={{ height: h, width: w, borderRadius: radius }}>
      <span style={{ position: 'relative' }}>{label}</span>
    </div>
  );
}

// ── Logo / marca radar mensal ───────────────────────────────────
function RadarMark({ size = 56, color = 'var(--ink)', accent = 'var(--red)' }) {
  return (
    <svg width={size} height={size} viewBox="0 0 56 56" style={{ display: 'block' }}>
      <circle cx="28" cy="28" r="26" fill="none" stroke={color} strokeWidth="1.5"/>
      <circle cx="28" cy="28" r="18" fill="none" stroke={color} strokeWidth="1" opacity="0.5"/>
      <circle cx="28" cy="28" r="10" fill="none" stroke={color} strokeWidth="1" opacity="0.3"/>
      <line x1="28" y1="2" x2="28" y2="54" stroke={color} strokeWidth="0.8" opacity="0.3"/>
      <line x1="2" y1="28" x2="54" y2="28" stroke={color} strokeWidth="0.8" opacity="0.3"/>
      <path d="M28 28 L48 14" stroke={accent} strokeWidth="2" strokeLinecap="round"/>
      <circle cx="48" cy="14" r="3" fill={accent}/>
      <circle cx="14" cy="40" r="1.6" fill={color}/>
      <circle cx="38" cy="36" r="1.6" fill={color}/>
      <circle cx="20" cy="18" r="1.6" fill={color}/>
    </svg>
  );
}

// ── Animated radar (used in onboarding / loading) ───────────────
function RadarAnim({ size = 120 }) {
  return (
    <div style={{ position: 'relative', width: size, height: size }}>
      <svg width={size} height={size} viewBox="0 0 100 100">
        <defs>
          <radialGradient id="rg" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stopColor="#4a7c3d" stopOpacity="0.0"/>
            <stop offset="100%" stopColor="#4a7c3d" stopOpacity="0.18"/>
          </radialGradient>
        </defs>
        <circle cx="50" cy="50" r="48" fill="url(#rg)" stroke="#1a1612" strokeWidth="0.8"/>
        <circle cx="50" cy="50" r="32" fill="none" stroke="#1a1612" strokeWidth="0.4" opacity="0.4"/>
        <circle cx="50" cy="50" r="16" fill="none" stroke="#1a1612" strokeWidth="0.4" opacity="0.4"/>
        <line x1="50" y1="2" x2="50" y2="98" stroke="#1a1612" strokeWidth="0.4" opacity="0.3"/>
        <line x1="2" y1="50" x2="98" y2="50" stroke="#1a1612" strokeWidth="0.4" opacity="0.3"/>
        <g style={{ transformOrigin: '50px 50px', animation: 'sweep 4s linear infinite' }}>
          <path d="M50 50 L50 2 A48 48 0 0 1 95.5 35 Z" fill="#4a7c3d" opacity="0.18"/>
          <line x1="50" y1="50" x2="50" y2="2" stroke="#4a7c3d" strokeWidth="1"/>
        </g>
        <circle cx="68" cy="30" r="2" fill="#c8553d"/>
        <circle cx="34" cy="62" r="1.4" fill="#1a1612"/>
        <circle cx="72" cy="58" r="1.4" fill="#1a1612"/>
      </svg>
      <style>{`@keyframes sweep { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`}</style>
    </div>
  );
}

Object.assign(window, { IDEAS, TAG_STYLES, Tag, Stars, Placeholder, RadarMark, RadarAnim });
