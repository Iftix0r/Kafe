<?php
// index.php — Premium Food WebApp (PHP Version 6.7)
$phpTgId = $_GET['tg_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Olmazor Go</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root { --p:#2481cc; --bg:#fff; --t:#1a1c1e; --sec:#f4f4f7; --r:20px; }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Outfit',sans-serif; -webkit-tap-highlight-color:transparent; }
        body { background:var(--bg) !important; color:var(--t) !important; padding-bottom:95px; }
        .hidden { display:none !important; }
        #ls { position:fixed; inset:0; background:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; z-index:10000; transition:0.3s; }
        .pulse { font-size:4rem; animation:pulse 1s infinite alternate; }
        @keyframes pulse { from{transform:scale(1)} to{transform:scale(1.1)} }
        .view { display:none; padding:20px; animation:viewIn 0.3s; }
        .view.active { display:block; }
        @keyframes viewIn { from{opacity:0; transform:translateY(10px)} }
        .cat-bar { display:flex; gap:10px; overflow-x:auto; padding:0 20px 15px; margin:0 -20px 5px; scrollbar-width:none; }
        .chip { padding:10px 22px; border-radius:30px; background:var(--sec); font-weight:700; border:none; color:#777; white-space:nowrap; }
        .chip.active { background:var(--p); color:#fff; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        .card { background:#fff; border-radius:var(--r); overflow:hidden; border:1px solid rgba(0,0,0,0.03); display:flex; flex-direction:column; box-shadow:0 4px 15px rgba(0,0,0,0.03); }
        .card img { width:100%; height:130px; object-fit:cover; background:var(--sec); }
        .card-body { padding:12px; flex:1; display:flex; flex-direction:column; justify-content:space-between; }
        .name { font-weight:700; font-size:0.9rem; margin-bottom:5px; }
        .price { font-weight:800; color:var(--p); font-size:1rem; }
        .btn-add { width:100%; padding:10px; border-radius:12px; background:#fff; color:var(--p); border:1.8px solid var(--p); font-weight:800; }
        .qty-row { display:flex; align-items:center; justify-content:space-between; background:var(--sec); border-radius:12px; padding:4px; }
        .qty-row button { width:32px; height:32px; border:none; background:#fff; border-radius:8px; font-weight:800; color:var(--p); }
        .hero { background:linear-gradient(135deg, #2481cc, #1c6ba8); padding:50px 20px; text-align:center; color:#fff; border-radius:0 0 50px 50px; margin:-20px -20px 30px; box-shadow:0 15px 40px rgba(36,129,204,0.25); }
        .avatar { width:95px; height:95px; background:rgba(255,255,255,0.2); border:3px solid #fff; border-radius:50%; margin:0 auto 15px; display:flex; align-items:center; justify-content:center; font-size:3rem; overflow:hidden; }
        .nav { position:fixed; bottom:0; left:0; width:100%; height:80px; background:rgba(255,255,255,0.9); backdrop-filter:blur(20px); display:flex; border-top:1px solid rgba(0,0,0,0.05); z-index:1000; }
        .nav-tab { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; font-size:0.75rem; font-weight:800; color:#999; position:relative; }
        .nav-tab.active { color:var(--p); }
        .badge { position:absolute; top:12px; right:20%; background:#ef4444; color:#fff; font-size:0.65rem; padding:2px 6px; border-radius:10px; font-weight:800; }
        .float-btn { position:fixed; bottom:95px; left:20px; right:20px; background:var(--p); color:#fff; padding:18px; border-radius:20px; font-weight:800; text-align:center; box-shadow:0 10px 30px rgba(36, 129, 204, 0.4); z-index:900; animation:pop 0.4s; }
        @keyframes pop { from{transform:scale(0.8); opacity:0} }
        #err-box { position:fixed; top:10px; left:10px; right:10px; background:#fee2e2; color:#ef4444; padding:15px; border-radius:15px; font-size:0.7rem; z-index:20000; display:none; }
    </style>
</head>
<body>
    <div id="ls">
        <div class="pulse">🍽️</div>
        <div id="ls-text" style="margin-top:20px; font-weight:800; opacity:0.3; font-size:0.9rem">v6.7 LIVE FETCH 🚀</div>
    </div>
    <div id="err-box" onclick="this.style.display='none'"></div>
    <main id="v-menu" class="view active">
        <div id="cats" class="cat-bar"></div>
        <div id="menu-grid" class="grid"></div>
    </main>
    <main id="v-cart" class="view">
        <h2 style="margin-bottom:20px;font-weight:800">Savat</h2>
        <div id="cart-list"></div>
        <div id="cart-empty" class="hidden" style="text-align:center;padding:50px;opacity:0.6">🛒 Bo'sh</div>
        <div id="cart-foot" class="hidden">
            <textarea id="note" placeholder="Izoh..." style="width:100%;padding:18px;border:none;background:var(--sec);border-radius:20px;margin:20px 0;min-height:90px;"></textarea>
            <div style="padding:22px;background:var(--sec);border-radius:25px;display:flex;justify-content:space-between;font-weight:800">Jami: <span id="total-val" style="color:var(--p)">0</span></div>
            <button onclick="app.send()" style="width:100%;padding:20px;background:var(--p);color:#fff;border:none;border-radius:22px;margin-top:20px;font-weight:800">Tasdiqlash</button>
        </div>
    </main>
    <div id="float" class="float-btn hidden" onclick="app.tab('cart')">🛒 Savatga o'tish → <span id="float-sum">0</span></div>
    <nav class="nav">
        <div class="nav-tab active" id="n-menu" onclick="app.tab('menu')">🍽️<br>Menyu</div>
        <div class="nav-tab" id="n-cart" onclick="app.tab('cart')">🛒<br>Savat<div id="badge" class="badge hidden">0</div></div>
    </nav>

    <script>
        window.onerror = function(msg, url, line) {
            const eb = document.getElementById('err-box');
            if(eb) { eb.style.display='block'; eb.textContent = "Error: " + msg + " at " + line; }
        };

        class App {
            constructor() {
                this.tg = window.Telegram?.WebApp;
                this.cart = {}; this.items = []; this.cur = 'menu';
                this.init();
            }
            async init() {
                if(this.tg) { this.tg.ready(); this.tg.expand(); }
                try { await this.load(); } catch(e) {}
                this.hideLS();
            }
            hideLS() { const l=document.getElementById('ls'); if(l) l.style.display='none'; }
            async load() {
                const r = await fetch('https://olmazorgo.bigsaver.ru/bot/api/menu.php');
                this.items = await r.json(); this.rC(); this.rI();
            }
            rC() {
                const c = document.getElementById('cats'); if(!c) return;
                c.innerHTML = '<button class="chip active" onclick="app.fil(\'all\',this)">Barchasi</button>';
                this.items.forEach(cat => c.innerHTML += `<button class="chip" onclick="app.fil(${cat.id},this)">${cat.name}</button>`);
            }
            rI(id='all') {
                const g = document.getElementById('menu-grid'); if(!g) return;
                g.innerHTML = '';
                const l = (id==='all') ? this.items.flatMap(c=>c.items) : (this.items.find(c=>c.id==id)?.items||[]);
                l.forEach(i => {
                    const d = document.createElement('div'); d.className = 'card';
                    const q = this.cart[i.id]?.q || 0;
                    d.innerHTML = `<img src="${i.image_url||''}"><div class="card-body"><div><div class="name">${i.name}</div><div class="price">${this.fmt(i.price)}</div></div><div id="i-${i.id}">${q>0?this.qH(i.id,q):`<button class="btn-add" onclick="app.add(${i.id},'${i.name}',${i.price})">Qo'shish</button>`}</div></div>`;
                    g.appendChild(d);
                });
            }
            qH(id,q) { return `<div class="qty-row"><button onclick="app.ch(${id},-1)">−</button><span>${q}</span><button onclick="app.ch(${id},1)">+</button></div>`; }
            fil(id,b) { document.querySelectorAll('.chip').forEach(x=>x.classList.remove('active')); b.classList.add('active'); this.rI(id); }
            add(id,n,p) { this.cart[id]={id,n,p,q:1}; this.up(id); this.tg?.HapticFeedback.impactOccurred('light'); }
            ch(id,d) { if(!this.cart[id])return; this.cart[id].q+=d; if(this.cart[id].q<=0)delete this.cart[id]; this.up(id); if(this.cur==='cart')this.rCrt(); }
            up(id) { const c=document.getElementById(`i-${id}`); if(c) c.innerHTML=this.cart[id]?this.qH(id,this.cart[id].q): `<button class="btn-add" onclick="app.add(${id},'${this.find(id).name}',${this.find(id).price})">Qo'shish</button>`; this.sy(); }
            sy() {
                const list = Object.values(this.cart); const sum = list.reduce((s,i)=>s+i.p*i.q,0); const n = list.reduce((s,i)=>s+i.q,0);
                const b = document.getElementById('badge'); if(b){ b.textContent=n; b.classList.toggle('hidden', n==0); }
                const f = document.getElementById('float'); if(f) { if(n>0 && this.cur==='menu'){ f.classList.remove('hidden'); document.getElementById('float-sum').textContent=this.fmt(sum); } else f.classList.add('hidden'); }
            }
            tab(t) {
                if(this.cur===t)return; this.cur=t;
                document.querySelectorAll('.view').forEach(v=>v.classList.remove('active')); document.getElementById(`v-${t}`).classList.add('active');
                document.querySelectorAll('.nav-tab').forEach(nt=>nt.classList.remove('active')); document.getElementById(`n-${t}`).classList.add('active');
                if(t==='cart')this.rCrt(); this.sy(); window.scrollTo(0,0);
            }
            rCrt() {
                const l=document.getElementById('cart-list'), e=document.getElementById('cart-empty'), f=document.getElementById('cart-foot'), list=Object.values(this.cart);
                if(list.length==0){ if(l)l.innerHTML=''; e.classList.remove('hidden'); f.classList.add('hidden'); }
                else {
                    e.classList.add('hidden'); f.classList.remove('hidden'); if(l)l.innerHTML='';
                    list.forEach(i=> {
                        const d=document.createElement('div'); d.style="display:flex;gap:12px;margin-bottom:12px;align-items:center;padding:20px;background:var(--sec);border-radius:20px";
                        d.innerHTML=`<div style="flex:1"><b>${i.n}</b><br><small style="color:var(--p);font-weight:700">${this.fmt(i.p)}</small></div><div style="width:100px">${this.qH(i.id,i.q)}</div>`;
                        if(l)l.appendChild(d);
                    });
                    const tv=document.getElementById('total-val'); if(tv) tv.textContent = this.fmt(list.reduce((s,i)=>s+i.p*i.q, 0));
                }
            }
            send() {
                const list=Object.values(this.cart); if(list.length==0)return;
                const d={items:list, total:list.reduce((s,i)=>s+i.p*i.q,0), note:document.getElementById('note').value};
                this.tg?.sendData(JSON.stringify(d)); this.tg?.close();
            }
            find(id) { return this.items.flatMap(c=>c.items).find(i=>i.id == id); }
            fmt(n) { return Number(n).toLocaleString('uz-UZ').replace(/,/g,' ')+' so\'m'; }
        }
        
        window.onload = () => { window.app = new App(); };
        setTimeout(() => { const l=document.getElementById('ls'); if(l && l.style.display!=='none') l.style.display='none'; }, 4000);
    </script>
</body>
</html>
