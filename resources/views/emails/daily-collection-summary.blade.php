<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Collection Summary</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #f4f4f4; }
    </style>
</head>
<body>
    <h2>Daily Collection Summary — {{ $stats['date'] }}</h2>
    <table>
        <tr><th>Metric</th><th>Amount (BDT)</th></tr>
        <tr><td>Collected Today</td><td>{{ number_format($stats['collected_today'], 2) }}</td></tr>
        <tr><td>Total Outstanding Dues</td><td>{{ number_format($stats['total_outstanding'], 2) }}</td></tr>
    </table>
    <p style="color: #777; font-size: 12px;">This is an automated daily report from Fiberloop.</p>
</body>
</html>
