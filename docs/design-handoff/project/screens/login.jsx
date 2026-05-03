// CleanMaster — Login screen
const LoginScreen = () => {
  const [showPw, setShowPw] = React.useState(false);
  const [remember, setRemember] = React.useState(true);
  return (
    <div className="cm-app" style={{height:'100%'}}>
      <div className="cm-login">
        <div className="cm-login-hero">
          <div style={{position:'relative', zIndex:1}}>
            <div className="cm-logo" style={{color:'#fff'}}>
              <div className="cm-logomark" style={{background:'rgba(255,255,255,0.15)', backdropFilter:'blur(8px)'}}><LogoMark /></div>
              <span style={{fontSize: 18, color:'#fff'}}>CleanMaster</span>
            </div>
          </div>
          <div style={{position:'relative', zIndex:1}}>
            <h1>Celá vaša firma <br/>pod kontrolou.</h1>
            <p className="sub">Správa klientov, zmlúv, zamestnancov a faktúr na jednom mieste. Vytvorené pre slovenské upratovacie firmy.</p>
            <div style={{display:'flex', gap: 24, marginTop: 36, fontSize: 13, color:'rgba(255,255,255,.85)'}}>
              <div style={{display:'flex', alignItems:'center', gap:8}}><Icon name="check"/> 14 dní zadarmo</div>
              <div style={{display:'flex', alignItems:'center', gap:8}}><Icon name="check"/> Bez kreditnej karty</div>
              <div style={{display:'flex', alignItems:'center', gap:8}}><Icon name="check"/> SK podpora</div>
            </div>
          </div>
          <div style={{position:'relative', zIndex:1, fontSize: 12, opacity:.6}}>© 2026 CleanMaster s.r.o.</div>
        </div>
        <div className="cm-login-form">
          <div className="top">
            <h2>Prihláste sa</h2>
            <div className="sub">Vitajte späť. Zadajte svoje údaje.</div>
          </div>
          <div className="cm-stack" style={{gap: 18}}>
            <div className="cm-field">
              <label>E-mail</label>
              <div className="cm-input-icon">
                <Icon name="mail" />
                <input className="cm-input" type="email" defaultValue="maria.kovacova@cleanmaster.sk" />
              </div>
            </div>
            <div className="cm-field">
              <label>Heslo</label>
              <div className="cm-input-icon" style={{position:'relative'}}>
                <Icon name="lock" />
                <input className="cm-input" type={showPw?'text':'password'} defaultValue="••••••••••" style={{paddingRight: 40}} />
                <div onClick={() => setShowPw(!showPw)} style={{position:'absolute', right: 8, top: '50%', transform:'translateY(-50%)', cursor:'pointer', color:'var(--n400)', padding: 6}}><Icon name="eye" /></div>
              </div>
            </div>
            <div style={{display:'flex', justifyContent:'space-between', alignItems:'center'}}>
              <div onClick={() => setRemember(!remember)} style={{display:'flex', alignItems:'center', gap: 8, cursor:'pointer'}}>
                <span className={'cm-checkbox' + (remember?' on':'')}><Icon name="check" size={12} /></span>
                <span style={{fontSize: 13, color:'var(--n700)'}}>Zapamätať si ma</span>
              </div>
              <a className="cm-link" style={{fontSize: 13}}>Zabudli ste heslo?</a>
            </div>
            <button className="cm-btn cm-btn-primary cm-btn-lg" style={{width:'100%', justifyContent:'center'}}>Prihlásiť sa</button>
            <div className="cm-divider"></div>
            <div style={{display:'flex', justifyContent:'center', gap: 12, fontSize: 12, color:'var(--n500)'}}>
              <span style={{fontWeight:600, color:'var(--n800)'}}>SK</span>
              <span>|</span>
              <span>EN</span>
              <span>|</span>
              <span>UA</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
window.LoginScreen = LoginScreen;
