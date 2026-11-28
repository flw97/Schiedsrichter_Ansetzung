<!DOCTYPE html>
<html lang="de">
<body style="font-family: sans-serif; line-height: 1.5; color: #13261f;">
<p>Hallo {{ $user->name }},</p>
<p>{{ $messages->count() === 1 ? 'in der Ansetzung gibt es eine neue Benachrichtigung:' : 'in der Ansetzung gibt es '.$messages->count().' neue Benachrichtigungen:' }}</p>
<ul>
@foreach ($messages as $message)
<li>{{ $message->text }} <span style="color: #4b6156;">({{ $message->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }})</span></li>
@endforeach
</ul>
<p><a href="{{ $appUrl }}">Zur Ansetzung</a></p>
</body>
</html>
