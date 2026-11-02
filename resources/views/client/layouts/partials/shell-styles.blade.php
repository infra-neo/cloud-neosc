{{-- Styles of the signed-in shell (sidebar + content). Colours come from the
     same variables the rest of the client area uses, so the customer's
     branding and the dark mode apply here too. --}}
<style>
    body.nsc-shell{display:block}
    .nsc-app{display:grid;grid-template-columns:248px minmax(0,1fr);min-height:100vh}
    .nsc-side{background:#0e1522;color:#c9d3e3;display:flex;flex-direction:column;gap:2px;padding:16px 12px;position:sticky;top:0;height:100vh;overflow-y:auto;border-right:1px solid #1b2638}
    .nsc-brand{display:flex;align-items:center;gap:10px;padding:4px 8px 14px;color:#fff;font-weight:700;font-size:17px;text-decoration:none;letter-spacing:-.2px}
    .nsc-brand img{max-height:34px;max-width:170px}
    .nsc-mark{width:30px;height:30px;border-radius:8px;background:var(--primary);color:#fff;display:grid;place-items:center;font-weight:800;font-size:15px;flex:none}
    .nsc-grp{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6f7f99;padding:14px 10px 4px}
    .nsc-nav a{display:block;padding:7px 10px;border-radius:8px;font-size:13.5px;font-weight:500;color:#c9d3e3;text-decoration:none;transition:background .12s,color .12s}
    .nsc-nav a:hover{background:#17223a;color:#fff}
    .nsc-nav a.active{background:var(--primary);color:#fff}
    .nsc-acct{padding:4px 8px 6px}
    .nsc-acct label{display:block;font-size:11px;color:#6f7f99;margin-bottom:4px;text-transform:uppercase;letter-spacing:.06em}
    .nsc-acct select{width:100%;padding:7px 8px;border-radius:8px;border:1px solid #243049;background:#121c30;color:#e6ecf6;font:inherit;font-size:13px}
    .nsc-user{margin-top:auto;padding-top:14px;display:grid;gap:8px}
    .nsc-user-card{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;background:#121c30;border:1px solid #1f2b42;color:#fff;text-decoration:none;min-width:0}
    .nsc-av{width:30px;height:30px;border-radius:50%;background:var(--primary);display:grid;place-items:center;font-weight:700;font-size:12px;flex:none}
    .nsc-user-name{display:flex;flex-direction:column;font-size:13px;font-weight:600;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .nsc-user-name small{font-weight:400;color:#8a99b3;overflow:hidden;text-overflow:ellipsis}
    .nsc-logout{width:100%;background:none;border:1px solid #2a3750;color:#f19999;border-radius:8px;padding:7px;cursor:pointer;font:inherit;font-size:13px}
    .nsc-logout:hover{background:#2a1618}
    .nsc-main{min-width:0;display:flex;flex-direction:column;min-height:100vh}
    .nsc-top{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:10px 40px 0}
    .nsc-top .pn-nav-right{margin-left:0}
    .nsc-menu{display:none;margin-right:auto;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:6px 12px;font:inherit;font-weight:600;color:var(--text);cursor:pointer}
    .nsc-main .pn-main{padding-top:20px}
    @media(max-width:960px){
        .nsc-app{grid-template-columns:minmax(0,1fr)}
        .nsc-side{position:fixed;inset:0 auto 0 0;width:270px;z-index:1100;transform:translateX(-100%);transition:transform .2s}
        .nsc-side.open{transform:none;box-shadow:0 0 0 100vmax rgba(0,0,0,.55)}
        .nsc-menu{display:inline-block}
        .nsc-top{padding:10px 16px 0}
        .nsc-main .pn-main{padding-left:16px;padding-right:16px}
    }
</style>
