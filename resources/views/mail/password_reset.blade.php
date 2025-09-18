<!DOCTYPE html>
<html>

<head>
    <title>Your Password Reset</title>
</head>

<body>
    @if ($model)
        <h1>Hello, {{ $model->first_name }} {{ $model->last_name }}!</h1>
    @else
        <h1>Hello!</h1>
    @endif

    <p>Your Account Password Reset Successfully</p>


    <p>Please don't share OTP with anyone.</p>

    <p>Thank you,<br>
        {{ config('app.name') }}</p>
</body>

</html>
