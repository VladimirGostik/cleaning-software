// CleanMaster — Zamestnanec detail s oprávneniami
const ZamestnanecDetail = ({ firma, setFirma }) => {
  const modules = [
    { name: 'Klienti', actions: ['zobraziť','vytvoriť','upraviť','zmazať'], on: ['zobraziť','vytvoriť','upraviť'] },
    { name: 'Objekty', actions: ['zobraziť','vytvoriť','upraviť','zmazať'], on: ['zobraziť','vytvoriť','upraviť'] },
    { name: 'Cenové ponuky', actions: ['zobraziť','vytvoriť','upraviť','odoslať'], on: ['zobraziť','vytvoriť','upraviť','odoslať'] },
    { name: 'Zmluvy', actions: ['zobraziť','vytvoriť','upraviť','ukončiť'], on: ['zobraziť','vytvoriť'] },
    { name: 'Rozvrh', actions: ['zobraziť','vytvoriť','upraviť','priradiť'], on: ['zobraziť','vytvoriť','upraviť','priradiť'] },
    { name: 'Zamestnanci', actions: ['zobraziť','vytvoriť','upraviť','priradiť'], on: ['zobraziť','priradiť'] },
    { name: 'Faktúry', actions: ['zobraziť','vytvoriť','vystaviť','stornovať'], on: ['zobraziť'] },
    { name: 'Šablóny', actions: ['zobraziť','nahrať','zmazať'], on: ['zobraziť'] },
  ];
  return (
    <AppShell active="zamestnanci" firma={firma} onFirma={setFirma}>
      <div style={{display:'flex', alignItems:'center', gap: 8, fontSize: 13, color:'var(--n500)', marginBottom: 12}}>
        <a className="cm-link">Zamestnanci</a> <Icon name="chevronRight" size={12}/> <span>Petra Hrašníková</span>
      </div>
      <PageHeader
        title={
          <span style={{display:'flex', alignItems:'center', gap: 14}}>
            <div className="cm-avatar" style={{width: 48, height: 48, fontSize: 18, background:'linear-gradient(135deg,#06b6d4,#0e7490)'}}>PH</div>
            <span>Petra Hrašníková</span>
          </span>
        }
        sub="petra.hrasnikova@cleanmaster.sk · +421 905 112 334"
        badges={<><Badge kind="primary">Vedúca</Badge> <Badge kind="success">Aktívna</Badge> <FirmaTag id="cm-bratislava"/></>}
        actions={<>
          <button className="cm-btn cm-btn-secondary"><Icon name="edit"/> Upraviť</button>
          <button className="cm-btn cm-btn-secondary"><Icon name="shield"/> Upraviť oprávnenia</button>
          <button className="cm-btn cm-btn-ghost"><Icon name="more"/></button>
        </>}
      />

      <div className="cm-cols">
        <div className="cm-col-stack">
          <div className="cm-card">
            <div style={{display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom: 12}}>
              <h3 style={{fontSize: 15}}>Oprávnenia v rámci firmy</h3>
              <button className="cm-btn cm-btn-ghost cm-btn-sm">Obnoviť default Vedúca</button>
            </div>
            <div className="cm-perms">
              {modules.map((m,i) => (
                <div key={i} className="cm-perm-row">
                  <div className="lab">{m.name}</div>
                  <div className="cm-perm-actions">
                    {m.actions.map(a => (
                      <span key={a} className={'cm-perm-pill ' + (m.on.includes(a)?'on':'off')}>
                        {m.on.includes(a) && <Icon name="check"/>}
                        {a}
                      </span>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="cm-card cm-card-flush">
            <div className="cm-card-h"><h3>Priradené objekty <span style={{color:'var(--n400)', fontWeight: 500, fontSize: 13, marginLeft: 6}}>5</span></h3></div>
            <table className="cm-table">
              <thead><tr><th>Objekt</th><th>Klient</th><th>Deň/čas</th><th>Rola</th></tr></thead>
              <tbody>
                <tr><td className="cm-cell-strong">Kancelária Hlavná 5</td><td className="cm-cell-muted">Alza.sk</td><td>Po, St, Pia · 07:30</td><td><Badge kind="primary">Vedúca</Badge></td></tr>
                <tr><td className="cm-cell-strong">Sklad Petržalka</td><td className="cm-cell-muted">Alza.sk</td><td>Ut, Št · 08:00</td><td><Badge kind="primary">Vedúca</Badge></td></tr>
                <tr><td className="cm-cell-strong">Notársky úrad Štúrova</td><td className="cm-cell-muted">JUDr. Horváthová</td><td>Po, Št · 14:00</td><td><Badge kind="neutral">Záskok</Badge></td></tr>
                <tr><td className="cm-cell-strong">Lekáreň Pri Hrade</td><td className="cm-cell-muted">BENU Slovensko</td><td>Pia · 09:30</td><td><Badge kind="primary">Vedúca</Badge></td></tr>
                <tr><td className="cm-cell-strong">SVB Dunaj 12</td><td className="cm-cell-muted">SVB Dunaj 12</td><td>Po · 16:00</td><td><Badge kind="primary">Vedúca</Badge></td></tr>
              </tbody>
            </table>
          </div>

          <div className="cm-card cm-card-flush">
            <div className="cm-card-h"><h3>Posledné zákazky</h3>
              <a className="cm-link" style={{fontSize: 12}}>Všetky →</a>
            </div>
            <table className="cm-table">
              <thead><tr><th>Dátum</th><th>Objekt</th><th>Check-in</th><th>Check-out</th><th>Trvanie</th><th>Stav</th></tr></thead>
              <tbody>
                <tr><td className="cm-cell-strong">2.5. (Pia)</td><td>Lekáreň Pri Hrade</td><td className="cm-mono">09:28</td><td className="cm-mono">11:14</td><td className="cm-mono cm-cell-muted">1h 46m</td><td><Badge kind="success">OK</Badge></td></tr>
                <tr><td className="cm-cell-strong">29.4. (St)</td><td>Kancelária Hlavná 5</td><td className="cm-mono">07:32</td><td className="cm-mono">09:18</td><td className="cm-mono cm-cell-muted">1h 46m</td><td><Badge kind="success">OK</Badge></td></tr>
                <tr><td className="cm-cell-strong">28.4. (Ut)</td><td>Sklad Petržalka</td><td className="cm-mono">08:01</td><td className="cm-mono">10:22</td><td className="cm-mono cm-cell-muted">2h 21m</td><td><Badge kind="success">OK</Badge></td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div className="cm-col-stack">
          <div className="cm-card">
            <h3 style={{fontSize: 14, marginBottom: 10}}>Kontakt</h3>
            <div className="cm-info-row"><Icon name="mail"/><span className="lab">E-mail</span><a className="cm-link val">petra.hrasnikova@cleanmaster.sk</a></div>
            <div className="cm-info-row"><Icon name="phone"/><span className="lab">Telefón</span><a className="cm-link val">+421 905 112 334</a></div>
            <div className="cm-info-row"><Icon name="mapPin"/><span className="lab">Bydlisko</span><span className="val">Trnavská 22, BA</span></div>
          </div>
          <div className="cm-card">
            <h3 style={{fontSize: 14, marginBottom: 10}}>Zamestnanecká zmluva</h3>
            <div className="cm-info-row"><span className="lab">Typ</span><span className="val">TPP · Plný úväzok</span></div>
            <div className="cm-info-row"><span className="lab">Platnosť</span><span className="val">1.6.2023 – neurčito</span></div>
            <div className="cm-info-row"><span className="lab">Sadzba</span><span className="val cm-mono cm-cell-strong">1 480 €/mes</span></div>
            <div style={{display:'flex', alignItems:'center', gap: 10, padding: 10, background: 'var(--n50)', borderRadius: 8, marginTop: 12}}>
              <div style={{width: 32, height: 32, background: 'var(--danger-light)', color: 'var(--danger)', borderRadius: 6, display:'flex', alignItems:'center', justifyContent:'center'}}><Icon name="pdf" size={16}/></div>
              <div style={{flex:1, minWidth: 0}}>
                <div className="cm-cell-strong cm-truncate" style={{fontSize: 12}}>TPP-Hrasnikova-2023.pdf</div>
                <div style={{fontSize: 11, color: 'var(--n500)'}}>1,8 MB</div>
              </div>
              <div className="cm-iconbtn"><Icon name="download" size={14}/></div>
            </div>
          </div>
          <div className="cm-card">
            <h3 style={{fontSize: 14, marginBottom: 10}}>Štatistiky</h3>
            <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap: 10}}>
              <div style={{padding: 12, background: 'var(--n50)', borderRadius: 8}}>
                <div style={{fontSize: 11, color:'var(--n500)', fontWeight:600, textTransform:'uppercase'}}>Tento mesiac</div>
                <div style={{fontSize: 22, fontWeight: 700}}>32</div>
                <div style={{fontSize: 11, color:'var(--n500)'}}>zákaziek</div>
              </div>
              <div style={{padding: 12, background: 'var(--n50)', borderRadius: 8}}>
                <div style={{fontSize: 11, color:'var(--n500)', fontWeight:600, textTransform:'uppercase'}}>Vyťaženosť</div>
                <div style={{fontSize: 22, fontWeight: 700, color:'var(--success)'}}>94%</div>
                <div style={{fontSize: 11, color:'var(--n500)'}}>z plánu</div>
              </div>
              <div style={{padding: 12, background: 'var(--n50)', borderRadius: 8}}>
                <div style={{fontSize: 11, color:'var(--n500)', fontWeight:600, textTransform:'uppercase'}}>Neprít.</div>
                <div style={{fontSize: 22, fontWeight: 700, color:'var(--warning)'}}>2 dni</div>
                <div style={{fontSize: 11, color:'var(--n500)'}}>tento rok</div>
              </div>
              <div style={{padding: 12, background: 'var(--n50)', borderRadius: 8}}>
                <div style={{fontSize: 11, color:'var(--n500)', fontWeight:600, textTransform:'uppercase'}}>Vo firme</div>
                <div style={{fontSize: 22, fontWeight: 700}}>2r 11m</div>
                <div style={{fontSize: 11, color:'var(--n500)'}}>od 6/2023</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppShell>
  );
};
window.ZamestnanecDetail = ZamestnanecDetail;
