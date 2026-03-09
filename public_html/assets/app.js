const STORE={name:"Chun Tian (démo)",address:"151 Rue Jean Jaurès, 94800 Villejuif, France",coords:{lat:48.792716,lon:2.359279},delivery_km:5,openingHours:{Mon:["09:00","19:00"],Tue:["09:00","19:00"],Wed:["09:00","19:00"],Thu:["09:00","19:00"],Fri:["09:00","19:00"],Sat:["09:00","18:00"],Sun:null},slotEveryMinutes:30};
const LS={get(k,f){try{const v=localStorage.getItem(k);return v?JSON.parse(v):f}catch{return f}},set(k,v){localStorage.setItem(k,JSON.stringify(v))}};
async function loadProducts(){try{const r=await fetch("/api/products.php",{cache:"no-store"});if(r.ok){const j=await r.json();if(j&&j.ok&&Array.isArray(j.products))return j.products}}catch{}try{const raw=localStorage.getItem("products");if(raw){const p=JSON.parse(raw);if(Array.isArray(p))return p}}catch{}try{const r=await fetch("assets/products.json",{cache:"no-store"});const p=await r.json();if(Array.isArray(p))return p}catch{}return[]}
let PRODUCTS_PROMISE;function getProducts(){return PRODUCTS_PROMISE??=(loadProducts())}
function saveProducts(list){LS.set("products",list)}
function getCart(){return LS.get("cart",[])}
function saveCart(cart){LS.set("cart",cart);updateCartBadge()}
function normRipeness(r){return r||null}
function addToCart(id,qty=1,ripeness=null){const key=normRipeness(ripeness);const cart=getCart();const f=cart.find(i=>i.id===id&&normRipeness(i.ripeness)===key);if(f){f.qty+=qty}else{cart.push({id,qty,ripeness:key})}saveCart(cart)}
function setQty(id,qty,ripeness=null){const key=normRipeness(ripeness);let cart=getCart().map(i=>i.id===id&&normRipeness(i.ripeness)===key?{...i,qty}:i).filter(i=>i.qty>0);saveCart(cart)}
function clearCart(){saveCart([])}
function updateCartBadge(){const el=document.querySelector("#cartCount");if(!el)return;const count=getCart().reduce((s,i)=>s+i.qty,0);el.textContent=String(count)}
async function findProduct(id){const list=await getProducts();return list.find(p=>p.id===id)}
async function listByTag(tag){const list=await getProducts();return list.filter(p=>(p.tags||[]).includes(tag))}
function money(n){return Number(n||0).toLocaleString("fr-FR",{style:"currency",currency:"EUR"})}
function fmtDate(d){return new Date(d).toLocaleString("fr-FR")}

// === Favoris produits ===
let FAVORITES = new Set();

async function loadFavorites(){
  try{
    const res = await fetch('/api/favorites.php');
    const data = await res.json();
    if(!res.ok || !data.ok || !Array.isArray(data.favorites)) return;
    FAVORITES = new Set(data.favorites);
  }catch(e){
    // ignore erreurs de favoris
  }
}

async function toggleFavorite(id){
  try{
    const res = await fetch('/api/favorites.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({product_id:id})
    });
    if(res.status === 401){
      flash('Connectez-vous pour ajouter des favoris', false);
      window.location.href = 'compte.html#login';
      return null;
    }
    const data = await res.json();
    if(!res.ok || !data.ok){
      flash('Erreur favoris', false);
      return null;
    }
    if(data.favorited){
      FAVORITES.add(id);
    }else{
      FAVORITES.delete(id);
    }
    return data.favorited;
  }catch(e){
    flash('Erreur favoris', false);
    return null;
  }
}
function productCard(p){
const onSale=Number.isFinite(p.oldPrice)&&p.oldPrice>p.price;
const badge=onSale?`<span class="badge sale">Promo</span>`:p.featured?`<span class="badge">⭐️</span>`:"";
const was=onSale?`<span class="was">${money(p.oldPrice)}</span>`:"";
const ripenessSelect=p.ripeness_enabled?`
    <div class="ripeness">
      <label class="small">Mûreté
        <select data-ripeness class="input">
          <option value="Mûre">Mûre</option>
          <option value="Presque mûre">Presque mûre</option>
          <option value="Pas Mûre">Pas Mûre</option>
        </select>
      </label>
    </div>`:"";
return`
<article class="card" data-product-id="${p.id}">
  <div class="thumb">
    ${badge}
    <button type="button" class="fav-btn ${FAVORITES.has(p.id)?'fav-active':''}" data-fav="${p.id}" aria-label="Ajouter aux favoris">❤</button>
    <img alt="${p.name}" src="${p.image||""}"/>
  </div>
  <div class="content">
    <div class="title">${p.name}</div>
    <div class="origin">ORIGINE : ${p.origin||""}</div>
    ${ripenessSelect}
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
// Custom dropdown enhancement for selected <select> elements
(function(){
  function enhanceSelect(select){
    if(!select || select.dataset.enhanced) return;
    select.dataset.enhanced = "1";
    const wrapper = document.createElement("div");
    wrapper.className = "select fancy-select";
    const parent = select.parentNode;
    parent.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    select.classList.add("native-select");
    const trigger = document.createElement("button");
    trigger.type = "button";
    trigger.className = "select-trigger";
    trigger.setAttribute("aria-haspopup","listbox");
    trigger.setAttribute("aria-expanded","false");
    const labelSpan = document.createElement("span");
    labelSpan.className = "select-label";
    trigger.appendChild(labelSpan);
    wrapper.insertBefore(trigger, select);
    const menu = document.createElement("ul");
    menu.className = "select-menu";
    menu.setAttribute("role","listbox");
    wrapper.appendChild(menu);
    function syncFromSelect(){
      const opts = Array.from(select.options);
      const sel = select.selectedIndex >= 0 ? select.options[select.selectedIndex] : opts[0];
      labelSpan.textContent = sel ? sel.textContent : "";
      menu.innerHTML = "";
      opts.forEach(opt=>{
        const li = document.createElement("li");
        li.className = "select-option";
        if(opt === sel) li.classList.add("is-active");
        li.dataset.value = opt.value;
        li.textContent = opt.textContent;
        menu.appendChild(li);
      });
    }
    syncFromSelect();
    trigger.addEventListener("click", e=>{
      const open = !wrapper.classList.contains("is-open");
      document.querySelectorAll(".fancy-select.is-open").forEach(fs=>{ if(fs!==wrapper) fs.classList.remove("is-open"); });
      wrapper.classList.toggle("is-open", open);
      trigger.setAttribute("aria-expanded", open ? "true" : "false");
    });
    menu.addEventListener("click", e=>{
      const option = e.target.closest(".select-option");
      if(!option) return;
      const value = option.dataset.value;
      select.value = value;
      select.dispatchEvent(new Event("change", {bubbles:true}));
      syncFromSelect();
      wrapper.classList.remove("is-open");
      trigger.setAttribute("aria-expanded","false");
    });
    select.addEventListener("change", syncFromSelect);
  }
  window.initFancySelects = function(root){
    (root || document).querySelectorAll("select.js-fancy").forEach(enhanceSelect);
  };
  document.addEventListener("click", e=>{
    document.querySelectorAll(".fancy-select.is-open").forEach(fs=>{
      if(!fs.contains(e.target)){
        const btn = fs.querySelector(".select-trigger");
        fs.classList.remove("is-open");
        if(btn) btn.setAttribute("aria-expanded","false");
      }
    });
  });
})();
function dayKey(d){return["Sun","Mon","Tue","Wed","Thu","Fri","Sat"][d.getDay()]}
function makeSlots(date){const key=dayKey(date);const range=STORE.openingHours[key];if(!range)return[];const[start,end]=range;const[sh,sm]=start.split(":").map(Number);const[eh,em]=end.split(":").map(Number);const slots=[];const step=STORE.slotEveryMinutes;const t0=new Date(date.getFullYear(),date.getMonth(),date.getDate(),sh,sm);const t1=new Date(date.getFullYear(),date.getMonth(),date.getDate(),eh,em);for(let t=new Date(t0);t<t1;t=new Date(t.getTime()+step*60000)){slots.push(new Date(t))}return slots}
function haversine(lat1,lon1,lat2,lon2){const R=6371;const toRad=x=>x*Math.PI/180;const dLat=toRad(lat2-lat1);const dLon=toRad(lon2-lon1);const a=Math.sin(dLat/2)**2+Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLon/2)**2;const c=2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));return R*c}
function getOrders(){return LS.get("orders",[])}
function saveOrders(x){LS.set("orders",x)}
function createOrder(payload){const id="ord_"+Math.random().toString(36).slice(2,8);const order={id,status:"pending",created:new Date().toISOString(),...payload};const all=getOrders();all.push(order);saveOrders(all);return order}
function setOrderStatus(id,status){const all=getOrders().map(o=>o.id===id?{...o,status,updated:new Date().toISOString()}:o);saveOrders(all)}


async function initAccountLinks(){
  const box = document.querySelector('.account-links');
  if(!box) return;
  try{
    const res = await fetch('/api/auth-me.php');
    const data = await res.json();
    const user = data.user || null;
    if(!user) return; // garder les boutons par défaut
    if(user.is_admin){
      box.innerHTML = `
        <a href="admin.html" class="btn primary">Espace gérant</a>
        <button type="button" class="btn danger nav-logout">Déconnexion</button>
      `;
    }else{
      box.innerHTML = `
        <a href="compte.html" class="btn">Mon compte</a>
        <button type="button" class="btn danger nav-logout">Déconnexion</button>
      `;
    }
    const logoutBtn = box.querySelector('.nav-logout');
    if(logoutBtn){
      logoutBtn.addEventListener('click', async ()=>{
        try{
          await fetch('/api/auth-logout.php', {method:'POST'});
          flash('Déconnecté', true);
          window.location.href = 'index.html';
        }catch(e){
          flash('Erreur déconnexion', false);
        }
      });
    }
  }catch(e){
    // en cas d'erreur, on laisse les boutons par défaut
  }
}

function mountHeader(active=""){
  document.body.insertAdjacentHTML("afterbegin", `
    <header class="site">
      <div class="nav">
        <div class="brand"><a href="index.html"><img src="assets/logo.png" alt="Chun Tian" class="logo"></a></div>
        <nav class="navlinks">
          <a href="index.html" class="${active==='home'?'active':''}">Accueil</a>
          <a href="fruits.html" class="${active==='fruits'?'active':''}">Fruits</a>
          <a href="legumes.html" class="${active==='legumes'?'active':''}">Légumes</a>
          <a href="herbes.html" class="${active==='herbes'?'active':''}">Herbes</a>
          <a href="contact.html" class="${active==='contact'?'active':''}">Contact</a>
          <a href="apropos.html" class="${active==='about'?'active':''}">À propos</a>
        </nav>
        <div class="account-links">
          <a href="compte.html#login" class="btn">Se connecter</a>
          <a href="compte.html#register" class="btn">S'inscrire</a>
        </div>
        <a class="cart" href="panier.html"><img src="assets/panier_vide.jpg" alt="Panier" class="cart-icon"><span id="cartCount">0</span></a>
      </div>
    </header>
  `);
  updateCartBadge();
  initAccountLinks();
}


function flash(msg,ok=true){const el=document.createElement("div");el.textContent=msg;el.style.position="fixed";el.style.bottom="16px";el.style.left="50%";el.style.transform="translateX(-50%)";el.style.background=ok?"#111827":"#ef4444";el.style.color="#fff";el.style.borderRadius="999px";el.style.padding="10px 16px";el.style.zIndex="9999";el.style.boxShadow="0 4px 20px rgba(0,0,0,.2)";document.body.appendChild(el);setTimeout(()=>el.remove(),1400)}
