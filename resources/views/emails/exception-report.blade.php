<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Exception Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
        }
        .container {
            padding: 20px;
            background-color: #fefefe;
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        .header {
            background-color: #e74c3c;
            color: white;
            padding: 10px;
            font-size: 18px;
            border-radius: 5px 5px 0 0;
        }
        .section {
            margin: 15px 0;
        }
        .label {
            font-weight: bold;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">🚨 Exception Report</div>

        <div class="section">
            <p><span class="label">Type:</span> {{ $errorData['type'] }}</p>
            <p><span class="label">Message:</span> {{ $errorData['message'] }}</p>
            <p><span class="label">Code:</span> {{ $errorData['code'] }}</p>
            <p><span class="label">File:</span> {{ $errorData['file'] }}</p>
            <p><span class="label">Line:</span> {{ $errorData['line'] }}</p>
            <p><span class="label">URL:</span> {{ $errorData['url'] }}</p>
            <p><span class="label">Method:</span> {{ $errorData['method'] }}</p>
            <p><span class="label">IP:</span> {{ $errorData['ip'] }}</p>
            <p><span class="label">User ID:</span> {{ $errorData['user_id'] }}</p>
            <p><span class="label">Timestamp:</span> {{ $errorData['timestamp'] }}</p>

            <hr>
            <h4>Stack Trace</h4>
            <pre style="background: #f9f9f9; padding: 10px; font-size: 13px; overflow: auto;">
        {{ $errorData['trace'] }}
            </pre>
        </div>


        <div class="footer">
            Sent automatically by {{ config('app.name') }} exception handler.
        </div>
    </div>
</body>
</html>
