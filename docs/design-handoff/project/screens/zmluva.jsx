// CleanMaster — Zmluva detail s tabmi
const ZmluvaDetail = ({ firma, setFirma }) => {
  const [tab, setTab] = React.useState('prehlad');
  return (
    <AppShell active="zmluvy" firma={firma} onFirma={setFirma}>
      <div style={{display:'flex', alignItems:'center', gap: 8, fontSize: 13, color:'var(--n500)', marginBottom: 12}}>
        <a className="cm-link">Zmluvy</a> <Icon name="chevronRight" size={12}/> <span className="cm-mono">ZML-2024-0042</span>
      </div>
      <PageHeader
        title={<span className="cm-mono">ZML-2024-0042</span>}
        sub="Alza.sk s.r.o. · Kancelária Hlavná 5"
        badges={<><Badge kind="success">Aktívna</Badge> <Badge kind="neutral">Na dobu neurčitú</Badge></>}
        actions={<>
          <button className="cm-btn cm-btn-secondary"><Icon name="edit"/> Upraviť</button>
          <button className="cm-btn cm-btn-secondary"><Icon name="clock"/> Predĺžiť</button>
          <button className="cm-btn cm-btn-primary"><Icon name="receipt"/> Vytvoriť faktúru</button>
          <button className="cm-btn cm-btn-ghost"><Icon name="more"/></button>
        </>}
      />

      <div className="cm-tabs">
        {[
          {id:'prehlad',l:'Prehľad'},
          {id:'faktury',l:'Faktúry',c:13},
          {id:'zakazky',l:'Zákazky',c:124},
          {id:'log',l:'História zmien',c:8},
        ].map(t => (
          <div key={t.id} className={'cm-tab' + (tab===t.id?' on':'')} onClick={()=>setTab(t.id)}>
            {t.l} {t.c?<span style={{marginLeft: 4, padding:'1px 6px', background: tab===t.id?'var(--primary-light)':'var(--n100)', color: tab===t.id?'var(--primary-dark)':'var(--n500)', borderRadius: 8, fontSize: 10}}>{t.c}</span>:null}
          </div>
        ))}
      </div>

      {tab==='prehlad' && (
        <div className="cm-cols">
          <div className="cm-col-stack">
            <div className="cm-card">
              <h3 style={{fontSize: 15, marginBottom: 14}}>Informácie o zmluve</h3>
              <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap: 24}}>
                <div>
                  <div className="cm-info-row"><span className="lab">Klient</span><a className="cm-link val">Alza.sk s.r.o.</a></div>
                  <div className="cm-info-row"><span className="lab">Objekt</span><a className="cm-link val">Kancelária Hlavná 5</a></div>
                  <div className="cm-info-row"><span className="lab">Adresa</span><span className="val">Hlavná 5, 811 01 BA</span></div>
                  <div className="cm-info-row"><span className="lab">Zdroj</span><a className="cm-link val cm-mono">CP-2024-0089</a></div>
                </div>
                <div>
                  <div className="cm-info-row"><span className="lab">Typ zmluvy</span><span className="val">Na dobu neurčitú</span></div>
                  <div className="cm-info-row"><span className="lab">Platnosť od</span><span className="val">1. 4. 2024</span></div>
                  <div className="cm-info-row"><span className="lab">Výpoveď</span><span className="val">3 mesiace</span></div>
                  <div className="cm-info-row"><span className="lab">Mesačne</span><span className="val cm-mono cm-cell-strong" style={{color: 'var(--primary)'}}>{fmt.eur(640)}</span></div>
                </div>
              </div>
            </div>

            <div className="cm-card cm-card-flush">
              <div className="cm-card-h"><h3>Rozpis prác</h3>
                <span className="cm-pill-stat">5 služieb · 3×/týždeň</span>
              </div>
              <table className="cm-table">
                <thead><tr><th>Služba</th><th>Popis</th><th>Frekvencia</th></tr></thead>
                <tbody>
                  <tr><td className="cm-cell-strong">Vysávanie</td><td>Všetky podlahové plochy a koberce</td><td><Badge kind="primary">3×/týždeň</Badge></td></tr>
                  <tr><td className="cm-cell-strong">Umývanie podláh</td><td>Tvrdé podlahy mokrou cestou</td><td><Badge kind="primary">3×/týždeň</Badge></td></tr>
                  <tr><td className="cm-cell-strong">Utieranie prachu</td><td>Stoly, parapety, police do 180 cm</td><td><Badge kind="primary">3×/týždeň</Badge></td></tr>
                  <tr><td className="cm-cell-strong">Sociálne zariadenia</td><td>Dezinfekcia, doplnenie spotrebáku</td><td><Badge kind="primary">3×/týždeň</Badge></td></tr>
                  <tr><td className="cm-cell-strong">Umývanie okien</td><td>Obojstranné, vrátane rámov</td><td><Badge kind="warning">1×/štvrťrok</Badge></td></tr>
                </tbody>
              </table>
            </div>

            <div className="cm-card cm-card-flush">
              <div className="cm-card-h"><h3>Priradené upratovačky</h3>
                <button className="cm-btn cm-btn-ghost cm-btn-sm"><Icon name="plus"/> Priradiť</button>
              </div>
              <table className="cm-table">
                <thead><tr><th>Meno</th><th>Deň/čas</th><th>Stav</th></tr></thead>
                <tbody>
                  <tr><td><div style={{display:'flex', alignItems:'center', gap: 8}}><div className="cm-avatar" style={{width:28, height:28, fontSize:11, background:'linear-gradient(135deg,#f97316,#c2410c)'}}>AN</div><span className="cm-cell-strong">Anna Novotná</span></div></td><td>Po, St, Pia · 07:30 – 09:30</td><td><Badge kind="success">Aktívna</Badge></td></tr>
                  <tr><td><div style={{display:'flex', alignItems:'center', gap: 8}}><div className="cm-avatar" style={{width:28, height:28, fontSize:11, background:'linear-gradient(135deg,#a855f7,#7e22ce)'}}>MK</div><span className="cm-cell-strong">Mária Kollárová</span></div></td><td>Záskok</td><td><Badge kind="neutral">Záskok</Badge></td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <div className="cm-col-stack">
            <div className="cm-card">
              <h3 style={{fontSize: 14, marginBottom: 12}}>Stav zmluvy</h3>
              <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap: 12, marginBottom: 14}}>
                <div style={{padding: 12, background: 'var(--success-light)', borderRadius: 8}}>
                  <div style={{fontSize: 11, color: '#15803d', fontWeight: 600, textTransform:'uppercase'}}>Trvanie</div>
                  <div style={{fontSize: 22, fontWeight: 700, color: '#14532d', marginTop: 2}}>2r 1m</div>
                </div>
                <div style={{padding: 12, background: 'var(--primary-light)', borderRadius: 8}}>
                  <div style={{fontSize: 11, color: 'var(--primary-dark)', fontWeight: 600, textTransform:'uppercase'}}>Faktúr</div>
                  <div style={{fontSize: 22, fontWeight: 700, color: '#1e3a8a', marginTop: 2}}>13</div>
                </div>
              </div>
              <div style={{padding: 12, background: 'var(--n50)', borderRadius: 8, fontSize: 12, color: 'var(--n600)'}}>
                <div style={{display:'flex', justifyContent:'space-between', marginBottom: 4}}><span>Celkom fakturované</span><span className="cm-mono cm-cell-strong">{fmt.eur(8320)}</span></div>
                <div style={{display:'flex', justifyContent:'space-between'}}><span>Posledná faktúra</span><span>1. 5. 2026</span></div>
              </div>
            </div>

            <div className="cm-card">
              <h3 style={{fontSize: 14, marginBottom: 10}}>Príloha</h3>
              <div style={{display:'flex', alignItems:'center', gap: 10, padding: 10, background: 'var(--n50)', borderRadius: 8, border: '1px solid var(--n200)'}}>
                <div style={{width: 36, height: 36, background: 'var(--danger-light)', color: 'var(--danger)', borderRadius: 6, display:'flex', alignItems:'center', justifyContent:'center'}}><Icon name="pdf" size={18}/></div>
                <div style={{flex: 1, minWidth: 0}}>
                  <div className="cm-cell-strong cm-truncate" style={{fontSize: 13}}>ZML-2024-0042-podpisana.pdf</div>
                  <div style={{fontSize: 11, color: 'var(--n500)'}}>2,4 MB · Nahrané 3.4.2024</div>
                </div>
                <div className="cm-iconbtn"><Icon name="download" size={14}/></div>
              </div>
            </div>
          </div>
        </div>
      )}

      {tab==='log' && (
        <div className="cm-card">
          <div className="cm-timeline">
            <div className="cm-tl-item"><div className="cm-tl-dot create"></div>
              <div className="cm-tl-meta">3. 4. 2024 · 09:14 · Mária Kováčová</div>
              <div className="cm-tl-title">Zmluva vytvorená a aktivovaná</div>
              <div className="cm-tl-detail">Z cenovej ponuky <a className="cm-link cm-mono">CP-2024-0089</a></div>
            </div>
            <div className="cm-tl-item"><div className="cm-tl-dot edit"></div>
              <div className="cm-tl-meta">15. 8. 2024 · 11:42 · Mária Kováčová</div>
              <div className="cm-tl-title">Pridaná upratovačka</div>
              <div className="cm-tl-detail">Anna Novotná — pravidelný režim Po, St, Pia</div>
            </div>
            <div className="cm-tl-item"><div className="cm-tl-dot edit"></div>
              <div className="cm-tl-meta">10. 1. 2025 · 14:08 · Peter Hrašnik</div>
              <div className="cm-tl-title">Úprava rozpisu prác</div>
              <div className="cm-tl-detail">Frekvencia umývania okien: <span className="old">2×/rok</span> → <span className="new">1×/štvrťrok</span></div>
            </div>
            <div className="cm-tl-item"><div className="cm-tl-dot extend"></div>
              <div className="cm-tl-meta">1. 4. 2025 · 08:30 · Mária Kováčová</div>
              <div className="cm-tl-title">Mesačná suma upravená</div>
              <div className="cm-tl-detail">Mesačná suma: <span className="old">580 €</span> → <span className="new">640 €</span> (inflačná úprava 10,3 %)</div>
            </div>
            <div className="cm-tl-item"><div className="cm-tl-dot edit"></div>
              <div className="cm-tl-meta">12. 11. 2025 · 16:22 · Peter Hrašnik</div>
              <div className="cm-tl-title">Pridaná záskoková upratovačka</div>
              <div className="cm-tl-detail">Mária Kollárová ako záskok</div>
            </div>
            <div className="cm-tl-item"><div className="cm-tl-dot edit"></div>
              <div className="cm-tl-meta">22. 4. 2026 · 10:01 · Mária Kováčová</div>
              <div className="cm-tl-title">Aktualizácia kontaktnej osoby</div>
              <div className="cm-tl-detail">Kontakt: <span className="old">Lucia Demská</span> → <span className="new">Ing. Jakub Slávik</span></div>
            </div>
          </div>
        </div>
      )}

      {tab==='faktury' && (
        <div className="cm-card cm-card-flush">
          <table className="cm-table">
            <thead><tr><th>Číslo</th><th>Obdobie</th><th>Vystavená</th><th>Splatnosť</th><th>Suma</th><th>Stav</th></tr></thead>
            <tbody>
              <tr><td className="cm-mono cm-cell-strong">FA-2026-0142</td><td>Apríl 2026</td><td>1.5.2026</td><td>15.5.2026</td><td className="cm-mono cm-cell-strong">{fmt.eur(640)}</td><td><Badge kind="primary">Vystavená</Badge></td></tr>
              <tr><td className="cm-mono cm-cell-strong">FA-2026-0098</td><td>Marec 2026</td><td>1.4.2026</td><td>15.4.2026</td><td className="cm-mono cm-cell-strong">{fmt.eur(640)}</td><td><Badge kind="success">Uhradená</Badge></td></tr>
              <tr><td className="cm-mono cm-cell-strong">FA-2026-0051</td><td>Február 2026</td><td>1.3.2026</td><td>15.3.2026</td><td className="cm-mono cm-cell-strong">{fmt.eur(640)}</td><td><Badge kind="success">Uhradená</Badge></td></tr>
              <tr><td className="cm-mono cm-cell-strong">FA-2026-0014</td><td>Január 2026</td><td>1.2.2026</td><td>15.2.2026</td><td className="cm-mono cm-cell-strong">{fmt.eur(640)}</td><td><Badge kind="success">Uhradená</Badge></td></tr>
            </tbody>
          </table>
        </div>
      )}

      {tab==='zakazky' && (
        <div className="cm-card cm-card-flush">
          <table className="cm-table">
            <thead><tr><th>Dátum</th><th>Čas</th><th>Upratovačka</th><th>Check-in</th><th>Check-out</th><th>Stav</th></tr></thead>
            <tbody>
              <tr><td className="cm-cell-strong">4. 5. 2026 (Po)</td><td className="cm-mono">07:30</td><td>Anna Novotná</td><td className="cm-mono">07:32</td><td className="cm-mono">09:18</td><td><Badge kind="success">Dokončená</Badge></td></tr>
              <tr><td className="cm-cell-strong">2. 5. 2026 (Pia)</td><td className="cm-mono">07:30</td><td>Anna Novotná</td><td className="cm-mono">07:28</td><td className="cm-mono">09:24</td><td><Badge kind="success">Dokončená</Badge></td></tr>
              <tr><td className="cm-cell-strong">29. 4. 2026 (St)</td><td className="cm-mono">07:30</td><td>Mária Kollárová</td><td className="cm-mono">07:35</td><td className="cm-mono">09:42</td><td><Badge kind="success">Dokončená</Badge></td></tr>
              <tr><td className="cm-cell-strong">27. 4. 2026 (Po)</td><td className="cm-mono">07:30</td><td>Anna Novotná</td><td className="cm-mono">07:30</td><td className="cm-mono">09:15</td><td><Badge kind="success">Dokončená</Badge></td></tr>
            </tbody>
          </table>
        </div>
      )}
    </AppShell>
  );
};
window.ZmluvaDetail = ZmluvaDetail;
