# Fixed
- CI stopped before deploy. `php artisan test` exited because `phpunit.xml` listed `tests/Unit`, which does not exist. PHPUnit 12 treats that missing directory as a failure and never runs `tests/Feature`. The empty Unit suite is removed.
