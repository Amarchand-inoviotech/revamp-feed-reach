<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>New Lead Notification</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #4f46e5;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }

        .card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .lead-details {
            padding: 20px;
            background-color: #f8fafc;
        }

        .detail-row {
            display: flex;
            margin-bottom: 12px;
        }

        .detail-label {
            font-weight: 500;
            color: #64748b;
            width: 120px;
        }

        .detail-value {
            flex: 1;
            font-weight: 400;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: #64748b;
            font-size: 14px;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4f46e5;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="header">
            <h1>New Lead Received!</h1>
        </div>

        <div class="lead-details">
            <div class="detail-row">
                <div class="detail-label">Name:</div>
                <div class="detail-value">{{ $data->name }}</div>
            </div>

            <div class="detail-row">
                <div class="detail-label">Email:</div>
                <div class="detail-value">{{ $data->email }}</div>
            </div>

            @if ($data->phone)
                <div class="detail-row">
                    <div class="detail-label">Phone:</div>
                    <div class="detail-value">{{ $data->phone }}</div>
                </div>
            @endif

            @if ($data->brand)
                <div class="detail-row">
                    <div class="detail-label">Brand:</div>
                    <div class="detail-value">{{ $data->brand->name }}</div>
                </div>
            @endif


            @if ($data->source)
                <div class="detail-row">
                    <div class="detail-label">Source:</div>
                    <div class="detail-value">{{ $data->source->name }}</div>
                </div>
            @endif

            @if ($data->location)
                <div class="detail-row">
                    <div class="detail-label">Location:</div>
                    <div class="detail-value">{{ $data->location }}</div>
                </div>
            @endif

            @if ($data->page_url)
                <div class="detail-row">
                    <div class="detail-label">Page URL:</div>
                    <div class="detail-value">
                        <a href="{{ $data->page_url }}" target="_blank">{{ $data->page_url }}</a>
                    </div>
                </div>
            @endif

            @if ($data->brief)
                <div class="detail-row">
                    <div class="detail-label">Brief:</div>
                    <div class="detail-value">{{ $data->brief }}</div>
                </div>
            @endif

            <div style="text-align: center; margin-top: 30px;">
                <a href="{{ route('api.back-offices.leads.show', $data->uuid) }}" class="button">
                    View Lead in Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>This email was sent automatically from {{ config('app.name') }}.</p>
        <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>

</html>
