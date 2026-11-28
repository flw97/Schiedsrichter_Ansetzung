<!DOCTYPE html>
<html lang="de">
<body style="font-family: sans-serif; line-height: 1.5; color: #13261f;">
<p>Hallo {{ $user->name }},</p>
<p>du kannst ein neues Passwort für die Ansetzung vergeben. Der Link ist 60 Minuten gültig. Das NuLiga-Passwort ändert sich dabei nicht. Wenn du das nicht warst, ignoriere diese E-Mail.</p>
<p><a href="{{ $url }}">Passwort zurücksetzen</a></p>
<p style="color: #4b6156; font-size: 14px;">{{ $url }}</p>
</body>
</html>
