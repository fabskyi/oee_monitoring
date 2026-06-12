<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

$error = '';
$remembered_username = isset($_COOKIE['remember_username']) ? htmlspecialchars($_COOKIE['remember_username']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=oee_monitoring;charset=utf8mb4", 'root', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $row = $stmt->fetch();
            if ($row && password_verify($password, $row['password_hash'])) {
                if (isset($row['is_active']) && $row['is_active'] == 0) {
                    $error = 'Account is inactive. Contact administrator.';
                } else {
                    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$row['id']]);
                    $_SESSION['user_id']   = $row['id'];
                    $_SESSION['username']  = $row['username'];
                    $_SESSION['full_name'] = $row['full_name'] ?? $row['username'];
                    $_SESSION['role']      = $row['role'] ?? 'operator';
                    $_SESSION['user_role'] = $row['role'] ?? 'operator';
                    $_SESSION['avatar']    = $row['avatar'] ?? null;
                    if ($remember) setcookie('remember_username', $username, time()+30*24*3600, '/');
                    else setcookie('remember_username', '', time()-3600, '/');
                    header('Location: dashboard.php'); exit;
                }
            } else { $error = 'Invalid username or password.'; }
        } catch (PDOException $e) { $error = 'Database connection error.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — OEE Monitoring System</title>
<link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap');
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --m900:#1a0000;--m800:#3d0000;--m700:#6b0000;--m600:#8B1A1A;
  --m500:#b91c1c;--m400:#dc2626;--m300:#ef4444;--m200:#fca5a5;
  --gold:#fbbf24;--green:#22c55e;
}
html,body{height:100%;overflow:hidden;}
body{font-family:'DM Sans',sans-serif;background:var(--m800);color:#fff;}

/* Background */
.bg-wrap{position:fixed;inset:0;overflow:hidden;z-index:0;
  background:linear-gradient(135deg,var(--m900) 0%,var(--m800) 40%,var(--m700) 100%);}
.blob{position:absolute;border-radius:50%;animation:blobPulse 6s ease-in-out infinite alternate;}
.blob-1{width:700px;height:700px;background:radial-gradient(circle,rgba(139,26,26,.55) 0%,transparent 70%);top:-250px;left:-150px;animation-duration:7s;}
.blob-2{width:500px;height:500px;background:radial-gradient(circle,rgba(185,28,28,.3) 0%,transparent 70%);top:80px;left:180px;animation-duration:9s;animation-delay:-3s;}
.blob-3{width:800px;height:800px;background:radial-gradient(circle,rgba(107,0,0,.4) 0%,transparent 70%);bottom:-300px;right:-200px;animation-duration:8s;animation-delay:-1s;}
@keyframes blobPulse{0%{transform:scale(1) translate(0,0);}100%{transform:scale(1.1) translate(18px,-18px);}}

.particles{position:fixed;inset:0;z-index:1;pointer-events:none;}
.p-dot{position:absolute;border-radius:50%;opacity:0;animation:rise linear infinite;}
@keyframes rise{0%{transform:translateY(100vh) translateX(0);opacity:0;}10%{opacity:1;}90%{opacity:.5;}100%{transform:translateY(-10vh) translateX(var(--dx));opacity:0;}}

/* Layout */
.main{
  position:relative;z-index:10;height:100vh;
  display:flex;align-items:center;
  padding:0 5% 0 6%;
  justify-content:center;
  gap:6%;
}

/* ─── LEFT CONTENT ─── */
.left-content{
  flex:1;max-width:600px;
  animation:slideLeft .9s cubic-bezier(.16,1,.3,1) forwards;
  opacity:0;transform:translateX(-50px);
}
@keyframes slideLeft{to{opacity:1;transform:translateX(0);}}

.left-tag{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);
  backdrop-filter:blur(8px);padding:7px 16px;border-radius:50px;
  font-size:.8rem;font-weight:600;letter-spacing:2px;
  color:var(--m200);margin-bottom:18px;text-transform:uppercase;
}
.live-dot{width:7px;height:7px;border-radius:50%;background:var(--gold);animation:blink 1.2s infinite;display:inline-block;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:.15;}}

.left-headline{
  font-family:'Sora',sans-serif;
  font-size:clamp(2.6rem,4.5vw,4rem);
  font-weight:800;line-height:1.08;
  margin-bottom:12px;color:#fff;
}
.left-headline em{
  font-style:normal;
  background:linear-gradient(90deg,var(--m300),var(--gold));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.left-desc{
  font-size:1rem;line-height:1.75;
  color:rgba(255,255,255,.62);max-width:460px;margin-bottom:24px;
}

/* ─── OEE WIDGET ─── */
.oee-widget{
  display:flex;align-items:center;gap:24px;
  background:rgba(0,0,0,.25);
  border:1px solid rgba(255,255,255,.1);
  backdrop-filter:blur(12px);
  border-radius:16px;padding:20px 24px;
  margin-bottom:24px;
}
.oee-main{position:relative;flex-shrink:0;}
.oee-main svg{transform:rotate(-90deg);}
.oee-ring-bg{fill:none;stroke:rgba(255,255,255,.08);stroke-width:8;}
.oee-ring-fill{fill:none;stroke-width:8;stroke-linecap:round;
  stroke:url(#oeeGrad);
  stroke-dasharray:408;stroke-dashoffset:408;
  animation:ringFill 2s cubic-bezier(.4,0,.2,1) .5s forwards;
}
@keyframes ringFill{to{stroke-dashoffset:52;}} /* 87.4% → 408*(1-.874)=51.7 */
.oee-center{
  position:absolute;inset:0;display:flex;flex-direction:column;
  align-items:center;justify-content:center;
}
.oee-pct{
  font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800;
  color:#fff;line-height:1;
}
.oee-lbl-c{font-size:.6rem;letter-spacing:1.5px;color:rgba(255,255,255,.45);text-transform:uppercase;margin-top:2px;}

.oee-metrics{flex:1;display:flex;flex-direction:column;gap:12px;}
.oee-metric-row{display:flex;align-items:center;gap:10px;}
.metric-label{font-size:.72rem;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.5);width:24px;flex-shrink:0;}
.metric-track{flex:1;height:7px;background:rgba(255,255,255,.08);border-radius:4px;overflow:hidden;}
.metric-bar{height:100%;border-radius:4px;width:0;animation:barFill 1.8s cubic-bezier(.4,0,.2,1) forwards;}
.metric-bar.avail{background:linear-gradient(90deg,#dc2626,#f87171);animation-delay:.7s;}
.metric-bar.perf {background:linear-gradient(90deg,#f59e0b,#fbbf24);animation-delay:.9s;}
.metric-bar.qual {background:linear-gradient(90deg,#16a34a,#4ade80);animation-delay:1.1s;}
@keyframes barFill{to{width:var(--w);}}
.metric-val{font-family:'Sora',sans-serif;font-size:.8rem;font-weight:700;color:#fff;width:40px;text-align:right;flex-shrink:0;}

.oee-metric-names{display:flex;flex-direction:column;gap:12px;}
.oee-metric-name{font-size:.72rem;color:rgba(255,255,255,.4);line-height:1;padding-top:1px;}

.widget-title{
  font-family:'Sora',sans-serif;font-size:.7rem;font-weight:700;
  letter-spacing:2px;text-transform:uppercase;
  color:rgba(255,255,255,.3);margin-bottom:8px;
}
.oee-right{flex:1;}

/* Mini pulse bars animation */
.pulse-bars{display:flex;align-items:flex-end;gap:4px;height:32px;margin-top:10px;}
.pb{width:8px;border-radius:2px;background:rgba(220,38,38,.5);animation:pulseBar 1.2s ease-in-out infinite alternate;}
.pb:nth-child(2){animation-delay:.15s;background:rgba(220,38,38,.6);}
.pb:nth-child(3){animation-delay:.3s;background:rgba(220,38,38,.7);}
.pb:nth-child(4){animation-delay:.45s;background:rgba(220,38,38,.65);}
.pb:nth-child(5){animation-delay:.6s;background:rgba(220,38,38,.5);}
.pb:nth-child(6){animation-delay:.75s;}
.pb:nth-child(7){animation-delay:.9s;}
@keyframes pulseBar{0%{height:6px;}100%{height:28px;}}

.left-btns{display:flex;align-items:center;gap:18px;}
.btn-hero-solid{
  background:#fff;color:var(--m600);
  padding:12px 28px;border-radius:50px;
  font-weight:700;font-size:.95rem;text-decoration:none;
  transition:all .3s;box-shadow:0 8px 24px rgba(0,0,0,.25);
}
.btn-hero-solid:hover{transform:translateY(-2px);box-shadow:0 14px 32px rgba(0,0,0,.35);}
.btn-hero-ghost{
  color:rgba(255,255,255,.75);font-weight:500;font-size:.95rem;
  text-decoration:none;display:flex;align-items:center;gap:8px;transition:color .2s;
}
.btn-hero-ghost:hover{color:#fff;}

/* ─── RIGHT CARD ─── */
.right-wrap{
  flex:0 0 420px;
  animation:slideRight .9s cubic-bezier(.16,1,.3,1) .15s forwards;
  opacity:0;transform:translateX(50px);
}
@keyframes slideRight{to{opacity:1;transform:translateX(0);}}

.login-card{
  background:#fff;border-radius:24px;
  padding:40px 36px;
  box-shadow:0 40px 90px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.06);
  position:relative;overflow:hidden;
}
.login-card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:5px;
  background:linear-gradient(90deg,var(--m600),var(--m400),var(--gold));
}

.card-logo{display:flex;flex-direction:column;align-items:center;margin-bottom:20px;}
.card-logo img{height:40px;margin-bottom:7px;}
.card-logo-name{font-family:'Sora',sans-serif;font-size:.78rem;font-weight:700;letter-spacing:3px;color:var(--m600);text-transform:uppercase;}
.card-logo-bar{width:30px;height:2px;background:linear-gradient(90deg,var(--m600),var(--m300));border-radius:2px;margin-top:5px;}

.card-title{font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:800;color:#111;margin-bottom:4px;text-align:center;}
.card-sub{font-size:.88rem;color:#999;text-align:center;margin-bottom:22px;}

.card-error{
  background:#fef2f2;border:1px solid #fecaca;border-left:4px solid var(--m400);
  padding:10px 13px;border-radius:8px;margin-bottom:16px;
  font-size:.85rem;color:var(--m600);display:flex;align-items:center;gap:8px;
  animation:shake .4s ease;
}
@keyframes shake{0%,100%{transform:translateX(0);}25%{transform:translateX(-6px);}75%{transform:translateX(6px);}}

.field-wrap{margin-bottom:15px;}
.field-label{font-size:.72rem;font-weight:700;color:#555;letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;display:block;}
.field-iw{position:relative;}
.field-ico{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#ccc;font-size:.85rem;pointer-events:none;}
.field-in{
  width:100%;padding:13px 14px 13px 42px;
  border:1.5px solid #e5e7eb;border-radius:11px;
  font-family:'DM Sans',sans-serif;font-size:1rem;
  color:#111;outline:none;transition:all .25s;background:#fafafa;
}
.field-in:focus{border-color:var(--m500);background:#fff;box-shadow:0 0 0 3px rgba(185,28,28,.1);}
.field-in::placeholder{color:#ccc;}
.field-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#ccc;cursor:pointer;padding:4px;transition:color .2s;font-size:.9rem;}
.field-toggle:hover{color:var(--m500);}

.remember-row{display:flex;align-items:center;margin-bottom:18px;}
.check-lbl{display:flex;align-items:center;gap:8px;cursor:pointer;}
.check-lbl input{accent-color:var(--m500);width:15px;height:15px;cursor:pointer;}
.check-lbl span{font-size:.85rem;color:#777;}

.btn-login{
  width:100%;padding:14px;
  background:linear-gradient(135deg,var(--m600),var(--m500));
  border:none;border-radius:11px;color:#fff;
  font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;
  letter-spacing:.5px;cursor:pointer;position:relative;overflow:hidden;
  transition:all .3s;box-shadow:0 4px 18px rgba(139,26,26,.4);
}
.btn-login::after{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.2),transparent);transition:left .55s;}
.btn-login:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(139,26,26,.55);}
.btn-login:hover::after{left:100%;}

.card-divider{display:flex;align-items:center;gap:10px;margin:18px 0;}
.card-divider::before,.card-divider::after{content:'';flex:1;height:1px;background:#eee;}
.card-divider span{font-size:.75rem;color:#bbb;}

.card-links{display:flex;justify-content:center;align-items:center;gap:16px;}
.card-link{font-size:.82rem;color:#999;text-decoration:none;display:flex;align-items:center;gap:5px;transition:color .2s;}
.card-link:hover{color:var(--m500);}
.card-link-sep{color:#ddd;}

@media(max-width:900px){
  .left-content{display:none;}
  .main{justify-content:center;padding:24px 16px;}
  .right-wrap{flex:0 0 100%;max-width:440px;}
}
</style>
</head>
<body>

<div class="bg-wrap">
  <div class="blob blob-1"></div>
  <div class="blob blob-2"></div>
  <div class="blob blob-3"></div>
</div>
<div class="particles" id="particles"></div>

<div class="main">

  <!-- LEFT -->
  <div class="left-content">
    <div class="left-tag"><span class="live-dot"></span> OEE Monitoring System v2.0</div>
    <h1 class="left-headline">Smart Factory.<br><em>Real-Time.</em></h1>
    <p class="left-desc">Monitor Overall Equipment Effectiveness across all Makino CNC machines. Track availability, performance &amp; quality — live from the floor.</p>

    <!-- OEE animated widget -->
    <div class="oee-widget">
      <!-- Main OEE ring -->
      <div class="oee-main">
        <svg width="110" height="110" viewBox="0 0 110 110">
          <defs>
            <linearGradient id="oeeGrad" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stop-color="#dc2626"/>
              <stop offset="100%" stop-color="#fbbf24"/>
            </linearGradient>
          </defs>
          <circle class="oee-ring-bg" cx="55" cy="55" r="45"/>
          <circle class="oee-ring-fill" cx="55" cy="55" r="45"/>
        </svg>
        <div class="oee-center">
          <div class="oee-pct" id="oeePct">0%</div>
          <div class="oee-lbl-c">OEE</div>
        </div>
      </div>

      <!-- Metrics -->
      <div class="oee-right">
        <div class="widget-title">Live Performance</div>
        <div class="oee-metrics">
          <div class="oee-metric-row">
            <div class="metric-label" style="color:rgba(248,113,113,.8)">A</div>
            <div class="metric-track"><div class="metric-bar avail" style="--w:94.2%"></div></div>
            <div class="metric-val" id="mA">0%</div>
          </div>
          <div class="oee-metric-row">
            <div class="metric-label" style="color:rgba(251,191,36,.8)">P</div>
            <div class="metric-track"><div class="metric-bar perf" style="--w:91.8%"></div></div>
            <div class="metric-val" id="mP">0%</div>
          </div>
          <div class="oee-metric-row">
            <div class="metric-label" style="color:rgba(74,222,128,.8)">Q</div>
            <div class="metric-track"><div class="metric-bar qual" style="--w:98.1%"></div></div>
            <div class="metric-val" id="mQ">0%</div>
          </div>
        </div>
        <div class="pulse-bars" style="margin-top:14px;">
          <div class="pb" style="height:10px"></div>
          <div class="pb" style="height:18px"></div>
          <div class="pb" style="height:24px"></div>
          <div class="pb" style="height:14px"></div>
          <div class="pb" style="height:28px"></div>
          <div class="pb" style="height:20px"></div>
          <div class="pb" style="height:16px"></div>
          <div class="pb" style="height:22px"></div>
        </div>
      </div>
    </div>

    <div class="left-btns">
      <a href="welcome.php" class="btn-hero-solid">Learn More</a>
      <a href="tv_dashboard.php" target="_blank" class="btn-hero-ghost"><i class="fas fa-tv"></i> TV Dashboard</a>
    </div>
  </div>

  <!-- RIGHT CARD -->
  <div class="right-wrap">
    <div class="login-card">
      <div class="card-logo">
        <img src="img/yanmar.png" alt="Logo">
        <div class="card-logo-name">PT. YADIN</div>
        <div class="card-logo-bar"></div>
      </div>
      <div class="card-title">Welcome Back</div>
      <div class="card-sub">Sign in to access the monitoring system</div>

      <?php if ($error): ?>
      <div class="card-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <div class="field-wrap">
          <label class="field-label">Username</label>
          <div class="field-iw">
            <i class="field-ico fas fa-user"></i>
            <input type="text" name="username" class="field-in" placeholder="Enter username" required autofocus value="<?= htmlspecialchars($remembered_username) ?>">
          </div>
        </div>
        <div class="field-wrap">
          <label class="field-label">Password</label>
          <div class="field-iw">
            <i class="field-ico fas fa-lock"></i>
            <input type="password" id="pwField" name="password" class="field-in" placeholder="Enter password" required>
            <button type="button" class="field-toggle" onclick="togglePw()"><i class="fas fa-eye" id="pwIcon"></i></button>
          </div>
        </div>
        <div class="remember-row">
          <label class="check-lbl">
            <input type="checkbox" name="remember" <?= $remembered_username?'checked':'' ?>>
            <span>Remember me (30 days)</span>
          </label>
        </div>
        <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt" style="margin-right:8px;"></i>Sign In</button>
      </form>

      <div class="card-divider"><span>or</span></div>
      <div class="card-links">
        <a class="card-link" href="forgot_password.php"><i class="fas fa-key"></i> Forgot password?</a>
        <span class="card-link-sep">|</span>
        <a class="card-link" href="welcome.php"><i class="fas fa-home"></i> Back to Home</a>
      </div>
    </div>
  </div>

</div>

<script>
function togglePw(){
  var f=document.getElementById('pwField'),i=document.getElementById('pwIcon');
  f.type=f.type==='password'?'text':'password';
  i.className=f.type==='password'?'fas fa-eye':'fas fa-eye-slash';
}

/* Count-up animation */
function countUp(el, target, duration, suffix) {
  var start=0, step=target/((duration/16));
  var t=setInterval(function(){
    start+=step;
    if(start>=target){start=target;clearInterval(t);}
    el.textContent=start.toFixed(1)+suffix;
  },16);
}
setTimeout(function(){
  countUp(document.getElementById('oeePct'), 87.4, 1800, '%');
  countUp(document.getElementById('mA'), 94.2, 1600, '%');
  countUp(document.getElementById('mP'), 91.8, 1600, '%');
  countUp(document.getElementById('mQ'), 98.1, 1600, '%');
}, 600);

/* Particles */
(function(){
  var c=document.getElementById('particles');
  for(var n=0;n<40;n++){
    var p=document.createElement('div');p.className='p-dot';
    var col=Math.random()>.6?'rgba(251,191,36,.5)':Math.random()>.5?'rgba(220,38,38,.4)':'rgba(255,255,255,.25)';
    p.style.cssText='width:'+(Math.random()<.3?3:2)+'px;height:'+(Math.random()<.3?3:2)+'px;background:'+col+';left:'+(Math.random()*100)+'%;animation-duration:'+(10+Math.random()*18)+'s;animation-delay:'+(Math.random()*20)+'s;--dx:'+((Math.random()-.5)*160)+'px;';
    c.appendChild(p);
  }
})();
</script>
</body>
</html>
