'use client';
import {useState} from 'react';
import {Coffee,Beer,Wine,Pizza,Utensils,Star,Heart,Gift,Flame,IceCreamBowl} from 'lucide-react';
import type {Campaign} from '../lib/api';

const options=[['coffee','Café',Coffee],['beer','Cerveza',Beer],['wine','Copa',Wine],['pizza','Pizza',Pizza],['utensils','Cubiertos',Utensils],['star','Estrella',Star],['heart','Corazón',Heart],['gift','Regalo',Gift],['flame','Llama',Flame],['ice-cream','Helado',IceCreamBowl]] as const;
export function StampIcon({name,size=25}:{name?:string;size?:number}) {
 const Icon=options.find(o=>o[0]===name)?.[2]||Coffee;
 return <Icon size={size}/>;
}
export default function CampaignBranding({campaign,onLoading}:{campaign?:Campaign;onLoading:(loading:boolean)=>void}) {
 const [logo,setLogo]=useState(campaign?.logo||'');
 const [icon,setIcon]=useState(campaign?.stamp_icon||'coffee');
 const [error,setError]=useState('');
 const [loading,setLoading]=useState(false);
 return <div className="wide campaign-branding">
  <label>Logo de la tarjeta<input type="file" accept="image/png,image/jpeg,image/webp" disabled={loading} onChange={async e=>{
   const file=e.target.files?.[0];e.target.value='';if(!file)return;
   setError('');
   if(!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>204800){setError('Selecciona un PNG, JPG o WebP de hasta 200 KB.');return}
   setLoading(true);onLoading(true);
   try {
    const value=await new Promise<string>((resolve,reject)=>{const reader=new FileReader();reader.onload=()=>resolve(String(reader.result));reader.onerror=()=>reject(new Error('No se pudo leer el logo.'));reader.readAsDataURL(file)});
    const img=new Image();img.src=value;await img.decode();
    if(img.width>2048||img.height>2048)throw new Error('El tamaño máximo es 2048 × 2048 píxeles.');
    setLogo(value);
   } catch(err){setError((err as Error).message)} finally{setLoading(false);onLoading(false)}
  }}/></label>
  <small className="muted">PNG, JPG o WebP · hasta 200 KB · máximo 2048 × 2048 píxeles.</small>
  <input type="hidden" name="logo" value={logo}/>
  {logo&&<div className="logo-preview"><img src={logo} alt="Logo seleccionado"/><button type="button" disabled={loading} onClick={()=>setLogo('')}>Quitar logo</button></div>}
  {error&&<p role="alert" className="danger">{error}</p>}
  {loading&&<p role="status">Leyendo logo…</p>}
  <label>Icono de los sellos</label><input type="hidden" name="stamp_icon" value={icon}/>
  <div className="stamp-options">{options.map(([value,label,Icon])=><button key={value} type="button" aria-pressed={icon===value} onClick={()=>setIcon(value)}><Icon size={22}/>{label}</button>)}</div>
  <p className="muted">Los sellos completados mantienen la marca de verificación.</p>
 </div>;
}
