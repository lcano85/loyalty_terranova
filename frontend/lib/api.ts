export const API_URL=process.env.NEXT_PUBLIC_API_URL || 'http://localhost:6010/api';
let csrf='';
export function setCsrf(value:string){csrf=value}
export async function api<T=unknown>(path:string,method='GET',data?:unknown):Promise<T>{
 const res=await fetch(`${API_URL}${path}`,{method,credentials:'include',headers:{'Content-Type':'application/json',...(method==='GET'?{}:{'X-CSRF-Token':csrf})},...(data===undefined?{}:{body:JSON.stringify(data)})});
 const payload=await res.json(); if(!res.ok) throw new Error(payload.error || 'No se pudo completar la solicitud.'); return payload;
}
export type Campaign={id:string;name:string;tagline:string;reward:string;total_stamps:number;color:string;background:string;enabled:number};
export type Customer={id:string;name:string;email:string|null;mobile:string;birth_date:string|null;active_cards:number};
export type Card={id:string;public_token:string;customer_id:string;customer_name:string;customer_email:string|null;campaign_id:string;snapshot:Campaign;stamps:number;status:'Active'|'Redeemed'|'Revoked';created_at:string;updated_at:string};
export type Transaction={id:string;card_id:string;type:string;amount:number;remarks:string;actor_name:string;actor_role:string;customer_name:string;customer_email:string;created_at:string;snapshot:string;public_token:string;campaign_id:string};
export type User={id:string;name:string;email:string;role:'owner'|'staff'};
export type Staff={id:string;name:string;email:string;active:number};
export type Workspace={business:{name:string;email:string;slug:string};campaigns:Campaign[];customers:Customer[];cards:Card[];transactions:Transaction[];staff:Staff[]};
