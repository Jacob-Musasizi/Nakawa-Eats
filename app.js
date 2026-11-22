const $ = s => document.querySelector(s);
const UGX = n => 'UGX ' + Number(n).toLocaleString();
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function api(a, body, qs = '') {
  const o = body instanceof FormData ? {method:'POST', body}
    : body ? {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)} : {};
  const r = await fetch('api.php?a=' + a + qs, o), d = await r.json();
  if (!r.ok) throw new Error(d.error || 'Something went wrong');
  return d;
}
let ac;
function beep() {
  try {
    ac = ac || new (window.AudioContext || window.webkitAudioContext)(); ac.resume();
    [0, .35, .7].forEach(t => {
      const o = ac.createOscillator(), g = ac.createGain(), s = ac.currentTime + t;
      o.frequency.value = 880; o.connect(g); g.connect(ac.destination);
      g.gain.setValueAtTime(.4, s); g.gain.exponentialRampToValueAtTime(.001, s + .3);
      o.start(s); o.stop(s + .3);
    });
  } catch (e) {}
}
function callToken(t) { // alarm + spoken call
  beep();
  try { speechSynthesis.speak(new SpeechSynthesisUtterance('Token number ' + t + '. Please pick up your order.')); } catch (e) {}
}

function forgotForm(type, el, back) { // type: 'staff' or 'customer'
  el.innerHTML = `<form class="card form" id="fp"><h2>Forgot your password?</h2><p>Enter the email you registered with and we will send you a link to set a new password.</p><input name="email" type="email" placeholder="Email" required><button class="big">Send reset link</button><a href="#" id="bk">Back to login</a></form>`;
  $('#bk').onclick = e => { e.preventDefault(); back(); };
  $('#fp').onsubmit = async e => {
    e.preventDefault();
    try {
      const r = await api('forgot', {email: new FormData(e.target).get('email'), type});
      el.innerHTML = `<div class="card form"><h2>Check your email</h2><p>${esc(r.message)}</p>${r.demo_link ? `<p><b>Test mode (no email server):</b> <a href="${esc(r.demo_link)}">open your reset link</a></p>` : ''}<a href="#" id="bk">Back to login</a></div>`;
      $('#bk').onclick = ev => { ev.preventDefault(); back(); };
    } catch (x) { alert(x.message); }
  };
}
