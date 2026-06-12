<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

$pdo=null;
try{$pdo=new PDO("mysql:host=localhost;dbname=oee_monitoring;charset=utf8mb4",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
catch(PDOException $e){die('DB error.');}

$step=1;$error='';$success='';

if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['step']??'')==='1'){
    $username=trim($_POST['username']??'');$full_name=trim($_POST['full_name']??'');
    if(empty($username)||empty($full_name)){$error='Please fill in all fields.';}
    else{
        $st=$pdo->prepare("SELECT id,username,full_name FROM users WHERE username=? AND is_active=1 LIMIT 1");
        $st->execute([$username]);$row=$st->fetch();
        if($row&&strtolower(trim($row['full_name']))===strtolower($full_name)){
            $_SESSION['reset_uid']=$row['id'];$_SESSION['reset_user']=$row['username'];$step=2;
        }else{$error='Identity verification failed. Check your username and full name.';}
    }
}
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['step']??'')==='2'){
    if(empty($_SESSION['reset_uid'])){$error='Session expired. Start over.';$step=1;}
    else{
        $new=$_POST['new_password']??'';$confirm=$_POST['confirm_password']??'';
        if(strlen($new)<6){$error='Password must be at least 6 characters.';$step=2;}
        elseif($new!==$confirm){$error='Passwords do not match.';$step=2;}
        else{
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT),$_SESSION['reset_uid']]);
            unset($_SESSION['reset_uid'],$_SESSION['reset_user']);
            $success='Password reset successful. You may now sign in.';$step=1;
        }
    }
}
if($step===1&&!empty($_SESSION['reset_uid']))$step=2;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password — OEE Monitoring System</title>
<link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap');
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --m900:#1a0000;--m800:#3d0000;--m700:#6b0000;--m600:#8B1A1A;
  --m500:#b91c1c;--m400:#dc2626;--m300:#ef4444;--m200:#fca5a5;
  --gold:#fbbf24;
}
html,body{height:100%;overflow:hidden;}
body{font-family:'DM Sans',sans-serif;background:var(--m800);color:#fff;}

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

/* ─── LEFT ─── */
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
.left-desc{font-size:1rem;line-height:1.75;color:rgba(255,255,255,.62);max-width:440px;margin-bottom:24px;}

/* Step cards */
.step-cards{display:flex;flex-direction:column;gap:12px;margin-bottom:28px;}
.step-card{
  display:flex;align-items:center;gap:16px;
  background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);
  backdrop-filter:blur(8px);border-radius:14px;padding:16px 20px;transition:all .35s;
}
.step-card.active{background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.22);box-shadow:0 6px 24px rgba(0,0,0,.25);}
.step-card.done{border-color:rgba(251,191,36,.35);background:rgba(251,191,36,.07);}
.step-num{
  width:38px;height:38px;border-radius:50%;flex-shrink:0;
  border:2px solid rgba(255,255,255,.2);
  display:flex;align-items:center;justify-content:center;
  font-family:'Sora',sans-serif;font-size:.82rem;font-weight:700;
  color:rgba(255,255,255,.35);
}
.step-card.active .step-num{border-color:#fff;color:#fff;background:rgba(255,255,255,.12);}
.step-card.done .step-num{border-color:var(--gold);color:var(--gold);background:rgba(251,191,36,.12);}
.step-title{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:rgba(255,255,255,.4);}
.step-card.active .step-title,.step-card.done .step-title{color:#fff;}
.step-sub{font-size:.78rem;color:rgba(255,255,255,.3);margin-top:3px;}
.step-card.active .step-sub{color:rgba(255,255,255,.55);}

/* OEE mini widget */
.oee-mini{
  display:flex;align-items:center;gap:16px;
  background:rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.08);
  backdrop-filter:blur(10px);border-radius:12px;
  padding:14px 18px;margin-bottom:24px;
}
.mini-rings{display:flex;gap:12px;}
.mini-ring-wrap{display:flex;flex-direction:column;align-items:center;gap:4px;}
.mini-ring-wrap svg{transform:rotate(-90deg);}
.mr-bg{fill:none;stroke:rgba(255,255,255,.07);stroke-width:5;}
.mr-fill{fill:none;stroke-width:5;stroke-linecap:round;stroke-dasharray:157;stroke-dashoffset:157;}
.mr-fill.avail{stroke:#f87171;animation:mrf1 1.8s cubic-bezier(.4,0,.2,1) .8s forwards;}
.mr-fill.perf {stroke:#fbbf24;animation:mrf2 1.8s cubic-bezier(.4,0,.2,1) 1s forwards;}
.mr-fill.qual {stroke:#4ade80;animation:mrf3 1.8s cubic-bezier(.4,0,.2,1) 1.2s forwards;}
@keyframes mrf1{to{stroke-dashoffset:9;}}  /* 94.2% */
@keyframes mrf2{to{stroke-dashoffset:13;}} /* 91.8% */
@keyframes mrf3{to{stroke-dashoffset:3;}}  /* 98.1% */
.mr-lbl{font-size:.62rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.4);}
.mr-pct{font-family:'Sora',sans-serif;font-size:.72rem;font-weight:700;color:#fff;}
.mini-title{font-family:'Sora',sans-serif;font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:2px;}
.mini-sub{font-size:.8rem;color:rgba(255,255,255,.55);}

.left-btns{display:flex;align-items:center;gap:18px;}
.btn-hero-ghost{color:rgba(255,255,255,.7);font-weight:500;font-size:.95rem;text-decoration:none;display:flex;align-items:center;gap:8px;transition:color .2s;}
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
.login-card::before{content:'';position:absolute;top:0;left:0;right:0;height:5px;background:linear-gradient(90deg,var(--m600),var(--m400),var(--gold));}

.card-logo{display:flex;flex-direction:column;align-items:center;margin-bottom:20px;}
.card-logo img{height:40px;margin-bottom:7px;}
.card-logo-name{font-family:'Sora',sans-serif;font-size:.78rem;font-weight:700;letter-spacing:3px;color:var(--m600);text-transform:uppercase;}
.card-logo-bar{width:30px;height:2px;background:linear-gradient(90deg,var(--m600),var(--m300));border-radius:2px;margin-top:5px;}

.card-title{font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:800;color:#111;margin-bottom:4px;text-align:center;}
.card-sub{font-size:.88rem;color:#999;text-align:center;margin-bottom:20px;line-height:1.55;}

.card-error{background:#fef2f2;border:1px solid #fecaca;border-left:4px solid var(--m400);padding:10px 13px;border-radius:8px;margin-bottom:16px;font-size:.85rem;color:var(--m600);display:flex;align-items:center;gap:8px;animation:shake .4s ease;}
.card-success{background:#f0fdf4;border:1px solid #bbf7d0;border-left:4px solid #16a34a;padding:10px 13px;border-radius:8px;margin-bottom:16px;font-size:.85rem;color:#15803d;display:flex;align-items:flex-start;gap:8px;}
@keyframes shake{0%,100%{transform:translateX(0);}25%{transform:translateX(-6px);}75%{transform:translateX(6px);}}

.field-wrap{margin-bottom:15px;}
.field-label{font-size:.72rem;font-weight:700;color:#555;letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;display:block;}
.field-iw{position:relative;}
.field-ico{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#ccc;font-size:.85rem;pointer-events:none;}
.field-in{width:100%;padding:13px 14px 13px 42px;border:1.5px solid #e5e7eb;border-radius:11px;font-family:'DM Sans',sans-serif;font-size:1rem;color:#111;outline:none;transition:all .25s;background:#fafafa;}
.field-in:focus{border-color:var(--m500);background:#fff;box-shadow:0 0 0 3px rgba(185,28,28,.1);}
.field-in::placeholder{color:#ccc;}
.field-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#ccc;cursor:pointer;padding:4px;transition:color .2s;font-size:.9rem;}
.field-toggle:hover{color:var(--m500);}
.field-hint{font-size:.75rem;color:#bbb;margin-top:4px;}

.str-wrap{height:4px;background:#f0f0f0;border-radius:2px;margin-top:7px;}
.str-bar{height:100%;width:0;border-radius:2px;transition:width .3s,background .3s;}
.str-txt{font-size:.72rem;margin-top:4px;color:#bbb;}
.match-msg{font-size:.75rem;margin-top:4px;}

.btn-submit{width:100%;padding:14px;background:linear-gradient(135deg,var(--m600),var(--m500));border:none;border-radius:11px;color:#fff;font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;letter-spacing:.5px;cursor:pointer;position:relative;overflow:hidden;transition:all .3s;box-shadow:0 4px 18px rgba(139,26,26,.4);margin-bottom:8px;}
.btn-submit::after{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.2),transparent);transition:left .55s;}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(139,26,26,.55);}
.btn-submit:hover::after{left:100%;}
.btn-secondary{width:100%;padding:12px;background:transparent;border:1.5px solid #e5e7eb;border-radius:11px;color:#888;font-family:'Sora',sans-serif;font-size:.9rem;font-weight:600;cursor:pointer;transition:all .25s;text-decoration:none;display:block;text-align:center;}
.btn-secondary:hover{border-color:var(--m300);color:var(--m500);background:#fef2f2;}

.card-divider{display:flex;align-items:center;gap:10px;margin:16px 0;}
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
  <div class="blob blob-1"></div><div class="blob blob-2"></div><div class="blob blob-3"></div>
</div>
<div class="particles" id="particles"></div>

<div class="main">

  <!-- LEFT -->
  <div class="left-content">
    <div class="left-tag"><span class="live-dot"></span> Account Recovery</div>
    <h1 class="left-headline">Secure<br><em>Recovery.</em></h1>
    <p class="left-desc">Verify your identity and regain access to the OEE Monitoring System in two simple steps.</p>

    <!-- Step cards -->
    <div class="step-cards">
      <div class="step-card <?= $step>=1?($step>1?'done':'active'):'' ?>">
        <div class="step-num"><?= $step>1?'✓':'01' ?></div>
        <div class="step-info">
          <div class="step-title">Verify Identity</div>
          <div class="step-sub">Username + full name check</div>
        </div>
      </div>
      <div class="step-card <?= $step>=2?'active':'' ?>">
        <div class="step-num">02</div>
        <div class="step-info">
          <div class="step-title">Set New Password</div>
          <div class="step-sub">Choose a strong new password</div>
        </div>
      </div>
    </div>

    <!-- OEE mini rings -->
    <div class="oee-mini">
      <div>
        <div class="mini-title">System Status</div>
        <div class="mini-sub">All 30 machines online</div>
      </div>
      <div class="mini-rings">
        <div class="mini-ring-wrap">
          <svg width="44" height="44" viewBox="0 0 44 44">
            <circle class="mr-bg" cx="22" cy="22" r="17"/>
            <circle class="mr-fill avail" cx="22" cy="22" r="17"/>
          </svg>
          <div class="mr-pct">94%</div>
          <div class="mr-lbl">Avail</div>
        </div>
        <div class="mini-ring-wrap">
          <svg width="44" height="44" viewBox="0 0 44 44">
            <circle class="mr-bg" cx="22" cy="22" r="17"/>
            <circle class="mr-fill perf" cx="22" cy="22" r="17"/>
          </svg>
          <div class="mr-pct">92%</div>
          <div class="mr-lbl">Perf</div>
        </div>
        <div class="mini-ring-wrap">
          <svg width="44" height="44" viewBox="0 0 44 44">
            <circle class="mr-bg" cx="22" cy="22" r="17"/>
            <circle class="mr-fill qual" cx="22" cy="22" r="17"/>
          </svg>
          <div class="mr-pct">98%</div>
          <div class="mr-lbl">Qual</div>
        </div>
      </div>
    </div>

    <div class="left-btns">
      <a href="login.php" class="btn-hero-ghost"><i class="fas fa-arrow-left"></i> Back to Sign In</a>
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
      <div class="card-title"><?= $step===1?'Forgot Password':'Set New Password' ?></div>
      <div class="card-sub">
        <?= $step===1
          ? 'Enter your username and registered full name to verify your identity.'
          : 'Set a new password for <strong style="color:var(--m600)">'.htmlspecialchars($_SESSION['reset_user']??'').'</strong>' ?>
      </div>

      <?php if($error): ?>
      <div class="card-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if($success): ?>
      <div class="card-success"><i class="fas fa-check-circle" style="margin-top:1px;"></i><div><?= htmlspecialchars($success) ?> <a href="login.php" style="color:var(--m600);font-weight:600;">Sign in now →</a></div></div>
      <?php endif; ?>

      <?php if($step===1&&!$success): ?>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="step" value="1">
        <div class="field-wrap">
          <label class="field-label">Username</label>
          <div class="field-iw">
            <i class="field-ico fas fa-user"></i>
            <input type="text" name="username" class="field-in" placeholder="Enter your username" required autofocus value="<?= htmlspecialchars($_POST['username']??'') ?>">
          </div>
        </div>
        <div class="field-wrap">
          <label class="field-label">Full Name <span style="font-weight:400;color:#bbb;text-transform:none;letter-spacing:0;">(as registered)</span></label>
          <div class="field-iw">
            <i class="field-ico fas fa-id-card"></i>
            <input type="text" name="full_name" class="field-in" placeholder="Enter your full name" required value="<?= htmlspecialchars($_POST['full_name']??'') ?>">
          </div>
          <div class="field-hint">Must match exactly as saved in your profile.</div>
        </div>
        <button type="submit" class="btn-submit"><i class="fas fa-arrow-right" style="margin-right:8px;"></i>Verify Identity</button>
      </form>
      <?php endif; ?>

      <?php if($step===2): ?>
      <form method="POST" id="fmReset" autocomplete="off">
        <input type="hidden" name="step" value="2">
        <div class="field-wrap">
          <label class="field-label">New Password</label>
          <div class="field-iw">
            <i class="field-ico fas fa-lock"></i>
            <input type="password" id="newPw" name="new_password" class="field-in" placeholder="Minimum 6 characters" required minlength="6" autocomplete="new-password">
            <button type="button" class="field-toggle" onclick="togglePw('newPw','ic1')"><i class="fas fa-eye" id="ic1"></i></button>
          </div>
          <div class="str-wrap"><div class="str-bar" id="strBar"></div></div>
          <div class="str-txt" id="strTxt"></div>
        </div>
        <div class="field-wrap">
          <label class="field-label">Confirm Password</label>
          <div class="field-iw">
            <i class="field-ico fas fa-lock"></i>
            <input type="password" id="confPw" name="confirm_password" class="field-in" placeholder="Repeat new password" required autocomplete="new-password">
            <button type="button" class="field-toggle" onclick="togglePw('confPw','ic2')"><i class="fas fa-eye" id="ic2"></i></button>
          </div>
          <div class="match-msg" id="matchMsg"></div>
        </div>
        <button type="submit" class="btn-submit"><i class="fas fa-save" style="margin-right:8px;"></i>Reset Password</button>
        <a href="forgot_password.php" class="btn-secondary"><i class="fas fa-undo" style="margin-right:6px;"></i>Start Over</a>
      </form>
      <?php endif; ?>

      <div class="card-divider"><span>or</span></div>
      <div class="card-links">
        <a class="card-link" href="login.php"><i class="fas fa-arrow-left"></i> Back to Sign In</a>
        <span class="card-link-sep">|</span>
        <a class="card-link" href="welcome.php"><i class="fas fa-home"></i> Home</a>
      </div>
    </div>
  </div>

</div>

<script>
function togglePw(id,ico){
  var e=document.getElementById(id),i=document.getElementById(ico);
  e.type=e.type==='password'?'text':'password';
  i.className=e.type==='password'?'fas fa-eye':'fas fa-eye-slash';
}
var nP=document.getElementById('newPw'),cP=document.getElementById('confPw');
if(nP){nP.addEventListener('input',function(){
  var pw=this.value,s=0;
  if(pw.length>=6)s++;if(pw.length>=10)s++;
  if(/[A-Z]/.test(pw)&&/[a-z]/.test(pw))s++;
  if(/[0-9]/.test(pw))s++;if(/[^A-Za-z0-9]/.test(pw))s++;
  var pct=s*20,col=pct<=40?'#dc2626':pct<=60?'#f59e0b':'#16a34a';
  var lbl=pct<=20?'Very Weak':pct<=40?'Weak':pct<=60?'Fair':pct<=80?'Strong':'Very Strong';
  document.getElementById('strBar').style.cssText='width:'+pct+'%;background:'+col;
  var t=document.getElementById('strTxt');t.textContent=pw.length?'Strength: '+lbl:'';t.style.color=col;
  chkMatch();
});}
if(cP)cP.addEventListener('input',chkMatch);
function chkMatch(){
  if(!cP||!nP)return;var m=document.getElementById('matchMsg');
  if(!cP.value){m.textContent='';return;}
  if(nP.value===cP.value){m.textContent='✓ Passwords match';m.style.color='#16a34a';}
  else{m.textContent='✗ Passwords do not match';m.style.color='#dc2626';}
}
var fr=document.getElementById('fmReset');
if(fr)fr.addEventListener('submit',function(e){if(nP.value!==cP.value){e.preventDefault();alert('Passwords do not match!');}});

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
