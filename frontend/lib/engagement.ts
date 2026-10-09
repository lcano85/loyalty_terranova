export type Promotion = {
  id:string; title:string; description:string; benefit:string; terms:string;
  start_date:string; end_date:string; start_time:string; end_time:string; campaign_id?:string|null; campaign_name?:string|null;
  published:number; availability:'draft'|'scheduled'|'active'|'expired'; request_count?:number;
};
export type CustomerNotification = {
  id:string; title:string; message:string; promotion_id:string|null; created_at:string;
  read_at:string|null; start_date:string|null; end_date:string|null; start_time:string|null; end_time:string|null;
  availability:'notice'|'scheduled'|'active'|'expired';
};
export type PromotionRequest = {
  id:string; promotion_id:string; code:string; promotion_title:string; benefit:string;
  status:'pending'|'approved'|'delivered'|'rejected'; customer_note:string; admin_note:string;
  created_at:string; updated_at:string; customer_name?:string; customer_email?:string;
};
export type Notice = {id:string; title:string; message:string; campaign_name:string|null; enabled:number; read_count:number; created_at:string};
export type RequestEvent = {id:string; request_id:string; actor_name:string; from_status:string; to_status:string; note:string; created_at:string};
export type EngagementAdminData = {promotions:Promotion[]; requests:PromotionRequest[]; notices:Notice[]; events:RequestEvent[]};
export type EngagementCustomerData = {promotions:Promotion[]; requests:PromotionRequest[]; notifications:CustomerNotification[]; unread_count:number};
export const requestStatus = {pending:'Pendiente',approved:'Aprobada',delivered:'Entregada',rejected:'Rechazada'};
export const promotionStatus = {draft:'Borrador',scheduled:'Programada',active:'Vigente',expired:'Finalizada'};
export function shortDate(value:string) {return new Date(value.slice(0,10)+'T12:00:00').toLocaleDateString('es-PE',{day:'numeric',month:'short',year:'numeric'})}

export function promotionDate(date:string,time:string) {return `${shortDate(date)} ${time.slice(0,5)}`}
