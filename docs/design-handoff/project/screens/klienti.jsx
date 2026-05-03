// CleanMaster — Klienti zoznam + detail
const KlientiList = ({ firma, setFirma }) => {
  const [type, setType] = React.useState('all');
  const [q, setQ] = React.useState('');
  const all = [
    { id:1, name:'Alza.sk s.r.o.', type:'corp', objects: 4, contracts: 3, email:'fakturacia@alza.sk', ico:'45 467 102', firma:'cm-bratislava' },
    { id:2, name:'BENU Slovensko a.s.', type:'corp', objects: 12, contracts: 8, email:'office@benu.sk', ico:'35 763 489', firma:'cm-bratislava' },
    { id:3, name:'JUDr. Eva Horváthová', type:'corp', objects: 1, contracts: 1, email:'horvathova@notar.sk', ico:'48 231 902', firma:'cm-bratislava' },
    { id:4, name:'Peter Varga', type:'private', objects: 1, contracts: 1, email:'p.varga@gmail.com', ico:'—', firma:'cm-bratislava' },
    { id:5, name:'Karola Beauty s.r.o.', type:'corp', objects: 2, contracts: 1, email:'info@karola.sk', ico:'52 340 118', firma:'cm-trnava' },
    { id:6, name:'Účto Plus s.r.o.', type:'corp', objects: 1, contracts: 1, email:'plus@ucto.sk', ico:'47 882 450', firma:'cm-trnava' },
    { id:7, name:'Gastro House s.r.o.', type:'corp', objects: 3, contracts: 2, email:'office@gastrohouse.sk', ico:'46 210 887', firma:'cm-kosice' },
    { id:8, name:'SVB Dunaj 12', type:'corp', objects: 1, contracts: 1, email:'svbdunaj@spravca.sk', ico:'42 119 003', firma:'cm-bratislava' },
    { id:9, name:'Mária Belánska', type:'private', objects: 1, contracts: 0, email:'belanska@centrum.sk', ico:'—', firma:'cm-bratislava' },
    { id:10, name:'Tatry Reality o.z.', type:'corp', objects: 6, contracts: 4, email:'info@tatryreality.sk', ico:'48 902 119', firma:'cm-kosice' },
  ];
  const filtered = all.filter(c => (type==='all'||c.type===type) && (!q || c.name.toLowerCase().includes(q.toLowerCase())));
  return (
    <AppShell active="klienti" firma={firma} onFirma={setFirma}>
      <PageHeader title="Klienti" sub={`${filtered.length} klientov · ${all.filter(c=>c.type==='corp').length} firemných, ${all.filter(c=>c.type==='private').length} súkromných`}
        actions={<>
          <button className="cm-btn cm-btn-secondary"><Icon name="download"/> Export</button>
          <button className="cm-btn cm-btn-primary"><Icon name="plus"/> Pridať klienta</button>
        </>}
      />
      <div className="cm-filters">
        <div className="cm-input-icon"><Icon name="search"/><input className="cm-input" placeholder="Hľadať podľa názvu, IČO, e-mailu…" value={q} onChange={e=>setQ(e.target.value)}/></div>
        <div className="cm-seg">
          <button className={type==='all'?'on':''} onClick={()=>setType('all')}>Všetci</button>
          <button className={type==='corp'?'on':''} onClick={()=>setType('corp')}>Firemní</button>
          <button className={type==='private'?'on':''} onClick={()=>setType('private')}>Súkromní</button>
        </div>
        <select className="cm-select"><option>Všetky firmy</option></select>
      </div>
      <div className="cm-card cm-card-flush">
        <table className="cm-table">
          <thead>
            <tr><th>Klient</th><th>Typ</th><th>IČO</th><th>Objekty</th><th>Aktívne zmluvy</th><th>E-mail</th><th>Firma</th><th></th></tr>
          </thead>
          <tbody>
            {filtered.map(c => (
              <tr key={c.id}>
                <td className="cm-cell-strong">{c.name}</td>
                <td><Badge kind={c.type==='corp'?'primary':'success'}>{c.type==='corp'?'Firemný':'Súkromný'}</Badge></td>
                <td className="cm-mono cm-cell-muted">{c.ico}</td>
                <td><span className="cm-pill-stat">{c.objects}</span></td>
                <td>{c.contracts > 0 ? <span className="cm-pill-stat" style={{background:'var(--success-light)', color:'#15803d'}}>{c.contracts} aktívne</span> : <span style={{color:'var(--n400)', fontSize: 12}}>—</span>}</td>
                <td className="cm-cell-muted">{c.email}</td>
                <td><FirmaTag id={c.firma}/></td>
                <td><Icon name="chevronRight" style={{color:'var(--n400)'}}/></td>
              </tr>
            ))}
          </tbody>
        </table>
        <div style={{padding: '12px var(--pad)', borderTop: '1px solid var(--n200)', display:'flex', justifyContent:'space-between', alignItems:'center', fontSize: 12, color:'var(--n500)'}}>
          <span>Zobrazených {filtered.length} z {all.length}</span>
          <div className="cm-row" style={{gap: 4}}>
            <button className="cm-btn cm-btn-ghost cm-btn-sm" disabled><Icon name="chevronLeft" size={14}/></button>
            <span style={{padding:'0 8px'}}>1 / 1</span>
            <button className="cm-btn cm-btn-ghost cm-btn-sm" disabled><Icon name="chevronRight" size={14}/></button>
          </div>
        </div>
      </div>
    </AppShell>
  );
};

const KlientDetail = ({ firma, setFirma }) => (
  <AppShell active="klienti" firma={firma} onFirma={setFirma}>
    <div style={{display:'flex', alignItems:'center', gap: 8, fontSize: 13, color:'var(--n500)', marginBottom: 12}}>
      <a className="cm-link">Klienti</a> <Icon name="chevronRight" size={12}/> <span>Alza.sk s.r.o.</span>
    </div>
    <PageHeader
      title="Alza.sk s.r.o."
      sub="Zákazník od 12. 3. 2024 · 4 objekty · 3 aktívne zmluvy"
      badges={<><Badge kind="primary">Firemný</Badge> <FirmaTag id="cm-bratislava"/></>}
      actions={<>
        <button className="cm-btn cm-btn-secondary"><Icon name="edit"/> Upraviť</button>
        <button className="cm-btn cm-btn-secondary"><Icon name="file"/> Vytvoriť ponuku</button>
        <button className="cm-btn cm-btn-primary"><Icon name="plus"/> Pridať objekt</button>
        <button className="cm-btn cm-btn-ghost"><Icon name="more"/></button>
      </>}
    />

    <div className="cm-cols">
      <div className="cm-col-stack">
        <div className="cm-card cm-card-flush">
          <div className="cm-card-h"><h3>Objekty <span style={{color:'var(--n400)', fontWeight: 500, fontSize: 13, marginLeft: 6}}>4</span></h3>
            <button className="cm-btn cm-btn-ghost cm-btn-sm"><Icon name="plus"/> Pridať</button>
          </div>
          <table className="cm-table">
            <thead><tr><th>Názov</th><th>Adresa</th><th>Typ</th><th>Aktívna zmluva</th></tr></thead>
            <tbody>
              <tr><td className="cm-cell-strong">Kancelária Hlavná 5</td><td className="cm-cell-muted">Hlavná 5, Bratislava</td><td><Badge>Kancelária</Badge></td><td><Badge kind="success">Áno</Badge></td></tr>
              <tr><td className="cm-cell-strong">Sklad Petržalka</td><td className="cm-cell-muted">Kopčianska 92, Bratislava</td><td><Badge>Sklad</Badge></td><td><Badge kind="success">Áno</Badge></td></tr>
              <tr><td className="cm-cell-strong">Showroom Polus</td><td className="cm-cell-muted">Vajnorská 100, Bratislava</td><td><Badge>Obchod</Badge></td><td><Badge kind="success">Áno</Badge></td></tr>
              <tr><td className="cm-cell-strong">Kancelária Einsteinova</td><td className="cm-cell-muted">Einsteinova 24, Bratislava</td><td><Badge>Kancelária</Badge></td><td><Badge kind="neutral">Draft</Badge></td></tr>
            </tbody>
          </table>
        </div>

        <div className="cm-card cm-card-flush">
          <div className="cm-card-h"><h3>Zmluvy</h3></div>
          <table className="cm-table">
            <thead><tr><th>Číslo</th><th>Objekt</th><th>Platnosť</th><th>Stav</th><th>Mesačne</th></tr></thead>
            <tbody>
              <tr><td className="cm-mono cm-cell-strong">ZML-2024-0042</td><td>Kancelária Hlavná 5</td><td className="cm-cell-muted">1.4.2024 – neurčito</td><td><Badge kind="success">Aktívna</Badge></td><td className="cm-cell-strong">{fmt.eur(640)}</td></tr>
              <tr><td className="cm-mono cm-cell-strong">ZML-2024-0089</td><td>Sklad Petržalka</td><td className="cm-cell-muted">15.6.2024 – neurčito</td><td><Badge kind="success">Aktívna</Badge></td><td className="cm-cell-strong">{fmt.eur(420)}</td></tr>
              <tr><td className="cm-mono cm-cell-strong">ZML-2025-0017</td><td>Showroom Polus</td><td className="cm-cell-muted">1.2.2025 – 31.1.2027</td><td><Badge kind="success">Aktívna</Badge></td><td className="cm-cell-strong">{fmt.eur(890)}</td></tr>
            </tbody>
          </table>
        </div>

        <div className="cm-card cm-card-flush">
          <div className="cm-card-h"><h3>Faktúry</h3><a className="cm-link" style={{fontSize: 12}}>Všetky →</a></div>
          <table className="cm-table">
            <thead><tr><th>Číslo</th><th>Dátum</th><th>Splatnosť</th><th>Suma</th><th>Stav</th></tr></thead>
            <tbody>
              <tr><td className="cm-mono cm-cell-strong">FA-2026-0142</td><td>1.5.2026</td><td>15.5.2026</td><td className="cm-cell-strong">{fmt.eur(1950)}</td><td><Badge kind="primary">Vystavená</Badge></td></tr>
              <tr><td className="cm-mono cm-cell-strong">FA-2026-0098</td><td>1.4.2026</td><td>15.4.2026</td><td className="cm-cell-strong">{fmt.eur(1950)}</td><td><Badge kind="success">Uhradená</Badge></td></tr>
              <tr><td className="cm-mono cm-cell-strong">FA-2026-0051</td><td>1.3.2026</td><td>15.3.2026</td><td className="cm-cell-strong">{fmt.eur(1820)}</td><td><Badge kind="success">Uhradená</Badge></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div className="cm-col-stack">
        <div className="cm-card">
          <h3 style={{marginBottom: 12, fontSize: 15}}>Kontaktné údaje</h3>
          <div className="cm-info-row"><Icon name="mail"/><span className="lab">E-mail</span><a className="cm-link val">fakturacia@alza.sk</a></div>
          <div className="cm-info-row"><Icon name="phone"/><span className="lab">Telefón</span><a className="cm-link val">+421 2 5556 6677</a></div>
          <div className="cm-info-row"><Icon name="mapPin"/><span className="lab">Adresa</span><span className="val">Sliačska 1/A<br/>831 02 Bratislava</span></div>
          <div className="cm-info-row"><Icon name="info"/><span className="lab">IČO</span><span className="val cm-mono">45 467 102</span></div>
          <div className="cm-info-row"><Icon name="info"/><span className="lab">DIČ</span><span className="val cm-mono">2023002419</span></div>
          <div className="cm-info-row"><Icon name="info"/><span className="lab">IČ DPH</span><span className="val cm-mono">SK2023002419</span></div>
        </div>
        <div className="cm-card">
          <h3 style={{marginBottom: 8, fontSize: 15}}>Kontaktná osoba</h3>
          <div style={{display:'flex', gap: 10, alignItems:'center'}}>
            <div className="cm-avatar" style={{background: 'linear-gradient(135deg,#0ea5e9,#0369a1)'}}>JS</div>
            <div>
              <div style={{fontWeight: 600, color: 'var(--n900)'}}>Ing. Jakub Slávik</div>
              <div style={{fontSize: 12, color: 'var(--n500)'}}>Office Manager</div>
            </div>
          </div>
        </div>
        <div className="cm-card">
          <h3 style={{marginBottom: 8, fontSize: 15}}>Poznámka</h3>
          <p style={{fontSize: 13, color: 'var(--n700)', lineHeight: 1.55}}>Klient preferuje upratovanie pred 8:00. Faktúry posielať aj na <span className="cm-mono" style={{fontSize: 12}}>uctovnictvo@alza.sk</span>. V kancelárii Hlavná 5 sa nachádzajú cenné prístroje — opatrnosť pri vysávaní.</p>
        </div>
      </div>
    </div>
  </AppShell>
);

window.KlientiList = KlientiList;
window.KlientDetail = KlientDetail;
