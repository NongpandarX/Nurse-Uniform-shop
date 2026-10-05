<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ — ร้านชุดพยาบาล มินลดา</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Sarabun', sans-serif; }
        body { background: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #1e293b; }
        .login-card { background: white; padding: 2.5rem; border-radius: 1rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08); width: 100%; max-width: 420px; }
        .logo-title { font-size: 1.5rem; font-weight: 700; color: #0284c7; text-align: center; margin-bottom: 0.5rem; }
        .logo-sub { font-size: 0.875rem; color: #64748b; text-align: center; margin-bottom: 2rem; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: #334155; }
        input[type="text"], input[type="password"] {
            width: 100%; padding: 0.75rem 1rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; font-size: 1rem; outline: none; transition: border-color 0.2s;
        }
        input:focus { border-color: #0284c7; ring: 2px #bae6fd; }
        .error-message { color: #ef4444; font-size: 0.8125rem; margin-top: 0.375rem; }
        .btn-submit {
            width: 100%; padding: 0.875rem; background: #0284c7; color: white; border: none; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s;
        }
        .btn-submit:hover { background: #0369a1; }
        .hint-box { margin-top: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 0.5rem; border: 1px solid #e2e8f0; font-size: 0.8125rem; color: #475569; }
        .hint-box ul { padding-left: 1.25rem; margin-top: 0.25rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1 class="logo-title">ร้านชุดพยาบาล มินลดา</h1>
        <p class="logo-sub">ระบบบริหารจัดการสาขาและสต็อกสินค้า</p>

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label for="employee_code">รหัสพนักงาน</label>
                <input type="text" id="employee_code" name="employee_code" value="{{ old('employee_code') }}" required autofocus placeholder="เช่น ADMIN-001 หรือ KKC-001">
                @error('employee_code')
                    <p class="error-message">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">รหัสผ่าน</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
                @error('password')
                    <p class="error-message">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-submit">เข้าสู่ระบบ</button>
        </form>

        <div class="hint-box">
            <strong>ข้อมูลเข้าใช้งานเริ่มต้น (หลังรัน Seed):</strong>
            <ul>
                <li>Admin: <code>ADMIN-001</code> / รหัสผ่าน: <code>password123</code></li>
                <li>พนักงาน: <code>KKC-001</code> / รหัสผ่าน: <code>password123</code></li>
            </ul>
        </div>
    </div>
</body>
</html>
