<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ด — ร้านชุดพยาบาล มินลดา</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Sarabun', sans-serif; }
        body { background: #f8fafc; color: #1e293b; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #0f172a; color: #f8fafc; padding: 1.5rem; display: flex; flex-direction: column; }
        .sidebar h2 { font-size: 1.125rem; font-weight: 700; color: #38bdf8; margin-bottom: 2rem; }
        .sidebar a { color: #94a3b8; text-decoration: none; padding: 0.625rem 0.75rem; border-radius: 0.375rem; display: block; margin-bottom: 0.375rem; font-size: 0.9375rem; }
        .sidebar a.active, .sidebar a:hover { background: #1e293b; color: white; }
        .main { flex: 1; padding: 2rem; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
        .card { background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e2e8f0; }
        .card-num { font-size: 2rem; font-weight: 700; color: #0284c7; margin-top: 0.5rem; }
        .btn-logout { background: none; border: none; color: #ef4444; cursor: pointer; padding: 0.5rem 0; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>ร้านชุดพยาบาล มินลดา</h2>
        <a href="{{ route('admin.dashboard') }}" class="active">📊 แดชบอร์ด</a>
        <a href="{{ route('admin.branches.index') }}">🏢 จัดการสาขา</a>
        <a href="{{ route('admin.employees.index') }}">👥 พนักงาน</a>
        <a href="{{ route('admin.devices.index') }}">📱 เครื่องพนักงาน</a>
        <a href="{{ route('admin.products.index') }}">👗 สินค้าและไซส์</a>
        <a href="{{ route('admin.stock.index') }}">📦 สต็อกสินค้า</a>
        <a href="{{ route('admin.stock-counts.index') }}">📋 รายงานนับสต็อก</a>
        <a href="{{ route('admin.attendances.index') }}">⏰ บันทึกเข้า-ออกงาน</a>
        <a href="{{ route('admin.checklist-templates.index') }}">📝 แม่แบบเช็คลิสต์</a>
        <a href="{{ route('admin.checklist-reviews.index') }}">🔍 รีวิวเช็คลิสต์</a>
        <a href="{{ route('admin.closings.index') }}">🚪 รายการปิดร้าน</a>
        <a href="{{ route('admin.settings.edit') }}">⚙️ ตั้งค่าระบบ</a>

        <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid #334155;">
            <p style="font-size: 0.875rem; color: #94a3b8;">เข้าใช้งานโดย: {{ auth()->user()->name }}</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-logout">ออกจากระบบ</button>
            </form>
        </div>
    </div>

    <div class="main">
        <div class="header">
            <div>
                <h1 style="font-size: 1.75rem; font-weight: 700;">แดชบอร์ดภาพรวมประจำวัน</h1>
                <p style="color: #64748b; font-size: 0.875rem;">วันที่: {{ $today }}</p>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <p style="color: #64748b; font-size: 0.875rem;">สาขาที่เปิดให้บริการ</p>
                <p class="card-num">{{ $stats['total_branches'] }}</p>
            </div>
            <div class="card">
                <p style="color: #64748b; font-size: 0.875rem;">พนักงานเช็คชื่อเข้างาน</p>
                <p class="card-num" style="color: #10b981;">{{ $stats['present_count'] }}</p>
            </div>
            <div class="card">
                <p style="color: #64748b; font-size: 0.875rem;">มาสายวันนี้</p>
                <p class="card-num" style="color: #f59e0b;">{{ $stats['late_count'] }}</p>
            </div>
            <div class="card">
                <p style="color: #64748b; font-size: 0.875rem;">ส่งผลนับสต็อกแล้ว</p>
                <p class="card-num">{{ $stats['stock_counted_branches'] }}</p>
            </div>
            <div class="card">
                <p style="color: #64748b; font-size: 0.875rem;">สินค้าใกล้หมดสต็อก</p>
                <p class="card-num" style="color: #ef4444;">{{ $stats['low_stock_count'] }}</p>
            </div>
            <div class="card">
                <p style="color: #64748b; font-size: 0.875rem;">เช็คลิสต์รอตรวจสอบ</p>
                <p class="card-num">{{ $stats['pending_checklists'] }}</p>
            </div>
        </div>
    </div>
</body>
</html>
