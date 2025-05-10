<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Himpri - Payment Receipt</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid #eeeeee;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 24px;
        }
        .company-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .company-logo img {
            max-height: 60px;
        }
        .receipt-details {
            padding: 20px 0;
        }
        .receipt-details table {
            width: 100%;
            border-collapse: collapse;
        }
        .receipt-details table td {
            padding: 10px;
            vertical-align: top;
        }
        .receipt-details .label {
            font-weight: bold;
            width: 40%;
            color: #555;
        }
        .receipt-details .value {
            width: 60%;
        }
        .receipt-amount {
            font-size: 18px;
            font-weight: bold;
        }
        .receipt-id {
            font-family: monospace;
            background-color: #f5f5f5;
            padding: 2px 5px;
            border-radius: 3px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eeeeee;
            font-size: 12px;
            color: #777;
        }
        .thank-you {
            text-align: center;
            margin: 30px 0;
            font-size: 18px;
            color: #27ae60;
        }
        .support-info {
            background-color: #f5f7fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 13px;
        }
        .date {
            text-align: right;
            font-size: 14px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="company-logo">
                <!-- You can replace with your company logo -->
                <h2>Himpri - Play and Win</h2>
            </div>
            <h1>Payment Receipt</h1>
        </div>
        
        <div class="date">
            Date: {{ date('F d, Y') }}
        </div>
        
        <div class="receipt-details">
            <p>Dear {{ $name }},</p>
            
            <p>Thank you for your payment. Please find your receipt details below:</p>
            
            <table>
                <tr>
                    <td class="label">Payment ID:</td>
                    <td class="value"><span class="receipt-id">{{ $payment_id }}</span></td>
                </tr>
                <tr>
                    <td class="label">Order ID:</td>
                    <td class="value"><span class="receipt-id">{{ $order_id }}</span></td>
                </tr>
                <tr>
                    <td class="label">Name:</td>
                    <td class="value">{{ $name }}</td>
                </tr>
                <tr>
                    <td class="label">Email:</td>
                    <td class="value">{{ $email }}</td>
                </tr>
                <tr>
                    <td class="label">Amount:</td>
                    <td class="value">
                        <span class="receipt-amount">₹{{ number_format($amount, 2) }}</span>
                    </td>
                </tr>
                <tr>
                    <td class="label">Payment Status:</td>
                    <td class="value">
                        <span style="color: #27ae60; font-weight: bold;">SUCCESS</span>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="thank-you">
            Thank you for your payment!
        </div>
        
        <div class="support-info">
            <p>If you have any questions about this receipt, please contact our customer support:</p>
            <p>Email: support@himpri.com</p>
        </div>
        
        <div class="footer">
            <p>This is an automatically generated receipt. Please keep it for your records.</p>
            <p>&copy; {{ date('Y') }} Himpri. All rights reserved.</p>
        </div>
    </div>
</body>
</html>