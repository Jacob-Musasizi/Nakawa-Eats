<?php
require 'config.php';
if (!defined('BASE_URL')) define('BASE_URL', 'http://localhost/restaurant-system');
if (!defined('DEMO_SHOW_RESET_LINK')) define('DEMO_SHOW_RESET_LINK', false);
header('Content-Type: application/json');
$a = $_GET['a'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?: [];
function out($d, $c = 200) { http_response_code($c); echo json_encode($d); exit; }
function q($sql, $p = []) { global $db; $s = $db->prepare($sql); $s->execute($p); return $s; }
function need() { if (empty($_SESSION['u'])) out(['error' => 'Please log in'], 401); return $_SESSION['u']; }
function owner() { $u = need(); if ($u['role'] !== 'owner') out(['error' => 'Owners only'], 403); return $u; }
function cust() { if (empty($_SESSION['c'])) out(['error' => 'Please log in to order'], 401); return $_SESSION['c']; }
function csess($id) { $c = q('SELECT id,name,email,phone,address FROM customers WHERE id=?', [$id])->fetch(); session_regenerate_id(true); $_SESSION['c'] = $c; out($c); }
function lines($r, $items) { // prices always come from the database, never the browser
  $l = []; $t = 0;
  foreach ($items as $i) {
    $m = q('SELECT id,name,price FROM menu_items WHERE id=? AND restaurant_id=? AND available=1', [(int)$i['id'], $r])->fetch();
    if ($m) { $m['qty'] = max(1, min(20, (int)$i['qty'])); $l[] = $m; $t += $m['price'] * $m['qty']; }
  }
  return [$l, $t];
}
function luhn($n) { $s = 0; foreach (array_reverse(str_split($n)) as $i => $d) { $d = (int)$d; if ($i % 2) { $d *= 2; if ($d > 9) $d -= 9; } $s += $d; } return $s % 10 === 0; }
// DEMO payment gateway: checks the details and approves. Before real use, replace the body with a call to a real
// MTN MoMo / Airtel Money / card provider (e.g. Flutterwave or Pesapal). Card details are never stored.
function gateway($m, $d) {
  if ($m === 'card') {
    $n = preg_replace('/\D/', '', $d['card'] ?? '');
    if (strlen($n) < 13 || strlen($n) > 19 || !luhn($n)) return 'Invalid card number';
    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $d['exp'] ?? '') || !preg_match('/^\d{3,4}$/', $d['cvv'] ?? '')) return 'Check the card expiry (MM/YY) and CVV';
    return true;
  }
  $p = preg_replace('/^256/', '0', preg_replace('/\D/', '', $d['momo'] ?? ''));
  if (!preg_match($m === 'mtn_momo' ? '/^0(77|78|76)\d{7}$/' : '/^0(70|75|74)\d{7}$/', $p)) return 'Enter a valid ' . ($m === 'mtn_momo' ? 'MTN' : 'Airtel') . ' number';
  return true;
}
function sess($id) {
  $u = q('SELECT u.id,u.name,u.role,u.restaurant_id rid,r.name restaurant,r.delivery_fee fee FROM users u JOIN restaurants r ON r.id=u.restaurant_id WHERE u.id=?', [$id])->fetch();
  session_regenerate_id(true); $_SESSION['u'] = $u; out($u);
}
try { switch ($a) {

// ---------- Public (customers, no account) ----------
case 'restaurants':
  out(q('SELECT id,name,location,delivery_fee FROM restaurants ORDER BY name')->fetchAll());

case 'menu':
  $r = (int)($_GET['r'] ?? 0);
  $n = q('SELECT name,delivery_fee FROM restaurants WHERE id=?', [$r])->fetch();
  if (!$n) out(['error' => 'Restaurant not found'], 404);
  out(['name' => $n['name'], 'fee' => $n['delivery_fee'], 'items' => q('SELECT id,name,category,price,image FROM menu_items WHERE restaurant_id=? AND available=1 ORDER BY name', [$r])->fetchAll()]);

case 'order':
  $r = (int)($in['r'] ?? 0);
  $type = ($in['type'] ?? '') === 'take_away' ? 'take_away' : 'dine_in';
  [$lines, $total] = lines($r, $in['items'] ?? []);
  if (!$lines) out(['error' => 'Your order is empty'], 400);
  $db->beginTransaction();
  $t = q('SELECT COALESCE(MAX(token),0)+1 FROM orders WHERE restaurant_id=? AND DATE(created_at)=CURDATE() FOR UPDATE', [$r])->fetchColumn();
  q('INSERT INTO orders(restaurant_id,token,order_type,total) VALUES(?,?,?,?)', [$r, $t, $type, $total]);
  $id = $db->lastInsertId();
  foreach ($lines as $m) q('INSERT INTO order_items(order_id,item_id,name,price,qty) VALUES(?,?,?,?,?)', [$id, $m['id'], $m['name'], $m['price'], $m['qty']]);
  $db->commit();
  out(['id' => $id, 'token' => $t, 'total' => $total]);

case 'track': // by order id, or by restaurant + token (today)
  $o = isset($_GET['id'])
    ? q('SELECT id,token,status,called_at,total FROM orders WHERE id=?', [(int)$_GET['id']])->fetch()
    : q('SELECT id,token,status,called_at,total FROM orders WHERE restaurant_id=? AND token=? AND DATE(created_at)=CURDATE()', [(int)$_GET['r'], (int)$_GET['t']])->fetch();
  $o ? out($o) : out(['error' => 'Token not found for today'], 404);

case 'board':
  $r = (int)($_GET['r'] ?? 0);
  out(['name' => q('SELECT name FROM restaurants WHERE id=?', [$r])->fetchColumn(),
       'orders' => q("SELECT token,status,called_at FROM orders WHERE restaurant_id=? AND DATE(created_at)=CURDATE() AND order_type<>'delivery' AND status IN('pending','preparing','ready') ORDER BY token", [$r])->fetchAll()]);

// ---------- Customers: online ordering and delivery ----------
case 'cregister':
  $e = strtolower(trim($in['email'] ?? '')); $pw = $in['password'] ?? '';
  if (!filter_var($e, FILTER_VALIDATE_EMAIL) || strlen($pw) < 6 || !trim($in['name'] ?? '') || !trim($in['phone'] ?? '')) out(['error' => 'Check your details (password needs 6+ characters)'], 400);
  if (q('SELECT 1 FROM customers WHERE email=?', [$e])->fetch()) out(['error' => 'That email is already registered'], 400);
  q('INSERT INTO customers(name,email,phone,address,password_hash) VALUES(?,?,?,?,?)', [trim($in['name']), $e, trim($in['phone']), trim($in['address'] ?? ''), password_hash($pw, PASSWORD_DEFAULT)]);
  csess($db->lastInsertId());

case 'clogin':
  $c = q('SELECT id,password_hash FROM customers WHERE email=?', [strtolower(trim($in['email'] ?? ''))])->fetch();
  if (!$c || !password_verify($in['password'] ?? '', $c['password_hash'])) out(['error' => 'Wrong email or password'], 401);
  csess($c['id']);

case 'cme': out(cust());
case 'clogout': unset($_SESSION['c']); out(['ok' => 1]);

case 'delivery':
  $c = cust(); $r = (int)($in['r'] ?? 0);
  $fee = q('SELECT delivery_fee FROM restaurants WHERE id=?', [$r])->fetchColumn();
  if ($fee === false) out(['error' => 'Restaurant not found'], 404);
  $addr = trim($in['address'] ?? ''); $phone = trim($in['phone'] ?? ''); $m = $in['method'] ?? '';
  if (!$addr || !$phone) out(['error' => 'Enter your delivery address and phone number'], 400);
  if (!in_array($m, ['mtn_momo', 'airtel_money', 'card'])) out(['error' => 'Choose a payment method'], 400);
  [$lines, $total] = lines($r, $in['items'] ?? []);
  if (!$lines) out(['error' => 'Your order is empty'], 400);
  $total += $fee;
  $g = gateway($m, $in['pay'] ?? []);
  if ($g !== true) out(['error' => $g], 402);
  $ref = strtoupper(bin2hex(random_bytes(5)));
  $db->beginTransaction();
  $t = q('SELECT COALESCE(MAX(token),0)+1 FROM orders WHERE restaurant_id=? AND DATE(created_at)=CURDATE() FOR UPDATE', [$r])->fetchColumn();
  q("INSERT INTO orders(restaurant_id,customer_id,token,order_type,total,delivery_fee,delivery_address,phone,payment_method,payment_status,payment_ref) VALUES(?,?,?,'delivery',?,?,?,?,?,'paid',?)", [$r, $c['id'], $t, $total, $fee, $addr, $phone, $m, $ref]);
  $id = $db->lastInsertId();
  foreach ($lines as $l) q('INSERT INTO order_items(order_id,item_id,name,price,qty) VALUES(?,?,?,?,?)', [$id, $l['id'], $l['name'], $l['price'], $l['qty']]);
  $db->commit();
  @mail($c['email'], "Receipt for order #$t", "Thank you, {$c['name']}. Order #$t, total UGX $total, paid by $m (ref $ref). Delivery to: $addr.", 'From: orders@localhost'); // works only if PHP mail is set up
  out(['id' => $id, 'token' => $t, 'total' => $total]);

case 'myorders':
  $c = cust();
  out(q('SELECT o.id,o.token,o.status,o.total,r.name restaurant FROM orders o JOIN restaurants r ON r.id=o.restaurant_id WHERE o.customer_id=? ORDER BY o.id DESC LIMIT 30', [$c['id']])->fetchAll());

case 'receipt':
  $c = cust();
  $o = q('SELECT o.id,o.token,o.total,o.delivery_fee,o.delivery_address,o.phone,o.payment_method,o.payment_ref,o.created_at,r.name restaurant,r.location FROM orders o JOIN restaurants r ON r.id=o.restaurant_id WHERE o.id=? AND o.customer_id=?', [(int)($_GET['id'] ?? 0), $c['id']])->fetch();
  if (!$o) out(['error' => 'Receipt not found'], 404);
  $o['items'] = q('SELECT name,price,qty FROM order_items WHERE order_id=?', [$o['id']])->fetchAll(); $o['customer'] = $c['name'];
  out($o);

// ---------- Restaurant accounts ----------
case 'signup':
  $e = strtolower(trim($in['email'] ?? '')); $pw = $in['password'] ?? '';
  if (!filter_var($e, FILTER_VALIDATE_EMAIL) || strlen($pw) < 6 || !trim($in['restaurant'] ?? '') || !trim($in['name'] ?? '')) out(['error' => 'Check your details (password needs 6+ characters)'], 400);
  if (q('SELECT 1 FROM users WHERE email=?', [$e])->fetch()) out(['error' => 'That email is already registered'], 400);
  q('INSERT INTO restaurants(name,location) VALUES(?,?)', [trim($in['restaurant']), trim($in['location'] ?? '')]);
  $rid = $db->lastInsertId();
  q("INSERT INTO users(restaurant_id,name,email,password_hash,role) VALUES(?,?,?,?,'owner')", [$rid, trim($in['name']), $e, password_hash($pw, PASSWORD_DEFAULT)]);
  sess($db->lastInsertId());

case 'login':
  $u = q('SELECT id,password_hash FROM users WHERE email=?', [strtolower(trim($in['email'] ?? ''))])->fetch();
  if (!$u || !password_verify($in['password'] ?? '', $u['password_hash'])) out(['error' => 'Wrong email or password'], 401);
  sess($u['id']);

case 'me': out(need());
case 'logout': session_destroy(); out(['ok' => 1]);

// ---------- Waiters and owners ----------
case 'orders':
  $u = need();
  $os = q("SELECT id,token,order_type,status,total,delivery_address,phone,payment_method FROM orders WHERE restaurant_id=? AND (DATE(created_at)=CURDATE() OR order_type='delivery') AND status NOT IN('collected','delivered') ORDER BY id", [$u['rid']])->fetchAll();
  foreach ($os as &$o) $o['items'] = q('SELECT name,qty FROM order_items WHERE order_id=?', [$o['id']])->fetchAll();
  out($os);

case 'status':
  $u = need(); $s = $in['status'] ?? '';
  if (!in_array($s, ['preparing', 'ready', 'collected', 'out_for_delivery', 'delivered'])) out(['error' => 'Bad status'], 400);
  q('UPDATE orders SET status=?' . ($s === 'ready' ? ',called_at=NOW()' : '') . ' WHERE id=? AND restaurant_id=?', [$s, (int)$in['id'], $u['rid']]); // "ready" again = call again
  out(['ok' => 1]);

// ---------- Owners only ----------
case 'myitems':
  $u = owner(); out(q('SELECT id,name,category,price,image FROM menu_items WHERE restaurant_id=? ORDER BY category,name', [$u['rid']])->fetchAll());

case 'additem':
  $u = owner(); $img = null;
  if (!empty($_FILES['image']['tmp_name'])) {
    $f = $_FILES['image']; $g = @getimagesize($f['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$g[2] ?? 0] ?? null;
    if (!$ext || $f['size'] > 3000000) out(['error' => 'Use a JPG, PNG or WEBP image under 3 MB'], 400);
    $img = bin2hex(random_bytes(8)) . '.' . $ext;
    move_uploaded_file($f['tmp_name'], __DIR__ . '/uploads/' . $img);
  }
  $c = in_array($_POST['category'] ?? '', ['Food', 'Drinks', 'Desserts']) ? $_POST['category'] : 'Food';
  q('INSERT INTO menu_items(restaurant_id,name,category,price,image) VALUES(?,?,?,?,?)', [$u['rid'], trim($_POST['name'] ?? ''), $c, max(0, (int)($_POST['price'] ?? 0)), $img]);
  out(['ok' => 1]);

case 'delitem':
  $u = owner(); $id = (int)($in['id'] ?? 0);
  $img = q('SELECT image FROM menu_items WHERE id=? AND restaurant_id=?', [$id, $u['rid']])->fetchColumn();
  q('DELETE FROM menu_items WHERE id=? AND restaurant_id=?', [$id, $u['rid']]);
  if ($img && is_file(__DIR__ . '/uploads/' . basename($img))) unlink(__DIR__ . '/uploads/' . basename($img));
  out(['ok' => 1]);

case 'addwaiter':
  $u = owner(); $e = strtolower(trim($in['email'] ?? ''));
  if (!filter_var($e, FILTER_VALIDATE_EMAIL) || strlen($in['password'] ?? '') < 6 || !trim($in['name'] ?? '')) out(['error' => 'Check the waiter details'], 400);
  if (q('SELECT 1 FROM users WHERE email=?', [$e])->fetch()) out(['error' => 'That email is already registered'], 400);
  q("INSERT INTO users(restaurant_id,name,email,password_hash,role) VALUES(?,?,?,?,'waiter')", [$u['rid'], trim($in['name']), $e, password_hash($in['password'], PASSWORD_DEFAULT)]);
  out(['ok' => 1]);

// ---------- Forgot / reset password (staff and customers) ----------
case 'forgot':
  $e = strtolower(trim($in['email'] ?? '')); $cust = ($in['type'] ?? '') === 'customer';
  $tbl = $cust ? 'customers' : 'users'; $acc = $cust ? 'customer' : 'user';
  $row = $e ? q("SELECT id,name FROM $tbl WHERE email=?", [$e])->fetch() : false;
  $res = ['message' => 'If that email is registered, a reset link has been sent. The link works for 1 hour.']; // same answer whether or not the email exists
  if ($row) {
    q('DELETE FROM password_resets WHERE account=? AND account_id=?', [$acc, $row['id']]);
    $tok = bin2hex(random_bytes(32)); // only a hash of the token is stored
    q('INSERT INTO password_resets(account,account_id,token_hash,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))', [$acc, $row['id'], hash('sha256', $tok)]);
    $link = BASE_URL . '/reset.html?k=' . ($cust ? 'customer' : 'staff') . '&t=' . $tok;
    @mail($e, 'Reset your password', "Hello {$row['name']},\n\nSet a new password here (valid for 1 hour):\n$link\n\nIf you did not ask for this, ignore this email.", 'From: orders@localhost'); // works only if PHP mail is set up
    if (DEMO_SHOW_RESET_LINK) $res['demo_link'] = $link;
  }
  out($res);

case 'reset':
  $cust = ($in['type'] ?? '') === 'customer'; $acc = $cust ? 'customer' : 'user'; $pw = $in['password'] ?? '';
  if (strlen($pw) < 6) out(['error' => 'Password needs 6 or more characters'], 400);
  $r = q('SELECT id,account_id FROM password_resets WHERE account=? AND token_hash=? AND expires_at>NOW()', [$acc, hash('sha256', $in['token'] ?? '')])->fetch();
  if (!$r) out(['error' => 'This reset link is invalid or has expired. Please request a new one.'], 400);
  q('UPDATE ' . ($cust ? 'customers' : 'users') . ' SET password_hash=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $r['account_id']]);
  q('DELETE FROM password_resets WHERE account=? AND account_id=?', [$acc, $r['account_id']]); // link works only once
  out(['ok' => 1]);

case 'setfee':
  $u = owner(); q('UPDATE restaurants SET delivery_fee=? WHERE id=?', [max(0, (int)($in['fee'] ?? 0)), $u['rid']]); out(['ok' => 1]);

default: out(['error' => 'Unknown action'], 404);
} } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); out(['error' => 'Server error: ' . $e->getMessage()], 500); }
