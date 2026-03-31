<?php
// index.php — Premium Food WebApp (PHP Version)
// Note: This is an HTML/JS app wrapped in PHP as requested by the user.
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Olmazor Go — Premium</title>
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

        .cat-bar { display:flex; gap:10px; overflow-x:auto; padding-bottom:15px; scrollbar-width:none; margin:0 -20px 5px; padding:0 20px 15px; }
        .cat-bar::-webkit-scrollbar { display:none; }
        .chip { padding:10px 22px; border-radius:30px; background:var(--sec); font-weight:700; border:none; color:#777; white-space:nowrap; }
        .chip.active { background:var(--p); color:#fff; box-shadow:0 8px 20px rgba(36,129,204,0.3); }

        .grid { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        .card { background:#fff; border-radius:var(--r); overflow:hidden; border:1px solid rgba(0,0,0,0.03); display:flex; flex-direction:column; box-shadow:0 4px 15px rgba(0,0,0,0.03); }
        .card img { width:100%; height:130px; object-fit:cover; background:var(--sec); }
        .card-body { padding:12px; flex:1; display:flex; flex-direction:column; justify-content:space-between; }
        .name { font-weight:700; font-size:0.9rem; margin-bottom:5px; }
        .price { font-weight:800; color:var(--p); font-size:1rem; }
        .btn-add { width:100%; padding:10px; border-radius:12px; background:#fff; color:var(--p); border:1.8px solid var(--p); font-weight:800; cursor:pointer; }
        .qty-row { display:flex; align-items:center; justify-content:space-between; background:var(--sec); border-radius:12px; padding:4px; }
        .qty-row button { width:32px; height:32px; border:none; background:#fff; border-radius:8px; font-weight:800; color:var(--p); box-shadow:0 2px 5px rgba(0,0,0,0.05); }

        .hero { background:linear-gradient(135deg, #2481cc, #1c6ba8); padding:50px 20px; text-align:center; color:#fff; border-radius:0 0 50px 50px; margin:-20px -20px 30px; box-shadow:0 15px 40px rgba(36,129,204,0.25); }
        .avatar { width:95px; height:95px; background:rgba(255,255,255,0.2); border:3px solid #fff; border-radius:50%; margin:0 auto 15px; display:flex; align-items:center; justify-content:center; font-size:3rem; overflow:hidden; }

        .nav { position:fixed; bottom:0; left:0; width:100%; height:80px; background:rgba(255,255,255,0.9); backdrop-filter:blur(20px); display:flex; border-top:1px solid rgba(0,0,0,0.05); z-index:1000; padding-bottom:env(safe-area-inset-bottom); }
        .nav-tab { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; font-size:0.75rem; font-weight:800; color:#999; position:relative; cursor:pointer; }
        .nav-tab.active { color:var(--p); }
        .badge { position:absolute; top:12px; right:20%; background:#ef4444; color:#fff; font-size:0.65rem; padding:2px 6px; border-radius:10px; font-weight:800; }
        
        .float-btn { position:fixed; bottom:95px; left:20px; right:20px; background:var(--p); color:#fff; padding:18px; border-radius:20px; font-weight:800; text-align:center; box-shadow:0 10px 30px rgba(36, 129, 204, 0.4); z-index:900; animation:pop 0.4s; font-size: 1.1rem; }
        @keyframes pop { from{transform:scale(0.8); opacity:0} }

        .row-item { padding:20px; background:var(--sec); border-radius:22px; font-weight:700; display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
        .sync-btn { width:100%; padding:20px; background:var(--p); color:#fff; border:none; border-radius:22px; font-weight:800; font-size:1.1rem; box-shadow:0 10px 20px rgba(36, 129, 204, 0.2); margin-top:10px; transition: 0.2s; }
        .sync-btn:active { transform: scale(0.97); opacity: 0.9; }
    </style>
</head>
<body>
    <!-- LOADING SCREEN -->
    <div id="ls">
        <div class="pulse">🍽️</div>
        <div style="margin-top:20px; font-weight:800; opacity:0.3; font-size:0.9rem">Olmazor Go — PHP v6.0</div>
    </div>

    <!-- VIEW: MENU -->
    <main id="v-menu" class="view active">
        <div id="cats" class="cat-bar"></div>
        <div id="menu-grid" class="grid"></div>
    </main>

    <!-- VIEW: CART -->
    <main id="v-cart" class="view">
        <h2 style="margin-bottom:20px;font-weight:800;font-size:1.8rem">Savatcha</h2>
        <div id="cart-list"></div>
        <div id="cart-empty" class="hidden" style="text-align:center;padding:60px 0;opacity:0.6;font-size:1.2rem">🛒 Tanlangan taomlar yo'q</div>
        <div id="cart-foot" class="hidden">
            <textarea id="note" placeholder="Izoh (masalan: qoshiq kerakmas, non bo'lsin)..." style="width:100%;padding:18px;border:none;background:var(--sec);border-radius:20px;margin:20px 0;min-height:90px;font-size:1rem;"></textarea>
            <div style="padding:22px;background:var(--sec);border-radius:25px;display:flex;justify-content:space-between;font-weight:800;font-size:1.2rem"><span>Jami:</span> <span id="total-val" style="color:var(--p)">0</span></div>
            <button onclick="app.send()" style="width:100%;padding:20px;background:var(--p);color:#fff;border:none;border-radius:22px;margin-top:20px;font-weight:800;font-size:1.2rem">Tasdiqlash</button>
        </div>
    </main>

    <!-- VIEW: PROFILE -->
    <main id="v-prof" class="view">
        <div class="hero">
            <div class="avatar" id="u-avatar">👤</div>
            <h2 id="u-name">Yuklanmoqda...</h2>
            <div id="u-debug" style="opacity:0.6;font-size:0.8rem;margin-top:8px">v6.0 - PHP EDITION</div>
        </div>
        <div style="display:flex; flex-direction:column;">
            <div class="row-item">
                <span>📞 Telefon:</span>
                <span id="u-phone" style="color:var(--p)">Tekshirilmoqda...</span>
            </div>
            <div class="row-item" style="opacity:0.6">
                <span>📦 Buyurtmalar:</span>
                <span>Yaqinda...</span>
            </div>
            <button class="sync-btn" onclick="app.sync(true)">Ma'lumotlarni Yangilash 🔄</button>
            <div id="final-log" style="font-size:0.6rem; color:#aaa; text-align:center; margin-top:25px">Initial Bridge Status...</div>
        </div>
    </main>

    <!-- FLOATING CHECKOUT -->
    <div id="float" class="float-btn hidden" onclick="app.tab('cart')">🛒 Davom etish → <span id="float-sum">0</span></div>

    <!-- BOTTOM NAVIGATION -->
    <nav class="nav">
        <div class="nav-tab active" id="n-menu" onclick="app.tab('menu')">🍽️<br>Menyu</div>
        <div class="nav-tab" id="n-cart" onclick="app.tab('cart')">🛒<br>Savat<div id="badge" class="badge hidden">0</div></div>
        <div class="nav-tab" id="n-prof" onclick="app.tab('prof')">👤<br>Profil</div>
    </nav>

    <script>
        class PremiumApp {
            constructor() {
                this.tg = window.Telegram?.WebApp;
                this.cart = {}; this.items = []; this.cur = 'menu';
                this.init();
            }
            async init() {
                // Bridge Check
                const log = document.getElementById('final-log');
                if (log) log.textContent = this.tg ? "TG Object: OK" : "TG Object: MISSING";

                if (this.tg) {
                    this.tg.ready(); this.tg.expand();
                    // Multi-attempt sync
                    this.sync();
                    setTimeout(() => this.sync(), 300);
                    setTimeout(() => this.sync(), 1000);
                }

                try { await this.load(); } catch(e) {}
                
                // Always hide loading after 2.5s
                setTimeout(() => { const l=document.getElementById('ls'); if(l)l.style.display='none'; }, 2500);
            }
            async sync(manual = false) {
                if (!this.tg) this.tg = window.Telegram?.WebApp;
                const dbg = document.getElementById('u-debug');
                const uName = document.getElementById('u-name');
                const uPhone = document.getElementById('u-phone');
                const log = document.getElementById('final-log');
                
                let u = this.tg?.initDataUnsafe?.user;
                if (!u && this.tg?.initData) {
                    try {
                        const s = JSON.parse(new URLSearchParams(this.tg.initData).get('user'));
                        if (s) u = s;
                    } catch(e){}
                }

                if (u) {
                    if (uName) uName.textContent = [u.first_name, u.last_name].filter(Boolean).join(' ') || 'User';
                    if (dbg) dbg.textContent = `ID: ${u.id} | v6.0`;
                    if (u.photo_url) document.getElementById('u-avatar').innerHTML = `<img src="${u.photo_url}" style="width:100%;height:100%;object-fit:cover">`;
                    if (log) log.textContent = `User Identified: ${u.id}`;

                    try {
                        // Testing multiple path versions for robust PHP connection
                        const paths = ['../bot/api/user.php?id=', '/bot/api/user.php?id=', '/Kafe/bot/api/user.php?id='];
                        let resData = null;
                        for (let p of paths) {
                            try {
                                const r = await fetch(p + u.id);
                                if (r.ok) { let j = await r.json(); if(j.ok){ resData = j; break; } }
                            } catch(e){}
                        }
                        
                        if (resData && resData.user) {
                            if (uPhone) uPhone.textContent = resData.user.phone_number || '-';
                            if (resData.user.first_name && uName) uName.textContent = resData.user.first_name + (resData.user.last_name?' '+resData.user.last_name:'');
                            if (log) log.textContent += " | DB: SUCCESS";
                        } else if (uPhone) {
                            uPhone.textContent = "Bazada yo'q";
                            if (log) log.textContent += " | DB: No User Found";
                        }
                    } catch(e) { if(uPhone) uPhone.textContent = "Xato"; if(log) log.textContent += " | DB: FETCH ERROR"; }
                } else {
                    if (uName) uName.textContent = "Mehmon";
                    if (uPhone) uPhone.textContent = "—";
                    if (dbg) dbg.textContent = "v6.0 - No Data Found";
                    if (log) log.textContent = "User Unknown - Check Connection";
                }
            }
            async load() {
                const r = await fetch('https://olmazorgo.bigsaver.ru/bot/api/menu.php');
                this.items = await r.json(); this.rC(); this.rI();
            }
            rC() {
                const c = document.getElementById('cats'); c.innerHTML = '<button class="chip active" onclick="app.fil(\'all\',this)">Barchasi</button>';
                this.items.forEach(cat => c.innerHTML += `<button class="chip" onclick="app.fil(${cat.id},this)">${cat.name}</button>`);
            }
            rI(id = 'all') {
                const g = document.getElementById('menu-grid'); g.innerHTML = '';
                let l = (id==='all') ? this.items.flatMap(c=>c.items) : (this.items.find(c=>c.id==id)?.items||[]);
                l.forEach(i => {
                    const d = document.createElement('div'); d.className = 'card';
                    const q = this.cart[i.id]?.q || 0;
                    d.innerHTML = `<img src="${i.image_url||''}"><div class="card-body"><div><div class="name">${i.name}</div><div class="price">${this.fmt(i.price)}</div></div><div id="i-${i.id}">${q>0?this.qH(i.id,q):`<button class="btn-add" onclick="app.add(${i.id},'${i.name}',${i.price})">Qo'shish</button>`}</div></div>`;
                    g.appendChild(d);
                });
            }
            qH(id,q) { return `<div class="qty-row"><button onclick="app.ch(${id},-1)">−</button><span style="font-weight:700">${q}</span><button onclick="app.ch(${id},1)">+</button></div>`; }
            fil(id,b) { document.querySelectorAll('.chip').forEach(x=>x.classList.remove('active')); b.classList.add('active'); this.rI(id); }
            add(id,n,p) { this.cart[id]={id,n,p,q:1}; this.up(id); this.tg?.HapticFeedback.impactOccurred('light'); }
            ch(id,d) { if(!this.cart[id])return; this.cart[id].q+=d; if(this.cart[id].q<=0)delete this.cart[id]; this.up(id); if(this.cur==='cart')this.rCrt(); }
            up(id) { const c=document.getElementById(`i-${id}`); if(c) c.innerHTML=this.cart[id]?this.qH(id,this.cart[id].q): `<button class="btn-add" onclick="app.add(${id},'${this.find(id).name}',${this.find(id).price})">Qo'shish</button>`; this.sy(); }
            sy() {
                const list = Object.values(this.cart); const sum = list.reduce((s,i)=>s+i.p*i.q,0); const n = list.reduce((s,i)=>s+i.q,0);
                const b = document.getElementById('badge'); b.textContent=n; b.classList.toggle('hidden', n==0);
                const f = document.getElementById('float'); if(f) { if(n>0 && this.cur==='menu'){ f.classList.remove('hidden'); document.getElementById('float-sum').textContent=this.fmt(sum); } else f.classList.add('hidden'); }
            }
            tab(t) {
                if(this.cur===t)return; this.cur=t;
                document.querySelectorAll('.view').forEach(v=>v.classList.remove('active')); document.getElementById(`v-${t}`).classList.add('active');
                document.querySelectorAll('.nav-tab').forEach(n=>n.classList.remove('active')); document.getElementById(`n-${t}`).classList.add('active');
                if(t==='cart')this.rCrt(); this.sy(); window.scrollTo(0,0);
            }
            rCrt() {
                const l=document.getElementById('cart-list'), e=document.getElementById('cart-empty'), f=document.getElementById('cart-foot'), list=Object.values(this.cart);
                if(list.length==0){ l.innerHTML=''; e.classList.remove('hidden'); f.classList.add('hidden'); }
                else {
                    e.classList.add('hidden'); f.classList.remove('hidden'); l.innerHTML='';
                    list.forEach(i=> {
                        const d=document.createElement('div'); d.style="display:flex;gap:12px;margin-bottom:12px;align-items:center;padding:20px;background:var(--sec);border-radius:20px";
                        d.innerHTML=`<div style="flex:1"><b>${i.n}</b><br><small style="color:var(--p);font-weight:700">${this.fmt(i.p)}</small></div><div style="width:100px">${this.qH(i.id,i.q)}</div>`;
                        l.appendChild(d);
                    });
                    document.getElementById('total-val').textContent = this.fmt(list.reduce((s,i)=>s+i.p*i.q, 0));
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
        const app = new PremiumApp();
    </script>
</body>
</html>
