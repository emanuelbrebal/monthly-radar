/* logos.jsx — 4 propostas de logomarca · vibe detetive */

// ── A · RADAR MENSAL (atual) ───────────────────────────────────
function MarkRadar({ size = 64, color = 'var(--ink)', accent = 'var(--red)' }) {
  return (
    <svg width={size} height={size} viewBox="0 0 64 64" style={{ display: 'block' }}>
      <circle cx="32" cy="32" r="30" fill="none" stroke={color} strokeWidth="1.5"/>
      <circle cx="32" cy="32" r="20" fill="none" stroke={color} strokeWidth="1" opacity="0.5"/>
      <circle cx="32" cy="32" r="10" fill="none" stroke={color} strokeWidth="1" opacity="0.3"/>
      <line x1="32" y1="2" x2="32" y2="62" stroke={color} strokeWidth="0.6" opacity="0.3"/>
      <line x1="2" y1="32" x2="62" y2="32" stroke={color} strokeWidth="0.6" opacity="0.3"/>
      <path d="M32 32 L54 16" stroke={accent} strokeWidth="2.2" strokeLinecap="round"/>
      <circle cx="54" cy="16" r="3.2" fill={accent}/>
      <circle cx="18" cy="44" r="1.6" fill={color}/>
      <circle cx="44" cy="40" r="1.6" fill={color}/>
    </svg>
  );
}

// ── B · DOSSIÊ MENSAL (pasta) ──────────────────────────────────
function MarkDossie({ size = 64, color = 'var(--ink)', accent = 'var(--red)' }) {
  return (
    <svg width={size} height={size} viewBox="0 0 64 64" style={{ display: 'block' }}>
      {/* aba */}
      <path d="M6 14 L22 14 L26 10 L42 10 L42 14" fill="none" stroke={color} strokeWidth="2" strokeLinejoin="round"/>
      {/* corpo da pasta */}
      <rect x="6" y="14" width="52" height="42" fill="none" stroke={color} strokeWidth="2"/>
      {/* dobra interna */}
      <line x1="6" y1="22" x2="58" y2="22" stroke={color} strokeWidth="0.8" opacity="0.4"/>
      {/* fita vermelha diagonal */}
      <path d="M44 6 L60 22" stroke={accent} strokeWidth="4" strokeLinecap="square"/>
      {/* pequeno selo redondo */}
      <circle cx="52" cy="14" r="2.5" fill={accent}/>
      {/* etiqueta lateral */}
      <line x1="14" y1="32" x2="32" y2="32" stroke={color} strokeWidth="1.2"/>
      <line x1="14" y1="38" x2="40" y2="38" stroke={color} strokeWidth="1.2" opacity="0.5"/>
      <line x1="14" y1="44" x2="28" y2="44" stroke={color} strokeWidth="1.2" opacity="0.5"/>
    </svg>
  );
}

// ── C · LUPA ────────────────────────────────────────────────────
function MarkLupa({ size = 64, color = 'var(--ink)', accent = 'var(--red)' }) {
  return (
    <svg width={size} height={size} viewBox="0 0 64 64" style={{ display: 'block' }}>
      {/* cabo */}
      <line x1="42" y1="42" x2="60" y2="60" stroke={color} strokeWidth="5" strokeLinecap="round"/>
      <line x1="42" y1="42" x2="60" y2="60" stroke={accent} strokeWidth="2" strokeLinecap="round"/>
      {/* lente */}
      <circle cx="26" cy="26" r="22" fill="var(--card)" stroke={color} strokeWidth="3"/>
      {/* crosshair / alvo dentro da lente */}
      <line x1="14" y1="26" x2="38" y2="26" stroke={color} strokeWidth="0.8" opacity="0.4"/>
      <line x1="26" y1="14" x2="26" y2="38" stroke={color} strokeWidth="0.8" opacity="0.4"/>
      <circle cx="26" cy="26" r="10" fill="none" stroke={color} strokeWidth="0.8" opacity="0.4"/>
      {/* ponto vermelho — "achou" */}
      <circle cx="26" cy="26" r="3.5" fill={accent}/>
      {/* brilho */}
      <path d="M14 16 Q12 22 14 28" fill="none" stroke={color} strokeWidth="1.2" opacity="0.4"/>
    </svg>
  );
}

// ── D · GABINETE (monograma carimbo) ────────────────────────────
function MarkGabinete({ size = 64, color = 'var(--ink)', accent = 'var(--red)' }) {
  return (
    <svg width={size} height={size} viewBox="0 0 64 64" style={{ display: 'block' }}>
      {/* selo externo dupla borda */}
      <circle cx="32" cy="32" r="30" fill="none" stroke={accent} strokeWidth="2.5"/>
      <circle cx="32" cy="32" r="25.5" fill="none" stroke={accent} strokeWidth="1"/>
      {/* texto orbital pontos */}
      <circle cx="32" cy="6" r="1.4" fill={accent}/>
      <circle cx="32" cy="58" r="1.4" fill={accent}/>
      <circle cx="6" cy="32" r="1.4" fill={accent}/>
      <circle cx="58" cy="32" r="1.4" fill={accent}/>
      {/* monograma RM */}
      <text x="32" y="42" textAnchor="middle" fontFamily="var(--f-display)" fontSize="32" fontWeight="400" fill={accent}>RM</text>
      {/* estrela noir */}
      <circle cx="32" cy="14" r="1.8" fill={accent}/>
    </svg>
  );
}

// ── Logo card — same shape for fair comparison ──────────────────
function LogoCard({ idx, mark: Mark, name, sub, kicker, metaphor, voice }) {
  return (
    <div style={{
      display: 'flex', flexDirection: 'column',
      border: '1px solid var(--ink)', background: 'var(--card)',
      overflow: 'hidden',
    }}>
      {/* topo: header com índice + kicker */}
      <div style={{ padding: '8px 14px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid var(--ink)', background: 'var(--paper-soft)' }}>
        <div className="rm-mono" style={{ fontSize: 10, letterSpacing: '0.16em', textTransform: 'uppercase' }}>opção {idx}</div>
        <div className="rm-mono" style={{ fontSize: 10, letterSpacing: '0.14em', color: 'var(--red)', textTransform: 'uppercase' }}>{kicker}</div>
      </div>

      {/* faixa clara: lockup grande */}
      <div style={{ padding: '22px 20px 18px', background: 'var(--paper)', borderBottom: '1px solid var(--rule-soft)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
          <Mark size={56}/>
          <div>
            <div className="rm-display" style={{ fontSize: 30, lineHeight: 0.95, letterSpacing: '-0.01em' }}>{name}</div>
            <div className="rm-mono" style={{ fontSize: 9, letterSpacing: '0.18em', textTransform: 'uppercase', color: 'var(--ink-mid)', marginTop: 4 }}>{sub}</div>
          </div>
        </div>
      </div>

      {/* faixa escura: mark inverso + mark pequeno */}
      <div style={{ padding: '14px 20px', background: 'var(--night)', display: 'flex', alignItems: 'center', gap: 14, justifyContent: 'space-between', borderBottom: '1px solid var(--ink)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <Mark size={32} color="var(--paper)" accent="var(--red)"/>
          <span style={{ fontFamily: 'var(--f-display)', fontSize: 18, color: 'var(--paper)', letterSpacing: '-0.01em' }}>{name}</span>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <Mark size={20} color="var(--paper)" accent="var(--red)"/>
          <Mark size={14} color="var(--paper)" accent="var(--red)"/>
        </div>
      </div>

      {/* notas */}
      <div style={{ padding: '14px 16px', display: 'flex', flexDirection: 'column', gap: 8, flex: 1 }}>
        <div>
          <div className="rm-mono" style={{ fontSize: 9, letterSpacing: '0.16em', textTransform: 'uppercase', color: 'var(--ink-mid)', marginBottom: 4 }}>metáfora</div>
          <div style={{ fontFamily: 'var(--f-serif)', fontSize: 13, lineHeight: 1.4, color: 'var(--ink)' }}>{metaphor}</div>
        </div>
        <div>
          <div className="rm-mono" style={{ fontSize: 9, letterSpacing: '0.16em', textTransform: 'uppercase', color: 'var(--ink-mid)', marginBottom: 4 }}>tom</div>
          <div style={{ fontFamily: 'var(--f-sans)', fontSize: 12, color: 'var(--ink-mid)', fontStyle: 'italic' }}>{voice}</div>
        </div>
      </div>
    </div>
  );
}

// ── Logos artboard ─────────────────────────────────────────────
function ArtLogos() {
  const opts = [
    {
      mark: MarkRadar,
      name: 'radar mensal',
      sub: 'boletim de ideias · mensal',
      kicker: 'baseline · atual',
      metaphor: 'Sinal varrendo o horizonte. Cada mês, novas pontuações no rastro. Conota monitoramento, frequência regular, alcance amplo.',
      voice: 'analítico, agente de inteligência, sereno',
    },
    {
      mark: MarkDossie,
      name: 'dossiê mensal',
      sub: 'casos abertos · um por mês',
      kicker: 'detetive · forte',
      metaphor: 'Pasta de caso com fita vermelha e selo. O produto é literal: cada ideia vira um arquivo. Mais on-brand com a metáfora do app.',
      voice: 'investigador particular, formal, confidencial',
    },
    {
      mark: MarkLupa,
      name: 'a lupa',
      sub: 'investigação mensal · n.º 17',
      kicker: 'detetive · universal',
      metaphor: 'Lupa com alvo vermelho no centro — “achou”. Símbolo mais reconhecível de detetive, lê instantaneamente, funciona em 16px.',
      voice: 'curioso, direto, mão na massa',
    },
    {
      mark: MarkGabinete,
      name: 'gabinete',
      sub: 'arquivos do agente L · maio',
      kicker: 'detetive · noir / pessoal',
      metaphor: 'Selo carimbo com monograma. Sentido de espaço privado, escritório do detetive. Soa como ferramenta interna — combina com uso pessoal.',
      voice: 'noir, lacônico, autoral',
    },
  ];

  return (
    <div style={{ width: 1280, height: 800, background: 'var(--paper)', padding: '36px 44px', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end' }}>
        <div>
          <div className="rm-eyebrow">06 · LOGOMARCA · 4 OPÇÕES</div>
          <div className="rm-display" style={{ fontSize: 44, marginTop: 8 }}>Quem é o detetive?</div>
          <div style={{ fontFamily: 'var(--f-serif)', fontSize: 15, color: 'var(--ink-mid)', fontStyle: 'italic', marginTop: 4, maxWidth: 720 }}>
            Quatro caminhos: o radar (atual, sinal e frequência) vs. três metáforas detetivescas — a pasta de caso, a lupa de campo e o selo do gabinete particular. Mesmo wordmark, marcas diferentes; mesmo sistema de cor.
          </div>
        </div>
        <div className="rm-mono" style={{ fontSize: 10, color: 'var(--ink-mid)', letterSpacing: '0.14em', textTransform: 'uppercase', textAlign: 'right' }}>
          paleta · tinta + carimbo<br/>monograma · serif display
        </div>
      </div>

      <div className="rm-rule-double" style={{ marginTop: 18, marginBottom: 22 }}/>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 14, flex: 1, minHeight: 0 }}>
        {opts.map((o, i) => (
          <LogoCard key={i} idx={String(i + 1).padStart(2, '0')} {...o}/>
        ))}
      </div>
    </div>
  );
}

Object.assign(window, { ArtLogos, MarkRadar, MarkDossie, MarkLupa, MarkGabinete });
