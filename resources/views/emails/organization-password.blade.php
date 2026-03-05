<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Organization Credentials</title>
</head>
<body>
    <h2>Welcome {{ $organization->name }}</h2>

    <p>Your organization account has been created successfully.</p>

    <p><strong>Email:</strong> {{ $organization->email }}</p>
    <p><strong>Password:</strong> {{ $password }}</p>

    <p>Please login and change your password immediately.</p>

    <br>
    <p>Regards,<br>{{ config('app.name') }}</p>
</body>
</html>
