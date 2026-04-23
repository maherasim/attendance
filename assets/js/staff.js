(function () {
  'use strict';

  const G = window.APP || {};
  const GEOFENCE_M = G.GEOFENCE_M || 500;
  const BYPASS_GEOFENCE = G.BYPASS_GEOFENCE === true;

  let ucList = [];
  let locState = 'idle';
  let loc = null;
  let distance = null;
  let photoBlob = null;

  const $ = (id) => document.getElementById(id);

  function distanceM(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const toRad = (d) => (d * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const a =
      Math.sin(dLat / 2) ** 2 +
      Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  function compressPhoto(dataUrl) {
    return new Promise((resolve) => {
      const img = new Image();
      img.onload = () => {
        const MAX = 400;
        let w = img.width;
        let h = img.height;
        if (w > MAX || h > MAX) {
          if (w > h) {
            h = Math.round((h * MAX) / w);
            w = MAX;
          } else {
            w = Math.round((w * MAX) / h);
            h = MAX;
          }
        }
        const c = document.createElement('canvas');
        c.width = w;
        c.height = h;
        c.getContext('2d').drawImage(img, 0, 0, w, h);
        c.toBlob(
          (blob) => resolve(blob || null),
          'image/jpeg',
          0.5
        );
      };
      img.src = dataUrl;
    });
  }

  function selectedUC() {
    const n = parseInt($('uc_no').value, 10);
    if (isNaN(n)) return null;
    return ucList.find((u) => u.no === n) || null;
  }

  function fillByNo(val) {
    $('uc_no').value = val;
    locState = 'idle';
    loc = null;
    distance = null;
    renderGeo();
    const n = parseInt(val, 10);
    const m = !isNaN(n) && ucList.find((u) => u.no === n);
    if (m) {
      $('uc_name').value = m.name;
      showTehsil(m.tehsil);
    } else {
      $('uc_name').value = '';
      showTehsil('');
    }
    hideSugg();
  }

  function fillByName(val) {
    $('uc_name').value = val;
    locState = 'idle';
    loc = null;
    distance = null;
    renderGeo();
    if (val.length >= 2) {
      const q = val.toLowerCase();
      const sugg = ucList
        .filter(
          (u) =>
            u.name.toLowerCase().includes(q) ||
            String(u.no).includes(val)
        )
        .slice(0, 7);
      showSugg(sugg);
    } else hideSugg();
  }

  function showTehsil(t) {
    const chip = $('tehsil-chip');
    if (t) {
      chip.hidden = false;
      chip.textContent = '📍 Tehsil: ' + t;
    } else {
      chip.hidden = true;
    }
  }

  function showSugg(list) {
    const box = $('sugg');
    box.innerHTML = '';
    list.forEach((uc) => {
      const row = document.createElement('div');
      row.className = 'dd-item';
      row.innerHTML =
        '<span class="dd-badge">UC ' +
        uc.no +
        '</span><span class="dd-name">' +
        uc.name +
        '</span><span class="dd-tehsil">' +
        uc.tehsil +
        '</span>';
      row.addEventListener('mousedown', (e) => {
        e.preventDefault();
        $('uc_no').value = String(uc.no);
        $('uc_name').value = uc.name;
        showTehsil(uc.tehsil);
        locState = 'idle';
        loc = null;
        distance = null;
        renderGeo();
        hideSugg();
      });
      box.appendChild(row);
    });
    box.hidden = list.length === 0;
  }

  function hideSugg() {
    $('sugg').hidden = true;
  }

  function renderGeo() {
    const box = $('geo-box');
    const btn = $('btn-loc');
    const office = $('office-info');
    const sel = selectedUC();
    if (sel) {
      office.hidden = false;
      office.innerHTML =
        '🏢 <strong>' +
        sel.name +
        '</strong> office · <a href="https://maps.google.com/?q=' +
        sel.lat +
        ',' +
        sel.lng +
        '" target="_blank" rel="noopener" class="link-inline">Map ↗</a>';
    } else {
      office.hidden = true;
    }

    btn.className = 'action-btn';
    if (locState === 'inside') btn.classList.add('action-ok');
    if (locState === 'outside') btn.classList.add('action-warn');
    if (locState === 'error') btn.classList.add('action-err');
    if (locState === 'fetching') btn.classList.add('action-busy');

    btn.disabled = locState === 'fetching';
    btn.innerHTML =
      locState === 'idle'
        ? '📍 Verify My Location'
        : locState === 'fetching'
        ? '<span class="pulsing">📡 Detecting GPS…</span>'
        : locState === 'inside'
        ? '📍 Location Verified ✓'
        : locState === 'outside'
        ? '📍 Location recorded (outside ' + GEOFENCE_M + 'm — submit OK)'
        : locState === 'error'
        ? '❌ GPS Error — Try Again'
        : '📍 Verify My Location';

    box.innerHTML = '';
    if (locState === 'fetching') {
      box.innerHTML =
        '<div class="geo-box geo-warn"><span class="pulsing">📡</span> Getting your GPS location…</div>';
    } else if (locState === 'inside' && loc) {
      box.innerHTML =
        '<div class="geo-box geo-ok"><div class="geo-title">✅ Location Verified</div><div class="geo-detail">📌 ' +
        loc.lat.toFixed(5) +
        ', ' +
        loc.lng.toFixed(5) +
        '<br/>📏 ' +
        distance +
        'm from office · ±' +
        loc.acc +
        'm</div></div>';
    } else if (locState === 'outside' && loc) {
      box.innerHTML =
        '<div class="geo-box geo-warn"><div class="geo-title">⚠️ Outside ' +
        GEOFENCE_M +
        'm reference</div><div class="geo-detail">You are <strong>' +
        distance +
        'm</strong> from the UC pin. Coordinates may be off; you can still submit — distance is saved for records.</div></div>';
    } else if (locState === 'error') {
      box.innerHTML =
        '<div class="geo-box geo-bad"><div class="geo-title">❌ GPS Error</div><div class="geo-detail">Allow location access and try again.</div></div>';
    }
  }

  function captureLocation() {
    const sel = selectedUC();
    if (!sel) {
      $('err-loc').textContent = '⚠️ Please select your UC number first.';
      $('err-loc').hidden = false;
      return;
    }
    $('err-loc').hidden = true;
    if (!navigator.geolocation) {
      locState = 'error';
      renderGeo();
      return;
    }
    locState = 'fetching';
    loc = null;
    distance = null;
    renderGeo();
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const myLat = pos.coords.latitude;
        const myLng = pos.coords.longitude;
        const acc = Math.round(pos.coords.accuracy);
        const dist = Math.round(distanceM(myLat, myLng, sel.lat, sel.lng));
        loc = { lat: myLat, lng: myLng, acc };
        distance = dist;
        locState = dist <= GEOFENCE_M ? 'inside' : 'outside';
        renderGeo();
      },
      () => {
        locState = 'error';
        renderGeo();
      },
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
  }

  function clearErrs() {
    ['err-uc_no', 'err-uc_name', 'err-sec', 'err-loc', 'err-photo'].forEach(
      (id) => {
        $(id).hidden = true;
      }
    );
  }

  function validate() {
    clearErrs();
    let ok = true;
    const n = parseInt($('uc_no').value, 10);
    if (!$('uc_no').value.trim() || isNaN(n) || n < 1 || n > 105) {
      $('err-uc_no').textContent = 'Valid UC Number (1–105) required';
      $('err-uc_no').hidden = false;
      ok = false;
    }
    if (!$('uc_name').value.trim()) {
      $('err-uc_name').textContent = 'UC Name is required';
      $('err-uc_name').hidden = false;
      ok = false;
    }
    if (!$('sec').value.trim()) {
      $('err-sec').textContent = 'Secretary name is required';
      $('err-sec').hidden = false;
      ok = false;
    }
    if (!photoBlob) {
      $('err-photo').textContent = 'Photo is mandatory';
      $('err-photo').hidden = false;
      ok = false;
    }
    if (
      !BYPASS_GEOFENCE &&
      (!loc || locState === 'idle' || locState === 'fetching' || locState === 'error')
    ) {
      $('err-loc').textContent =
        'Tap “Verify My Location” and allow GPS (inside or outside ' +
        GEOFENCE_M +
        'm is OK).';
      $('err-loc').hidden = false;
      ok = false;
    }
    return ok;
  }

  async function submit() {
    if (!validate()) return;
    const sel = selectedUC();
    if (!sel) return;
    let lat;
    let lng;
    let acc;
    if (BYPASS_GEOFENCE) {
      if (loc) {
        lat = loc.lat;
        lng = loc.lng;
        acc = loc.acc;
      } else {
        lat = sel.lat;
        lng = sel.lng;
        acc = 0;
      }
    } else {
      lat = loc.lat;
      lng = loc.lng;
      acc = loc.acc;
    }
    const btn = $('btn-submit');
    btn.disabled = true;
    btn.textContent = '⏳ Submitting…';
    const fd = new FormData();
    fd.append('uc_no', String(sel.no));
    fd.append('secretary_name', $('sec').value.trim());
    fd.append('lat', String(lat));
    fd.append('lng', String(lng));
    fd.append('accuracy', String(acc));
    fd.append('photo', photoBlob, 'photo.jpg');

    try {
      const res = await fetch(G.API_SUBMIT, { method: 'POST', body: fd });
      let data = {};
      try {
        data = await res.json();
      } catch (_) {
        data = { success: false, error: 'Invalid server response.' };
      }
      if (data.success) {
        $('form-screen').hidden = true;
        $('ok-saved').hidden = false;
        setTimeout(resetForm, 3200);
      } else {
        $('fail-msg').textContent =
          data.error || 'Server rejected the request.';
        $('form-screen').hidden = true;
        $('ok-fail').hidden = false;
        setTimeout(() => {
          $('ok-fail').hidden = true;
          $('form-screen').hidden = false;
        }, 4000);
      }
    } catch (e) {
      $('fail-msg').textContent = 'Network error — try again.';
      $('form-screen').hidden = true;
      $('ok-fail').hidden = false;
      setTimeout(() => {
        $('ok-fail').hidden = true;
        $('form-screen').hidden = false;
      }, 4000);
    }
    btn.disabled = false;
    btn.textContent = '✅ Submit Attendance';
  }

  function resetForm() {
    $('ok-saved').hidden = true;
    $('form-screen').hidden = false;
    $('uc_no').value = '';
    $('uc_name').value = '';
    $('sec').value = '';
    showTehsil('');
    photoBlob = null;
    $('photo').value = '';
    $('photo-preview-wrap').hidden = true;
    $('photo-lbl').textContent = '📸 Take Selfie / Upload Photo';
    locState = 'idle';
    loc = null;
    distance = null;
    clearErrs();
    renderGeo();
  }

  async function loadUcs() {
    const r = await fetch(G.API_UCS + '?t=' + Date.now());
    const data = await r.json();
    if (data.success && data.ucs) {
      ucList = data.ucs.map((u) => ({
        no: u.no,
        name: u.name,
        tehsil: u.tehsil,
        lat: parseFloat(u.lat),
        lng: parseFloat(u.lng),
      }));
    }
  }

  function initGpsBanner() {
    const banner = $('gps-banner');
    if (!navigator.geolocation) {
      banner.hidden = false;
      banner.textContent = '⚠️ GPS is not supported on this device.';
      return;
    }
    navigator.geolocation.getCurrentPosition(
      () => {
        $('perm-hint').hidden = true;
      },
      (err) => {
        if (err.code === 1) {
          banner.hidden = false;
          banner.textContent =
            '⚠️ Location denied. Tap 🔒 in the address bar → Allow Location → refresh.';
          $('perm-hint').hidden = false;
          $('perm-hint').innerHTML =
            '<strong>📍 Permission needed</strong><br/>Allow location, then refresh.';
        }
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
  }

  document.addEventListener('DOMContentLoaded', async () => {
    await loadUcs();
    initGpsBanner();
    $('uc_no').addEventListener('input', (e) => fillByNo(e.target.value));
    $('uc_name').addEventListener('input', (e) => fillByName(e.target.value));
    $('uc_name').addEventListener('blur', () =>
      setTimeout(() => hideSugg(), 200)
    );
    $('btn-loc').addEventListener('click', captureLocation);
    $('btn-submit').addEventListener('click', submit);
    $('photo').addEventListener('change', async (e) => {
      const f = e.target.files && e.target.files[0];
      if (!f) return;
      const reader = new FileReader();
      reader.onload = async (ev) => {
        photoBlob = await compressPhoto(ev.target.result);
        const url = URL.createObjectURL(photoBlob);
        $('photo-preview').src = url;
        $('photo-preview-wrap').hidden = false;
        $('photo-lbl').textContent = '📸 Photo Captured ✓';
      };
      reader.readAsDataURL(f);
    });
    $('photo-clear').addEventListener('click', () => {
      photoBlob = null;
      $('photo').value = '';
      $('photo-preview-wrap').hidden = true;
      $('photo-lbl').textContent = '📸 Take Selfie / Upload Photo';
    });
    renderGeo();
  });
})();
