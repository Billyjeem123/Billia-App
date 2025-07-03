<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>@yield('title')</title>

    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:AllowPNG/>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->

    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
        }

        table {
            border-collapse: collapse;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        td {
            border-collapse: collapse;
        }

        img {
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
            max-width: 100%;
            display: block;
        }

        /* Main container */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        /* Header styles */
        .header {
            background: white;
            padding: 30px 20px;
            text-align: center;
        }

        .logo {
            font-size: 32px;
            font-weight: bold;
            color: black;
            text-decoration: none;
            letter-spacing: -1px;
        }

        .tagline {
            color: black;
            font-size: 14px;
            margin-top: 8px;
        }

        /* Content styles */
        .content {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 24px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 20px;
            line-height: 1.3;;
            text-align: center;
            display: block;
        }

        .message {
            font-size: 16px;
            line-height: 1.6;
            color: #4a5568;
            margin-bottom: 30px;
        }

        .highlight-box {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 30px 0;
            border-radius: 0 8px 8px 0;
        }

        .highlight-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .highlight-text {
            font-size: 16px;
            color: #4a5568;
            line-height: 1.5;
        }

        /* Button styles */
        .cta-container {
            text-align: center;
            margin: 40px 0;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 16px 32px;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* Stats/metrics section */
        .stats-container {
            display: table;
            width: 100%;
            margin: 30px 0;
        }

        .stat-item {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 20px 10px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            line-height: 1;
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
            margin-top: 5px;
        }

        /* Footer styles */
        .footer {
            background-color: #f7fafc;
            padding: 30px 20px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }

        .footer-text {
            font-size: 14px;
            color: #718096;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .social-links {
            margin: 20px 0;
        }

        .social-link {
            display: inline-block;
            margin: 0 10px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .social-link:hover {
            background-color: #667eea;
            color: #ffffff;
        }

        .unsubscribe {
            font-size: 12px;
            color: #a0aec0;
            margin-top: 20px;
        }

        .unsubscribe a {
            color: #667eea;
            text-decoration: none;
        }

        /* Mobile responsive */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
            }

            .content {
                padding: 30px 20px !important;
            }

            .greeting {
                font-size: 20px !important;
            }

            .message {
                font-size: 15px !important;
            }

            .stats-container {
                display: block !important;
            }

            .stat-item {
                display: block !important;
                width: 100% !important;
                margin-bottom: 20px;
            }

            .cta-button {
                padding: 14px 28px !important;
                font-size: 15px !important;
            }

            .highlight-box {
                margin: 20px 0 !important;
                padding: 15px !important;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .email-container {
                background-color: #1a202c !important;
            }

            .content {
                background-color: #1a202c !important;
            }

            .greeting {
                color: #f7fafc !important;
            }

            .message {
                color: #cbd5e0 !important;
            }

            .highlight-box {
                background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%) !important;
            }

            .highlight-title {
                color: #f7fafc !important;
            }

            .highlight-text {
                color: #cbd5e0 !important;
            }
        }
    </style>
</head>

<body>
<div style="display: none; font-size: 1px; color: #fefefe; line-height: 1px; font-family: 'Segoe UI', sans-serif; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
    Your financial journey just got easier with {{ config('app.name') }} - Secure, Smart, Simple
</div>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 20px 0;">
            <div class="email-container">

                <!-- Header -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td class="header">
                            <a href="#" class="logo">{{ config('app.name') }}</a>
                            <div class="tagline">Your Smart Financial Companion</div>
                        </td>
                    </tr>
                </table>

                <!-- Main Content -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    @yield('content')

                </table>

                <!-- Footer -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td class="footer">

                            <div class="footer-text">
                                <strong>{{ config('app.name') }}</strong><br>
                                Making finance simple, secure, and accessible for everyone.
                            </div>

                            <div class="social-links">
                                <a href="#" class="social-link">Help Center</a>
                                <a href="#" class="social-link">Twitter</a>
                                <a href="#" class="social-link">LinkedIn</a>
                            </div>

                            <div class="footer-text" style="margin-top: 20px;">
                                © 2025 {{ config('app.name') }}. All rights reserved.<br>
                                123 Fintech Street, Digital City, DC 12345
                            </div>

                            <div class="unsubscribe">
                                <a href="#">Unsubscribe</a> | <a href="#">Privacy Policy</a> | <a href="#">Terms of Service</a>
                            </div>

                        </td>
                    </tr>
                </table>

            </div>
        </td>
    </tr>
</table>
</body>
</html>
