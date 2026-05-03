// CleanMaster — Dashboard
const Dashboard = ({ firma, setFirma }) => {
  const stats = [
    { label: 'Dnešné zákazky', value: 12, icon: 'calendar', color: 'var(--primary)', bg: 'var(--primary-light)', trend: '+3 oproti včera', trendUp: true },
    { label: 'Bez priradenia', value: 2, icon: 'alert', color: 'var(--danger)', bg: 'var(--danger-light)', trend: 'Vyžaduje akciu', trendUp: false },
    { label: 'Nefakturované', value: 14, icon: 'receipt', color: 'var(--warning)', bg: 'var(--warning-light)', trend: '€ 3 240 čaká', trendUp: false },
    { label: 'Končiace zmluvy (30d)', value: 3, icon: 'clock', color: 'var(--warning)', bg: 'var(--warning-light)', trend: 'Predĺžiť', trendUp: false },
  ];
  const today = [
    { time: '07:30', obj: 'Kancelária Hlavná 5', client: 'Alza.sk s.r.o.', upr: 'Anna Novotná', state: 'done', firma: 'cm-bratislava' },
    { time: '08:00', obj: 'Notársky úrad Štúrova', client: 'JUDr. Eva Horváthová', upr: 'Mária Kollárová', state: 'planned', firma: 'cm-bratislava' },
    { time: '09:30', obj: 'Lekáreň Pri Hrade', client: 'BENU Slovensko', upr: 'Jana Šimková', state: 'planned', firma: 'cm-bratislava' },
    { time: '10:00', obj: 'Kaderníctvo Karola', client: 'Karola Beauty s.r.o.', upr: null, state: 'unassigned', firma: 'cm-trnava' },
    { time: '11:30', obj: 'Byt Mickiewiczova 3', client: 'Peter Varga', upr: 'Lenka Mokrá', state: 'planned', firma: 'cm-bratislava' },
    { time: '13:00', obj: 'Účtovná kancelária', client: 'Účto Plus s.r.o.', upr: 'Anna Novotná', state: 'planned', firma: 'cm-trnava' },
    { time: '14:30', obj: 'Reštaurácia U Zlatého', client: 'Gastro House s.r.o.', upr: null, state: 'unassigned', firma: 'cm-kosice' },
    { time: '16:00', obj: 'Spoločné priestory Dunaj', client: 'SVB Dunaj 12', upr: 'Monika Tóthová', state: 'planned', firma: 'cm-bratislava' },
  ];
  const absences = [
    { name: 'Lucia Kušnírová', dates: '6.5. – 10.5.', reason: 'Choroba', affected: 4 },
    { name: 'Daniela Polláková', dates: '12.5. – 19.5.', reason: 'Dovolenka', affected: 7 },
  ];
  const unbilled = [
    { date: '28.4.', obj: 'Kancelária Hlavná 5', client: 'Alza.sk s.r.o.', sum: 320 },
    { date: '29.4.', obj: 'Lekáreň Pri Hrade', client: 'BENU Slovensko', sum: 180 },
    { date: '30.4.', obj: 'Účtovná kancelária', client: 'Účto Plus s.r.o.', sum: 240 },
    { date: '1.5.', obj: 'Notársky úrad Štúrova', client: 'JUDr. Eva Horváthová', sum: 220 },
  ];
  const stateBadge = (s) => {
    if (s === 'done') return <Badge kind="success">Dokončená</Badge>;
    if (s === 'planned') return <Badge kind="primary">Plánovaná</Badge>;
    if (s === 'unassigned') return <Badge kind="danger">Bez upratovačky</Badge>;
    return <Badge>—</Badge>;
  };
  return (
    <AppShell active="dashboard" firma={firma} onFirma={setFirma}>
      <PageHeader
        title="Dashboard"
        sub="Pondelok, 4. mája 2026 · Prehľad za vybranú firmu"
        actions={<>
          <button className="cm-btn cm-btn-secondary"><Icon name="download" /> Export</button>
          <button className="cm-btn cm-btn-primary"><Icon name="plus" /> Nová zákazka</button>
        </>}
      />

      <div className="cm-stats">
        {stats.map((s,i) => (
          <div key={i} className="cm-stat">
            <div className="cm-stat-label">
              <span>{s.label}</span>
              <span className="cm-stat-icon" style={{background: s.bg, color: s.color}}><Icon name={s.icon} /></span>
            </div>
            <div className="cm-stat-value" style={{color: s.color}}>{s.value}</div>
            <div className={'cm-stat-trend ' + (s.trendUp?'up':'')}>
              {s.trendUp ? <Icon name="arrowUp" size={12}/> : null}
              {s.trend}
            </div>
          </div>
        ))}
      </div>

      <div style={{marginTop: 'var(--gap)', display:'grid', gridTemplateColumns: '2fr 1fr', gap: 'var(--gap)'}}>
        <div className="cm-card cm-card-flush">
          <div className="cm-card-h">
            <h3>Dnešné zákazky</h3>
            <div className="cm-actions">
              <span className="cm-pill-stat"><Icon name="calendar" size={12}/> 8 dnes</span>
              <a className="cm-link" style={{fontSize: 12}}>Zobraziť rozvrh →</a>
            </div>
          </div>
          <table className="cm-table">
            <thead>
              <tr><th>Čas</th><th>Objekt</th><th>Klient</th><th>Upratovačka</th><th>Firma</th><th>Stav</th></tr>
            </thead>
            <tbody>
              {today.map((r,i) => (
                <tr key={i}>
                  <td className="cm-mono cm-cell-strong">{r.time}</td>
                  <td className="cm-cell-strong">{r.obj}</td>
                  <td className="cm-cell-muted">{r.client}</td>
                  <td>{r.upr || <span style={{color:'var(--danger)', fontWeight: 600}}>— Nepriradená</span>}</td>
                  <td><FirmaTag id={r.firma}/></td>
                  <td>{stateBadge(r.state)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="cm-col-stack">
          <div className="cm-card cm-card-flush">
            <div className="cm-card-h">
              <h3>Nahlásené neprítomnosti</h3>
              <span className="cm-pill-stat" style={{background:'var(--danger-light)', color: 'var(--danger)'}}>{absences.length}</span>
            </div>
            <div style={{padding: 'var(--pad)'}}>
              {absences.map((a,i) => (
                <div key={i} style={{padding: '12px 0', borderTop: i?'1px solid var(--n100)':'none'}}>
                  <div style={{display:'flex', justifyContent:'space-between', alignItems:'flex-start', marginBottom: 4}}>
                    <div>
                      <div style={{fontWeight: 600, color: 'var(--n900)', fontSize: 13}}>{a.name}</div>
                      <div style={{fontSize: 12, color: 'var(--n500)'}}>{a.dates} · {a.reason}</div>
                    </div>
                    <Badge kind="danger">{a.affected} zákaziek</Badge>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="cm-card cm-card-flush">
            <div className="cm-card-h">
              <h3>Nefakturované</h3>
              <a className="cm-link" style={{fontSize: 12}}>Všetky →</a>
            </div>
            <div style={{padding: '4px var(--pad) var(--pad)'}}>
              {unbilled.map((u,i) => (
                <div key={i} style={{display:'flex', justifyContent:'space-between', alignItems:'center', padding:'10px 0', borderTop: i?'1px solid var(--n100)':'none'}}>
                  <div style={{minWidth: 0, flex: 1}}>
                    <div className="cm-truncate" style={{fontSize: 13, fontWeight: 600, color: 'var(--n900)'}}>{u.obj}</div>
                    <div style={{fontSize: 11, color: 'var(--n500)'}}>{u.date} · {fmt.eur(u.sum)}</div>
                  </div>
                  <button className="cm-btn cm-btn-secondary cm-btn-sm">Fakturovať</button>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </AppShell>
  );
};
window.Dashboard = Dashboard;
