'use client';
import {useCallback,useEffect,useState,type ReactNode} from 'react';
import {Bell,Check,Gift,RefreshCw,WalletCards} from 'lucide-react';
import {api} from '../lib/api';
import {type EngagementCustomerData,requestStatus,shortDate,promotionDate} from '../lib/engagement';

export default function CustomerPortal({token,ready,active,children}:{token:string;ready:boolean;active:boolean;children:ReactNode}) {
  const [tab,setTab]=useState<'card'|'promotions'|'notifications'>('card');
  const [data,setData]=useState<EngagementCustomerData|null>(null);
  const [error,setError]=useState(''); const [notice,setNotice]=useState('');
  const [busy,setBusy]=useState(false); const [notes,setNotes]=useState<Record<string,string>>({});
  const base=`/public/cards/${token}`;
  const refresh=useCallback(async()=>{const next=await api<EngagementCustomerData>(`${base}/engagement`);setData(next)},[base]);
  useEffect(()=>{
    let alive=true;
    const load=()=>{if(alive)refresh().catch(e=>{if(alive)setError(e.message)})};
    load();
    const timer=setInterval(()=>{if(document.visibilityState==='visible')load()},60000);
    window.addEventListener('focus',load);
    return()=>{alive=false;clearInterval(timer);window.removeEventListener('focus',load)};
  },[refresh]);
  async function action(path:string,body:unknown={},message='') {
    if(busy||!ready)return;setBusy(true);setError('');setNotice('');
    try{await api(base+path,'POST',body);await refresh();setNotice(message)}
    catch(e){setError((e as Error).message)}finally{setBusy(false)}
  }
  return <section className="customer-portal">
    <header className="portal-heading"><div><span className="eyebrow">TU ESPACIO TERRANOVA</span><h1>Tus visitas tienen premio</h1><p className="muted">Tu tarjeta, promociones y novedades en un solo lugar.</p></div><button className="icon-btn" aria-label="Actualizar novedades" disabled={busy} onClick={()=>refresh().then(()=>setError('')).catch(e=>setError(e.message))}><RefreshCw size={19}/></button></header>
    <nav className="portal-tabs" aria-label="Panel del cliente">
      <button className={tab==='card'?'selected':''} onClick={()=>setTab('card')}><WalletCards size={18}/>Mi tarjeta</button>
      <button className={tab==='promotions'?'selected':''} onClick={()=>setTab('promotions')}><Gift size={18}/>Promociones</button>
      <button className={tab==='notifications'?'selected':''} onClick={()=>setTab('notifications')}><Bell size={18}/>Notificaciones{(data?.unread_count||0)>0&&<span className="unread-count">{data?.unread_count}</span>}</button>
    </nav>
    {error&&<div className="alert" role="alert">{error}</div>}
    {notice&&<div className="portal-success" role="status"><Check size={18}/>{notice}</div>}
    {tab==='card'&&<div className="portal-card-view">{children}{data&&<button className="portal-offer-link" onClick={()=>setTab('promotions')}><Gift size={20}/><span><strong>Descubre tus promociones</strong><small>{data.promotions.length} disponibles o próximas · {data.unread_count} novedades sin leer</small></span></button>}<p className="muted">Guarda este enlace para volver a tu tarjeta y bandeja.</p></div>}
    {tab==='promotions'&&<><div className="portal-section-title"><h2>Hechas para tu próxima visita</h2><p className="muted">Solicita una promoción vigente y sigue su estado aquí.</p></div>{!active&&<p className="alert">Tu tarjeta ya no está activa. Solicita una nueva en el local para pedir promociones.</p>}
      <div className="portal-offers">{data?.promotions.map(p=>{
        const request=data.requests.find(r=>r.promotion_id===p.id);
        return <article className="panel offer-card" key={p.id}><div className="offer-top"><span className="offer-icon"><Gift size={24}/></span><span className={`badge ${p.availability==='active'?'green':''}`}>{p.availability==='active'?'Vigente':'Próximamente'}</span></div><h2>{p.title}</h2><strong className="offer-benefit">{p.benefit}</strong><p className="preserve-lines">{p.description}</p><div className="offer-dates">Del {promotionDate(p.start_date,p.start_time)} al {promotionDate(p.end_date,p.end_time)} · hora de Lima</div>{p.terms&&<details><summary>Condiciones</summary><p className="muted preserve-lines">{p.terms}</p></details>}
        {request?<div className="offer-requested"><span className="badge blue">{requestStatus[request.status]}</span><small>Código: <strong className="mono">{request.code}</strong></small></div>:<><label className="offer-note">Mensaje opcional<input maxLength={500} value={notes[p.id]||''} onChange={e=>setNotes({...notes,[p.id]:e.target.value})} placeholder="¿Algo que debamos saber?"/></label><button className="primary full" disabled={busy||!ready||!active||p.availability!=='active'} onClick={()=>action(`/promotions/${p.id}/request`,{customer_note:notes[p.id]||''},'Solicitud enviada. Puedes consultar su estado en Mis solicitudes.')}>{p.availability==='active'?'Solicitar promoción':`Disponible desde ${promotionDate(p.start_date,p.start_time)}`}</button></>}
        </article>
      })}</div>{data&&!data.promotions.length&&<div className="panel empty">Pronto tendremos nuevas promociones para ti.</div>}
      <section className="panel"><h2>Mis solicitudes</h2><p className="muted">Presenta el código cuando tu solicitud esté aprobada. Cada promoción se puede solicitar una vez por cliente.</p>{data?.requests.map(r=><article className="customer-request" key={r.id}><div className="section-heading"><strong>{r.promotion_title}</strong><span className={`badge ${r.status==='approved'||r.status==='delivered'?'green':''}`}>{requestStatus[r.status]}</span></div><p>{r.benefit}</p><div className="request-code">{r.code}</div><small className="muted">Solicitada el {shortDate(r.created_at)}</small>{r.admin_note&&<p className="preserve-lines">Mensaje de Terranova: {r.admin_note}</p>}</article>)}{data&&!data.requests.length&&<p className="muted">Aún no has solicitado promociones.</p>}</section>
    </>}
    {tab==='notifications'&&<><div className="portal-section-title"><h2>Tu bandeja de novedades</h2><p className="muted">Promociones y mensajes de Terranova. Se actualiza mientras tienes la página abierta.</p></div>{data?.notifications.map(n=><article className={`panel notification-item ${n.read_at?'':'unread'}`} key={n.id}><div className="section-heading"><h2>{n.title}</h2><span className="badge">{n.read_at?'Leída':'Nueva'}</span></div><p className="preserve-lines">{n.message}</p><small className="muted">{shortDate(n.created_at)}</small>{n.promotion_id&&<p className="muted">{n.availability==='expired'?'Esta promoción ya finalizó.':`Válida del ${promotionDate(n.start_date!,n.start_time!)} al ${promotionDate(n.end_date!,n.end_time!)}.`}</p>}<div className="actions">{!n.read_at&&<button disabled={busy||!ready} onClick={()=>action(`/notifications/${n.id}/read`)}><Check size={16}/>Marcar como leída</button>}{n.promotion_id&&n.availability!=='expired'&&<button onClick={()=>{setTab('promotions');if(!n.read_at)void action(`/notifications/${n.id}/read`)}}>Ver promociones</button>}</div></article>)}{data&&!data.notifications.length&&<div className="panel empty"><Bell size={28}/><p>Tu bandeja está al día. Aquí aparecerán las novedades.</p></div>}</>}
    {!data&&!error&&<p className="muted">Cargando novedades…</p>}
  </section>;
}
