<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Business Query</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 300;
        }
        .header .subtitle {
            margin: 10px 0 0 0;
            font-size: 16px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .detail-row {
            display: flex;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #555;
            min-width: 140px;
            flex-shrink: 0;
            text-transform: capitalize;
        }
        .detail-value {
            color: #333;
            flex: 1;
            word-break: break-word;
        }
        .nested-data {
            margin-left: 20px;
            border-left: 2px solid #e9ecef;
            padding-left: 15px;
            margin-top: 10px;
        }
        .timestamp {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e9ecef;
            font-size: 14px;
            color: #666;
        }
        .badge {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        @media (max-width: 600px) {
            .container {
                margin: 10px;
                border-radius: 5px;
            }
            .header, .content {
                padding: 20px;
            }
            .detail-row {
                flex-direction: column;
            }
            .detail-label {
                min-width: auto;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏢 New Business Query</h1>
            <p class="subtitle">A new business inquiry has been submitted</p>
            <span class="badge">New Inquiry</span>
        </div>

        <div class="content">
            @foreach($queryData as $key => $value)
                @if(!in_array($key, ['ip_address', 'user_agent', 'submitted_at', 'show_raw_data']))
                    <div class="detail-row">
                        <div class="detail-label">{{ str_replace('_', ' ', $key) }}:</div>
                        <div class="detail-value">
                            @if(is_array($value))
                                <div class="nested-data">
                                    @foreach($value as $subKey => $subValue)
                                        <div class="detail-row">
                                            <div class="detail-label">{{ str_replace('_', ' ', $subKey) }}:</div>
                                            <div class="detail-value">
                                                @if(is_array($subValue))
                                                    {{ json_encode($subValue) }}
                                                @else
                                                    @if(filter_var($subValue, FILTER_VALIDATE_EMAIL))
                                                        <a href="mailto:{{ $subValue }}" style="color: #667eea; text-decoration: none;">{{ $subValue }}</a>
                                                    @elseif(filter_var($subValue, FILTER_VALIDATE_URL))
                                                        <a href="{{ $subValue }}" target="_blank" style="color: #667eea; text-decoration: none;">{{ $subValue }}</a>
                                                    @elseif(preg_match('/^[\+]?[1-9][\d]{0,15}$/', str_replace([' ', '-', '(', ')'], '', $subValue)))
                                                        <a href="tel:{{ $subValue }}" style="color: #667eea; text-decoration: none;">{{ $subValue }}</a>
                                                    @else
                                                        {{ $subValue }}
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                @if(filter_var($value, FILTER_VALIDATE_EMAIL))
                                    <a href="mailto:{{ $value }}" style="color: #667eea; text-decoration: none;">{{ $value }}</a>
                                @elseif(filter_var($value, FILTER_VALIDATE_URL))
                                    <a href="{{ $value }}" target="_blank" style="color: #667eea; text-decoration: none;">{{ $value }}</a>
                                @elseif(preg_match('/^[\+]?[1-9][\d]{0,15}$/', str_replace([' ', '-', '(', ')'], '', $value)))
                                    <a href="tel:{{ $value }}" style="color: #667eea; text-decoration: none;">{{ $value }}</a>
                                @else
                                    {{ $value }}
                                @endif
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach

            <!-- Timestamp -->
            <div class="timestamp">
                <strong>Query Submitted:</strong> {{ now()->format('F j, Y \a\t g:i A T') }}
                @if(isset($queryData['ip_address']))
                    <br><strong>IP Address:</strong> {{ $queryData['ip_address'] }}
                @endif
                @if(isset($queryData['user_agent']))
                    <br><strong>User Agent:</strong> {{ $queryData['user_agent'] }}
                @endif
            </div>
        </div>

        <div class="footer">
            <p><strong>{{ config('app.name') }}</strong></p>
            <p>This is an automated notification. Please respond to the customer using their provided contact information.</p>
            @if(isset($queryData['email']))
                <p>
                    <a href="mailto:{{ $queryData['email'] }}?subject=Re: Your Business Inquiry"
                       style="color: #667eea; text-decoration: none; font-weight: 600;">
                        📧 Reply to Customer
                    </a>
                </p>
            @endif
        </div>
    </div>
</body>
</html>
