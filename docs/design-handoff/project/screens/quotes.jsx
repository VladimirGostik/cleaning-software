// CleanMaster — Cenové ponuky: vytvoriť + detail
const QuoteCreate = ({ firma, setFirma }) => {
  const [items, setItems] = React.useState([
    { service: 'Pravidelné upratovanie kancelárií', desc: 'Vysávanie, umývanie podláh, utieranie prachu, čistenie sociálnych zariadení', freq: '3×/týždeň', unit: 'Paušál', qty: 1, price: 640 },
    { service: 'Umývanie okien', desc: 'Obojstranné umývanie okien vrátane rámov', freq: '1×/štvrťrok', unit: 'm²', qty: 85, price: 2.5 },
    { service: 'Hĺbkové čistenie kobercov', desc: 'Profesionálne extrakčné čistenie', freq: '2×/rok', unit: 'm²', qty: 120, price: 4.2 },
  ]);
  const subtotal = items.reduce((s,i) => s + i.qty * i.price, 0);
  const dph = subtotal * 0.23;
  return (
    <AppShell active="ponuky" firma={firma} onFirma={setFirma}>
      <div style={{display:'flex', alignItems:'center', gap: 8, fontSize: 13, color:'var(--n500)', marginBottom: 12}}>
        <a className="cm-link">Cenové ponuky</a> <Icon name="chevronRight" size={12}/> <span>Nová</span>
      </div>
      <PageHeader title="Nová cenová ponuka"
        actions={<>
          <button className="cm-btn cm-btn-secondary">Zrušiť</button>
          <button className="cm-btn cm-btn-secondary">Uložiť ako draft</button>
          <button className="cm-btn cm-btn-primary"><Icon name="check"/> Uložiť a odoslať</button>
        </>}
      />
      <div style={{display:'grid', gridTemplateColumns:'1fr 320px', gap: 'var(--gap)'}}>
        <div className="cm-col-stack">
          <div className="cm-card">
            <h3 style={{marginBottom: 14, fontSize: 15}}>Základné údaje</h3>
            <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap: 12}}>
              <div className="cm-field"><label>Klient<span className="cm-req">*</span></label>
                <div className="cm-input-icon"><Icon name="search"/><input className="cm-input" defaultValue="Alza.sk s.r.o." style={{paddingLeft: 34}}/></div>
              </div>
              <div className="cm-field"><label>Objekt<span className="cm-req">*</span></label>
                <select className="cm-select"><option>Kancelária Einsteinova</option><option>Kancelária Hlavná 5</option></select>
              </div>
              <div className="cm-field"><label>Číslo ponuky</label><input className="cm-input cm-mono" defaultValue="CP-2026-0034"/></div>
              <div className="cm-field"><label>Platná do<span className="cm-req">*</span></label><input className="cm-input" type="text" defaultValue="3.6.2026"/></div>
            </div>
          </div>

          <div className="cm-card">
            <div style={{display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom: 14}}>
              <h3 style={{fontSize: 15}}>Položky ponuky</h3>
              <span className="cm-pill-stat">{items.length} položiek</span>
            </div>
            <table className="cm-lines">
              <thead>
                <tr><th>Služba</th><th>Frekvencia</th><th style={{width: 90}}>Jedn.</th><th style={{width: 80}}>Množ.</th><th style={{width: 100}}>Cena/j.</th><th style={{width: 100}}>Suma</th><th style={{width: 32}}></th></tr>
              </thead>
              <tbody>
                {items.map((it,i) => (
                  <tr key={i}>
                    <td>
                      <input className="cm-input" defaultValue={it.service} style={{marginBottom: 4}}/>
                      <textarea className="cm-textarea" defaultValue={it.desc} style={{minHeight: 40, fontSize: 12}}/>
                    </td>
                    <td><select className="cm-select"><option>{it.freq}</option></select></td>
                    <td><select className="cm-select"><option>{it.unit}</option></select></td>
                    <td><input className="cm-input" type="text" defaultValue={it.qty} style={{textAlign:'right'}}/></td>
                    <td><input className="cm-input cm-mono" type="text" defaultValue={it.price.toFixed(2)} style={{textAlign:'right'}}/></td>
                    <td className="cm-mono cm-cell-strong" style={{textAlign:'right'}}>{fmt.eur(it.qty * it.price)}</td>
                    <td><div className="cm-line-rm"><Icon name="x" size={14}/></div></td>
                  </tr>
                ))}
              </tbody>
            </table>
            <div className="cm-line-add"><Icon name="plus" size={14}/> Pridať položku</div>
            <div className="cm-summary" style={{marginTop: 10}}>
              <div className="cm-summary-row"><span>Suma bez DPH</span><span className="cm-mono">{fmt.eur(subtotal)}</span></div>
              <div className="cm-summary-row cm-cell-muted"><span>DPH 23 %</span><span className="cm-mono">{fmt.eur(dph)}</span></div>
              <div className="cm-summary-row total"><span>Suma s DPH</span><span className="cm-mono">{fmt.eur(subtotal + dph)}</span></div>
            </div>
          </div>

          <div className="cm-card">
            <h3 style={{marginBottom: 10, fontSize: 15}}>Poznámka pre klienta</h3>
            <textarea className="cm-textarea" defaultValue="Cena je platná pri záväzku minimálne 12 mesiacov. V cene je zahrnutý čistiaci materiál a profesionálne stroje. Možnosť mimoriadnych objednávok podľa cenníka."/>
          </div>
        </div>

        <div className="cm-col-stack">
          <div className="cm-card" style={{position: 'sticky', top: 16}}>
            <h3 style={{fontSize: 14, marginBottom: 12}}>Náhľad ponuky</h3>
            <div style={{padding: 14, background: 'var(--n50)', borderRadius: 8, border: '1px solid var(--n200)', fontSize: 12}}>
              <div className="cm-mono" style={{fontWeight: 700, color: 'var(--n900)', fontSize: 14}}>CP-2026-0034</div>
              <div style={{color: 'var(--n500)', marginTop: 2}}>pre Alza.sk s.r.o.</div>
              <div style={{marginTop: 12, paddingTop: 12, borderTop: '1px solid var(--n200)'}}>
                {items.map((it,i)=>(
                  <div key={i} style={{display:'flex', justifyContent:'space-between', padding:'4px 0', fontSize: 11}}>
                    <span className="cm-truncate" style={{maxWidth: 160}}>{it.service}</span>
                    <span className="cm-mono">{fmt.eur(it.qty * it.price)}</span>
                  </div>
                ))}
              </div>
              <div style={{marginTop: 10, paddingTop: 10, borderTop: '1px solid var(--n200)', display:'flex', justifyContent:'space-between', fontWeight: 700, color:'var(--n900)'}}>
                <span>Spolu s DPH</span>
                <span className="cm-mono">{fmt.eur(subtotal + dph)}</span>
              </div>
            </div>
            <div className="cm-stack" style={{marginTop: 14, gap: 8}}>
              <button className="cm-btn cm-btn-secondary" style={{justifyContent:'center'}}><Icon name="eye"/> Náhľad PDF</button>
              <button className="cm-btn cm-btn-secondary" style={{justifyContent:'center'}}><Icon name="mail"/> Skopírovať odkaz</button>
            </div>
          </div>
        </div>
      </div>
    </AppShell>
  );
};

const QuoteDetail = ({ firma, setFirma }) => (
  <AppShell active="ponuky" firma={firma} onFirma={setFirma}>
    <div style={{display:'flex', alignItems:'center', gap: 8, fontSize: 13, color:'var(--n500)', marginBottom: 12}}>
      <a className="cm-link">Cenové ponuky</a> <Icon name="chevronRight" size={12}/> <span className="cm-mono">CP-2026-0028</span>
    </div>
    <PageHeader
      title={<span className="cm-mono">CP-2026-0028</span>}
      sub="Vytvorené 22. apríla 2026 · Odoslané klientovi"
      badges={<><Badge kind="primary">Odoslaná</Badge></>}
      actions={<>
        <button className="cm-btn cm-btn-secondary"><Icon name="download"/> PDF</button>
        <button className="cm-btn cm-btn-secondary"><Icon name="mail"/> Odoslať znova</button>
        <button className="cm-btn cm-btn-success"><Icon name="check"/> Schváliť → Vytvoriť zmluvu</button>
        <button className="cm-btn cm-btn-ghost"><Icon name="more"/></button>
      </>}
    />

    <div className="cm-cols">
      <div className="cm-col-stack">
        <div className="cm-card">
          <div style={{display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom: 16}}>
            <h3 style={{fontSize: 15}}>Položky ponuky</h3>
            <span className="cm-muted" style={{fontSize: 12}}>3 položky</span>
          </div>
          <table className="cm-lines">
            <thead><tr><th>Služba</th><th>Frekvencia</th><th>Jedn.</th><th style={{textAlign:'right'}}>Množ.</th><th style={{textAlign:'right'}}>Cena/j.</th><th style={{textAlign:'right'}}>Suma</th></tr></thead>
            <tbody>
              <tr><td><div style={{fontWeight: 600, color:'var(--n900)'}}>Pravidelné upratovanie kancelárií</div><div style={{fontSize: 12, color: 'var(--n500)', marginTop: 2}}>Vysávanie, umývanie podláh, utieranie prachu</div></td><td>3×/týždeň</td><td>Paušál</td><td className="cm-mono" style={{textAlign:'right'}}>1</td><td className="cm-mono" style={{textAlign:'right'}}>640,00</td><td className="cm-mono cm-cell-strong" style={{textAlign:'right'}}>{fmt.eur(640)}</td></tr>
              <tr><td><div style={{fontWeight: 600, color:'var(--n900)'}}>Umývanie okien</div><div style={{fontSize: 12, color: 'var(--n500)', marginTop: 2}}>Obojstranné, vrátane rámov</div></td><td>1×/štvrťrok</td><td>m²</td><td className="cm-mono" style={{textAlign:'right'}}>85</td><td className="cm-mono" style={{textAlign:'right'}}>2,50</td><td className="cm-mono cm-cell-strong" style={{textAlign:'right'}}>{fmt.eur(212.5)}</td></tr>
              <tr><td><div style={{fontWeight: 600, color:'var(--n900)'}}>Hĺbkové čistenie kobercov</div><div style={{fontSize: 12, color: 'var(--n500)', marginTop: 2}}>Profesionálne extrakčné čistenie</div></td><td>2×/rok</td><td>m²</td><td className="cm-mono" style={{textAlign:'right'}}>120</td><td className="cm-mono" style={{textAlign:'right'}}>4,20</td><td className="cm-mono cm-cell-strong" style={{textAlign:'right'}}>{fmt.eur(504)}</td></tr>
            </tbody>
          </table>
          <div className="cm-summary">
            <div className="cm-summary-row"><span>Suma bez DPH</span><span className="cm-mono">{fmt.eur(1356.5)}</span></div>
            <div className="cm-summary-row cm-cell-muted"><span>DPH 23 %</span><span className="cm-mono">{fmt.eur(312)}</span></div>
            <div className="cm-summary-row total"><span>Suma s DPH</span><span className="cm-mono">{fmt.eur(1668.5)}</span></div>
          </div>
        </div>

        <div className="cm-card">
          <h3 style={{fontSize: 15, marginBottom: 6}}>Vygenerovaný rozpis prác</h3>
          <p style={{fontSize: 12, color: 'var(--n500)', marginBottom: 12}}>Rozpis sa priradí k objektu po vytvorení zmluvy.</p>
          <table className="cm-table">
            <thead><tr><th>Služba</th><th>Popis</th><th>Frekvencia</th></tr></thead>
            <tbody>
              <tr><td className="cm-cell-strong">Vysávanie</td><td>Všetky podlahové plochy a koberce</td><td>3×/týždeň</td></tr>
              <tr><td className="cm-cell-strong">Umývanie podláh</td><td>Tvrdé podlahy mokrou cestou</td><td>3×/týždeň</td></tr>
              <tr><td className="cm-cell-strong">Sociálne zariadenia</td><td>Dezinfekcia, doplnenie spotrebného materiálu</td><td>3×/týždeň</td></tr>
              <tr><td className="cm-cell-strong">Utieranie prachu</td><td>Stoly, parapety, police do výšky 180 cm</td><td>3×/týždeň</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div className="cm-col-stack">
        <div className="cm-card">
          <h3 style={{fontSize: 14, marginBottom: 10}}>Informácie</h3>
          <div className="cm-info-row"><span className="lab">Klient</span><a className="cm-link val">Alza.sk s.r.o.</a></div>
          <div className="cm-info-row"><span className="lab">Objekt</span><a className="cm-link val">Kancelária Einsteinova</a></div>
          <div className="cm-info-row"><span className="lab">Vytvorená</span><span className="val">22. 4. 2026</span></div>
          <div className="cm-info-row"><span className="lab">Odoslaná</span><span className="val">22. 4. 2026 o 14:32</span></div>
          <div className="cm-info-row"><span className="lab">Platná do</span><span className="val" style={{color: 'var(--warning)', fontWeight: 600}}>22. 5. 2026 (o 18 dní)</span></div>
          <div className="cm-info-row"><span className="lab">Suma</span><span className="val cm-mono cm-cell-strong">{fmt.eur(1668.5)}</span></div>
        </div>
        <div className="cm-card">
          <h3 style={{fontSize: 14, marginBottom: 10}}>Aktivita</h3>
          <div className="cm-timeline">
            <div className="cm-tl-item"><div className="cm-tl-dot create"></div>
              <div className="cm-tl-meta">22. 4. 2026 · 11:08</div>
              <div className="cm-tl-title">Vytvorené</div>
              <div className="cm-tl-detail">Mária Kováčová vytvorila ponuku</div>
            </div>
            <div className="cm-tl-item"><div className="cm-tl-dot edit"></div>
              <div className="cm-tl-meta">22. 4. 2026 · 14:32</div>
              <div className="cm-tl-title">Odoslané klientovi</div>
              <div className="cm-tl-detail">E-mail na fakturacia@alza.sk</div>
            </div>
            <div className="cm-tl-item"><div className="cm-tl-dot edit"></div>
              <div className="cm-tl-meta">25. 4. 2026 · 09:14</div>
              <div className="cm-tl-title">Klient otvoril ponuku</div>
              <div className="cm-tl-detail">Z PDF odkazu (3 zobrazenia)</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppShell>
);

window.QuoteCreate = QuoteCreate;
window.QuoteDetail = QuoteDetail;
