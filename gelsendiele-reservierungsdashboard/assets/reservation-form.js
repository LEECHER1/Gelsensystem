(function(){
'use strict';
const cfg=window.GDReservationForm||{};
const pad=n=>String(n).padStart(2,'0');
const iso=d=>`${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

async function readJsonResponse(response){
  const text=await response.text();
  try{return JSON.parse(text);}catch(e){
    const starts=[text.indexOf('{'),text.indexOf('[')].filter(v=>v>=0).sort((a,b)=>a-b);
    const first=starts.length?starts[0]:-1;
    const last=Math.max(text.lastIndexOf('}'),text.lastIndexOf(']'));
    if(first!==-1&&last>first){try{return JSON.parse(text.slice(first,last+1));}catch(ignore){}}
    throw new Error(`Ungültige Serverantwort (${response.status})`);
  }
}

document.querySelectorAll('[data-gdrf-form]').forEach(form=>{
  const date=form.elements.date,time=form.elements.time,party=form.elements.party;
  const msg=form.querySelector('[data-gdrf-message]');
  const btn=form.querySelector('button[type=submit]');
  const initialButtonText=btn.textContent;
  const dateNotice=form.querySelector('[data-gdrf-date-notice]');
  const dateBtn=form.querySelector('[data-gdrf-date-button]');
  const dateLabel=form.querySelector('[data-gdrf-date-label]');
  const calendar=form.querySelector('[data-gdrf-calendar]');
  const monthLabel=form.querySelector('[data-gdrf-month]');
  const daysWrap=form.querySelector('[data-gdrf-days]');
  const weekdaysWrap=form.querySelector('[data-gdrf-weekdays]');
  const prev=form.querySelector('[data-gdrf-prev]');
  const next=form.querySelector('[data-gdrf-next]');
  const today=new Date(`${cfg.today}T00:00:00`);
  const maxDate=new Date(`${cfg.maxDate}T23:59:59`);
  let view=new Date(today.getFullYear(),today.getMonth(),1);
  let availability={};

  (cfg.locale?.weekdays||['Mo','Di','Mi','Do','Fr','Sa','So']).forEach(w=>{
    const el=document.createElement('span');el.textContent=w;weekdaysWrap.appendChild(el);
  });

  function formatDate(value){
    const d=new Date(`${value}T00:00:00`);
    return new Intl.DateTimeFormat('de-AT',{weekday:'short',day:'2-digit',month:'2-digit',year:'numeric'}).format(d);
  }

  async function slots(){
    time.disabled=true;
    time.innerHTML='<option value="">Lade Zeiten …</option>';
    if(!date.value){time.innerHTML='<option value="">Zuerst Datum wählen</option>';return;}
    const body=new URLSearchParams({action:'gd_public_slots',nonce:cfg.nonce,date:date.value,party:party.value||'1'});
    try{
      const r=await fetch(cfg.ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body,credentials:'same-origin',cache:'no-store'});
      const j=await readJsonResponse(r);
      const s=j.success&&j.data?j.data.slots:[];
      const notice=j.success&&j.data?String(j.data.notice||''):'';
      time.innerHTML=s.length?'<option value="">Uhrzeit wählen</option>'+s.map(v=>`<option value="${v}">${v} Uhr</option>`).join(''):'<option value="">Keine Zeit verfügbar</option>';
      time.disabled=!s.length;
      if(dateNotice){dateNotice.textContent=notice;dateNotice.hidden=!notice;}
    }catch(e){time.innerHTML='<option value="">Fehler beim Laden</option>';if(dateNotice){dateNotice.textContent='';dateNotice.hidden=true;}}
  }

  async function loadMonth(){
    daysWrap.classList.add('is-loading');
    const y=view.getFullYear(),m=view.getMonth()+1;
    const body=new URLSearchParams({action:'gd_public_month_availability',nonce:cfg.nonce,year:String(y),month:String(m),party:party.value||'1'});
    try{
      const r=await fetch(cfg.ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body,credentials:'same-origin',cache:'no-store'});
      const j=await readJsonResponse(r);
      availability=j.success&&j.data?j.data.dates:{};
    }catch(e){availability={};}
    renderCalendar();
    daysWrap.classList.remove('is-loading');
  }

  function renderCalendar(){
    const y=view.getFullYear(),m=view.getMonth();
    monthLabel.textContent=`${(cfg.locale?.months||[])[m]||new Intl.DateTimeFormat('de-AT',{month:'long'}).format(view)} ${y}`;
    daysWrap.innerHTML='';
    const first=new Date(y,m,1);
    const offset=(first.getDay()+6)%7;
    for(let i=0;i<offset;i++){const blank=document.createElement('span');blank.className='gdrf-day-blank';daysWrap.appendChild(blank);}
    const total=new Date(y,m+1,0).getDate();
    for(let d=1;d<=total;d++){
      const current=new Date(y,m,d),value=iso(current);
      const el=document.createElement('button');
      el.type='button';el.className='gdrf-day';el.textContent=String(d);el.dataset.date=value;
      const inRange=current>=today&&current<=maxDate;
      const available=availability[value]===true;
      if(!inRange||!available){el.disabled=true;el.classList.add('is-disabled');}
      if(value===cfg.today)el.classList.add('is-today');
      if(value===date.value)el.classList.add('is-selected');
      if(!el.disabled)el.addEventListener('click',()=>{
        date.value=value;dateLabel.textContent=formatDate(value);dateBtn.classList.add('has-value');
        calendar.hidden=true;dateBtn.setAttribute('aria-expanded','false');
        slots();renderCalendar();
      });
      daysWrap.appendChild(el);
    }
    prev.disabled=new Date(y,m,1)<=new Date(today.getFullYear(),today.getMonth(),1);
    next.disabled=new Date(y,m+1,1)>new Date(maxDate.getFullYear(),maxDate.getMonth(),1);
  }

  dateBtn.addEventListener('click',()=>{
    calendar.hidden=!calendar.hidden;
    dateBtn.setAttribute('aria-expanded',String(!calendar.hidden));
    if(!calendar.hidden)loadMonth();
  });
  prev.addEventListener('click',()=>{view=new Date(view.getFullYear(),view.getMonth()-1,1);loadMonth();});
  next.addEventListener('click',()=>{view=new Date(view.getFullYear(),view.getMonth()+1,1);loadMonth();});
  document.addEventListener('click',e=>{if(!calendar.hidden&&!calendar.contains(e.target)&&!dateBtn.contains(e.target)){calendar.hidden=true;dateBtn.setAttribute('aria-expanded','false');}});

  party.addEventListener('change',()=>{
    if(date.value)slots();
    if(!calendar.hidden)loadMonth();
  });

  form.addEventListener('submit',async e=>{
    e.preventDefault();
    msg.className='gdrf-message';msg.textContent='';
    if(!date.value){msg.textContent='Bitte wählen Sie ein verfügbares Datum.';msg.classList.add('is-error');return;}
    if(!form.reportValidity())return;
    btn.disabled=true;btn.textContent='Wird gesendet …';
    const data=new FormData(form);data.append('action','gd_public_create_booking');data.append('nonce',cfg.nonce);
    try{
      const r=await fetch(cfg.ajaxUrl,{method:'POST',body:data,credentials:'same-origin',cache:'no-store'});
      const j=await readJsonResponse(r);
      msg.textContent=(j.data&&j.data.message)||'Die Anfrage konnte nicht gesendet werden.';
      msg.classList.add(j.success?'is-success':'is-error');
      if(j.success){
        form.reset();date.value='';dateLabel.textContent='Datum auswählen';dateBtn.classList.remove('has-value');
        time.disabled=true;time.innerHTML='<option value="">Zuerst Datum wählen</option>';availability={};renderCalendar();
        msg.scrollIntoView({behavior:'smooth',block:'center'});
      }
    }catch(err){
      msg.textContent='Die Serverantwort konnte nicht verarbeitet werden. Bitte prüfen Sie, ob die Reservierung bereits eingegangen ist.';
      msg.classList.add('is-error');
    }finally{btn.disabled=false;btn.textContent=initialButtonText;}
  });
  renderCalendar();
});
})();
