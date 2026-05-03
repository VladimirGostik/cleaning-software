// CleanMaster — shared layout chrome (Sidebar, Topbar, AppShell)

const Sidebar = ({ active = 'dashboard', sidebarStyle = 'dark' }) => {
  const dark = sidebarStyle === 'dark';
  const sty = dark ? {} : { background: '#fff', borderRight: '1px solid var(--n200)', color: 'var(--n700)' };
  return (
    <aside className="cm-sidebar" style={sty}>
      <div style={{padding: '6px 10px 18px'}}>
        <div className="cm-logo" style={{color: dark ? '#fff' : 'var(--n900)'}}>
          <div className="cm-logomark"><LogoMark /></div>
          <span style={{fontSize: 16}}>CleanMaster</span>
        </div>
      </div>
      <nav className="cm-nav-section">
        {NAV.map(item => (
          <div key={item.id} className={'cm-nav-item' + (active === item.id ? ' active' : '')} style={!dark && active !== item.id ? {color:'var(--n600)'} : null}>
            <Icon name={item.icon} />
            <span>{item.label}</span>
            {item.badge ? <span className="cm-nav-badge">{item.badge}</span> : null}
          </div>
        ))}
      </nav>
      <nav className="cm-nav-section">
        <div className="cm-nav-heading">Administrácia</div>
        {NAV_ADMIN.map(item => (
          <div key={item.id} className={'cm-nav-item' + (active === item.id ? ' active' : '')} style={!dark && active !== item.id ? {color:'var(--n600)'} : null}>
            <Icon name={item.icon} />
            <span>{item.label}</span>
          </div>
        ))}
      </nav>
    </aside>
  );
};

const LogoMark = () => (
  <svg viewBox="0 0 24 24" fill="none">
    <path d="M5 14c0-3.31 2.69-6 6-6s6 2.69 6 6" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round"/>
    <path d="M8 18h10" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round"/>
    <circle cx="18" cy="7" r="1.5" fill="currentColor"/>
    <circle cx="14" cy="5" r="1" fill="currentColor"/>
  </svg>
);

const Topbar = ({ firma = 'all', onFirma, multiFirma = true, lang = 'SK', user = 'Mária Kováčová' }) => {
  const [open, setOpen] = React.useState(false);
  const cur = FIRMS.find(f => f.id === firma) || FIRMS[0];
  return (
    <header className="cm-topbar">
      {multiFirma ? (
        <div style={{position:'relative'}}>
          <div className="cm-firma-pick" onClick={() => setOpen(!open)}>
            <span className="cm-firma-dot" style={{background: cur.color}}></span>
            <span>{cur.name}</span>
            <Icon name="chevronDown" size={14} />
          </div>
          {open && (
            <div style={{position:'absolute', top:'100%', left:0, marginTop: 6, background:'#fff', border:'1px solid var(--n200)', borderRadius: 8, boxShadow: 'var(--shadow-lg)', minWidth: 240, padding: 6, zIndex: 20}}>
              {FIRMS.map(f => (
                <div key={f.id} onClick={() => { onFirma && onFirma(f.id); setOpen(false); }} style={{display:'flex', alignItems:'center', gap:8, padding:'8px 10px', borderRadius:6, cursor:'pointer', background: f.id === firma ? 'var(--n50)' : 'transparent', fontSize: 13, fontWeight: 500}}>
                  <span className="cm-firma-dot" style={{background: f.color}}></span>
                  <span>{f.name}</span>
                  {f.id === firma && <Icon name="check" size={14} style={{marginLeft:'auto', color:'var(--primary)'}} />}
                </div>
              ))}
            </div>
          )}
        </div>
      ) : null}
      <div className="cm-topbar-spacer"></div>
      <div className="cm-lang">
        {['SK','EN','UA'].map(l => <button key={l} className={l === lang ? 'on' : ''}>{l}</button>)}
      </div>
      <div className="cm-iconbtn"><Icon name="bell" /><span className="cm-pulse"></span></div>
      <div style={{display:'flex', alignItems:'center', gap: 8, padding: '4px 8px 4px 4px', borderRadius: 18, cursor:'pointer'}}>
        <div className="cm-avatar">MK</div>
        <span style={{fontSize: 13, fontWeight: 600, color: 'var(--n800)'}}>{user}</span>
        <Icon name="chevronDown" size={12} style={{color:'var(--n500)'}} />
      </div>
    </header>
  );
};

const AppShell = ({ children, active, firma, onFirma, multiFirma, sidebarStyle }) => (
  <div className="cm-app cm-shell">
    <Topbar firma={firma} onFirma={onFirma} multiFirma={multiFirma} />
    <Sidebar active={active} sidebarStyle={sidebarStyle} />
    <main className="cm-content">{children}</main>
  </div>
);

const PageHeader = ({ title, sub, badges, actions }) => (
  <div className="cm-pageheader">
    <div>
      <h1>
        <span>{title}</span>
        {badges}
      </h1>
      {sub && <div className="cm-page-sub">{sub}</div>}
    </div>
    <div className="cm-actions">{actions}</div>
  </div>
);

const Badge = ({ kind = 'neutral', children, dot = true }) => (
  <span className={`cm-badge cm-badge-${kind}`}>
    {dot && <span className="cm-badge-dot"></span>}
    {children}
  </span>
);

const FirmaTag = ({ id }) => {
  const f = FIRMS.find(x => x.id === id);
  if (!f) return null;
  const bg = f.color + '22';
  return <span className="cm-firma-tag" style={{background: bg, color: f.color}}><span style={{width:6,height:6,borderRadius:'50%',background:f.color,display:'inline-block'}}></span>{f.name.replace('CleanMaster ','')}</span>;
};

Object.assign(window, { Sidebar, Topbar, AppShell, PageHeader, Badge, FirmaTag, LogoMark });
