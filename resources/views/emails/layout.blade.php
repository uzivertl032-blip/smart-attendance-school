<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'SmartAttendance Notification' }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            line-height: 1.6;
        }
        .email-wrapper {
            width: 100%;
            background-color: #f1f5f9;
            padding: 30px 15px;
        }
        .email-container {
            max-width: 580px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .email-header {
            background: #1e3a8a;
            color: #ffffff;
            padding: 24px 30px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }
        .email-header p {
            margin: 4px 0 0 0;
            font-size: 13px;
            color: #93c5fd;
        }
        .email-body {
            padding: 30px;
        }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .badge-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-warning { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-info { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #f8fafc;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .data-table td {
            padding: 10px 16px;
            font-size: 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        .data-table tr:last-child td {
            border-bottom: none;
        }
        .data-table td.label {
            color: #64748b;
            font-weight: 500;
            width: 38%;
        }
        .data-table td.value {
            color: #0f172a;
            font-weight: 600;
        }
        .btn-action {
            display: inline-block;
            background: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            padding: 11px 24px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            margin-top: 15px;
            text-align: center;
        }
        .email-footer {
            background: #f8fafc;
            padding: 18px 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="email-header">
                <h1>SmartAttendance School</h1>
                <p>Sistem Presensi & Kehadiran Digital Sekolah</p>
            </div>
            <div class="email-body">
                @yield('content')
            </div>
            <div class="email-footer">
                <p style="margin: 0;">Email ini dikirimkan otomatis oleh sistem SmartAttendance School.</p>
                <p style="margin: 4px 0 0 0;">Mohon jangan membalas langsung email ini.</p>
            </div>
        </div>
    </div>
</body>
</html>
