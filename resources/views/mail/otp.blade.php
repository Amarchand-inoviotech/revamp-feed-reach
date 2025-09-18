<!DOCTYPE html>
<html>

<head>
    <title>Your OTP</title>
</head>

<body>
    @if ($user)
        <h1>Hello, {{ $user->first_name }} {{ $user->last_name }}!</h1>
    @else
        <h1>Hello!</h1>
    @endif

    <p>Your One-Time Password Email (OTP) is:</p>

    <h2 style="color: #2563eb; font-size: 24px; font-weight: bold;">
        {{ $otp->token }}
    </h2>

    <p>This OTP is valid for a limited time. Please do not share it with anyone.</p>

    <p>Thank you,<br>
        {{ config('app.name') }}</p>
</body>

</html>
