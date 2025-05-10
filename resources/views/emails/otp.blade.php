<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your OTP Code</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .header {
            background-color: #4F46E5;
            padding: 25px 0;
            text-align: center;
        }
        
        .header h1 {
            color: white;
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .content {
            padding: 30px 40px;
            text-align: center;
        }
        
        .otp-container {
            margin: 25px 0;
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            border: 1px dashed #ddd;
        }
        
        .otp-code {
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 5px;
            color: #4F46E5;
            margin: 0;
            font-family: 'Courier New', monospace;
        }
        
        .timer {
            margin-top: 25px;
            background-color: #FEF9C3;
            padding: 12px 20px;
            border-radius: 6px;
            display: inline-block;
        }
        
        .timer-icon {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #CA8A04;
            border-radius: 50%;
            position: relative;
            top: 4px;
            margin-right: 8px;
        }
        
        .timer-text {
            color: #854D0E;
            font-weight: 500;
            font-size: 15px;
        }
        
        .message {
            margin: 25px 0;
            color: #4B5563;
            font-size: 16px;
            line-height: 1.7;
        }
        
        .safety-tip {
            background-color: #F3F4F6;
            padding: 15px 20px;
            border-radius: 6px;
            margin: 25px 0;
            text-align: left;
            border-left: 4px solid #9CA3AF;
        }
        
        .safety-tip p {
            margin: 0;
            color: #4B5563;
            font-size: 14px;
        }
        
        .safety-tip strong {
            color: #374151;
        }
        
        .footer {
            background-color: #F9FAFB;
            padding: 20px;
            text-align: center;
            font-size: 13px;
            color: #6B7280;
            border-top: 1px solid #E5E7EB;
        }
        
        .footer p {
            margin: 5px 0;
        }
        
        .company-logo {
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: bold;
            color: #4F46E5;
        }
        
        @media only screen and (max-width: 600px) {
            .container {
                width: 100%;
                margin: 0;
                border-radius: 0;
            }
            
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Verification Code</h1>
        </div>
        
        <div class="content">
            <div class="company-logo">
                Himpri - Play and Win
            </div>
            
            <div class="message">
                <p>We need to verify it's really you. Use the following One-Time Password (OTP) to complete your action:</p>
            </div>
            
            <div class="otp-container">
                <h2 class="otp-code">{{ $otp }}</h2>
            </div>
            
            <div class="timer">
                <span class="timer-icon"></span>
                <span class="timer-text">This code will expire in 5 minutes</span>
            </div>
            
            <div class="safety-tip">
                <p><strong>Security Notice:</strong> Never share this code with anyone. Our representatives will never ask for your OTP.</p>
            </div>
            
            <p>If you didn't request this code, please ignore this email or contact support if you're concerned.</p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} Himpri. All rights reserved.</p>
        </div>
    </div>
</body>
</html>