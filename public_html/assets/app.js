const STORE={name:"Chun Tian (démo)",address:"151 Rue Jean Jaurès, 94800 Villejuif, France",coords:{lat:48.792716,lon:2.359279},delivery_km:5,openingHours:{Mon:["09:00","19:00"],Tue:["09:00","19:00"],Wed:["09:00","19:00"],Thu:["09:00","19:00"],Fri:["09:00","19:00"],Sat:["09:00","18:00"],Sun:null},slotEveryMinutes:30};
const LS={get(k,f){try{const v=localStorage.getItem(k);return v?JSON.parse(v):f}catch{return f}},set(k,v){localStorage.setItem(k,JSON.stringify(v))}};
async function loadProducts(){try{const r=await fetch("/api/products.php",{cache:"no-store"});if(r.ok){const j=await r.json();if(j&&j.ok&&Array.isArray(j.products))return j.products}}catch{}try{const raw=localStorage.getItem("products");if(raw){const p=JSON.parse(raw);if(Array.isArray(p))return p}}catch{}try{const r=await fetch("assets/products.json",{cache:"no-store"});const p=await r.json();if(Array.isArray(p))return p}catch{}return[]}
let PRODUCTS_PROMISE;function getProducts(){return PRODUCTS_PROMISE??=(loadProducts())}
function saveProducts(list){LS.set("products",list)}
function getCart(){return LS.get("cart",[])}
function saveCart(cart){LS.set("cart",cart);updateCartBadge()}
function addToCart(id,qty=1){const cart=getCart();const f=cart.find(i=>i.id===id);if(f){f.qty+=qty}else{cart.push({id,qty})}saveCart(cart)}
function setQty(id,qty){let cart=getCart().map(i=>i.id===id?{...i,qty}:i).filter(i=>i.qty>0);saveCart(cart)}
function clearCart(){saveCart([])}
function updateCartBadge(){const el=document.querySelector("#cartCount");if(!el)return;const count=getCart().reduce((s,i)=>s+i.qty,0);el.textContent=String(count)}
async function findProduct(id){const list=await getProducts();return list.find(p=>p.id===id)}
async function listByTag(tag){const list=await getProducts();return list.filter(p=>(p.tags||[]).includes(tag))}
function money(n){return Number(n||0).toLocaleString("fr-FR",{style:"currency",currency:"EUR"})}
function fmtDate(d){return new Date(d).toLocaleString("fr-FR")}
function productCard(p){const onSale=Number.isFinite(p.oldPrice)&&p.oldPrice>p.price;const badge=onSale?`<span class="badge sale">Promo</span>`:p.featured?`<span class="badge">⭐️</span>`:"";const was=onSale?`<span class="was">${money(p.oldPrice)}</span>`:"";return`
<article class="card">
  <div class="thumb">${badge}<img alt="${p.name}" src="${p.image||""}"/></div>
  <div class="content">
    <div class="title">${p.name}</div>
    <div class="origin">ORIGINE : ${p.origin||""}</div>
    <div class="price"><span class="now">${money(p.price)}</span> ${was}</div>
    <div class="qty">
      <select data-unit>
        <option>${p.unit||"1 pièce"}</option>
        <option>+ 1</option><option>+ 2</option>
      </select>
      <button class="btn primary" data-add="${p.id}">Ajouter</button>
    </div>
  </div>
</article>`}
function dayKey(d){return["Sun","Mon","Tue","Wed","Thu","Fri","Sat"][d.getDay()]}
function makeSlots(date){const key=dayKey(date);const range=STORE.openingHours[key];if(!range)return[];const[start,end]=range;const[sh,sm]=start.split(":").map(Number);const[eh,em]=end.split(":").map(Number);const slots=[];const step=STORE.slotEveryMinutes;const t0=new Date(date.getFullYear(),date.getMonth(),date.getDate(),sh,sm);const t1=new Date(date.getFullYear(),date.getMonth(),date.getDate(),eh,em);for(let t=new Date(t0);t<t1;t=new Date(t.getTime()+step*60000)){slots.push(new Date(t))}return slots}
function haversine(lat1,lon1,lat2,lon2){const R=6371;const toRad=x=>x*Math.PI/180;const dLat=toRad(lat2-lat1);const dLon=toRad(lon2-lon1);const a=Math.sin(dLat/2)**2+Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLon/2)**2;const c=2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));return R*c}
function getOrders(){return LS.get("orders",[])}
function saveOrders(x){LS.set("orders",x)}
function createOrder(payload){const id="ord_"+Math.random().toString(36).slice(2,8);const order={id,status:"pending",created:new Date().toISOString(),...payload};const all=getOrders();all.push(order);saveOrders(all);return order}
function setOrderStatus(id,status){const all=getOrders().map(o=>o.id===id?{...o,status,updated:new Date().toISOString()}:o);saveOrders(all)}


function mountHeader(active=""){
  document.body.insertAdjacentHTML("afterbegin", `
    <header class="site">
      <div class="nav">
        <div class="brand"><a href="index.html"><img src="assets/logo.png" alt="Chun Tian" class="logo"></a></div>
        <nav class="navlinks">
          <a href="index.html" class="${active==='home'?'active':''}">Accueil</a>
          <a href="fruits.html" class="${active==='fruits'?'active':''}">Fruits</a>
          <a href="legumes.html" class="${active==='legumes'?'active':''}">Légumes</a>
          <a href="contact.html" class="${active==='contact'?'active':''}">Contact</a>
          <a href="apropos.html" class="${active==='about'?'active':''}">À propos</a>
        </nav>
        <a class="cart" href="panier.html"><img src="assets/panier_vide.jpg" alt="Panier" class="cart-icon"><span id="cartCount">0</span></a>
      </div>
    </header>
  `);
  updateCartBadge();
}


function flash(msg,ok=true){const el=document.createElement("div");el.textContent=msg;el.style.position="fixed";el.style.bottom="16px";el.style.left="50%";el.style.transform="translateX(-50%)";el.style.background=ok?"#111827":"#ef4444";el.style.color="#fff";el.style.borderRadius="999px";el.style.padding="10px 16px";el.style.zIndex="9999";el.style.boxShadow="0 4px 20px rgba(0,0,0,.2)";document.body.appendChild(el);setTimeout(()=>el.remove(),1400)}
