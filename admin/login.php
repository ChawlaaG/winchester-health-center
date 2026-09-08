<?php
// Initialize session
session_start();

// Include database wrapper
require_once '../db.php';

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ./');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        if (!isset($pdo)) {
            $error = 'Database connection is offline. Check db.php configurations.';
        } else {
            try {
                // Fetch user
                $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                // Verify password hash
                if ($user && password_verify($password, $user['password_hash'])) {
                    // Set session variables
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $user['username'];
                    
                    // Redirect to dashboard
                    header('Location: ./');
                    exit;
                } else {
                    $error = 'Invalid username or password.';
                }
            } catch (PDOException $e) {
                $error = 'Authentication database error occurred.';
            }
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Portal Login | Winchester Health Centre</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Lora:ital,wght@0,500;0,600;1,500&display=swap" rel="stylesheet">
  <style>
    :root {
      --navy:    #0a2f52;
      --navy-dk: #061f38;
      --navy-lt: #14456f;
      --amber:   #c9922f;
      --amber-lt:#dba646;
      --white:   #ffffff;
      --text:    #0a1c2a;
      --grey:    #5e6e7d;
      --grey-pale:#f4f7fa;
      --shadow:  0 12px 48px rgba(0,24,48,0.3);
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    body {
      font-family: 'Nunito', sans-serif;
      background: linear-gradient(135deg, var(--navy-dk) 0%, var(--navy) 100%);
      color: var(--white);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      position: relative;
    }

    /* Radial gradient lights */
    body::before {
      content: '';
      position: absolute;
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, rgba(183,142,58,0.15) 0%, transparent 70%);
      top: -100px;
      right: -100px;
      border-radius: 50%;
      z-index: 0;
    }
    
    body::after {
      content: '';
      position: absolute;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(0,56,105,0.4) 0%, transparent 70%);
      bottom: -150px;
      left: -100px;
      border-radius: 50%;
      z-index: 0;
    }
    
    .login-container {
      background: rgba(255, 255, 255, 0.04);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 2px solid rgba(255, 255, 255, 0.08);
      border-radius: 24px;
      padding: 3rem 2.5rem;
      width: 440px;
      max-width: 90%;
      box-shadow: var(--shadow);
      position: relative;
      z-index: 1;
      text-align: center;
      animation: loadIn 0.5s ease-out;
    }
    
    @keyframes loadIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .logo-container {
      margin-bottom: 2rem;
    }
    
    .logo-container img {
      height: 72px;
      width: auto;
      background: var(--white);
      padding: 6px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    h2 {
      font-family: 'Lora', serif;
      font-size: 1.8rem;
      margin-bottom: 0.5rem;
      color: var(--white);
      font-weight: 500;
    }
    
    .subtitle {
      font-size: 0.95rem;
      color: rgba(255, 255, 255, 0.6);
      margin-bottom: 2.25rem;
      font-weight: 600;
    }
    
    .form-group {
      margin-bottom: 1.5rem;
      text-align: left;
    }
    
    label {
      font-size: 0.85rem;
      color: rgba(255, 255, 255, 0.75);
      font-weight: 700;
      display: block;
      margin-bottom: 0.5rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    
    .input-wrapper {
      position: relative;
    }
    
    input {
      background: rgba(255, 255, 255, 0.07);
      border: 2px solid rgba(255, 255, 255, 0.15);
      border-radius: 12px;
      padding: 0.9rem 1.2rem;
      color: var(--white);
      font-family: 'Nunito', sans-serif;
      font-size: 1rem;
      font-weight: 600;
      width: 100%;
      outline: none;
      transition: all 0.2s;
    }
    
    input:focus {
      border-color: var(--amber);
      background: rgba(255, 255, 255, 0.12);
      box-shadow: 0 0 12px rgba(183, 142, 58, 0.25);
    }
    
    .error-msg {
      background: rgba(239, 68, 68, 0.15);
      border: 1.5px solid rgba(239, 68, 68, 0.4);
      color: #fca5a5;
      font-size: 0.9rem;
      padding: 0.8rem 1rem;
      border-radius: 10px;
      margin-bottom: 1.5rem;
      font-weight: 700;
      text-align: left;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .btn-login {
      background: linear-gradient(135deg, var(--amber), var(--amber-lt));
      color: var(--white);
      border: none;
      border-radius: 12px;
      padding: 1rem;
      font-size: 1.05rem;
      font-weight: 800;
      cursor: pointer;
      width: 100%;
      font-family: 'Nunito', sans-serif;
      transition: all 0.2s;
      box-shadow: 0 4px 15px rgba(183, 142, 58, 0.2);
    }
    
    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(183, 142, 58, 0.35);
    }

    .btn-login:active {
      transform: translateY(0);
    }
    
    .footer-note {
      font-size: 0.8rem;
      color: rgba(255, 255, 255, 0.35);
      margin-top: 2rem;
      font-weight: 600;
    }
  </style>
</head>
<body>

  <div class="login-container">
    <div class="logo-container">
      <img src="../assets/logo.png" alt="Winchester Health Centre Logo">
    </div>
    <h2>Admin Dashboard</h2>
    <p class="subtitle">Winchester Health Centre Management Console</p>
    
    <?php if (!empty($error)): ?>
      <div class="error-msg">
        <span>⚠️</span> <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>
    
    <form action="" method="POST">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="e.g. admin" required autocomplete="username">
      </div>
      
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="••••••••••••" required autocomplete="current-password">
      </div>
      
      <button type="submit" class="btn-login">🔑 Log In to Dashboard</button>
    </form>
    
    <p class="footer-note">🔒 Secured session. Authorized administrative access only.</p>
  </div>

</body>
</html>
