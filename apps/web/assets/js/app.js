/* OpenCollab Music - MVP (no backend)
 * Storage layer: localStorage
 * WARNING: This is a prototype for UI demo & presentation.
 */

const DB_KEY = 'ocm_db_v1';
const SESSION_KEY = 'ocm_session_v1';

function nowISO(){ return new Date().toISOString(); }
function uid(){ return Math.floor(Math.random()*1e9) + Date.now(); }

function readDB(){
  const raw = localStorage.getItem(DB_KEY);
  if(!raw) return null;
  try { return JSON.parse(raw); } catch(e){ return null; }
}

function writeDB(db){
  localStorage.setItem(DB_KEY, JSON.stringify(db));
}

function seedDBIfNeeded(){
  let db = readDB();
  if(db) return db;

  const artistId = uid();
  const musicianId = uid();

  db = {
    version: 1,
    users: [
      {
        id: artistId,
        name: 'Artist Demo',
        email: 'artist@demo.com',
        // prototype only: store plaintext to keep demo simple
        password: 'Password123!',
        role: 'ARTIST',
        createdAt: nowISO(),
      },
      {
        id: musicianId,
        name: 'Musician Demo',
        email: 'musician@demo.com',
        password: 'Password123!',
        role: 'MUSICIAN',
        createdAt: nowISO(),
      }
    ],
    songs: [
      {
        id: uid(),
        userId: artistId,
        title: 'Ikhlas Sendiri (Demo)',
        genre: 'Ballad',
        visibility: 'DEMO',
        lyrics: 'Ini hanya contoh lirik demo untuk presentasi.',
        demoFileName: null,
        demoFileSize: null,
        createdAt: nowISO(),
      }
    ],
    collabRequests: [
      {
        id: uid(),
        createdByUserId: artistId,
        neededRole: 'GUITARIST',
        title: 'Butuh Gitaris Pengiring (Fingerstyle)',
        genre: 'Ballad',
        summary: 'Aku penulis lagu, vokal pas-pasan. Butuh gitaris jago ngiringin untuk demo rekaman sederhana.',
        status: 'OPEN',
        createdAt: nowISO(),
      },
      {
        id: uid(),
        createdByUserId: artistId,
        neededRole: 'DRUMMER',
        title: 'Cari Drummer untuk Aransemen Pop Rock',
        genre: 'Pop Rock',
        summary: 'Project latihan. Fokus groove & dinamika. Bisa remote.',
        status: 'OPEN',
        createdAt: nowISO(),
      }
    ]
  };

  writeDB(db);
  return db;
}

function getSession(){
  const raw = localStorage.getItem(SESSION_KEY);
  if(!raw) return null;
  try { return JSON.parse(raw); } catch(e){ return null; }
}

function setSession(session){
  localStorage.setItem(SESSION_KEY, JSON.stringify(session));
}

function clearSession(){
  localStorage.removeItem(SESSION_KEY);
}

function getCurrentUser(){
  const db = seedDBIfNeeded();
  const s = getSession();
  if(!s) return null;
  return db.users.find(u => u.id === s.userId) || null;
}

function requireAuth(){
  const u = getCurrentUser();
  if(!u){
    window.location.href = 'login.html';
    return null;
  }
  return u;
}

function toast(message, type='ok'){
  const el = document.querySelector('[data-toast]');
  if(!el) return alert(message);
  el.classList.remove('ok','err','show');
  el.querySelector('.title').textContent = (type==='ok' ? 'Success' : 'Error');
  el.querySelector('.msg').textContent = message;
  el.classList.add(type === 'ok' ? 'ok' : 'err');
  el.classList.add('show');
  setTimeout(()=> el.classList.remove('show'), 3200);
}

function bindNav(){
  const u = getCurrentUser();
  const navUser = document.querySelector('[data-nav-user]');
  const navAuth = document.querySelector('[data-nav-auth]');
  const navGuest = document.querySelector('[data-nav-guest]');

  if(u){
    if(navUser) navUser.textContent = `${u.name} (${u.role})`;
    if(navAuth) navAuth.style.display = 'flex';
    if(navGuest) navGuest.style.display = 'none';
  } else {
    if(navUser) navUser.textContent = 'Guest';
    if(navAuth) navAuth.style.display = 'none';
    if(navGuest) navGuest.style.display = 'flex';
  }

  const logoutBtn = document.querySelector('[data-logout]');
  if(logoutBtn){
    logoutBtn.addEventListener('click', (e)=>{
      e.preventDefault();
      clearSession();
      window.location.href = 'index.html';
    });
  }
}

// ---------- Page: Register ----------
function initRegister(){
  seedDBIfNeeded();
  bindNav();

  const form = document.querySelector('#registerForm');
  if(!form) return;

  form.addEventListener('submit', (e)=>{
    e.preventDefault();

    const name = form.name.value.trim();
    const email = form.email.value.trim().toLowerCase();
    const password = form.password.value;
    const role = form.role.value;

    if(!name || !email || !password || !role){
      toast('Lengkapi semua field.', 'err');
      return;
    }

    if(password.length < 8){
      toast('Password minimal 8 karakter.', 'err');
      return;
    }

    const db = seedDBIfNeeded();
    if(db.users.some(u => u.email === email)){
      toast('Email sudah terdaftar. Silakan login.', 'err');
      return;
    }

    db.users.push({
      id: uid(),
      name,
      email,
      password, // prototype only
      role,
      createdAt: nowISO(),
    });

    writeDB(db);
    toast('Register berhasil. Silakan login.', 'ok');
    setTimeout(()=> window.location.href='login.html', 900);
  });
}

// ---------- Page: Login ----------
function initLogin(){
  seedDBIfNeeded();
  bindNav();

  const form = document.querySelector('#loginForm');
  if(!form) return;

  form.addEventListener('submit', (e)=>{
    e.preventDefault();

    const email = form.email.value.trim().toLowerCase();
    const password = form.password.value;

    const db = seedDBIfNeeded();
    const u = db.users.find(x => x.email === email && x.password === password);
    if(!u){
      toast('Email / password salah.', 'err');
      return;
    }

    setSession({ userId: u.id, role: u.role, createdAt: nowISO() });
    toast('Login sukses.', 'ok');
    setTimeout(()=> window.location.href='dashboard.html', 600);
  });
}

// ---------- Page: Index / Landing ----------
function initIndex(){
  const db = seedDBIfNeeded();
  bindNav();

  const listEl = document.querySelector('#requestList');
  if(!listEl) return;

  const q = document.querySelector('#q');
  const role = document.querySelector('#role');
  const genre = document.querySelector('#genre');

  function render(){
    const keyword = (q?.value || '').trim().toLowerCase();
    const roleVal = role?.value || 'ALL';
    const genreVal = genre?.value || 'ALL';

    const rows = db.collabRequests
      .filter(r => r.status === 'OPEN')
      .filter(r => roleVal==='ALL' ? true : r.neededRole===roleVal)
      .filter(r => genreVal==='ALL' ? true : r.genre===genreVal)
      .filter(r => {
        if(!keyword) return true;
        return (
          r.title.toLowerCase().includes(keyword) ||
          r.summary.toLowerCase().includes(keyword) ||
          r.neededRole.toLowerCase().includes(keyword) ||
          r.genre.toLowerCase().includes(keyword)
        );
      })
      .sort((a,b)=> (a.createdAt < b.createdAt ? 1 : -1));

    listEl.innerHTML = '';

    if(rows.length === 0){
      listEl.innerHTML = `<div class="card">Tidak ada request yang cocok.</div>`;
      return;
    }

    for(const r of rows){
      const creator = db.users.find(u => u.id === r.createdByUserId);
      const creatorName = creator ? creator.name : 'Unknown';

      const item = document.createElement('div');
      item.className = 'card';
      item.innerHTML = `
        <div class="row" style="align-items:flex-start;gap:14px">
          <div style="flex:1">
            <div class="h2" style="margin:0 0 6px">${escapeHtml(r.title)}</div>
            <div class="muted">Need: <b>${escapeHtml(r.neededRole)}</b> • Genre: <b>${escapeHtml(r.genre)}</b> • by ${escapeHtml(creatorName)}</div>
            <p style="margin:10px 0 0">${escapeHtml(r.summary || '')}</p>
          </div>
          <div class="row" style="align-items:center;justify-content:flex-end;flex-wrap:wrap">
            <a class="btn" href="login.html">Apply / DM</a>
          </div>
        </div>
      `;
      listEl.appendChild(item);
    }
  }

  // fill filter options
  if(role){
    const roles = Array.from(new Set(db.collabRequests.map(x => x.neededRole))).sort();
    role.innerHTML = `<option value="ALL">All roles</option>` + roles.map(r=>`<option value="${escapeAttr(r)}">${escapeHtml(r)}</option>`).join('');
  }
  if(genre){
    const genres = Array.from(new Set(db.collabRequests.map(x => x.genre))).sort();
    genre.innerHTML = `<option value="ALL">All genres</option>` + genres.map(g=>`<option value="${escapeAttr(g)}">${escapeHtml(g)}</option>`).join('');
  }

  q?.addEventListener('input', render);
  role?.addEventListener('change', render);
  genre?.addEventListener('change', render);

  render();
}

// ---------- Page: Dashboard ----------
function initDashboard(){
  const u = requireAuth();
  if(!u) return;
  const db = seedDBIfNeeded();
  bindNav();

  const elName = document.querySelector('[data-me-name]');
  const elRole = document.querySelector('[data-me-role]');
  if(elName) elName.textContent = u.name;
  if(elRole) elRole.textContent = u.role;

  const mySongs = db.songs.filter(s => s.userId === u.id);
  const myRequests = db.collabRequests.filter(r => r.createdByUserId === u.id);

  const statSongs = document.querySelector('[data-stat-songs]');
  const statOpen = document.querySelector('[data-stat-open]');
  const statClosed = document.querySelector('[data-stat-closed]');

  if(statSongs) statSongs.textContent = mySongs.length;
  if(statOpen) statOpen.textContent = myRequests.filter(r=>r.status==='OPEN').length;
  if(statClosed) statClosed.textContent = myRequests.filter(r=>r.status==='CLOSED').length;

  const listSongs = document.querySelector('#mySongs');
  if(listSongs){
    listSongs.innerHTML = '';
    if(mySongs.length === 0){
      listSongs.innerHTML = `<div class="muted">Belum ada lagu. Coba upload demo dulu.</div>`;
    } else {
      for(const s of mySongs.sort((a,b)=> a.createdAt < b.createdAt ? 1 : -1)){
        const row = document.createElement('div');
        row.className = 'card';
        row.innerHTML = `
          <div class="row" style="align-items:center;justify-content:space-between;gap:10px">
            <div>
              <div class="h2" style="margin:0 0 4px">${escapeHtml(s.title)}</div>
              <div class="muted">${escapeHtml(s.genre)} • ${escapeHtml(s.visibility)} • ${new Date(s.createdAt).toLocaleString()}</div>
            </div>
            <a class="btn" href="upload.html">Upload lagi</a>
          </div>
        `;
        listSongs.appendChild(row);
      }
    }
  }

  const listReq = document.querySelector('#myRequests');
  if(listReq){
    listReq.innerHTML='';
    if(myRequests.length===0){
      listReq.innerHTML = `<div class="muted">Belum ada request kolaborasi.</div>`;
    } else {
      for(const r of myRequests.sort((a,b)=> a.createdAt < b.createdAt ? 1 : -1)){
        const row = document.createElement('div');
        row.className = 'card';
        row.innerHTML = `
          <div class="row" style="align-items:flex-start;justify-content:space-between;gap:10px">
            <div style="flex:1">
              <div class="h2" style="margin:0 0 4px">${escapeHtml(r.title)}</div>
              <div class="muted">Need: <b>${escapeHtml(r.neededRole)}</b> • ${escapeHtml(r.genre)} • Status: <b>${escapeHtml(r.status)}</b></div>
              <p style="margin:10px 0 0">${escapeHtml(r.summary||'')}</p>
            </div>
            <div class="row" style="gap:8px;flex-wrap:wrap;justify-content:flex-end">
              <button class="btn" data-toggle-status="${r.id}">${r.status==='OPEN'?'Close':'Reopen'}</button>
            </div>
          </div>
        `;
        listReq.appendChild(row);
      }

      listReq.querySelectorAll('[data-toggle-status]').forEach(btn=>{
        btn.addEventListener('click', ()=>{
          const id = Number(btn.getAttribute('data-toggle-status'));
          const rr = db.collabRequests.find(x => x.id === id && x.createdByUserId===u.id);
          if(!rr) return;
          rr.status = rr.status === 'OPEN' ? 'CLOSED' : 'OPEN';
          writeDB(db);
          toast('Status updated.', 'ok');
          setTimeout(()=> window.location.reload(), 350);
        });
      });
    }
  }

  const newReqForm = document.querySelector('#newRequestForm');
  if(newReqForm){
    newReqForm.addEventListener('submit', (e)=>{
      e.preventDefault();
      const title = newReqForm.title.value.trim();
      const neededRole = newReqForm.neededRole.value.trim();
      const genre = newReqForm.genre.value.trim();
      const summary = newReqForm.summary.value.trim();
      if(!title || !neededRole || !genre){
        toast('Judul, role, dan genre wajib diisi.', 'err');
        return;
      }
      db.collabRequests.push({
        id: uid(),
        createdByUserId: u.id,
        neededRole,
        title,
        genre,
        summary,
        status: 'OPEN',
        createdAt: nowISO(),
      });
      writeDB(db);
      toast('Request dibuat.', 'ok');
      setTimeout(()=> window.location.reload(), 600);
    });
  }
}

// ---------- Page: Upload ----------
function initUpload(){
  const u = requireAuth();
  if(!u) return;
  const db = seedDBIfNeeded();
  bindNav();

  const form = document.querySelector('#uploadForm');
  if(!form) return;

  form.addEventListener('submit', (e)=>{
    e.preventDefault();

    const title = form.title.value.trim();
    const genre = form.genre.value.trim();
    const visibility = form.visibility.value;
    const lyrics = form.lyrics.value.trim();
    const file = form.demo.files?.[0] || null;

    if(!title || !genre){
      toast('Judul dan genre wajib diisi.', 'err');
      return;
    }

    db.songs.push({
      id: uid(),
      userId: u.id,
      title,
      genre,
      visibility,
      lyrics,
      demoFileName: file ? file.name : null,
      demoFileSize: file ? file.size : null,
      createdAt: nowISO(),
    });

    writeDB(db);
    toast('Upload demo berhasil (metadata tersimpan).', 'ok');
    setTimeout(()=> window.location.href='dashboard.html', 800);
  });
}

// ---------- Util ----------
function escapeHtml(str){
  return String(str)
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'",'&#39;');
}
function escapeAttr(str){
  return escapeHtml(str).replaceAll('`','');
}

window.OCM = {
  seedDBIfNeeded,
  getCurrentUser,
  clearSession,
  initRegister,
  initLogin,
  initIndex,
  initDashboard,
  initUpload,
  toast,
};
