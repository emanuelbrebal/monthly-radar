/* foundations.jsx — Brand, paleta, tipografia, voz, componentes */

// ── Brand artboard ──────────────────────────────────────────────
function ArtBrand() {
  return (
    <div style={{ width: '100%', height: '100%', background: 'var(--paper)', padding: '40px 44px', display: 'flex', flexDirection: 'column', gap: 28 }}>
      <div className="rm-eyebrow">001 · MARCA</div>
      <div style={{ display: 'flex', alignItems: 'flex-end', gap: 24 }}>
        <RadarMark size={88}/>
        <div>
          <div className="rm-display" style={{ fontSize: 64, letterSpacing: '-0.02em' }}>radar mensal</div>
          <div className="rm-mono" style={{ fontSize: 11, letterSpacing: '0.18em', textTransform: 'uppercase', color: 'var(--ink-mid)', marginTop: 6 }}>
            edição n.º 17 · maio · MMXXVI
          </div>
        </div>
      </div>

      <div className="rm-rule" style={{ marginTop: 4 }}/>
      <div style={{ fontFamily: 'var(--f-serif)', fontSize: 18, lineHeight: 1.45, color: 'var(--ink-mid)', maxWidth: 540 }}>
        Um boletim de ideias. Toda manhã, dezenas de oportunidades caem aqui — scrapadas da web, dos podcasts, dos repositórios. Você passa por elas como quem folheia o jornal: aceita, rejeita, fricciona, arquiva.
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 24, marginTop: 'auto' }}>
        <Pillar n="i" title="Filtrar é o trabalho" body="Ler mil ideias para encontrar três. O produto reduz fricção do descarte."/>
        <Pillar n="ii" title="Brasileiro de origem" body="Lente: o que cabe no Nordeste? O que rola no SaaS pra cá?"/>
        <Pillar n="iii" title="Hábito mensal" body="Não é diário, não é anual. Ritmo de revista, não de Twitter."/>
      </div>
    </div>
  );
}
function Pillar({ n, title, body }) {
  return (
    <div>
      <div className="rm-mono" style={{ fontSize: 11, color: 'var(--red)', textTransform: 'uppercase', letterSpacing: '0.15em' }}>{n}.</div>
      <div className="rm-display" style={{ fontSize: 22, marginTop: 4, marginBottom: 6 }}>{title}</div>
      <div style={{ fontFamily: 'var(--f-sans)', fontSize: 13, lineHeight: 1.5, color: 'var(--ink-mid)' }}>{body}</div>
    </div>
  );
}

// ── Palette ─────────────────────────────────────────────────────
function ArtPalette() {
  const groups = [
    {
      name: 'Papel · base',
      swatches: [
        { name: 'Papel',       hex: '#f3ebda', token: '--paper' },
        { name: 'Papel suave', hex: '#ede2cb', token: '--paper-soft' },
        { name: 'Papel fundo', hex: '#e3d6b9', token: '--paper-deep' },
        { name: 'Card',        hex: '#fbf6e8', token: '--card' },
      ],
    },
    {
      name: 'Tinta · texto',
      swatches: [
        { name: 'Tinta',       hex: '#1a1612', token: '--ink' },
        { name: 'Tinta média', hex: '#5a544c', token: '--ink-mid' },
        { name: 'Tinta clara', hex: '#8a8175', token: '--ink-soft' },
      ],
    },
    {
      name: 'Acentos · semânticos',
      swatches: [
        { name: 'Vermelho carimbo', hex: '#c8553d', token: '--red',   role: 'aceitar · destaque' },
        { name: 'Verde validado',   hex: '#4a7c3d', token: '--green', role: 'tag positiva' },
        { name: 'Azul nota',        hex: '#2a4b6e', token: '--blue',  role: 'info · meta' },
        { name: 'Ouro grifo',       hex: '#b08a3e', token: '--gold',  role: 'tag neutra · estrelas' },
      ],
    },
    {
      name: 'Noite · console / radar',
      swatches: [
        { name: 'Noite',     hex: '#14110d', token: '--night' },
        { name: 'Noite 2',   hex: '#221c14', token: '--night-card' },
        { name: 'Texto',     hex: '#f0e8d4', token: '--night-text' },
        { name: 'Texto mid', hex: '#b7ad94', token: '--night-text-mid' },
      ],
    },
  ];
  return (
    <div style={{ width: '100%', height: '100%', background: 'var(--paper)', padding: '40px 44px', overflow: 'hidden' }}>
      <div className="rm-eyebrow">002 · PALETA</div>
      <div className="rm-display" style={{ fontSize: 38, marginTop: 8, marginBottom: 20 }}>Cores</div>
      <div className="rm-rule-double" style={{ marginBottom: 22 }}/>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 28 }}>
        {groups.map((g, i) => (
          <div key={i}>
            <div className="rm-mono" style={{ fontSize: 10, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'var(--ink-mid)', marginBottom: 10 }}>{g.name}</div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
              {g.swatches.map((s, j) => (
                <div key={j} style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                  <div style={{ width: 44, height: 44, background: s.hex, border: '1px solid var(--rule-soft)' }}/>
                  <div style={{ flex: 1 }}>
                    <div style={{ fontFamily: 'var(--f-sans)', fontSize: 13, fontWeight: 500 }}>{s.name}</div>
                    <div className="rm-mono" style={{ fontSize: 10, color: 'var(--ink-soft)' }}>{s.hex} · {s.token}{s.role ? ' · ' + s.role : ''}</div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

// ── Typography ──────────────────────────────────────────────────
function ArtType() {
  return (
    <div style={{ width: '100%', height: '100%', background: 'var(--paper)', padding: '40px 44px', overflow: 'hidden' }}>
      <div className="rm-eyebrow">003 · TIPOGRAFIA</div>
      <div className="rm-display" style={{ fontSize: 38, marginTop: 8, marginBottom: 14 }}>Pareamento</div>
      <div className="rm-rule-double" style={{ marginBottom: 22 }}/>

      <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 32 }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
          <Spec label="Display · Instrument Serif" sample="Boletim de ideias" font="var(--f-display)" size={56} note="manchete, números grandes, capa de seção"/>
          <Spec label="Serif · Newsreader" sample="Reportagens & cabeçalhos longos." font="var(--f-serif)" size={20} note="sub-manchete, citações, copy editorial"/>
          <Spec label="Sans · Inter Tight" sample="Corpo de texto e botões — leitura limpa em UI." font="var(--f-sans)" size={15} weight={500} note="body, labels, navegação"/>
          <Spec label="Mono · JetBrains Mono" sample="DATA · TAG · STATUS · 04/17" font="var(--f-mono)" size={12} note="eyebrows, tags, dados tabulares"/>
        </div>
        <div>
          <div className="rm-mono" style={{ fontSize: 10, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'var(--ink-mid)', marginBottom: 10 }}>Escala</div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4, fontFamily: 'var(--f-mono)', fontSize: 11, color: 'var(--ink-mid)' }}>
            {[
              ['display-xl', 72], ['display-l', 48], ['display-m', 32], ['title', 24],
              ['body-l', 17], ['body', 15], ['body-s', 13], ['caption', 11],
            ].map(([n, px]) => (
              <div key={n} style={{ display: 'flex', alignItems: 'baseline', gap: 12, padding: '4px 0', borderBottom: '1px dotted var(--rule-soft)' }}>
                <span style={{ width: 80 }}>{n}</span>
                <span style={{ width: 36, textAlign: 'right' }}>{px}px</span>
                <span style={{ fontFamily: n.startsWith('display') ? 'var(--f-display)' : 'var(--f-sans)', fontSize: Math.min(px, 28), color: 'var(--ink)', flex: 1 }}>Aa</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
function Spec({ label, sample, font, size, weight, note }) {
  return (
    <div style={{ borderTop: '1px solid var(--rule-soft)', paddingTop: 10 }}>
      <div className="rm-mono" style={{ fontSize: 10, letterSpacing: '0.12em', textTransform: 'uppercase', color: 'var(--ink-mid)', marginBottom: 6 }}>{label}</div>
      <div style={{ fontFamily: font, fontSize: size, fontWeight: weight || 400, lineHeight: 1.05, color: 'var(--ink)' }}>{sample}</div>
      <div style={{ fontFamily: 'var(--f-sans)', fontSize: 11, color: 'var(--ink-soft)', marginTop: 6 }}>uso: {note}</div>
    </div>
  );
}

// ── Voice & tone ───────────────────────────────────────────────
function ArtVoice() {
  const pairs = [
    { yes: 'Boletim do mês. 87 ideias pra você passar o olho.', no: 'Welcome back! Discover new opportunities today 🚀' },
    { yes: 'Ideia mais ou menos. Anota e vamos pra próxima.', no: 'This idea did not match your preferences.' },
    { yes: 'Aposta arquivada. Bora pra investigação.', no: 'Success! Item added to your kanban board.' },
    { yes: 'Nada novo no radar hoje. Volta amanhã.', no: 'No results found. Try changing your filters.' },
  ];
  return (
    <div style={{ width: '100%', height: '100%', background: 'var(--paper)', padding: '40px 44px', overflow: 'hidden' }}>
      <div className="rm-eyebrow">004 · VOZ</div>
      <div className="rm-display" style={{ fontSize: 38, marginTop: 8, marginBottom: 14 }}>Tom de quem lê jornal</div>
      <div className="rm-rule-double" style={{ marginBottom: 22 }}/>

      <div style={{ fontFamily: 'var(--f-serif)', fontSize: 17, lineHeight: 1.5, color: 'var(--ink-mid)', maxWidth: 560, marginBottom: 22 }}>
        Brevidade editorial. Voz brasileira, coloquial mas seca. Nunca corporativo. Frases curtas, verbos no presente. Cita números quando souber. Usa <em>aposta</em>, <em>radar</em>, <em>edição</em>, <em>boletim</em>, <em>manchete</em>, <em>dossiê</em>.
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, columnGap: 32 }}>
        <div className="rm-mono" style={{ fontSize: 10, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'var(--green)' }}>Fala assim ↓</div>
        <div className="rm-mono" style={{ fontSize: 10, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'var(--red)' }}>Nunca assim ↓</div>
        {pairs.map((p, i) => (
          <React.Fragment key={i}>
            <div style={{ fontFamily: 'var(--f-serif)', fontSize: 16, lineHeight: 1.4, padding: '10px 0', borderTop: '1px solid var(--rule-soft)' }}>“{p.yes}”</div>
            <div style={{ fontFamily: 'var(--f-sans)', fontSize: 14, lineHeight: 1.4, padding: '10px 0', borderTop: '1px solid var(--rule-soft)', color: 'var(--ink-soft)', textDecoration: 'line-through' }}>“{p.no}”</div>
          </React.Fragment>
        ))}
      </div>
    </div>
  );
}

// ── Badge / Tag system ─────────────────────────────────────────
function ArtTags() {
  const groups = [
    { title: 'Positivas', items: ['bem tropicalizado', 'inédita no brasil', 'poucos concorrentes', 'boa recepção do público', 'TAM grande', 'ideia boa'] },
    { title: 'Neutras / ressalvas', items: ['ideia boa de melhorar', 'ideia mais ou menos', 'difícil distribuição'] },
    { title: 'Vermelhas', items: ['regulação pesada', 'mercado saturado', 'ideia muito comum'] },
  ];
  return (
    <div style={{ width: '100%', height: '100%', background: 'var(--paper)', padding: '40px 44px', overflow: 'hidden' }}>
      <div className="rm-eyebrow">005 · TAGS</div>
      <div className="rm-display" style={{ fontSize: 38, marginTop: 8, marginBottom: 14 }}>Sistema de etiquetas</div>
      <div className="rm-rule-double" style={{ marginBottom: 22 }}/>
      <div style={{ fontFamily: 'var(--f-serif)', fontSize: 15, color: 'var(--ink-mid)', marginBottom: 22, maxWidth: 520 }}>
        Cada ideia recebe entre 1 e 4 tags automáticas. Verde, amarela ou vermelha — leitura instantânea.
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
        {groups.map((g, i) => (
          <div key={i}>
            <div className="rm-mono" style={{ fontSize: 10, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'var(--ink-mid)', marginBottom: 10 }}>{g.title}</div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
              {g.items.map((t) => <Tag key={t} label={t} size="lg"/>)}
            </div>
          </div>
        ))}
      </div>
      <div style={{ marginTop: 24, paddingTop: 18, borderTop: '1px solid var(--rule-soft)', display: 'flex', alignItems: 'center', gap: 24 }}>
        <div>
          <div className="rm-mono" style={{ fontSize: 10, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'var(--ink-mid)', marginBottom: 8 }}>Filtro estrelas</div>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <Stars value={3} size={18}/>
            <span className="rm-mono" style={{ fontSize: 11, color: 'var(--ink-mid)' }}>≥ 3</span>
          </div>
        </div>
        <div>
          <div className="rm-mono" style={{ fontSize: 10, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'var(--ink-mid)', marginBottom: 8 }}>Carimbo</div>
          <span className="rm-stamp" style={{ fontSize: 11 }}>aposta</span>
        </div>
      </div>
    </div>
  );
}

// ── Component anatomy ──────────────────────────────────────────
function ArtAnatomy() {
  return (
    <div style={{ width: '100%', height: '100%', background: 'var(--paper)', padding: '40px 44px', overflow: 'hidden' }}>
      <div className="rm-eyebrow">006 · ANATOMIA</div>
      <div className="rm-display" style={{ fontSize: 38, marginTop: 8, marginBottom: 14 }}>Card de ideia</div>
      <div className="rm-rule-double" style={{ marginBottom: 22 }}/>

      <div style={{ display: 'grid', gridTemplateColumns: '320px 1fr', gap: 36 }}>
        {/* the actual card with callouts */}
        <div style={{ position: 'relative' }}>
          <div style={{ background: 'var(--card)', border: '1px solid var(--rule)', padding: 18 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
              <div className="rm-mono" style={{ fontSize: 10, color: 'var(--ink-mid)', letterSpacing: '0.14em', textTransform: 'uppercase' }}>fintech · social</div>
              <Stars value={4} size={13}/>
            </div>
            <Placeholder label="screenshot · app de divisão" h={120}/>
            <div className="rm-display" style={{ fontSize: 22, lineHeight: 1.05, marginTop: 12 }}>Divisor de despesas para repúblicas universitárias</div>
            <div style={{ fontFamily: 'var(--f-serif)', fontSize: 13, color: 'var(--ink-mid)', marginTop: 8, lineHeight: 1.4 }}>Tipo Splitwise, mas otimizado para repúblicas com 6+ pessoas...</div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4, marginTop: 12 }}>
              <Tag label="bem tropicalizado"/>
              <Tag label="poucos concorrentes"/>
            </div>
          </div>
          <Callout n="A" top={-6} left={-30} label="categoria · monoespaçado, alto contraste"/>
          <Callout n="B" top={-6} right={-12} label="estrelas · score 1–5"/>
          <Callout n="C" top={86} left={-30} label="screenshot · proporção 16:9, placeholder"/>
          <Callout n="D" top={180} right={-12} label="manchete · serifa display"/>
          <Callout n="E" bottom={-6} left={-30} label="tags · 1–4 max"/>
        </div>
        <div>
          <div className="rm-mono" style={{ fontSize: 10, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'var(--ink-mid)', marginBottom: 12 }}>Espaçamento</div>
          <div style={{ fontFamily: 'var(--f-mono)', fontSize: 11, color: 'var(--ink-mid)', lineHeight: 1.9 }}>
            <div>padding: 18px (card)</div>
            <div>gap entre seções: 12px</div>
            <div>gap entre tags: 4px</div>
            <div>imagem 16:9 · radius 0</div>
            <div>borda: 1px solid #1a1612</div>
          </div>
          <div className="rm-mono" style={{ fontSize: 10, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'var(--ink-mid)', margin: '20px 0 12px' }}>Estado</div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 6, fontFamily: 'var(--f-sans)', fontSize: 12, color: 'var(--ink-mid)' }}>
            <Row k="default" v="bg #fbf6e8 / border 1px ink"/>
            <Row k="hover" v="bg #fff / sombra 0 4 16 rgba ink"/>
            <Row k="swipe →" v="translate +40deg / red overlay"/>
            <Row k="swipe ←" v="translate -40deg / fade"/>
            <Row k="aceita" v="carimbo APOSTA aplicado"/>
          </div>
        </div>
      </div>
    </div>
  );
}
function Callout({ n, label, top, left, right, bottom }) {
  return (
    <div style={{ position: 'absolute', top, left, right, bottom, display: 'flex', alignItems: 'center', gap: 6 }}>
      <span style={{ width: 18, height: 18, borderRadius: '50%', border: '1px solid var(--red)', color: 'var(--red)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontFamily: 'var(--f-mono)', fontSize: 10, background: 'var(--paper)' }}>{n}</span>
      <span className="rm-mono" style={{ fontSize: 9, color: 'var(--ink-mid)', textTransform: 'uppercase', letterSpacing: '0.1em', whiteSpace: 'nowrap' }}>{label}</span>
    </div>
  );
}
function Row({ k, v }) {
  return (
    <div style={{ display: 'flex', gap: 12, borderBottom: '1px dotted var(--rule-soft)', paddingBottom: 4 }}>
      <span className="rm-mono" style={{ fontSize: 10, color: 'var(--red)', width: 70 }}>{k}</span>
      <span style={{ flex: 1 }}>{v}</span>
    </div>
  );
}

Object.assign(window, { ArtBrand, ArtPalette, ArtType, ArtVoice, ArtTags, ArtAnatomy });
