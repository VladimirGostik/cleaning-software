// CleanMaster — Faktúry: vytvoriť + detail
const InvoiceCreate = ({ firma, setFirma }) => {
  const lines = [
    { d: 'Pravidelné upratovanie kancelárií — Apríl 2026', q: 1, u: 'mes', p: 640 },
    { d: 'Mimoriadne upratovanie po stavebných prácach', q: 4, u: 'hod', p: 22 },
    { d: 'Umývanie okien (Q1)', q: 85, u: 'm²', p: 2.5 },
  ];
  const sub = lines.reduce((s,l)=>s+l.q*l.p,0);
  const dph = sub*0.23;
  const unbilled = [
    { d: '4.4.2026', obj: 'Kancelária Hlavná 5', t: 'Pravidelná', s: 320, sel: true },
    { d: '11.4.2026', obj: 'Kancelária Hlavná 5', t: 'Pravidelná', s: 320, sel: true },
    { d: '15.4.2026', obj: 'Kancelária Hlavná 5', t: 'Špeciálna', s: 88, sel: true },
    { d: '22.4.2026', obj: 'Sklad Petržalka', t: 'Pravidelná', s: 180, sel: false },
  ];
  return (
    <AppShell active="faktury" firma={firma} onFirma={setFirma}>
      <div style={{display:'flex', alignItems:'center', gap: 8, fontSize: 13, color:'var(--n500)', marginBottom: 12}}>
        <a className="cm-link">Faktúry</a> <Icon name="chevronRight" size={12}/> <span>Nová</span>
      </div>
      <PageHeader title="Nová faktúra"
        actions={<>
          <button className="cm-btn cm-btn-secondary">Zrušiť</button>
          <button className="cm-btn cm-btn-secondary">Uložiť ako draft</button>
          <button className="cm-btn cm-btn-primary"><Icon name="check"/> Vystaviť</button>
        </>}/>
      <div style={{display:'grid', gridTemplateColumns:'1fr 320px', gap: 'var(--gap)'}}>
        <div className="cm-col-stack">
          <div className="cm-card">
            <h3 style={{marginBottom: 14, fontSize: 15}}>Základné údaje</h3>
            <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap: 12}}>
              <div className="cm-field"><label>Číslo faktúry</label><input className="cm-input cm-mono" defaultValue="FA-2026-0152"/></div>
              <div className="cm-field"><label>Typ<span className="cm-req">*</span></label>
                <div className="cm-seg" style={{height: 36, alignItems:'center'}}>
                  <button className="on">Mesačná</button><button>Jednorazová</button><button>Špeciálna</button>
                </div>
              </div>
              <div className="cm-field"><label>Klient<span className="cm-req">*</span></label>
                <div className="cm-input-icon"><Icon name="search"/><input className="cm-input" defaultValue="Alza.sk s.r.o." style={{paddingLeft: 34}}/></div>
              </div>
              <div className="cm-field"><label>Objekt</label><select className="cm-select"><option>Kancelária Hlavná 5</option></select></div>
              <div className="cm-field"><label>Obdobie od – do</label><input className="cm-input" defaultValue="1.4.2026 – 30.4.2026"/></div>
              <div className="cm-field"><label>Dátum vystavenia<span className="cm-req">*</span></label><input className="cm-input" defaultValue="4.5.2026"/></div>
              <div className="cm-field"><label>Splatnosť<span className="cm-req">*</span></label><input className="cm-input" defaultValue="18.5.2026"/></div>
              <div className="cm-field"><label>VS / KS</label><input className="cm-input cm-mono" defaultValue="20260152 / 0308"/></div>
            </div>
          </div>

          <div className="cm-card" style={{background: 'linear-gradient(135deg, #FFFBEB, #FEF3C7)', borderColor:'#FDE68A'}}>
            <div style={{display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom: 10}}>
              <h3 style={{fontSize: 15, color: '#78350F'}}>Nefakturované zákazky pre tohto klienta</h3>
              <span style={{fontSize: 12, color: '#92400E', fontWeight: 600}}>3 vybrané · {fmt.eur(728)}</span>
            </div>
            <p style={{fontSize: 12, color: '#78350F', marginBottom: 12}}>Zaškrtnite zákazky ktoré chcete zahrnúť do faktúry.</p>
            <table className="cm-table" style={{background: 'rgba(255,255,255,.5)', borderRadius: 6, overflow:'hidden'}}>
              <thead><tr><th style={{width: 32}}></th><th>Dátum</th><th>Objekt</th><th>Typ</th><th style={{textAlign:'right'}}>Suma</th></tr></thead>
              <tbody>
                {unbilled.map((u,i) => (
                  <tr key={i}><td><span className={'cm-checkbox'+(u.sel?' on':'')}><Icon name="check" size={11}/></span></td><td className="cm-cell-strong">{u.d}</td><td>{u.obj}</td><td><Badge kind="primary">{u.t}</Badge></td><td className="cm-mono cm-cell-strong" style={{textAlign:'right'}}>{fmt.eur(u.s)}</td></tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="cm-card">
            <h3 style={{marginBottom: 14, fontSize: 15}}>Položky faktúry</h3>
            <table className="cm-lines">
              <thead><tr><th>Popis</th><th style={{width: 80}}>Množ.</th><th style={{width: 80}}>Jedn.</th><th style={{width: 100}}>Cena/j.</th><th style={{width: 100, textAlign:'right'}}>Suma</th><th style={{width: 32}}></th></tr></thead>
              <tbody>
                {lines.map((l,i)=>(
                  <tr key={i}><td><input className="cm-input" defaultValue={l.d}/></td><td><input className="cm-input cm-mono" defaultValue={l.q} style={{textAlign:'right'}}/></td><td><input className="cm-input" defaultValue={l.u}/></td><td><input className="cm-input cm-mono" defaultValue={l.p.toFixed(2)} style={{textAlign:'right'}}/></td><td className="cm-mono cm-cell-strong" style={{textAlign:'right'}}>{fmt.eur(l.q*l.p)}</td><td><div className="cm-line-rm"><Icon name="x" size={14}/></div></td></tr>
                ))}
              </tbody>
            </table>
            <div className="cm-line-add"><Icon name="plus" size={14}/> Pridať položku</div>
            <div className="cm-summary" style={{marginTop: 10}}>
              <div className="cm-summary-row"><span>Suma bez DPH</span><span className="cm-mono">{fmt.eur(sub)}</span></div>
              <div className="cm-summary-row cm-cell-muted"><span>DPH 23 %</span><span className="cm-mono">{fmt.eur(dph)}</span></div>
              <div className="cm-summary-row total"><span>Spolu</span><span className="cm-mono">{fmt.eur(sub+dph)}</span></div>
            </div>
          </div>
        </div>
        <div className="cm-col-stack">
          <div className="cm-card" style={{position:'sticky', top: 16}}>
            <h3 style={{fontSize: 14, marginBottom: 12}}>Náhľad</h3>
            <div className="cm-invoice-doc" style={{padding: 14, fontSize: 11, transform:'scale(.9)', transformOrigin:'top'}}>
              <div className="num">FA-2026-0152</div>
              <div className="lab2">Faktúra · daňový doklad</div>
              <div style={{marginTop: 12, paddingTop: 10, borderTop: '1px solid var(--n200)'}}>
                <div style={{display:'flex', justifyContent:'space-between'}}><span style={{fontSize:10,color:'var(--n500)'}}>Dátum</span><span>4. 5. 2026</span></div>
                <div style={{display:'flex', justifyContent:'space-between'}}><span style={{fontSize:10,color:'var(--n500)'}}>Splatnosť</span><span>18. 5. 2026</span></div>
              </div>
              <div style={{marginTop: 10, paddingTop: 10, borderTop: '1px solid var(--n200)', display:'flex', justifyContent:'space-between', fontWeight: 700, color:'var(--n900)', fontSize: 13}}>
                <span>Spolu s DPH</span><span className="cm-mono">{fmt.eur(sub+dph)}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppShell>
  );
};

const InvoiceDetail = ({ firma, setFirma }) => (
  <AppShell active="faktury" firma={firma} onFirma={setFirma}>
    <div style={{display:'flex', alignItems:'center', gap: 8, fontSize: 13, color:'var(--n500)', marginBottom: 12}}>
      <a className="cm-link">Faktúry</a> <Icon name="chevronRight" size={12}/> <span className="cm-mono">FA-2026-0142</span>
    </div>
    <PageHeader
      title={<span className="cm-mono">FA-2026-0142</span>}
      sub="Alza.sk s.r.o. · Apríl 2026"
      badges={<><Badge kind="primary">Vystavená</Badge> <Badge kind="neutral">Mesačná</Badge></>}
      actions={<>
        <button className="cm-btn cm-btn-secondary"><Icon name="download"/> PDF</button>
        <button className="cm-btn cm-btn-primary"><Icon name="mail"/> Odoslať e-mailom</button>
        <button className="cm-btn cm-btn-ghost"><Icon name="more"/></button>
      </>}/>
    <div className="cm-cols">
      <div className="cm-card">
        <div className="cm-invoice-doc" style={{border:'none', padding: 0}}>
          <div className="head">
            <div>
              <div className="num">FA-2026-0142</div>
              <div className="lab2">Faktúra · daňový doklad</div>
              <div style={{marginTop: 16}}>
                <div className="lab2">Dodávateľ</div>
                <h4>CleanMaster Bratislava s.r.o.</h4>
                <address>Sliačska 1, 831 02 Bratislava<br/>IČO: 52 119 803 · DIČ: 2120998112<br/>IČ DPH: SK2120998112</address>
              </div>
            </div>
            <div>
              <div style={{textAlign:'right'}}>
                <div className="qr" style={{marginLeft:'auto'}}></div>
                <div style={{fontSize: 10, color:'var(--n500)', marginTop: 4}}>Pay by Square</div>
              </div>
              <div style={{marginTop: 12, textAlign:'right'}}>
                <div className="lab2">Odberateľ</div>
                <h4>Alza.sk s.r.o.</h4>
                <address>Sliačska 1/A, 831 02 Bratislava<br/>IČO: 45 467 102 · DIČ: 2023002419<br/>IČ DPH: SK2023002419</address>
              </div>
            </div>
          </div>
          <div style={{display:'grid', gridTemplateColumns:'repeat(4,1fr)', gap: 8, marginBottom: 18, fontSize: 12}}>
            <div><div className="lab2">Dátum vystavenia</div><div style={{fontWeight:600}}>1. 5. 2026</div></div>
            <div><div className="lab2">Dátum dodania</div><div style={{fontWeight:600}}>30. 4. 2026</div></div>
            <div><div className="lab2">Splatnosť</div><div style={{fontWeight:600}}>15. 5. 2026</div></div>
            <div><div className="lab2">Forma úhrady</div><div style={{fontWeight:600}}>Prevodom</div></div>
          </div>
          <table className="cm-lines">
            <thead><tr><th>Popis</th><th style={{textAlign:'right'}}>Množ.</th><th>Jedn.</th><th style={{textAlign:'right'}}>Cena/j.</th><th style={{textAlign:'right'}}>Suma</th></tr></thead>
            <tbody>
              <tr><td><strong>Pravidelné upratovanie kancelárií</strong><br/><span style={{color:'var(--n500)', fontSize: 11}}>Hlavná 5, 811 01 BA · Apríl 2026 · 13 zákaziek</span></td><td className="cm-mono" style={{textAlign:'right'}}>1</td><td>mes</td><td className="cm-mono" style={{textAlign:'right'}}>520,33</td><td className="cm-mono cm-cell-strong" style={{textAlign:'right'}}>{fmt.eur(520.33)}</td></tr>
              <tr><td><strong>Umývanie okien Q2</strong><br/><span style={{color:'var(--n500)', fontSize: 11}}>Obojstranné, vrátane rámov</span></td><td className="cm-mono" style={{textAlign:'right'}}>85</td><td>m²</td><td className="cm-mono" style={{textAlign:'right'}}>2,50</td><td className="cm-mono cm-cell-strong" style={{textAlign:'right'}}>{fmt.eur(212.5)}</td></tr>
              <tr><td><strong>Mimoriadne upratovanie 15.4.</strong><br/><span style={{color:'var(--n500)', fontSize: 11}}>Po stavebných prácach</span></td><td className="cm-mono" style={{textAlign:'right'}}>4</td><td>hod</td><td className="cm-mono" style={{textAlign:'right'}}>22,00</td><td className="cm-mono cm-cell-strong" style={{textAlign:'right'}}>{fmt.eur(88)}</td></tr>
            </tbody>
          </table>
          <div className="cm-summary">
            <div className="cm-summary-row"><span>Suma bez DPH</span><span className="cm-mono">{fmt.eur(820.83)}</span></div>
            <div className="cm-summary-row cm-cell-muted"><span>DPH 23 %</span><span className="cm-mono">{fmt.eur(188.79)}</span></div>
            <div className="cm-summary-row total"><span>Spolu k úhrade</span><span className="cm-mono">{fmt.eur(1009.62)}</span></div>
          </div>
          <div className="pay-block">
            <div className="qr"></div>
            <div style={{fontSize: 12}}>
              <div style={{display:'grid', gridTemplateColumns:'auto 1fr', gap:'2px 12px'}}>
                <span className="cm-muted">IBAN</span><span className="cm-mono cm-cell-strong">SK21 1100 0000 0029 4012 6783</span>
                <span className="cm-muted">SWIFT</span><span className="cm-mono">TATRSKBX</span>
                <span className="cm-muted">VS</span><span className="cm-mono">20260142</span>
                <span className="cm-muted">KS</span><span className="cm-mono">0308</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div className="cm-col-stack">
        <div className="cm-card">
          <h3 style={{fontSize: 14, marginBottom: 10}}>Akcie</h3>
          <div className="cm-stack" style={{gap: 6}}>
            <button className="cm-btn cm-btn-secondary" style={{justifyContent:'center'}}><Icon name="download"/> Stiahnuť PDF</button>
            <button className="cm-btn cm-btn-secondary" style={{justifyContent:'center'}}><Icon name="mail"/> Odoslať klientovi</button>
            <button className="cm-btn cm-btn-secondary" style={{justifyContent:'center'}}><Icon name="check"/> Označiť ako uhradenú</button>
            <button className="cm-btn cm-btn-ghost" style={{justifyContent:'center', color: 'var(--danger)'}}><Icon name="x"/> Stornovať</button>
          </div>
        </div>
        <div className="cm-card">
          <h3 style={{fontSize: 14, marginBottom: 10}}>Prepojenia</h3>
          <div className="cm-info-row"><span className="lab">Klient</span><a className="cm-link val">Alza.sk s.r.o.</a></div>
          <div className="cm-info-row"><span className="lab">Objekt</span><a className="cm-link val">Kancelária Hlavná 5</a></div>
          <div className="cm-info-row"><span className="lab">Zmluva</span><a className="cm-link val cm-mono">ZML-2024-0042</a></div>
          <div className="cm-info-row"><span className="lab">Zákaziek</span><span className="val">14 spojených</span></div>
        </div>
        <div className="cm-card">
          <h3 style={{fontSize: 14, marginBottom: 10}}>Aktivita</h3>
          <div className="cm-timeline">
            <div className="cm-tl-item"><div className="cm-tl-dot create"></div>
              <div className="cm-tl-meta">1. 5. 2026 · 09:02</div>
              <div className="cm-tl-title">Vystavená automaticky</div>
              <div className="cm-tl-detail">Cron · mesačná fakturácia</div>
            </div>
            <div className="cm-tl-item"><div className="cm-tl-dot edit"></div>
              <div className="cm-tl-meta">1. 5. 2026 · 09:03</div>
              <div className="cm-tl-title">E-mail odoslaný</div>
              <div className="cm-tl-detail">Na fakturacia@alza.sk</div>
            </div>
            <div className="cm-tl-item"><div className="cm-tl-dot edit"></div>
              <div className="cm-tl-meta">3. 5. 2026 · 11:24</div>
              <div className="cm-tl-title">PDF zobrazené</div>
              <div className="cm-tl-detail">Klient otvoril odkaz</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppShell>
);

window.InvoiceCreate = InvoiceCreate;
window.InvoiceDetail = InvoiceDetail;
