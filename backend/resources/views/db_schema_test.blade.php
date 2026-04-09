<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test DB — schema {{ $schema }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 48rem; margin: 2rem auto; padding: 0 1rem; }
        h1 { font-size: 1.25rem; }
        .ok { color: #0a0; }
        .err { color: #c00; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: 0.5rem 0.75rem; border: 1px solid #ccc; }
        th { background: #f4f4f4; }
        code { background: #eee; padding: 0.1rem 0.35rem; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Kết nối DB</h1>
    @if ($ok)
        <p class="ok">Đã kết nối — database: <code>{{ $database }}</code>, schema: <code>{{ $schema }}</code></p>
        <h2>Bảng trong schema <code>{{ $schema }}</code> ({{ count($tables) }})</h2>
        @if (count($tables) === 0)
            <p>Không có bảng nào.</p>
        @else
            <table>
                <thead>
                    <tr><th>#</th><th>Tên bảng</th></tr>
                </thead>
                <tbody>
                    @foreach ($tables as $i => $name)
                        <tr><td>{{ $i + 1 }}</td><td><code>{{ $name }}</code></td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @else
        <p class="err">Lỗi: {{ $error }}</p>
    @endif
</body>
</html>
