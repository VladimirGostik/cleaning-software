// CleanMaster — Rozvrh kalendár
const Rozvrh = ({ firma, setFirma }) => {
  const days = [
    { name: 'Po', num: 4, today: true },
    { name: 'Ut', num: 5 },
    { name: 'St', num: 6 },
    { name: 'Št', num: 7 },
    { name: 'Pia', num: 8 },
    { name: 'So', num: 9 },
    { name: 'Ne', num: 10 },
  ];
  const hours = [];
  for (let h = 6; h <= 19; h++) hours.push(h);
  // events: { day(0-6), startH, endH, title, sub, kind }
  const events = [
    { day: 0, s: 7.5, e: 9.5, title: 'Hlavná 5', sub: 'AN · Alza', kind: 'done' },
    { day: 0, s: 10, e: 12, title: 'Štúrova 12', sub: 'MK · Notár', kind: 'done' },
    { day: 0, s: 13, e: 14.5, title: 'Pri Hrade', sub: 'JŠ · BENU', kind: 'planned' },
    { day: 0, s: 15, e: 17, title: 'Dunaj 12', sub: 'MT · SVB', kind: 'planned' },
    { day: 1, s: 8, e: 10, title: 'Hlavná 5', sub: 'AN · Alza', kind: 'planned' },
    { day: 1, s: 10.5, e: 12, title: 'Einsteinova', sub: '— Nepriradená', kind: 'unassigned' },
    { day: 1, s: 14, e: 16, title: 'Karola Beauty', sub: 'LM', kind: 'planned' },
    { day: 2, s: 7.5, e: 9.5, title: 'Hlavná 5', sub: 'AN · Alza', kind: 'planned' },
    { day: 2, s: 10, e: 12, title: 'Mickiewiczova 3', sub: 'LM · Varga', kind: 'planned' },
    { day: 2, s: 13.5, e: 15, title: 'Účto Plus', sub: 'AN', kind: 'planned' },
    { day: 2, s: 16, e: 18, title: 'Polus showroom', sub: 'MT · Alza', kind: 'planned' },
    { day: 3, s: 8, e: 10, title: 'Petržalka sklad', sub: 'AN', kind: 'planned' },
    { day: 3, s: 11, e: 13, title: 'Reštaurácia U Z.', sub: '— Nepriradená', kind: 'unassigned' },
    { day: 3, s: 14, e: 16, title: 'Notár Štúrova', sub: 'MK', kind: 'planned' },
    { day: 4, s: 7.5, e: 9.5, title: 'Hlavná 5', sub: 'AN · Alza', kind: 'planned' },
    { day: 4, s: 10, e: 11.5, title: 'Pri Hrade', sub: 'JŠ', kind: 'planned' },
    { day: 4, s: 13, e: 15, title: 'Dunaj 12', sub: 'MT', kind: 'planned' },
    { day: 4, s: 15.5, e: 17, title: 'Karola Beauty', sub: 'LM', kind: 'cancelled' },
    { day: 5, s: 9, e: 12, title: 'Hĺbkové čistenie Polus', sub: 'AN, MT', kind: 'planned' },
    { day: 6, s: 0, e: 0, title: '', sub: '', kind: '' },
  ].filter(e => e.title);

  // Lucia Kušnírová absent days 0-1 (Po-Ut)
  const absentDays = [0, 1];

  return (
    <AppShell active="rozvrh" firma={firma} onFirma={setFirma}>
      <PageHeader title="Rozvrh"
        sub="Týždeň 19 · 4. – 10. máj 2026"
        actions={<>
          <button className="cm-btn cm-btn-secondary"><Icon name="chevronLeft" size={14}/></button>
          <button className="cm-btn cm-btn-secondary">Dnes</button>
          <button className="cm-btn cm-btn-secondary"><Icon name="chevronRight" size={14}/></button>
          <div style={{width: 8}}></div>
          <div className="cm-seg" style={{height: 36, alignItems:'center'}}>
            <button className="on">Týždeň</button><button>Mesiac</button>
          </div>
          <button className="cm-btn cm-btn-primary"><Icon name="plus"/> Nová zákazka</button>
        </>}
      />
      <div className="cm-filters">
        <select className="cm-select"><option>Všetky objekty</option></select>
        <select className="cm-select"><option>Všetky upratovačky</option></select>
        <select className="cm-select"><option>Všetky stavy</option></select>
        <div style={{flex: 1}}></div>
        <div className="cm-row" style={{gap: 12, fontSize: 12, color:'var(--n600)'}}>
          <div className="cm-row" style={{gap: 4}}><span style={{width:10,height:10,background:'var(--primary)',borderRadius:2}}></span>Plánovaná</div>
          <div className="cm-row" style={{gap: 4}}><span style={{width:10,height:10,background:'var(--success)',borderRadius:2}}></span>Dokončená</div>
          <div className="cm-row" style={{gap: 4}}><span style={{width:10,height:10,background:'var(--danger)',borderRadius:2}}></span>Nepriradená</div>
          <div className="cm-row" style={{gap: 4}}><span style={{width:10,height:10,background:'var(--n400)',borderRadius:2}}></span>Zrušená</div>
        </div>
      </div>

      <div className="cm-cal">
        <div className="cm-cal-h">
          <div className="cm-cal-h-cell"></div>
          {days.map((d,i) => (
            <div key={i} className="cm-cal-h-cell" style={{position:'relative'}}>
              <div className="cm-cal-day-name">{d.name}</div>
              <div className={'cm-cal-day-num'+(d.today?' today':'')}>{d.num}</div>
              {absentDays.includes(i) && <div style={{position:'absolute', bottom: 4, left: '50%', transform:'translateX(-50%)', fontSize: 9, color:'var(--danger)', fontWeight: 600, whiteSpace:'nowrap'}}>● Lucia neprítomná</div>}
            </div>
          ))}
        </div>
        <div className="cm-cal-grid">
          <div>
            {hours.map(h => <div key={h} className="cm-cal-time">{h}:00</div>)}
          </div>
          {days.map((d, di) => (
            <div key={di} className="cm-cal-col">
              {hours.map(h => <div key={h} className="cm-cal-hour"></div>)}
              {absentDays.includes(di) && <div className="cm-cal-absent" title="Lucia Kušnírová neprítomná"></div>}
              {events.filter(e => e.day === di).map((e, ei) => {
                const top = (e.s - 6) * 60;
                const height = (e.e - e.s) * 60 - 2;
                return (
                  <div key={ei} className={'cm-cal-event ' + e.kind} style={{top, height}}>
                    <div className="cm-cal-event-time">{Math.floor(e.s)}:{(e.s%1)?'30':'00'} – {Math.floor(e.e)}:{(e.e%1)?'30':'00'}</div>
                    <div className="cm-cal-event-title cm-truncate">{e.title}</div>
                    <div className="cm-cal-event-meta cm-truncate">{e.sub}</div>
                  </div>
                );
              })}
            </div>
          ))}
        </div>
      </div>
    </AppShell>
  );
};
window.Rozvrh = Rozvrh;
