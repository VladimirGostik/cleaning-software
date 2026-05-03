// CleanMaster — Landing page
const LandingScreen = () => (
  <div className="cm-land cm-app">
    <nav className="cm-land-nav">
      <div className="cm-logo" style={{marginRight: 'auto'}}>
        <div className="cm-logomark"><LogoMark /></div>
        <span style={{fontSize: 17}}>CleanMaster</span>
      </div>
      <a>Funkcie</a>
      <a>Cenník</a>
      <a>O nás</a>
      <a>Kontakt</a>
      <div className="cm-lang"><button className="on">SK</button><button>EN</button><button>UA</button></div>
      <button className="cm-btn cm-btn-ghost">Prihlásiť</button>
      <button className="cm-btn cm-btn-primary">Vyskúšať zadarmo</button>
    </nav>

    <section className="cm-land-hero">
      <div style={{display:'inline-flex', alignItems:'center', gap:8, padding:'5px 12px', background:'var(--primary-light)', color:'var(--primary-dark)', borderRadius: 20, fontSize: 12, fontWeight: 600, marginBottom: 28}}>
        <Icon name="sparkle" size={12} /> Nová verzia · Multi-firma podpora
      </div>
      <h1>Celá vaša upratovacia firma <br/><span className="accent">pod kontrolou.</span></h1>
      <p>Klienti, zmluvy, rozvrh a faktúry na jednom mieste. Vytvorené pre slovenské upratovacie firmy — od jednej firmy po sieť pobočiek.</p>
      <div className="cm-land-hero-cta">
        <button className="cm-btn cm-btn-primary cm-btn-lg">Vyskúšať zadarmo →</button>
        <button className="cm-btn cm-btn-secondary cm-btn-lg">Pozrieť demo</button>
      </div>
      <div style={{marginTop: 18, fontSize: 13, color:'var(--n500)'}}>14 dní zadarmo · bez kreditnej karty · zrušiť kedykoľvek</div>

      <div className="cm-land-mock">
        <div style={{height: 28, background: 'var(--n100)', borderBottom:'1px solid var(--n200)', display:'flex', alignItems:'center', padding:'0 12px', gap: 6}}>
          <div style={{width:10, height:10, borderRadius:'50%', background:'#FF5F57'}}></div>
          <div style={{width:10, height:10, borderRadius:'50%', background:'#FEBC2E'}}></div>
          <div style={{width:10, height:10, borderRadius:'50%', background:'#28C840'}}></div>
          <div style={{flex:1, height: 18, background:'#fff', borderRadius:6, marginLeft: 12, fontSize: 11, color:'var(--n500)', display:'flex', alignItems:'center', justifyContent:'center'}}>app.cleanmaster.sk/dashboard</div>
        </div>
        <div style={{height: 380, background:'var(--n50)', display:'grid', gridTemplateColumns:'180px 1fr', gap: 0}}>
          <div style={{background:'var(--n900)', padding: 12, display:'flex', flexDirection:'column', gap: 4}}>
            {NAV.slice(0,7).map((n,i) => (
              <div key={n.id} style={{display:'flex', alignItems:'center', gap:8, padding:'6px 8px', borderRadius:5, fontSize: 11, color: i===0?'#fff':'#94a3b8', background: i===0?'var(--primary)':'transparent'}}>
                <Icon name={n.icon} size={12} />{n.label}
              </div>
            ))}
          </div>
          <div style={{padding: 18}}>
            <div style={{fontSize: 18, fontWeight: 700, color:'var(--n900)', marginBottom: 16}}>Dashboard</div>
            <div style={{display:'grid', gridTemplateColumns:'repeat(4,1fr)', gap: 10, marginBottom: 16}}>
              {[
                {l:'Dnes', v:'8', c:'var(--primary)'},
                {l:'Bez upratovačky', v:'2', c:'var(--danger)'},
                {l:'Nefakturované', v:'14', c:'var(--warning)'},
                {l:'Končiace', v:'3', c:'var(--warning)'},
              ].map((s,i)=>(
                <div key={i} style={{background:'#fff', border:'1px solid var(--n200)', padding: 10, borderRadius: 6}}>
                  <div style={{fontSize: 9, color:'var(--n500)', textTransform:'uppercase', fontWeight: 600}}>{s.l}</div>
                  <div style={{fontSize: 22, fontWeight: 700, color: s.c}}>{s.v}</div>
                </div>
              ))}
            </div>
            <div style={{background:'#fff', border:'1px solid var(--n200)', borderRadius: 6, padding: 12}}>
              <div style={{fontSize: 12, fontWeight: 700, marginBottom: 10}}>Dnešné zákazky</div>
              {['08:00 · Hlavná 5 · Anna N.', '10:30 · Štúrova 12 · Mária K.', '14:00 · Mickiewiczova 3 · Jana S.'].map((r,i)=>(
                <div key={i} style={{display:'flex', alignItems:'center', justifyContent:'space-between', padding:'6px 0', borderTop: i?'1px solid var(--n100)':'none', fontSize: 11, color:'var(--n700)'}}>
                  <span>{r}</span>
                  <span style={{fontSize: 9, padding:'1px 6px', background:'var(--success-light)', color:'#15803d', borderRadius: 8, fontWeight: 600}}>Plánovaná</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>

    <section className="cm-land-features">
      <h2>Všetko čo potrebuje moderná upratovacia firma</h2>
      <div className="cm-land-fgrid">
        {[
          {i:'fileSign', t:'Papierovačky', d:'Cenové ponuky a zmluvy s automatickým generovaním PDF. Šablóny, podpisy, prehľadná história zmien.'},
          {i:'userCog', t:'Zamestnanci a rozvrh', d:'Plánovanie zákaziek, priraďovanie upratovačiek, neprítomnosti. Mobilná app s check-in/check-out.'},
          {i:'receipt', t:'Fakturácia', d:'Mesačné a jednorazové faktúry. QR platby (Pay by Square), DPH, slovenská legislatíva.'},
          {i:'building', t:'Klienti a objekty', d:'Firemní aj súkromní klienti. Rozpis prác, prístupové informácie, kontakty na mieste.'},
          {i:'shield', t:'Oprávnenia', d:'Predefinované role aj vlastné oprávnenia podľa modulov. Multi-firma s rôznymi prístupmi.'},
          {i:'phone', t:'Mobilná aplikácia', d:'Pre upratovačky aj zákazníkov. Nahlasovanie neprítomností, fotodokumentácia, reklamácie.'},
        ].map((f,i)=>(
          <div key={i} className="cm-land-fcard">
            <div className="cm-land-ficon"><Icon name={f.i} size={20} /></div>
            <h3>{f.t}</h3>
            <p>{f.d}</p>
          </div>
        ))}
      </div>
    </section>

    <section className="cm-land-pricing">
      <div style={{textAlign:'center'}}>
        <h2 style={{fontSize: 38, marginBottom: 8}}>Cenník</h2>
        <p style={{color:'var(--n600)', fontSize: 17}}>Začnite zadarmo, plaťte mesačne, zrušte kedykoľvek.</p>
      </div>
      <div className="cm-land-pgrid">
        {[
          {n:'Free', p:'0', firms:'1', users:'1', clients:'5', feat:['Základné funkcie','SK podpora']},
          {n:'Štart', p:'19', firms:'1', users:'3', clients:'∞', feat:['Všetko z Free','Šablóny dokumentov','PDF export'], pop: true},
          {n:'Business', p:'39', firms:'3', users:'10', clients:'∞', feat:['Multi-firma (3)','Mobilná app','API prístup']},
          {n:'Premium', p:'69', firms:'∞', users:'∞', clients:'∞', feat:['Všetko bez limitov','Prioritná podpora','Vlastný branding']},
        ].map((p,i)=>(
          <div key={i} className={'cm-land-pcard' + (p.pop?' featured':'')}>
            {p.pop && <div className="badge-pop">Najobľúbenejší</div>}
            <h4>{p.n}</h4>
            <div className="price">{p.p} €<small> / mes</small></div>
            <ul>
              <li><Icon name="check"/> Firmy: {p.firms}</li>
              <li><Icon name="check"/> Používatelia: {p.users}</li>
              <li><Icon name="check"/> Klienti: {p.clients}</li>
              {p.feat.map((f,j)=> <li key={j}><Icon name="check"/> {f}</li>)}
            </ul>
            <button className={'cm-btn ' + (p.pop?'cm-btn-primary':'cm-btn-secondary')} style={{width:'100%', justifyContent:'center'}}>Vybrať plán</button>
          </div>
        ))}
      </div>
    </section>

    <section className="cm-land-cta">
      <h2>Začnite ešte dnes zadarmo</h2>
      <p>Bez kreditnej karty. Zrušte kedykoľvek.</p>
      <div className="cm-land-cta-form">
        <input placeholder="vas@email.sk" />
        <button className="cm-btn" style={{background:'#fff', color:'var(--primary)'}}>Začať →</button>
      </div>
    </section>

    <footer className="cm-land-footer">
      <div className="cm-row" style={{gap: 12}}>
        <div className="cm-logomark" style={{width:24, height:24}}><LogoMark /></div>
        <span>© 2026 CleanMaster s.r.o.</span>
      </div>
      <div className="cm-row" style={{gap: 24}}>
        <a style={{color:'inherit'}}>Kontakt</a>
        <a style={{color:'inherit'}}>Podmienky</a>
        <a style={{color:'inherit'}}>GDPR</a>
      </div>
    </footer>
  </div>
);
window.LandingScreen = LandingScreen;
