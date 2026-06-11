/* direction-c.jsx — DIRECTION C · MASHUP (manchete + caderno) */
/* Masthead editorial de A + index card & radar live de B */

const C_W = 1280, C_H = 800;

// ── C1 · Radar — manchete + caderno ─────────────────────────────
function ArtCFeed() {
  const [idx, setIdx] = React.useState(1);
  const [drag, setDrag] = React.useState({ x: 0, y: 0, active: false });
  const [committed, setCommitted] = React.useState(null);
  const startRef = React.useRef(null);

  const idea = IDEAS[idx % IDEAS.length];
  const next = IDEAS[(idx + 1) % IDEAS.length];

  const onDown = (e) => { startRef.current = { x: e.clientX, y: e.clientY }; setDrag({ x: 0, y: 0, active: true }); };
  const onMove = (e) => { if (!drag.active) return; setDrag({ active: true, x: e.clientX - startRef.current.x, y: e.clientY - startRef.current.y }); };
  const onUp = () => {
    if (!drag.active) return;
    if (drag.x > 80) { setCommitted('accept'); setTimeout(() => { setIdx(i => i + 1); setCommitted(null); setDrag({ x: 0, y: 0, active: false }); }, 280); }
    else if (drag.x < -80) { setCommitted('reject'); setTimeout(() => { setIdx(i => i + 1); setCommitted(null); setDrag({ x: 0, y: 0, active: false }); }, 280); }
    else setDrag({ x: 0, y: 0, active: false });
  };
  const rotate = drag.x * 0.05;
  const tx = committed === 'accept' ? 600 : committed === 'reject' ? -600 : drag.x;

  return (
    <div style={{ width: C_W, height: C_H, background: 'var(--paper)', padding: '28px 44px 0', display: 'flex', flexDirection: 'column' }}
         onPointerMove={onMove} onPointerUp={onUp} onPointerLeave={onUp}>
      <Masthead active="radar" stats="17 novas · 4 apostas · streak 12d"/>

      <div style={{ display: 'grid', gridTemplateColumns: '240px 1fr 280px', gap: 28, flex: 1, marginTop: 18, minHeight: 0 }}>
        {/* left — fila */}
        <div style={{ overflow: 'hidden' }}>
          <div className="rm-eyebrow">¶ na fila</div>
          <div className="rm-rule" style={{ margin: '8px 0 12px' }}/>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            {IDEAS.slice(1, 5).map((it, i) => (
              <div key={i} style={{ paddingBottom: 10, borderBottom: '1px dotted var(--rule-soft)' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
                  <span className="rm-mono" style={{ fontSize: 9, color: 'var(--ink-mid)', textTransform: 'uppercase', letterSpacing: '0.12em' }}>{it.category}</span>
                  <Stars value={it.stars} size={10}/>
                </div>
                <div style={{ fontFamily: 'var(--f-display)', fontSize: 16, lineHeight: 1.05, marginTop: 4 }}>{it.title}</div>
              </div>
            ))}
            <div className="rm-mono" style={{ fontSize: 10, color: 'var(--ink-soft)', marginTop: 4, textAlign: 'center', textTransform: 'uppercase', letterSpacing: '0.1em' }}>+ 14 ideias</div>
          </div>

          <div style={{ marginTop: 22 }}>
            <div className="rm-eyebrow">filtros</div>
            <div className="rm-rule" style={{ margin: '8px 0 10px' }}/>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              <CheckRow label="estrelas ≥ 3" checked/>
              <CheckRow label="só inéditas no BR" checked/>
              <CheckRow label="esconder saturadas"/>
            </div>
          </div>
        </div>

        {/* center — index card swipe horizontal */}
        <div style={{ position: 'relative', backgroundImage: dotGrid, display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 -10px', borderTop: '1px solid var(--rule-soft)', borderBottom: '1px solid var(--rule-soft)' }}>
          <div className="rm-mono" style={{ position: 'absolute', top: 14, left: 18, fontSize: 10, color: 'var(--ink-mid)', textTransform: 'uppercase', letterSpacing: '0.14em' }}>
            ficha {String(idx + 1).padStart(2, '0')} / 87
          </div>
          <div className="rm-mono" style={{ position: 'absolute', top: 14, right: 18, fontSize: 10, color: 'var(--ink-mid)', textTransform: 'uppercase', letterSpacing: '0.14em' }}>
            ← passa · aposta →
          </div>

          {/* under-card preview */}
          <div style={{ position: 'absolute', width: 420, height: 520, transform: 'translate(-6px, 10px) rotate(-2deg)', opacity: 0.45, pointerEvents: 'none' }}>
            <IndexCard idea={next}/>
          </div>

          <div onPointerDown={onDown}
            style={{
              width: 420, height: 520, position: 'relative', cursor: 'grab', touchAction: 'none',
              transform: `translate(${tx}px, ${drag.y}px) rotate(${rotate}deg)`,
              transition: drag.active ? 'none' : 'transform .25s cubic-bezier(.2,.7,.3,1)',
              userSelect: 'none',
            }}>
            <IndexCard idea={idea}/>

            <div style={{ position: 'absolute', top: 60, left: 24, opacity: Math.max(0, -drag.x / 100), pointerEvents: 'none' }}>
              <Stamp color="var(--ink-mid)" rotate={-14} size={14}>passa</Stamp>
            </div>
            <div style={{ position: 'absolute', top: 60, right: 24, opacity: Math.max(0, drag.x / 100), pointerEvents: 'none' }}>
              <Stamp rotate={10} size={14}>aposta!</Stamp>
            </div>
          </div>

          <div style={{ position: 'absolute', bottom: 18, left: '50%', transform: 'translateX(-50%)', display: 'flex', gap: 10 }}>
            <KeyBtn label="←" sub="passa"/>
            <KeyBtn label="◇" sub="anota"/>
            <KeyBtn label="→" sub="aposta" emphasis/>
          </div>
        </div>

        {/* right — radar live */}
        <div style={{ background: 'var(--night)', color: 'var(--night-text)', padding: '20px 22px', margin: '0 -8px 0 0', display: 'flex', flexDirection: 'column' }}>
          <div className="rm-mono" style={{ fontSize: 10, letterSpacing: '0.14em', textTransform: 'uppercase', opacity: 0.55, marginBottom: 12 }}>
            ◉ radar ao vivo
          </div>
          <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 12 }}>
            <RadarAnim size={150}/>
          </div>

          <div style={{ borderTop: '1px solid var(--night-rule)', paddingTop: 10, marginBottom: 12 }}>
            <div className="rm-mono" style={{ fontSize: 9, opacity: 0.5, textTransform: 'uppercase', letterSpacing: '0.16em' }}>próx. varredura</div>
            <div className="rm-display" style={{ fontSize: 28, color: 'var(--night-text)', marginTop: 2 }}>02 : 47 : 12</div>
          </div>

          <div className="rm-mono" style={{ fontSize: 10, textTransform: 'uppercase', letterSpacing: '0.12em', opacity: 0.85, marginBottom: 6 }}>captura recente</div>
          <div style={{ fontFamily: 'var(--f-mono)', fontSize: 11, lineHeight: 1.8, flex: 1 }}>
            <LiveRow t="14:02" s="producthunt · 3"/>
            <LiveRow t="13:58" s="reddit · 1"/>
            <LiveRow t="13:41" s="indiehackers · 2"/>
            <LiveRow t="13:20" s="podcast · transcrito"/>
          </div>

          <div style={{ borderTop: '1px solid var(--night-rule)', paddingTop: 10 }}>
            <div className="rm-mono" style={{ fontSize: 9, opacity: 0.5, textTransform: 'uppercase', letterSpacing: '0.16em' }}>streak</div>
            <div style={{ display: 'flex', alignItems: 'baseline', gap: 6, marginTop: 2 }}>
              <span className="rm-display" style={{ fontSize: 32, color: 'var(--red)' }}>12d</span>
              <span className="rm-mono" style={{ fontSize: 10, opacity: 0.6 }}>seguidos no rádio</span>
            </div>
            <div style={{ display: 'flex', gap: 3, marginTop: 6 }}>
              {Array.from({ length: 14 }).map((_, i) => (
                <div key={i} style={{ width: 11, height: 11, background: i < 12 ? 'var(--red)' : 'transparent', border: '1px solid var(--night-rule)' }}/>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

// ── C2 · Pipeline — manchete editorial + mesa de trabalho ──────
function ArtCKanban() {
  const cols = [
    { id: 'radar', label: 'I · RADAR', sub: 'caiu agora', n: 12, ideas: [IDEAS[0], IDEAS[3]] },
    { id: 'val',   label: 'II · VALIDANDO', sub: 'investigação · TAM', n: 5, ideas: [IDEAS[1], IDEAS[4]] },
    { id: 'aposta',label: 'III · APOSTA', sub: 'pipeline real', n: 3, ideas: [IDEAS[2]] },
    { id: 'build', label: 'IV · BUILD', sub: 'MVP rolando', n: 1, ideas: [] },
  ];
  return (
    <div style={{ width: C_W, height: C_H, background: 'var(--paper)', padding: '28px 44px 0', display: 'flex', flexDirection: 'column' }}>
      <Masthead active="kanban" stats="mesa · 21 fichas"/>

      <div style={{ flex: 1, backgroundImage: dotGrid, margin: '18px -16px 0', padding: '22px 28px 24px', borderTop: '1px solid var(--rule-soft)', display: 'flex', flexDirection: 'column', minHeight: 0 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 18 }}>
          <div>
            <div className="rm-display" style={{ fontSize: 32 }}>mesa de trabalho · 21 fichas</div>
            <div className="rm-mono" style={{ fontSize: 11, color: 'var(--ink-mid)', textTransform: 'uppercase', letterSpacing: '0.12em', marginTop: 2 }}>arraste para mover entre colunas</div>
          </div>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <div className="rm-mono" style={{ fontSize: 10, padding: '6px 10px', border: '1px solid var(--ink)', background: 'var(--card)', textTransform: 'uppercase', letterSpacing: '0.14em' }}>★ ≥ 3</div>
            <div className="rm-mono" style={{ fontSize: 10, padding: '6px 10px', border: '1px solid var(--rule-soft)', textTransform: 'uppercase', letterSpacing: '0.14em', color: 'var(--ink-mid)' }}>setor: todos</div>
            <div className="rm-mono" style={{ fontSize: 10, padding: '6px 10px', border: '1px dashed var(--ink-mid)', textTransform: 'uppercase', letterSpacing: '0.14em', color: 'var(--ink-mid)' }}>+ filtro</div>
          </div>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 14, flex: 1, minHeight: 0 }}>
          {cols.map((c) => (
            <div key={c.id} style={{ background: 'rgba(251,246,232,0.6)', border: '1px solid var(--ink)', padding: 14, display: 'flex', flexDirection: 'column', minHeight: 0 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 4 }}>
                <div className="rm-mono" style={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.14em', textTransform: 'uppercase' }}>{c.label}</div>
                <div style={{ width: 22, height: 22, borderRadius: '50%', background: 'var(--ink)', color: 'var(--paper)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontFamily: 'var(--f-mono)', fontSize: 11 }}>{c.n}</div>
              </div>
              <div className="rm-mono" style={{ fontSize: 9, color: 'var(--ink-mid)', textTransform: 'uppercase', letterSpacing: '0.14em', marginBottom: 14 }}>{c.sub}</div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 12, flex: 1, minHeight: 0 }}>
                {c.ideas.map((it, j) => <PinCard key={j} idea={it} rotate={0}/>)}
                {c.ideas.length === 0 && (
                  <div style={{ border: '1.5px dashed var(--ink-soft)', padding: 18, fontFamily: 'var(--f-mono)', fontSize: 11, color: 'var(--ink-soft)', textTransform: 'uppercase', letterSpacing: '0.14em', textAlign: 'center' }}>
                    nenhuma ficha
                  </div>
                )}
              </div>
              <div style={{ borderTop: '1px dotted var(--rule-soft)', marginTop: 12, paddingTop: 10, textAlign: 'center', fontFamily: 'var(--f-mono)', fontSize: 10, color: 'var(--ink-mid)', textTransform: 'uppercase', letterSpacing: '0.14em' }}>+ nova ficha</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

// ── C3 · Dossiê — manchete + pasta ──────────────────────────────
function ArtCDetail() {
  const idea = IDEAS[1];
  return (
    <div style={{ width: C_W, height: C_H, background: 'var(--paper)', padding: '28px 44px 0', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
      <Masthead active="dossie" stats="dossiê · cardapio-ia"/>

      <div style={{ flex: 1, marginTop: 22, backgroundImage: dotGrid, margin: '22px -16px 0', padding: '32px 28px 16px', borderTop: '1px solid var(--rule-soft)', position: 'relative', minHeight: 0 }}>
        {/* manila folder */}
        <div style={{ position: 'relative', background: 'var(--paper)', border: '1px solid var(--ink)', boxShadow: '6px 6px 0 rgba(26,22,18,0.18)', height: '100%' }}>
          <div style={{ position: 'absolute', top: -22, left: 22, background: 'var(--gold)', color: 'var(--ink)', padding: '4px 18px 6px', fontFamily: 'var(--f-mono)', fontSize: 11, letterSpacing: '0.18em', textTransform: 'uppercase', border: '1px solid var(--ink)', borderBottom: 'none' }}>
            dossiê 003 · foodtech
          </div>
          <div style={{ position: 'absolute', top: -28, right: 28 }}>
            <Stamp size={12} rotate={-6}>aposta</Stamp>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1.3fr 1fr', height: '100%' }}>
            <div style={{ padding: '24px 28px', borderRight: '1px solid var(--rule-soft)', overflow: 'hidden' }}>
              <div className="rm-mono" style={{ fontSize: 10, color: 'var(--red)', letterSpacing: '0.16em', textTransform: 'uppercase' }}>foodtech · IA · cardapio-ia</div>
              <div className="rm-display" style={{ fontSize: 44, lineHeight: 0.97, letterSpacing: '-0.015em', marginTop: 10 }}>
                {idea.title}
              </div>
              <div style={{ fontFamily: 'var(--f-serif)', fontSize: 16, lineHeight: 1.5, color: 'var(--ink-mid)', marginTop: 12, fontStyle: 'italic' }}>
                {idea.summary}
              </div>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 14 }}>
                {idea.tags.map(t => <Tag key={t} label={t} size="lg"/>)}
              </div>

              <div className="rm-rule" style={{ marginTop: 18, marginBottom: 14 }}/>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', columnGap: 22, rowGap: 12, fontFamily: 'var(--f-serif)', fontSize: 13, lineHeight: 1.55, color: 'var(--ink)' }}>
                <div>
                  <div className="rm-eyebrow" style={{ marginBottom: 4 }}>contexto</div>
                  <p style={{ margin: 0 }}>QR cardápio virou padrão. Sobrou um nicho: bares e barracas de rua, cardápio na mão. <span style={{ background: 'var(--highlight)' }}>nada localizado pra cá.</span></p>
                </div>
                <div>
                  <div className="rm-eyebrow" style={{ marginBottom: 4 }}>modelo</div>
                  <p style={{ margin: 0 }}>R$ 29/mês por loja. Free tier 1 cardápio. Take rate 3% em pedidos.</p>
                </div>
                <div>
                  <div className="rm-eyebrow" style={{ marginBottom: 4 }}>concorrência</div>
                  <p style={{ margin: 0 }}>Goomer / Anota AI — pesados pra barraca de pastel.</p>
                </div>
                <div>
                  <div className="rm-eyebrow" style={{ marginBottom: 4 }}>distribuição</div>
                  <p style={{ margin: 0 }}>Bairro a bairro. Olinda, Recife, JP. Catador local.</p>
                </div>
              </div>
            </div>

            <div style={{ padding: '24px 26px', overflow: 'hidden', display: 'flex', flexDirection: 'column', gap: 14 }}>
              <Placeholder label="capa do produto" h={170}/>
              <div>
                <div className="rm-eyebrow" style={{ marginBottom: 8 }}>números de campo</div>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                  <Mini n="R$ 2,1bi" l="TAM Brasil"/>
                  <Mini n="700k" l="bares informais"/>
                  <Mini n="≤R$30k" l="investimento"/>
                  <Mini n="2sem" l="MVP"/>
                </div>
              </div>
              <div>
                <div className="rm-eyebrow" style={{ marginBottom: 8 }}>histórico</div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 2, fontFamily: 'var(--f-mono)', fontSize: 11 }}>
                  <Log d="11 abr" e="radar → entrou"/>
                  <Log d="14 abr" e="validando · concorrentes"/>
                  <Log d="22 abr" e={<span style={{ color: 'var(--red)' }}>marcada · APOSTA</span>}/>
                </div>
              </div>
              <div style={{ background: '#f6e8b8', border: '1px solid var(--gold)', padding: 10, marginTop: 'auto' }}>
                <div className="rm-mono" style={{ fontSize: 9, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'var(--gold)' }}>anotação à mão</div>
                <div style={{ fontFamily: 'var(--f-serif)', fontSize: 12, fontStyle: 'italic', color: 'var(--ink)', marginTop: 4 }}>
                  “validar com 5 bares em recife antes do build.”
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

// ── C4 · Painel — gabinete (estilo B4) com manchete editorial ──
function ArtCDashboard() {
  return (
    <div style={{ width: C_W, height: C_H, background: 'var(--paper)', padding: '28px 44px 0', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
      <Masthead active="painel" stats="gabinete · maio · encerrado 31/05"/>

      <div style={{ flex: 1, backgroundImage: dotGrid, margin: '18px -16px 0', padding: '24px 28px 24px', borderTop: '1px solid var(--rule-soft)', display: 'flex', flexDirection: 'column', gap: 18, minHeight: 0 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
          <div>
            <div className="rm-mono" style={{ fontSize: 11, color: 'var(--ink-mid)', textTransform: 'uppercase', letterSpacing: '0.16em' }}>gabinete · diário do agente</div>
            <div className="rm-display" style={{ fontSize: 42, marginTop: 6 }}>edição n.º 17 · maio</div>
          </div>
          <Stamp size={12} rotate={-4}>encerrado 31/05</Stamp>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(6, 1fr)', gap: 12 }}>
          <Mini n="87" l="ideias passadas"/>
          <Mini n="34" l="anotadas"/>
          <Mini n="14" l="apostas"/>
          <Mini n="61%" l="descartadas"/>
          <Mini n="12d" l="streak"/>
          <Mini n="5" l="fontes ativas"/>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 18, flex: 1, minHeight: 0 }}>
          <Box title="calendário do mês">
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 4 }}>
              {Array.from({ length: 31 }).map((_, i) => {
                const v = [3,5,2,0,4,7,8, 6,3,5,9,2,1,0, 4,6,8,5,3,2,7, 8,6,4,9,5,3,2, 6,4,7][i] || 0;
                return (
                  <div key={i} title={`dia ${i+1} · ${v} ideias`} style={{ aspectRatio: '1', background: v === 0 ? 'transparent' : `rgba(200,85,61,${Math.min(0.15 + v * 0.1, 0.9)})`, border: '1px solid var(--rule-soft)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontFamily: 'var(--f-mono)', fontSize: 10, color: v > 5 ? 'var(--paper)' : 'var(--ink-mid)' }}>
                    {i + 1}
                  </div>
                );
              })}
            </div>
            <div className="rm-mono" style={{ fontSize: 9, color: 'var(--ink-mid)', textTransform: 'uppercase', letterSpacing: '0.12em', marginTop: 10, display: 'flex', justifyContent: 'space-between' }}>
              <span>1° de maio</span><span>+ ideias = + escuro</span><span>31</span>
            </div>
          </Box>

          <Box title="por setor">
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              {[
                { l: 'fintech', v: 22, c: 'var(--red)' },
                { l: 'foodtech', v: 18, c: 'var(--blue)' },
                { l: 'beauty', v: 12, c: 'var(--gold)' },
                { l: 'comércio', v: 14, c: 'var(--green)' },
                { l: 'saúde', v: 9, c: 'var(--ink)' },
                { l: 'outros', v: 12, c: 'var(--ink-mid)' },
              ].map((r, i) => (
                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <span className="rm-mono" style={{ fontSize: 10, width: 70, textTransform: 'uppercase', letterSpacing: '0.1em' }}>{r.l}</span>
                  <div style={{ flex: 1, height: 14, background: 'var(--paper-soft)', border: '1px solid var(--rule-soft)' }}>
                    <div style={{ height: '100%', width: (r.v / 22 * 100) + '%', background: r.c }}/>
                  </div>
                  <span className="rm-mono" style={{ fontSize: 11, width: 24, textAlign: 'right' }}>{r.v}</span>
                </div>
              ))}
            </div>
            <div style={{ marginTop: 12, paddingTop: 10, borderTop: '1px dotted var(--rule-soft)', background: '#f6e8b8', border: '1px solid var(--gold)', padding: 10, marginLeft: -2, marginRight: -2 }}>
              <div className="rm-mono" style={{ fontSize: 9, color: 'var(--gold)', letterSpacing: '0.14em', textTransform: 'uppercase' }}>tendência</div>
              <div style={{ fontFamily: 'var(--f-serif)', fontSize: 12, color: 'var(--ink)', marginTop: 4, lineHeight: 1.4 }}>
                PIX antecipado virou padrão em barbearia, condomínio, lavanderia.
              </div>
            </div>
          </Box>

          <Box title="apostas do mês">
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              {IDEAS.slice(0, 4).map((it, i) => (
                <div key={i} style={{ display: 'flex', gap: 10, alignItems: 'flex-start', paddingBottom: 8, borderBottom: '1px dotted var(--rule-soft)' }}>
                  <span className="rm-mono" style={{ fontSize: 10, color: 'var(--red)', width: 18 }}>0{i + 1}</span>
                  <div style={{ flex: 1 }}>
                    <div style={{ fontFamily: 'var(--f-display)', fontSize: 16, lineHeight: 1.1 }}>{it.title}</div>
                    <div className="rm-mono" style={{ fontSize: 9, color: 'var(--ink-mid)', textTransform: 'uppercase', letterSpacing: '0.1em', marginTop: 3 }}>{it.category}</div>
                  </div>
                  <Stars value={it.stars} size={11}/>
                </div>
              ))}
            </div>
          </Box>
        </div>
      </div>
    </div>
  );
}

// ── C5 · Mobile mashup ──────────────────────────────────────────
function ArtCMobile() {
  const idea = IDEAS[1];
  return (
    <div style={{ width: 380, height: 780, background: 'var(--paper)', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
      <div style={{ padding: '14px 20px 6px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <span className="rm-mono" style={{ fontSize: 12, fontWeight: 600 }}>9:41</span>
        <span className="rm-mono" style={{ fontSize: 10, letterSpacing: '0.1em' }}>● ●● ▮▮▮</span>
      </div>
      <div style={{ padding: '4px 20px 12px', borderBottom: '3px double var(--ink)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <RadarMark size={24}/>
          <div className="rm-display" style={{ fontSize: 28, letterSpacing: '-0.015em' }}>radar mensal</div>
        </div>
        <div className="rm-mono" style={{ fontSize: 9, letterSpacing: '0.16em', textTransform: 'uppercase', color: 'var(--ink-mid)', marginTop: 4 }}>n.º 17 · 17 novas · streak 12d</div>
      </div>

      <div style={{ flex: 1, padding: 14, backgroundImage: dotGrid, display: 'flex', flexDirection: 'column', justifyContent: 'center', position: 'relative' }}>
        <div className="rm-mono" style={{ position: 'absolute', top: 10, left: 14, fontSize: 9, color: 'var(--ink-mid)', textTransform: 'uppercase', letterSpacing: '0.14em' }}>
          ← passa · aposta →
        </div>
        <div className="rm-mono" style={{ position: 'absolute', top: 10, right: 14, fontSize: 9, color: 'var(--ink-mid)' }}>02 / 87</div>

        <div style={{ transform: 'rotate(-1deg)', boxShadow: '4px 4px 0 var(--ink)' }}>
          <IndexCard idea={idea}/>
        </div>

        <div style={{ display: 'flex', justifyContent: 'space-around', marginTop: 18 }}>
          <KeyBtn label="←" sub="passa"/>
          <KeyBtn label="◇" sub="anota"/>
          <KeyBtn label="→" sub="aposta" emphasis/>
        </div>
      </div>

      <div style={{ borderTop: '1px solid var(--ink)', padding: '10px 20px', display: 'flex', justifyContent: 'space-around', alignItems: 'center', background: 'var(--card)' }}>
        {[['◉', 'radar'], ['▤', 'pipe'], ['❑', 'dossiê'], ['☼', 'painel']].map(([i, l], k) => (
          <div key={k} style={{ textAlign: 'center', color: k === 0 ? 'var(--red)' : 'var(--ink-mid)' }}>
            <div className="rm-mono" style={{ fontSize: 16 }}>{i}</div>
            <div className="rm-mono" style={{ fontSize: 9, letterSpacing: '0.12em', textTransform: 'uppercase', marginTop: 2 }}>{l}</div>
          </div>
        ))}
      </div>
    </div>
  );
}

Object.assign(window, { ArtCFeed, ArtCKanban, ArtCDetail, ArtCDashboard, ArtCMobile });
