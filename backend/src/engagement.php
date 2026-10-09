<?php
declare(strict_types=1);

function engagementCard(string $token): array {
    $card=one("SELECT id,customer_id,campaign_id,status FROM cards WHERE public_token=? AND status!='Revoked'",[$token]);
    if (!$card) fail('Tarjeta no disponible.',404);
    return $card;
}
function promotionAvailability(array $promotion): string {
    if (!(bool)$promotion['published']) return 'draft';
    if ($promotion['start_date'].' '.($promotion['start_time'] ?? '00:00:00') > date('Y-m-d H:i:s')) return 'scheduled';
    if ($promotion['end_date'].' '.($promotion['end_time'] ?? '23:59:59') < date('Y-m-d H:i:s')) return 'expired';
    return 'active';
}
function promotionFields(array $data): array {
    $title=textField($data,'title'); $description=textField($data,'description',4000);
    $benefit=textField($data,'benefit',255); $terms=textField($data,'terms',4000,false);
    $start=textField($data,'start_date',10); $end=textField($data,'end_date',10);
    foreach ([$start,$end] as $value) {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if (!$date || $date->format('Y-m-d') !== $value || (int)$date->format('Y')<1000) fail('Revisa las fechas de la promoción.');
    }
    $times=[];
    foreach (['start_time'=>'00:00:00','end_time'=>'23:59:59'] as $key=>$default) {
        $time=array_key_exists($key,$data) ? textField($data,$key,8) : $default;
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/',$time)) fail('Revisa las horas de la promoción.');
        $times[]=strlen($time)===5 ? $time.':00' : $time;
    }
    if ($start.' '.$times[0] > $end.' '.$times[1]) fail('La fecha y hora de fin deben ser iguales o posteriores al inicio.');
    $campaign=textField($data,'campaign_id',32,false);
    if ($campaign !== '' && !one('SELECT id FROM campaigns WHERE id=?',[$campaign])) fail('Campaña inexistente.');
    return [$title,$description,$benefit,$terms,$start,$end,...$times,$campaign ?: null];
}
function syncPromotionAnnouncement(array $promotion): void {
    query('INSERT INTO customer_notifications (id,title,message,promotion_id,campaign_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),message=VALUES(message),campaign_id=VALUES(campaign_id),enabled=1',[
        uid(),$promotion['title'],$promotion['description'],$promotion['id'],$promotion['campaign_id'],
    ]);
}
function customerNotifications(array $card): array {
    return rows("SELECT n.id,n.title,n.message,n.promotion_id,n.created_at,r.read_at,p.start_date,p.end_date,p.start_time,p.end_time,
        CASE WHEN p.id IS NULL THEN 'notice' WHEN TIMESTAMP(p.start_date,p.start_time)>NOW() THEN 'scheduled' WHEN TIMESTAMP(p.end_date,p.end_time)<NOW() THEN 'expired' ELSE 'active' END AS availability
        FROM customer_notifications n
        LEFT JOIN notification_reads r ON r.notification_id=n.id AND r.customer_id=?
        LEFT JOIN promotions p ON p.id=n.promotion_id
        WHERE n.enabled=1 AND (n.customer_id IS NULL OR n.customer_id=?)
          AND (n.campaign_id IS NULL OR n.campaign_id=?)
          AND (n.promotion_id IS NULL OR p.published=1)
        ORDER BY n.created_at DESC,n.id DESC",[$card['customer_id'],$card['customer_id'],$card['campaign_id']]);
}
function handlePublicEngagement(string $path,string $method): void {
    if (!preg_match('#^/api/public/cards/([a-f0-9]{64})/(engagement|notifications/([a-f0-9]{32})/read|promotions/([a-f0-9]{32})/request)$#',$path,$m)) return;
    $card=engagementCard($m[1]);
    if ($m[2]==='engagement' && $method==='GET') {
        $promotions=rows('SELECT id,title,description,benefit,terms,start_date,end_date,start_time,end_time,published FROM promotions WHERE published=1 AND TIMESTAMP(end_date,end_time)>=NOW() AND (campaign_id IS NULL OR campaign_id=?) ORDER BY start_date DESC,created_at DESC',[$card['campaign_id']]);
        foreach ($promotions as &$promotion) $promotion['availability']=promotionAvailability($promotion);
        unset($promotion);
        $notifications=customerNotifications($card);
        $requests=rows('SELECT id,promotion_id,code,promotion_title,benefit,status,customer_note,admin_note,created_at,updated_at FROM promotion_requests WHERE customer_id=? ORDER BY created_at DESC',[$card['customer_id']]);
        respond(['promotions'=>$promotions,'notifications'=>$notifications,'requests'=>$requests,'unread_count'=>count(array_filter($notifications,fn($n)=>$n['read_at']===null))]);
    }
    if (isset($m[3]) && $m[3]!=='' && $method==='POST') {
        $notifications=customerNotifications($card);
        if (!in_array($m[3],array_column($notifications,'id'),true)) fail('Notificación no disponible.',404);
        query('INSERT IGNORE INTO notification_reads (notification_id,customer_id) VALUES (?,?)',[$m[3],$card['customer_id']]);
        respond(['ok'=>true]);
    }
    if (isset($m[4]) && $m[4]!=='' && $method==='POST') {
        $note=textField(body(),'customer_note',500,false);
        db()->beginTransaction();
        // Recheck the card under lock so revocation cannot race a request.
        $locked=one('SELECT status FROM cards WHERE id=? FOR UPDATE',[$card['id']]);
        if ($locked['status']!=='Active') fail('Necesitas una tarjeta activa para solicitar promociones.');
        $promotion=one('SELECT * FROM promotions WHERE id=? FOR UPDATE',[$m[4]]);
        if (!$promotion || ($promotion['campaign_id']!==null && $promotion['campaign_id']!==$card['campaign_id'])) fail('Promoción no disponible.',404);
        if (promotionAvailability($promotion)!=='active') fail('La promoción no está vigente.');
        $existing=one('SELECT id,code,status FROM promotion_requests WHERE promotion_id=? AND customer_id=?',[$promotion['id'],$card['customer_id']]);
        if ($existing) {db()->commit(); respond(['request'=>$existing,'already_requested'=>true]);}
        $id=uid(); $code=strtoupper(bin2hex(random_bytes(6)));
        query('INSERT INTO promotion_requests (id,promotion_id,customer_id,card_id,code,promotion_title,benefit,customer_note) VALUES (?,?,?,?,?,?,?,?)',[
            $id,$promotion['id'],$card['customer_id'],$card['id'],$code,$promotion['title'],$promotion['benefit'],$note,
        ]);
        query('INSERT INTO promotion_request_events (id,request_id,actor_name,from_status,to_status,note) VALUES (?,?,?, ?,?,?)',[uid(),$id,'Cliente','','pending',$note]);
        db()->commit(); respond(['request'=>['id'=>$id,'code'=>$code,'status'=>'pending'],'already_requested'=>false],201);
    }
    fail('Ruta no encontrada.',404);
}
function handleAdminEngagement(string $path,string $method,array $actor): void {
    if ($path==='/api/engagement' && $method==='GET') {
        $promotions=$actor['role']==='owner' ? rows('SELECT p.*,c.name AS campaign_name,(SELECT COUNT(*) FROM promotion_requests r WHERE r.promotion_id=p.id) AS request_count FROM promotions p LEFT JOIN campaigns c ON c.id=p.campaign_id ORDER BY p.created_at DESC') : [];
        foreach ($promotions as &$promotion) $promotion['availability']=promotionAvailability($promotion);
        unset($promotion);
        $requests=rows('SELECT r.*,cu.name AS customer_name,cu.email AS customer_email FROM promotion_requests r JOIN customers cu ON cu.id=r.customer_id ORDER BY r.created_at DESC');
        $notices=$actor['role']==='owner' ? rows('SELECT n.*,c.name AS campaign_name,(SELECT COUNT(*) FROM notification_reads r WHERE r.notification_id=n.id) AS read_count FROM customer_notifications n LEFT JOIN campaigns c ON c.id=n.campaign_id WHERE n.promotion_id IS NULL AND n.customer_id IS NULL ORDER BY n.created_at DESC') : [];
        respond(['promotions'=>$promotions,'requests'=>$requests,'notices'=>$notices,'events'=>rows('SELECT * FROM promotion_request_events ORDER BY created_at DESC')]);
    }
    if ($path==='/api/promotions' && $method==='POST') {
        owner(); $fields=promotionFields(body()); $id=uid();
        query('INSERT INTO promotions (title,description,benefit,terms,start_date,end_date,start_time,end_time,campaign_id,id,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[...$fields,$id,$actor['id']]);
        respond(['id'=>$id],201);
    }
    if (preg_match('#^/api/promotions/([a-f0-9]{32})$#',$path,$m) && $method==='PATCH') {
        owner(); $data=body(); db()->beginTransaction();
        $promotion=one('SELECT * FROM promotions WHERE id=? FOR UPDATE',[$m[1]]);
        if (!$promotion) fail('Promoción inexistente.',404);
        if (array_key_exists('published',$data)) {
            if (!is_bool($data['published'])) fail('Estado inválido.');
            if ($data['published'] && $promotion['end_date'].' '.$promotion['end_time']<date('Y-m-d H:i:s')) fail('Actualiza las fechas y horas antes de publicar una promoción vencida.');
            query('UPDATE promotions SET published=? WHERE id=?',[(int)$data['published'],$m[1]]);
        } else {
            $fields=promotionFields($data);
            query('UPDATE promotions SET title=?,description=?,benefit=?,terms=?,start_date=?,end_date=?,start_time=?,end_time=?,campaign_id=? WHERE id=?',[...$fields,$m[1]]);
        }
        $promotion=one('SELECT * FROM promotions WHERE id=?',[$m[1]]);
        if ($promotion['published']) syncPromotionAnnouncement($promotion);
        db()->commit(); respond(['ok'=>true]);
    }
    if ($path==='/api/customer-notifications' && $method==='POST') {
        owner(); $data=body(); $campaign=textField($data,'campaign_id',32,false);
        if ($campaign!=='' && !one('SELECT id FROM campaigns WHERE id=?',[$campaign])) fail('Campaña inexistente.');
        $id=uid(); query('INSERT INTO customer_notifications (id,title,message,campaign_id) VALUES (?,?,?,?)',[$id,textField($data,'title'),textField($data,'message',4000),$campaign ?: null]);
        respond(['id'=>$id],201);
    }
    if (preg_match('#^/api/customer-notifications/([a-f0-9]{32})$#',$path,$m) && $method==='PATCH') {
        owner(); $data=body(); if (!is_bool($data['enabled'] ?? null)) fail('Estado inválido.');
        if (!one('SELECT id FROM customer_notifications WHERE id=? AND promotion_id IS NULL AND customer_id IS NULL',[$m[1]])) fail('Aviso inexistente.',404);
        query('UPDATE customer_notifications SET enabled=? WHERE id=?',[(int)$data['enabled'],$m[1]]); respond(['ok'=>true]);
    }
    if (preg_match('#^/api/promotion-requests/([a-f0-9]{32})$#',$path,$m) && $method==='PATCH') {
        $data=body(); $status=textField($data,'status',15); $note=textField($data,'admin_note',500,false);
        db()->beginTransaction(); $request=one('SELECT * FROM promotion_requests WHERE id=? FOR UPDATE',[$m[1]]);
        if (!$request) fail('Solicitud inexistente.',404);
        if ($request['status']===$status) {db()->commit();respond(['ok'=>true]);}
        $allowed=['pending'=>['approved','rejected'],'approved'=>['delivered','rejected'],'delivered'=>[],'rejected'=>[]];
        if (!in_array($status,$allowed[$request['status']],true)) fail('Este cambio de estado no está permitido.');
        query('UPDATE promotion_requests SET status=?,admin_note=? WHERE id=?',[$status,$note,$request['id']]);
        query('INSERT INTO promotion_request_events (id,request_id,actor_id,actor_name,from_status,to_status,note) VALUES (?,?,?,?,?,?,?)',[uid(),$request['id'],$actor['id'],$actor['name'],$request['status'],$status,$note]);
        $messages=['approved'=>'Tu solicitud fue aprobada. Presenta tu código en Terranova.','delivered'=>'Registramos la entrega de tu promoción. ¡Gracias por visitarnos!','rejected'=>'Tu solicitud no fue aprobada.'];
        query('INSERT INTO customer_notifications (id,title,message,customer_id) VALUES (?,?,?,?)',[uid(),$request['promotion_title'],$messages[$status].($note!==''?' '.$note:''),$request['customer_id']]);
        db()->commit(); respond(['ok'=>true]);
    }
}
